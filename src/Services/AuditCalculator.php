<?php

declare(strict_types=1);

namespace Whatsdiff\Services;

use Composer\Semver\Semver;
use Illuminate\Support\Collection;
use Whatsdiff\Analyzers\BaseAnalyzer;
use Whatsdiff\Analyzers\LockFile\ComposerLockFile;
use Whatsdiff\Analyzers\LockFile\LockFileInterface;
use Whatsdiff\Analyzers\LockFile\NpmPackageLockFile;
use Whatsdiff\Analyzers\LockFile\PnpmLockFile;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\SecurityAdvisories\SeverityResolver;
use Whatsdiff\Data\AuditResult;
use Whatsdiff\Data\PackageAudit;
use Whatsdiff\Data\SecurityAdvisory;
use Whatsdiff\Enums\Severity;

/**
 * Orchestrates security audits over installed dependencies.
 *
 * Supports two modes:
 *  - current-state: read the working-tree lock files (or a specific commit via --at)
 *    and report all advisories that affect the installed versions.
 *  - diff: compare two refs and report advisories newly affecting the "to" version.
 */
class AuditCalculator
{
    /**
     * @var array<PackageManagerType>
     */
    private array $types = [];

    private ?string $atCommit = null;

    private ?string $fromCommit = null;

    private ?string $toCommit = null;

    private bool $resolveFixes = true;

    /**
     * @var array<string, ?Severity>
     */
    private array $resolvedSeverityCache = [];

    public function __construct(
        private readonly AnalyzerRegistry $analyzerRegistry,
        private readonly GitRepository $git,
        private readonly FixSuggestionResolver $fixSuggestionResolver,
        private readonly SeverityResolver $severityResolver,
    ) {}

    public function for(PackageManagerType $type): self
    {
        $this->types[] = $type;

        return $this;
    }

    public function atCommit(?string $commit): self
    {
        $this->atCommit = $commit;

        return $this;
    }

    public function fromCommit(?string $commit): self
    {
        $this->fromCommit = $commit;

        return $this;
    }

    public function toCommit(?string $commit): self
    {
        $this->toCommit = $commit;

        return $this;
    }

    public function withFixSuggestions(bool $resolve = true): self
    {
        $this->resolveFixes = $resolve;

        return $this;
    }

    public function run(): AuditResult
    {
        if ($this->fromCommit !== null || $this->toCommit !== null) {
            return $this->runDiffMode();
        }

        return $this->runCurrentStateMode();
    }

    private function runCurrentStateMode(): AuditResult
    {
        $audits = collect();

        foreach ($this->getActiveTypes() as $type) {
            $content = $this->loadLockContent($type, $this->atCommit);

            if ($content === null) {
                continue;
            }

            $parser = $this->createParser($type, $content);
            $installedPackages = $parser->getAllVersions();

            if (empty($installedPackages)) {
                continue;
            }

            $advisoriesByPackage = $this->fetchAdvisories($type, array_keys($installedPackages));

            foreach ($installedPackages as $package => $version) {
                $affecting = $this->filterAffecting($advisoriesByPackage[$package] ?? [], $version);

                if (empty($affecting)) {
                    continue;
                }

                $audits->push(new PackageAudit(
                    name: $package,
                    type: $type,
                    installedVersion: $version,
                    advisories: $affecting,
                    suggestedFixVersion: $this->resolveFix($type, $package, $version, $affecting),
                ));
            }
        }

        return new AuditResult(
            audits: $this->sortAudits($audits),
            isDiffMode: false,
            fromCommit: $this->atCommit,
            toCommit: null,
        );
    }

    private function runDiffMode(): AuditResult
    {
        $fromHash = $this->fromCommit !== null ? $this->git->resolveCommitHash($this->fromCommit) : null;
        $toHash = $this->toCommit !== null
            ? $this->git->resolveCommitHash($this->toCommit)
            : $this->git->resolveCommitHash('HEAD');

        $audits = collect();

        foreach ($this->getActiveTypes() as $type) {
            $toContent = $this->loadLockContent($type, $toHash);
            $fromContent = $fromHash !== null ? $this->loadLockContent($type, $fromHash) : null;

            if ($toContent === null) {
                continue;
            }

            $toParser = $this->createParser($type, $toContent);
            $toVersions = $toParser->getAllVersions();

            $fromVersions = [];
            if ($fromContent !== null) {
                $fromParser = $this->createParser($type, $fromContent);
                $fromVersions = $fromParser->getAllVersions();
            }

            $packagesOnTo = array_keys($toVersions);
            if (empty($packagesOnTo)) {
                continue;
            }

            $advisoriesByPackage = $this->fetchAdvisories($type, $packagesOnTo);

            foreach ($toVersions as $package => $toVersion) {
                $advisories = $advisoriesByPackage[$package] ?? [];
                if (empty($advisories)) {
                    continue;
                }

                $affecting = $this->filterAffecting($advisories, $toVersion);
                if (empty($affecting)) {
                    continue;
                }

                $fromVersion = $fromVersions[$package] ?? null;

                // New advisories = those affecting the to-version that didn't affect the from-version
                $newlyAffecting = [];
                foreach ($affecting as $advisory) {
                    if ($fromVersion === null) {
                        $newlyAffecting[] = $advisory;

                        continue;
                    }

                    try {
                        $fromAffected = Semver::satisfies($fromVersion, $advisory->affectedVersions);
                    } catch (\Exception $e) {
                        $fromAffected = false;
                    }

                    if (! $fromAffected) {
                        $newlyAffecting[] = $advisory;
                    }
                }

                if (empty($newlyAffecting)) {
                    continue;
                }

                $audits->push(new PackageAudit(
                    name: $package,
                    type: $type,
                    installedVersion: $toVersion,
                    advisories: $newlyAffecting,
                    suggestedFixVersion: $this->resolveFix($type, $package, $toVersion, $newlyAffecting),
                ));
            }
        }

        return new AuditResult(
            audits: $this->sortAudits($audits),
            isDiffMode: true,
            fromCommit: $fromHash,
            toCommit: $toHash,
        );
    }

    /**
     * @return array<PackageManagerType>
     */
    private function getActiveTypes(): array
    {
        return empty($this->types) ? PackageManagerType::cases() : $this->types;
    }

    private function loadLockContent(PackageManagerType $type, ?string $commit): ?string
    {
        $filename = $type->getLockFileName();

        if ($commit !== null) {
            $content = $this->git->getFileContentAtCommit($filename, $commit);

            return $content === '' ? null : $content;
        }

        if (! file_exists($filename)) {
            return null;
        }

        $content = file_get_contents($filename);

        return $content === false ? null : $content;
    }

    private function createParser(PackageManagerType $type, string $content): LockFileInterface
    {
        return match ($type) {
            PackageManagerType::COMPOSER => new ComposerLockFile($content),
            PackageManagerType::NPM => new NpmPackageLockFile($content),
            PackageManagerType::PNPM => new PnpmLockFile($content),
        };
    }

    /**
     * @param  array<string>  $packages
     * @return array<string, array<SecurityAdvisory>>
     */
    private function fetchAdvisories(PackageManagerType $type, array $packages): array
    {
        if (empty($packages)) {
            return [];
        }

        $analyzer = $this->analyzerRegistry->get($type);

        if (! $analyzer instanceof BaseAnalyzer) {
            return [];
        }

        try {
            $advisories = $analyzer->getRegistry()->getSecurityAdvisories($packages);
        } catch (\Exception $e) {
            return [];
        }

        return $this->backfillSeverities($advisories);
    }

    /**
     * @param  array<string, array<SecurityAdvisory>>  $advisoriesByPackage
     * @return array<string, array<SecurityAdvisory>>
     */
    private function backfillSeverities(array $advisoriesByPackage): array
    {
        foreach ($advisoriesByPackage as $package => $advisories) {
            foreach ($advisories as $index => $advisory) {
                if ($advisory->severity !== Severity::Unknown) {
                    continue;
                }
                if ($advisory->cve === null || $advisory->cve === '') {
                    continue;
                }

                $cve = $advisory->cve;
                if (! array_key_exists($cve, $this->resolvedSeverityCache)) {
                    $this->resolvedSeverityCache[$cve] = $this->severityResolver->resolve($cve);
                }

                $resolved = $this->resolvedSeverityCache[$cve];
                if ($resolved !== null) {
                    $advisoriesByPackage[$package][$index] = $advisory->withSeverity($resolved);
                }
            }
        }

        return $advisoriesByPackage;
    }

    /**
     * @param  array<SecurityAdvisory>  $advisories
     * @return array<SecurityAdvisory>
     */
    private function filterAffecting(array $advisories, string $version): array
    {
        $affecting = [];

        foreach ($advisories as $advisory) {
            if ($advisory->affectedVersions === '') {
                continue;
            }

            try {
                if (Semver::satisfies($version, $advisory->affectedVersions)) {
                    $affecting[] = $advisory;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $affecting;
    }

    /**
     * @param  array<SecurityAdvisory>  $advisories
     */
    private function resolveFix(PackageManagerType $type, string $package, string $version, array $advisories): ?string
    {
        if (! $this->resolveFixes) {
            return null;
        }

        return $this->fixSuggestionResolver->suggest($type, $package, $version, $advisories);
    }

    /**
     * @param  Collection<int, PackageAudit>  $audits
     * @return Collection<int, PackageAudit>
     */
    private function sortAudits(Collection $audits): Collection
    {
        return $audits
            ->sortBy([
                fn (PackageAudit $a, PackageAudit $b) => $b->maxSeverity()->rank() <=> $a->maxSeverity()->rank(),
                fn (PackageAudit $a, PackageAudit $b) => strcmp($a->name, $b->name),
            ])
            ->values();
    }
}
