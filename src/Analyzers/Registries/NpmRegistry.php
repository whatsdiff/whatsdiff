<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\Registries;

use Composer\Semver\Comparator;
use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Services\HttpService;

/**
 * Registry client for npm packages.
 *
 * Handles communication with npm registry API.
 * This registry is shared by npm, yarn, pnpm, and bun (they all use the same registry).
 */
class NpmRegistry implements RegistryInterface
{
    private HttpService $httpService;

    public function __construct(HttpService $httpService)
    {
        $this->httpService = $httpService;
    }

    /**
     * Get complete package metadata from npm registry.
     *
     * @param string $package Package name
     * @param array<string, mixed> $options Options may include:
     *   - 'url': Custom registry URL (for private registries)
     * @return array<string, mixed> Package metadata
     * @throws PackageInformationsException If package cannot be fetched
     */
    public function getPackageMetadata(string $package, array $options = []): array
    {
        $url = $options['url'] ?? PackageManagerType::NPM->getRegistryUrl($package);

        try {
            $response = $this->httpService->get($url);
        } catch (\Exception $e) {
            throw new PackageInformationsException(
                "Failed to fetch package information for {$package}: " . $e->getMessage()
            );
        }

        $packageData = json_decode($response, true);

        if ($packageData === null) {
            throw new PackageInformationsException(
                "Invalid JSON response from npm registry for package {$package}"
            );
        }

        return $packageData;
    }

    /**
     * Get versions of a package between two version constraints.
     *
     * @param string $package Package name
     * @param string $from Starting version (exclusive)
     * @param string $to Ending version (inclusive)
     * @param array<string, mixed> $options Options (see getPackageMetadata)
     * @return array<int, string> Array of version strings
     * @throws PackageInformationsException If package cannot be fetched
     */
    public function getVersions(string $package, string $from, string $to, array $options = []): array
    {
        $packageData = $this->getPackageMetadata($package, $options);

        if (!isset($packageData['versions'])) {
            return [];
        }

        $versions = $packageData['versions'];
        $returnVersions = [];

        foreach ($versions as $info) {
            $version = $info['version'];

            if (Comparator::greaterThan($version, $from) && Comparator::lessThanOrEqualTo($version, $to)) {
                $returnVersions[] = $version;
            }
        }

        return $returnVersions;
    }

    /**
     * Get repository URL for a package from npm registry.
     *
     * @param string $package Package name
     * @param array<string, mixed> $options Options (see getPackageMetadata)
     * @return string|null Repository URL or null if not available
     */
    public function getRepositoryUrl(string $package, array $options = []): ?string
    {
        try {
            $packageData = $this->getPackageMetadata($package, $options);
        } catch (PackageInformationsException $e) {
            return null;
        }

        // npm registry may have repository info at the root level
        if (isset($packageData['repository']['url'])) {
            return $this->normalizeRepositoryUrl($packageData['repository']['url']);
        }

        if (isset($packageData['repository']) && is_string($packageData['repository'])) {
            return $this->normalizeRepositoryUrl($packageData['repository']);
        }

        return null;
    }

    /**
     * Normalize repository URL from npm format.
     *
     * npm repository URLs may be in formats like:
     * - "git+https://github.com/user/repo.git"
     * - "git://github.com/user/repo.git"
     * - "https://github.com/user/repo"
     *
     * @param string $url Repository URL from npm
     * @return string Normalized HTTPS URL
     */
    private function normalizeRepositoryUrl(string $url): string
    {
        // Remove git+ prefix
        $url = preg_replace('/^git\+/', '', $url);

        // Convert git:// to https://
        $url = preg_replace('/^git:\/\//', 'https://', $url);

        // Remove .git suffix
        $url = preg_replace('/\.git$/', '', $url);

        return $url ?? '';
    }
}
