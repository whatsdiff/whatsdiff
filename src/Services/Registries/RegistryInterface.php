<?php

declare(strict_types=1);

namespace Whatsdiff\Services\Registries;

/**
 * Interface for package registry clients.
 *
 * Registry clients are responsible for:
 * - HTTP communication with package registries
 * - Authentication handling (API tokens, http-basic, etc.)
 * - Parsing registry-specific response formats
 * - Handling registry rate limits and errors
 */
interface RegistryInterface
{
    /**
     * Get complete package metadata from the registry.
     *
     * @param string $package Package name
     * @param array<string, mixed> $options Additional options (e.g., auth credentials, registry URL)
     * @return array<string, mixed> Package metadata
     * @throws \Whatsdiff\Analyzers\Exceptions\PackageInformationsException If package cannot be fetched
     */
    public function getPackageMetadata(string $package, array $options = []): array;

    /**
     * Get versions of a package between two version constraints.
     *
     * @param string $package Package name
     * @param string $from Starting version (exclusive)
     * @param string $to Ending version (inclusive)
     * @param array<string, mixed> $options Additional options (e.g., auth credentials, registry URL)
     * @return array<int, string> Array of version strings
     * @throws \Whatsdiff\Analyzers\Exceptions\PackageInformationsException If package cannot be fetched
     */
    public function getVersions(string $package, string $from, string $to, array $options = []): array;

    /**
     * Get repository URL for a package.
     *
     * @param string $package Package name
     * @param array<string, mixed> $options Additional options (e.g., auth credentials)
     * @return string|null Repository URL or null if not available
     */
    public function getRepositoryUrl(string $package, array $options = []): ?string;
}
