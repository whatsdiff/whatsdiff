<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\LockFile;

use Illuminate\Support\Collection;

/**
 * Stateful parser for composer.lock files.
 *
 * Handles the Composer lock file format used by PHP package manager.
 * Parses the lock file once in the constructor and stores data internally.
 * Extracts both regular and dev dependencies.
 */
class ComposerLockFile implements LockFileInterface
{
    private array $lockData;
    private Collection $packages;

    /**
     * Create a new parser and parse the lock file content.
     *
     * @param string $lockFileContent Raw composer.lock content (JSON)
     */
    public function __construct(string $lockFileContent)
    {
        $this->lockData = json_decode($lockFileContent, true) ?? [];
        $this->packages = $this->parsePackages();
    }

    /**
     * Get all packages with their metadata.
     *
     * @return Collection<string, array{version: string, repository?: string}> Package name => package data
     */
    public function getPackages(): Collection
    {
        return $this->packages;
    }

    /**
     * Get version for a specific package.
     *
     * @param string $package Package name
     * @return string|null Version or null if package not found
     */
    public function getVersion(string $package): ?string
    {
        return $this->packages->get($package)['version'] ?? null;
    }

    /**
     * Get repository URL for a specific package.
     *
     * @param string $package Package name
     * @return string|null Repository URL or null if not found
     */
    public function getRepositoryUrl(string $package): ?string
    {
        return $this->packages->get($package)['repository'] ?? null;
    }

    /**
     * Get all package versions as a simple array.
     *
     * @return array<string, string> Package name => version
     */
    public function getAllVersions(): array
    {
        return $this->packages->map(fn ($data) => $data['version'])->toArray();
    }

    /**
     * Parse packages from lock file data.
     *
     * @return Collection<string, array{version: string, repository?: string}>
     */
    private function parsePackages(): Collection
    {
        $packages = collect($this->lockData['packages'] ?? [])
            ->merge($this->lockData['packages-dev'] ?? []);

        return $packages->mapWithKeys(function ($package) {
            $data = [
                'version' => $package['version'],
            ];

            // Extract repository URL from various possible locations
            if (isset($package['source']['url'])) {
                $data['repository'] = $package['source']['url'];
            } elseif (isset($package['dist']['url'])) {
                $data['repository'] = $package['dist']['url'];
            }

            return [$package['name'] => $data];
        });
    }
}
