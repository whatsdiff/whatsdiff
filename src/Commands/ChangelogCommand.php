<?php

declare(strict_types=1);

namespace Whatsdiff\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Analyzers\LockFile\ComposerLockFile;
use Whatsdiff\Analyzers\LockFile\NpmPackageLockFile;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\Registries\NpmRegistry;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;
use Whatsdiff\Analyzers\ReleaseNotes\ReleaseNotesResolver;
use Whatsdiff\Outputs\ReleaseNotes\ReleaseNotesJsonOutput;
use Whatsdiff\Outputs\ReleaseNotes\ReleaseNotesMarkdownOutput;
use Whatsdiff\Outputs\ReleaseNotes\ReleaseNotesTextOutput;
use Whatsdiff\Services\CacheService;
use Whatsdiff\Helpers\CommandErrorHandler;
use Whatsdiff\Services\GitRepository;
use Whatsdiff\Helpers\VersionNormalizer;

#[AsCommand(
    name: 'changelog',
    description: 'Show changelog/release notes for a specific package',
)]
class ChangelogCommand extends Command
{
    use SharedCommandOptions;

    public function __construct(
        private readonly GitRepository $gitRepository,
        private readonly PackagistRegistry $packagistRegistry,
        private readonly NpmRegistry $npmRegistry,
        private readonly CacheService $cacheService,
        private readonly ReleaseNotesResolver $releaseNotesResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Display changelog/release notes for a package. Versions can be specified directly or extracted from lock files at git commits.')
            ->addArgument(
                'package',
                InputArgument::REQUIRED,
                'Package name (e.g., symfony/console or react)'
            )
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'Version or version range (e.g., 5.1.0 or 5.0.0...5.1.0)'
            )
            ->addFromOption()
            ->addToOption()
            ->addIgnoreLastOption()
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Package manager type (composer or npm)'
            )
            ->addFormatOption()
            ->addOption(
                'summary',
                's',
                InputOption::VALUE_NONE,
                'Show summarized changelog (combines all releases)'
            )
            ->addNoCacheOption()
            ->addOption(
                'include-prerelease',
                null,
                InputOption::VALUE_NONE,
                'Include pre-release versions (beta, alpha, RC, etc.)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $package = $input->getArgument('package');
        $versionArg = $input->getArgument('version');
        $fromCommit = $input->getOption('from');
        $toCommit = $input->getOption('to') ?? 'HEAD';
        $ignoreLast = (bool) $input->getOption('ignore-last');
        $type = $input->getOption('type');
        $format = $input->getOption('format');
        $summary = (bool) $input->getOption('summary');
        $noCache = (bool) $input->getOption('no-cache');
        $includePrerelease = (bool) $input->getOption('include-prerelease');

        // Validate options
        if ($versionArg && ($fromCommit || $toCommit !== 'HEAD')) {
            $output->writeln('<error>Cannot use version argument with --from or --to options</error>');

            return Command::FAILURE;
        }

        if ($versionArg && $ignoreLast) {
            $output->writeln('<error>Cannot use version argument with --ignore-last option</error>');

            return Command::FAILURE;
        }

        if (($fromCommit || $toCommit !== 'HEAD') && $ignoreLast) {
            $output->writeln('<error>Cannot use --ignore-last with --from or --to options</error>');

            return Command::FAILURE;
        }

        try {
            // Disable cache if requested
            if ($noCache) {
                $this->cacheService->disableCache();
            }

            // Auto-detect or validate package manager type
            $packageManagerType = $this->detectPackageManager($package, $type);
            if ($packageManagerType === null) {
                $output->writeln("<error>Package '{$package}' not found in lock files. Try specifying --type=(composer|npm)</error>");

                return Command::FAILURE;
            }

            // Get version range from git commits or version argument
            [$fromVersion, $toVersion] = $this->getVersionRange(
                $package,
                $packageManagerType,
                $versionArg,
                $fromCommit,
                $toCommit,
                $ignoreLast
            );

            if ($fromVersion === null || $toVersion === null) {
                $output->writeln("<error>Could not determine version range for package '{$package}'</error>");

                return Command::FAILURE;
            }

            // Normalize versions (remove 'v' prefix for consistency)
            $fromVersion = ltrim($fromVersion, 'vV');
            $toVersion = ltrim($toVersion, 'vV');

            // Get repository URL from registry
            $repositoryUrl = $this->getRepositoryUrl($package, $packageManagerType);
            if ($repositoryUrl === null) {
                $output->writeln("<error>Could not determine repository URL for package '{$package}'</error>");

                return Command::FAILURE;
            }

            // Determine local path
            $localPath = $this->getLocalPath($package, $packageManagerType);

            // Fetch release notes
            $releaseNotes = $this->releaseNotesResolver->resolve(
                package: $package,
                fromVersion: $fromVersion,
                toVersion: $toVersion,
                repositoryUrl: $repositoryUrl,
                packageManagerType: $packageManagerType,
                localPath: $localPath,
                includePrerelease: $includePrerelease
            );

            if ($releaseNotes === null || $releaseNotes->isEmpty()) {
                $output->writeln("<comment>No release notes found for {$package} between {$fromVersion} and {$toVersion}</comment>");

                return Command::SUCCESS;
            }

            // Format and output
            $formatter = $this->getFormatter($format, $summary, ! $output->isDecorated());
            $formatter->format($releaseNotes, $output);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            return CommandErrorHandler::handle($e, $output, $format);
        }
    }

    /**
     * Detect package manager type using multiple strategies.
     *
     * Strategy order:
     * 1. Use explicit --type option if provided
     * 2. Pattern matching on package name format
     * 3. Probe registries to see which has the package
     * 4. Check lock files for the package
     */
    private function detectPackageManager(string $package, ?string $typeOption): ?PackageManagerType
    {
        // Strategy 1: If type is specified, validate and return
        if ($typeOption !== null) {
            return match (strtolower($typeOption)) {
                'composer' => PackageManagerType::COMPOSER,
                'npm', 'npmjs' => PackageManagerType::NPM,
                default => null,
            };
        }

        // Strategy 2: Pattern matching on package name format
        // Composer packages typically use vendor/package format (contains /)
        // NPM packages are usually single words or @scope/package
        $detectedType = $this->detectByPackageNamePattern($package);
        if ($detectedType !== null) {
            // Verify by probing the registry
            if ($this->verifyPackageExistsInRegistry($package, $detectedType)) {
                return $detectedType;
            }
        }

        // Strategy 3: Probe both registries to see which has the package
        $detectedType = $this->detectByRegistryProbe($package);
        if ($detectedType !== null) {
            return $detectedType;
        }

        // Strategy 4: Check lock files
        // Check composer.lock
        try {
            $composerLockContent = $this->gitRepository->getFileContentAtCommit('composer.lock', 'HEAD');
            if (! empty($composerLockContent)) {
                $lockFile = new ComposerLockFile($composerLockContent);
                $versions = $lockFile->getAllVersions();
                if (isset($versions[$package])) {
                    return PackageManagerType::COMPOSER;
                }
            }
        } catch (\Exception $e) {
            // composer.lock not found or invalid
        }

        // Check package-lock.json
        try {
            $npmLockContent = $this->gitRepository->getFileContentAtCommit('package-lock.json', 'HEAD');
            if (! empty($npmLockContent)) {
                $lockFile = new NpmPackageLockFile($npmLockContent);
                $versions = $lockFile->getAllVersions();
                if (isset($versions[$package])) {
                    return PackageManagerType::NPM;
                }
            }
        } catch (\Exception $e) {
            // package-lock.json not found or invalid
        }

        return null;
    }

    /**
     * Detect package manager type by analyzing package name pattern.
     *
     * Composer: vendor/package (e.g., symfony/console, laravel/framework)
     * NPM: package-name or @scope/package-name (e.g., react, @types/node)
     */
    private function detectByPackageNamePattern(string $package): ?PackageManagerType
    {
        // NPM scoped packages start with @
        if (str_starts_with($package, '@') && str_contains($package, '/')) {
            return PackageManagerType::NPM;
        }

        // Composer packages typically have vendor/package format with lowercase vendor
        if (str_contains($package, '/')) {
            $parts = explode('/', $package, 2);
            $vendor = $parts[0];

            // If vendor part is all lowercase (common for composer packages)
            // and doesn't start with @ (which would be NPM scoped)
            if ($vendor === strtolower($vendor) && ! str_starts_with($vendor, '@')) {
                return PackageManagerType::COMPOSER;
            }
        }

        // Unable to determine from pattern alone
        return null;
    }

    /**
     * Verify if a package exists in a specific registry.
     */
    private function verifyPackageExistsInRegistry(string $package, PackageManagerType $type): bool
    {
        try {
            $registry = $type === PackageManagerType::COMPOSER
                ? $this->packagistRegistry
                : $this->npmRegistry;

            $registry->getPackageMetadata($package);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Detect package manager type by probing both registries.
     *
     * Returns the type if found in exactly one registry.
     * Returns null if found in both or neither (ambiguous).
     */
    private function detectByRegistryProbe(string $package): ?PackageManagerType
    {
        $foundInComposer = $this->verifyPackageExistsInRegistry($package, PackageManagerType::COMPOSER);
        $foundInNpm = $this->verifyPackageExistsInRegistry($package, PackageManagerType::NPM);

        // If found in exactly one registry, return that type
        if ($foundInComposer && ! $foundInNpm) {
            return PackageManagerType::COMPOSER;
        }

        if ($foundInNpm && ! $foundInComposer) {
            return PackageManagerType::NPM;
        }

        // Ambiguous: found in both or neither
        return null;
    }

    /**
     * Get version range from version argument, git commits, or auto-detection.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function getVersionRange(
        string $package,
        PackageManagerType $packageManagerType,
        ?string $versionArg,
        ?string $fromCommit,
        string $toCommit,
        bool $ignoreLast
    ): array {
        // Priority 1: Version argument provided
        if ($versionArg !== null) {
            return $this->parseVersionArgument($versionArg, $package, $packageManagerType);
        }

        // Priority 2: --from/--to flags provided
        if ($fromCommit !== null || $toCommit !== 'HEAD') {
            $toVersion = $this->getPackageVersionAtCommit($package, $packageManagerType, $toCommit, $ignoreLast);
            $fromVersion = $fromCommit !== null
                ? $this->getPackageVersionAtCommit($package, $packageManagerType, $fromCommit)
                : $this->findPreviousVersion($package, $packageManagerType, $toCommit);

            return [$fromVersion, $toVersion];
        }

        // Priority 3: Auto-detect from git history
        $toVersion = $this->getPackageVersionAtCommit($package, $packageManagerType, $toCommit, $ignoreLast);

        if ($toVersion === null) {
            return [null, null];
        }

        $fromVersion = $this->findPreviousVersion($package, $packageManagerType, $toCommit);

        // Priority 4: Newly added package (no previous version)
        if ($fromVersion === null) {
            // Show the current version's changelog
            $fromVersion = $toVersion;
        }

        return [$fromVersion, $toVersion];
    }

    /**
     * Parse version argument (single version or range).
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function parseVersionArgument(
        string $versionArg,
        string $package,
        PackageManagerType $packageManagerType
    ): array {
        // Check for range separator (...)
        if (str_contains($versionArg, '...')) {
            [$fromVersion, $toVersion] = explode('...', $versionArg, 2);

            return [
                VersionNormalizer::normalize(trim($fromVersion)),
                VersionNormalizer::normalize(trim($toVersion)),
            ];
        }

        // Normalize single version
        $versionArg = VersionNormalizer::stripPrefix($versionArg);

        // Single version: show just that release
        // To show a single release, we need from < to, so we'll use the previous version
        $previousVersion = $this->getPreviousVersionFromRegistry($package, $versionArg, $packageManagerType);

        if ($previousVersion === null) {
            // No previous version found, use same version for both (will show just this release)
            return [$versionArg, $versionArg];
        }

        return [$previousVersion, $versionArg];
    }

    /**
     * Get the previous version of a package from the registry.
     */
    private function getPreviousVersionFromRegistry(
        string $package,
        string $currentVersion,
        PackageManagerType $packageManagerType
    ): ?string {
        try {
            $registry = $packageManagerType === PackageManagerType::COMPOSER
                ? $this->packagistRegistry
                : $this->npmRegistry;

            // Get all available versions
            $metadata = $registry->getPackageMetadata($package);

            // Extract versions from metadata
            $versions = [];
            if (isset($metadata['packages'][$package])) {
                foreach ($metadata['packages'][$package] as $versionData) {
                    if (isset($versionData['version'])) {
                        $versions[] = ltrim($versionData['version'], 'vV');
                    }
                }
            }

            if (empty($versions)) {
                return null;
            }

            // Sort versions in ascending order
            usort($versions, function ($a, $b) {
                try {
                    return version_compare($a, $b);
                } catch (\Exception $e) {
                    return strcmp($a, $b);
                }
            });

            // Find the version just before the current version
            $currentIndex = array_search($currentVersion, $versions, true);

            if ($currentIndex === false || $currentIndex === 0) {
                return null;
            }

            return $versions[$currentIndex - 1];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get package version from lock file at a specific commit.
     */
    private function getPackageVersionAtCommit(
        string $package,
        PackageManagerType $packageManagerType,
        string $commit,
        bool $ignoreLast = false
    ): ?string {
        $lockFileName = $packageManagerType->getLockFileName();

        // If ignore-last and commit is HEAD, get previous commit
        if ($ignoreLast && $commit === 'HEAD') {
            $commits = $this->gitRepository->getFileCommitLogs($lockFileName);
            if (empty($commits)) {
                return null;
            }
            $commit = $commits[0]; // Most recent committed version
        }

        $lockContent = $this->gitRepository->getFileContentAtCommit($lockFileName, $commit);
        if (empty($lockContent)) {
            return null;
        }

        $lockFile = $packageManagerType === PackageManagerType::COMPOSER
            ? new ComposerLockFile($lockContent)
            : new NpmPackageLockFile($lockContent);

        $versions = $lockFile->getAllVersions();

        return $versions[$package] ?? null;
    }

    /**
     * Find the previous version of a package in git history.
     */
    private function findPreviousVersion(
        string $package,
        PackageManagerType $packageManagerType,
        string $currentCommit
    ): ?string {
        $lockFileName = $packageManagerType->getLockFileName();

        // Get commit history for the lock file
        $commits = $this->gitRepository->getFileCommitLogs($lockFileName, $currentCommit);

        if (count($commits) < 2) {
            // No previous version available
            return null;
        }

        // Get current version
        $currentVersion = $this->getPackageVersionAtCommit($package, $packageManagerType, $currentCommit);

        // Search backwards for a different version
        for ($i = 1; $i < count($commits); $i++) {
            $previousVersion = $this->getPackageVersionAtCommit($package, $packageManagerType, $commits[$i]);

            if ($previousVersion !== null && $previousVersion !== $currentVersion) {
                return $previousVersion;
            }
        }

        return null;
    }

    /**
     * Get repository URL from package registry.
     */
    private function getRepositoryUrl(string $package, PackageManagerType $packageManagerType): ?string
    {
        $registry = $packageManagerType === PackageManagerType::COMPOSER
            ? $this->packagistRegistry
            : $this->npmRegistry;

        return $registry->getRepositoryUrl($package);
    }

    /**
     * Get local filesystem path to the package.
     */
    private function getLocalPath(string $package, PackageManagerType $packageManagerType): ?string
    {
        $basePath = $this->gitRepository->getGitRoot();

        $relativePath = $packageManagerType === PackageManagerType::COMPOSER
            ? "vendor/{$package}"
            : "node_modules/{$package}";

        $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;

        return file_exists($fullPath) ? $fullPath : null;
    }

    /**
     * Get output formatter based on format option.
     */
    private function getFormatter(string $format, bool $summary, bool $noAnsi): object
    {
        return match ($format) {
            'json' => new ReleaseNotesJsonOutput($summary),
            'markdown' => new ReleaseNotesMarkdownOutput($summary),
            default => new ReleaseNotesTextOutput($summary, ! $noAnsi),
        };
    }
}
