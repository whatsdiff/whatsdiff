<?php

declare(strict_types=1);

namespace Whatsdiff\Services;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Whatsdiff\Analyzers\BaseAnalyzer;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Data\SecurityAdvisory;

/**
 * Finds the lowest version of a package that is not affected by any of the
 * given security advisories. Used by the audit command to suggest a safe
 * upgrade target per vulnerable package.
 */
class FixSuggestionResolver
{
    public function __construct(
        private readonly AnalyzerRegistry $analyzerRegistry,
    ) {}

    /**
     * @param  array<SecurityAdvisory>  $advisories
     */
    public function suggest(
        PackageManagerType $type,
        string $package,
        string $installedVersion,
        array $advisories,
        array $context = [],
    ): ?string {
        if (empty($advisories)) {
            return null;
        }

        $analyzer = $this->analyzerRegistry->get($type);

        if (! $analyzer instanceof BaseAnalyzer) {
            return null;
        }

        try {
            $versions = $analyzer->getRegistry()->getVersions(
                $package,
                $installedVersion,
                '99999.0.0',
                $context,
            );
        } catch (\Exception $e) {
            return null;
        }

        if (empty($versions)) {
            return null;
        }

        usort($versions, fn (string $a, string $b) => Comparator::lessThan($a, $b) ? -1 : 1);

        foreach ($versions as $candidate) {
            if ($this->isSafe($candidate, $advisories)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<SecurityAdvisory>  $advisories
     */
    private function isSafe(string $version, array $advisories): bool
    {
        foreach ($advisories as $advisory) {
            if ($advisory->affectedVersions === '') {
                continue;
            }

            try {
                if (Semver::satisfies($version, $advisory->affectedVersions)) {
                    return false;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return true;
    }
}
