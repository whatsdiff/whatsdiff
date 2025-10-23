<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\ReleaseNotes;

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Data\ReleaseNotesCollection;

interface ReleaseNotesFetcherInterface
{
    /**
     * Fetch release notes for a package between two versions.
     *
     * @param string $package Package name (e.g., "symfony/console")
     * @param string $fromVersion Starting version (exclusive)
     * @param string $toVersion Ending version (inclusive)
     * @param string $repositoryUrl Repository URL (e.g., "https://github.com/symfony/console")
     * @param PackageManagerType $packageManagerType Package manager type (COMPOSER or NPM)
     * @param string|null $localPath Local filesystem path to package (vendor/package or node_modules/package)
     * @param bool $includePrerelease Whether to include pre-release versions
     * @return ReleaseNotesCollection|null Release notes collection or null if fetch fails
     */
    public function fetch(
        string $package,
        string $fromVersion,
        string $toVersion,
        string $repositoryUrl,
        PackageManagerType $packageManagerType,
        ?string $localPath,
        bool $includePrerelease
    ): ?ReleaseNotesCollection;

    /**
     * Check if this fetcher supports the given source.
     *
     * @param string $repositoryUrl Repository URL
     * @param string|null $localPath Local filesystem path to package
     * @return bool True if this fetcher can handle the source
     */
    public function supports(string $repositoryUrl, ?string $localPath): bool;
}
