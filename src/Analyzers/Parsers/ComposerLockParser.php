<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\Parsers;

use Illuminate\Support\Collection;

/**
 * Parser for composer.lock files.
 *
 * Handles the Composer lock file format used by PHP package manager.
 * Extracts both regular and dev dependencies.
 */
class ComposerLockParser implements ParserInterface
{
    /**
     * Parse composer.lock content and extract all packages.
     *
     * @param string $lockFileContent Raw composer.lock content (JSON)
     * @return Collection<string, array{version: string, repository?: string}> Package name => package data
     */
    public function parse(string $lockFileContent): Collection
    {
        $lockFileData = json_decode($lockFileContent, true);

        if ($lockFileData === null) {
            return collect();
        }

        $packages = collect($lockFileData['packages'] ?? [])
            ->merge($lockFileData['packages-dev'] ?? []);

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

    /**
     * Extract package versions from parsed composer.lock data.
     *
     * @param array<string, mixed> $lockFileData Parsed composer.lock data
     * @return array<string, string> Package name => version
     */
    public function extractPackageVersions(array $lockFileData): array
    {
        return collect($lockFileData['packages'] ?? [])
            ->merge($lockFileData['packages-dev'] ?? [])
            ->mapWithKeys(fn ($package) => [$package['name'] => $package['version']])
            ->toArray();
    }

    /**
     * Get repository URL for a specific package from composer.lock data.
     *
     * @param string $package Package name
     * @param array<string, mixed> $lockFileData Parsed composer.lock data
     * @return string|null Repository URL or null if not found
     */
    public function getRepositoryUrl(string $package, array $lockFileData): ?string
    {
        $packageInfo = collect($lockFileData['packages'] ?? [])
            ->merge($lockFileData['packages-dev'] ?? [])
            ->first(fn ($pkg) => $pkg['name'] === $package);

        if (!$packageInfo) {
            return null;
        }

        // Try source URL first, fall back to dist URL
        if (isset($packageInfo['source']['url'])) {
            return $packageInfo['source']['url'];
        }

        if (isset($packageInfo['dist']['url'])) {
            return $packageInfo['dist']['url'];
        }

        return null;
    }
}
