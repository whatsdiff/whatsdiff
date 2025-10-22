<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\Parsers;

use Illuminate\Support\Collection;

/**
 * Parser for package-lock.json files (npm).
 *
 * Handles the npm lock file format used by Node.js package manager.
 * Supports both lockfileVersion 1, 2, and 3 formats.
 */
class PackageLockParser implements ParserInterface
{
    /**
     * Parse package-lock.json content and extract all packages.
     *
     * @param string $lockFileContent Raw package-lock.json content (JSON)
     * @return Collection<string, array{version: string, repository?: string}> Package name => package data
     */
    public function parse(string $lockFileContent): Collection
    {
        $lockFileData = json_decode($lockFileContent, true);

        if ($lockFileData === null) {
            return collect();
        }

        $packages = collect($lockFileData['packages'] ?? [])
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

    /**
     * Extract package versions from parsed package-lock.json data.
     *
     * @param array<string, mixed> $lockFileData Parsed package-lock.json data
     * @return array<string, string> Package name => version
     */
    public function extractPackageVersions(array $lockFileData): array
    {
        return collect($lockFileData['packages'] ?? [])
            ->filter(fn ($package, $key) => !empty($key) && !empty($package['version']))
            ->mapWithKeys(fn ($package, $key) => [
                str_replace('node_modules/', '', $key) => $package['version'],
            ])
            ->filter(fn ($version, $name) => !empty($name))
            ->toArray();
    }

    /**
     * Get repository URL for a specific package from package-lock.json data.
     *
     * @param string $package Package name
     * @param array<string, mixed> $lockFileData Parsed package-lock.json data
     * @return string|null Repository URL or null if not found
     */
    public function getRepositoryUrl(string $package, array $lockFileData): ?string
    {
        $packages = collect($lockFileData['packages'] ?? []);

        // Try to find the package with exact match or node_modules/ prefix
        $packageInfo = $packages->get($package)
            ?? $packages->get("node_modules/{$package}");

        if (!$packageInfo) {
            return null;
        }

        return $packageInfo['resolved'] ?? null;
    }
}
