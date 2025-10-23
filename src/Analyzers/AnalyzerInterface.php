<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers;

interface AnalyzerInterface
{
    /**
     * Get the package manager type this analyzer handles.
     */
    public function getType(): PackageManagerType;

    /**
     * Extract package versions from lock file content array.
     *
     * @param array $lockContent Lock file content as an associative array
     * @return array<string, string> Package name => version map
     */
    public function extractPackageVersions(array $lockContent): array;

    /**
     * Calculate the difference between two lock file versions.
     *
     * @param string $lastLockContent Current/latest lock file content (JSON)
     * @param string|null $previousLockContent Previous lock file content (JSON), null if no previous version
     * @return array Array of package changes with name, from, to versions, and optional metadata
     */
    public function calculateDiff(string $lastLockContent, ?string $previousLockContent): array;

    /**
     * Get the number of releases between two versions.
     *
     * @param string $package Package name
     * @param string $from Starting version
     * @param string $to Ending version
     * @param array<string, mixed> $context Additional context (e.g., registry URL)
     * @return int|null Number of releases, or null on error
     */
    public function getReleasesCount(string $package, string $from, string $to, array $context = []): ?int;
}
