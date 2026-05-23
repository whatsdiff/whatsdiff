<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\LockFile;

use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Stateful parser for pnpm-lock.yaml files
 *
 * Handles the pnpm lock file format used by the pnpm package manager.
 * Parses the lock file once in the constructor and stores data internally.
 */
class PnpmLockFile implements LockFileInterface
{
    public const MINIMUM_SUPPORTED_VERSION = 9.0;

    private array $lockData;

    private Collection $packages;

    /**
     * Create a new parser and parse the lock file content.
     *
     * @param  string  $lockFileContent  Raw pnpm-lock.yaml content (YAML)
     */
    public function __construct(string $lockFileContent)
    {
        try {
            $parsed = Yaml::parse($lockFileContent);
            $this->lockData = is_array($parsed) ? $parsed : [];
        } catch (ParseException) {
            $this->lockData = [];
        }

        $this->packages = $this->parsePackages();
    }

    /**
     * Get the lockfileVersion as a float, or null if absent.
     *
     * Normalizes both unquoted numeric YAML values (e.g. 5.4 → 5.4)
     * and quoted string values (e.g. '9.0' → 9.0).
     */
    public function getLockfileVersion(): ?float
    {
        $version = $this->lockData['lockfileVersion'] ?? null;

        return is_string($version) || is_int($version) || is_float($version)
            ? (float) $version
            : null;
    }

    /**
     * Get all packages with their metadata.
     *
     * @return Collection<string, array{version: string}> Package name => package data
     */
    public function getPackages(): Collection
    {
        return $this->packages;
    }

    /**
     * Get version for a specific package.
     *
     * @param  string  $package  Package name
     * @return string|null Version or null if package not found
     */
    public function getVersion(string $package): ?string
    {
        return $this->packages->get($package)['version'] ?? null;
    }

    /**
     * Get repository URL for a specific package.
     *
     * pnpm v9 does not store resolved URLs in the packages section.
     *
     * @param  string  $package  Package name
     * @return string|null Always null for pnpm
     */
    public function getRepositoryUrl(string $package): ?string
    {
        return null;
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
     * Only processes the `packages:` section (not `snapshots:` or `importers:`).
     *
     * @return Collection<string, array{version: string}>
     */
    private function parsePackages(): Collection
    {
        $rawPackages = $this->lockData['packages'] ?? [];

        if (empty($rawPackages) || ! is_array($rawPackages)) {
            return collect();
        }

        $result = [];

        foreach ($rawPackages as $key => $packageData) {
            $parsed = $this->parsePackageKey((string) $key);

            if ($parsed === null) {
                continue;
            }

            ['name' => $name, 'version' => $version] = $parsed;

            // For packages with peer-dep variants (e.g., react-dom@18.2.0(react@18.2.0)
            // and react-dom@18.2.0(react@17.0.0)), only keep the first entry.
            if (isset($result[$name])) {
                continue;
            }

            $result[$name] = ['version' => $version];
        }

        return collect($result);
    }

    /**
     * Parse a pnpm v9 package key into name and version.
     *
     * Handles regular packages (lodash@4.17.21), scoped packages, and
     * peer-dep variants (react-dom@18.2.0(react@18.2.0)) by stripping the suffix.
     *
     * @return array{name: string, version: string}|null
     */
    private function parsePackageKey(string $key): ?array
    {
        // Strip peer-dep suffix e.g. (react@18.2.0)
        $key = (string) preg_replace('/\([^)]*\)$/', '', $key);
        $key = trim($key);

        if (empty($key)) {
            return null;
        }

        // Find last '@' at position > 0 (offset 1 skips the leading '@' of scoped packages)
        $lastAt = strrpos($key, '@', 1);

        if ($lastAt === false) {
            return null;
        }

        $name = substr($key, 0, $lastAt);
        $version = substr($key, $lastAt + 1);

        if (empty($name) || empty($version)) {
            return null;
        }

        return ['name' => $name, 'version' => $version];
    }
}
