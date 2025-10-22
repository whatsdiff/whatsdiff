<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\LockFile;

use Illuminate\Support\Collection;

/**
 * Interface for stateful lock file parsers.
 *
 * Parsers are immutable objects created with lock file content.
 * They parse the content once in the constructor and provide query methods.
 *
 * Lock file parsers are responsible for:
 * - Parsing lock file formats (composer.lock, package-lock.json, etc.)
 * - Storing parsed data internally
 * - Providing query methods for package information
 * - Handling lock file-specific quirks and structure
 */
interface LockFileInterface
{
    /**
     * Create a new parser with lock file content.
     * The parser will parse and store the data internally.
     *
     * @param string $lockFileContent Raw lock file content (JSON)
     */
    public function __construct(string $lockFileContent);

    /**
     * Get all packages with their metadata.
     *
     * @return Collection<string, array{version: string, repository?: string}> Package name => package data
     */
    public function getPackages(): Collection;

    /**
     * Get version for a specific package.
     *
     * @param string $package Package name
     * @return string|null Version or null if package not found
     */
    public function getVersion(string $package): ?string;

    /**
     * Get repository URL for a specific package.
     *
     * @param string $package Package name
     * @return string|null Repository URL or null if not found
     */
    public function getRepositoryUrl(string $package): ?string;

    /**
     * Get all package versions as a simple array.
     * Useful for diff calculation.
     *
     * @return array<string, string> Package name => version
     */
    public function getAllVersions(): array;
}
