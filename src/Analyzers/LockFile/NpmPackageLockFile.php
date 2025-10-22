<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\LockFile;

use Illuminate\Support\Collection;

/**
 * Stateful parser for package-lock.json files (npm).
 *
 * Handles the npm lock file format used by Node.js package manager.
 * Parses the lock file once in the constructor and stores data internally.
 * Supports both lockfileVersion 1, 2, and 3 formats.
 */
class NpmPackageLockFile implements LockFileInterface
{
    private array $lockData;
    private Collection $packages;

    /**
     * Create a new parser and parse the lock file content.
     *
     * @param string $lockFileContent Raw package-lock.json content (JSON)
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
            ->filter(fn ($package, $key) => !empty($key) && !empty($package['version']));

        return $packages->mapWithKeys(function ($package, $key) {
            $packageName = str_replace('node_modules/', '', $key);

            $data = [
                'version' => $package['version'],
            ];

            // Extract repository URL if available
            if (isset($package['resolved'])) {
                $data['repository'] = $package['resolved'];
            }

            return [$packageName => $data];
        })->filter(fn ($data, $name) => !empty($name));
    }
}
