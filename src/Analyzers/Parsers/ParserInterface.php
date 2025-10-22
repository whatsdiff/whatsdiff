<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\Parsers;

use Illuminate\Support\Collection;

/**
 * Interface for parsing lock files and extracting package information.
 *
 * Lock file parsers are responsible for:
 * - Parsing lock file formats (composer.lock, package-lock.json, etc.)
 * - Extracting package names and versions
 * - Extracting repository URLs from lock file metadata
 * - Handling lock file-specific quirks and structure
 */
interface ParserInterface
{
    /**
     * Parse lock file content and extract all packages.
     *
     * @param string $lockFileContent Raw lock file content
     * @return Collection<string, array{version: string, repository?: string}> Package name => package data
     */
    public function parse(string $lockFileContent): Collection;

    /**
     * Extract package versions from parsed lock file data.
     *
     * @param array<string, mixed> $lockFileData Parsed lock file data
     * @return array<string, string> Package name => version
     */
    public function extractPackageVersions(array $lockFileData): array;

    /**
     * Get repository URL for a specific package from lock file data.
     *
     * @param string $package Package name
     * @param array<string, mixed> $lockFileData Parsed lock file data
     * @return string|null Repository URL or null if not found
     */
    public function getRepositoryUrl(string $package, array $lockFileData): ?string;
}
