<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\Registries;

use Composer\Semver\Comparator;
use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Services\HttpService;

/**
 * Registry client for Packagist (Composer packages).
 *
 * Handles communication with Packagist API and private Composer repositories.
 * Supports http-basic authentication for private packages.
 */
class PackagistRegistry implements RegistryInterface
{
    private HttpService $httpService;

    public function __construct(HttpService $httpService)
    {
        $this->httpService = $httpService;
    }

    /**
     * Get complete package metadata from Packagist.
     *
     * @param string $package Package name
     * @param array<string, mixed> $options Options may include:
     *   - 'url': Custom registry URL (for private repositories)
     *   - 'auth': Array with 'username' and 'password' for http-basic auth
     * @return array<string, mixed> Package metadata
     * @throws PackageInformationsException If package cannot be fetched
     */
    public function getPackageMetadata(string $package, array $options = []): array
    {
        $url = $options['url'] ?? PackageManagerType::COMPOSER->getRegistryUrl($package);

        try {
            // Extract authentication from URL if present
            $authOptions = $this->extractAuthFromUrl($url);
            $cleanUrl = $authOptions['url'];
            $httpOptions = $authOptions['options'];

            // Load auth from auth.json if not explicitly provided
            if (!isset($options['auth']) && empty($httpOptions['auth'])) {
                $authJson = $this->loadAuthJson();
                $domain = parse_url($cleanUrl, PHP_URL_HOST);

                if ($domain && isset($authJson['http-basic'][$domain])) {
                    $httpOptions['auth'] = $authJson['http-basic'][$domain];
                }
            }

            // Merge with provided auth options (explicit auth overrides auth.json)
            if (isset($options['auth'])) {
                $httpOptions['auth'] = $options['auth'];
            }

            $response = $this->httpService->get($cleanUrl, $httpOptions);
        } catch (\Exception $e) {
            throw new PackageInformationsException(
                "Failed to fetch package information for {$package}: " . $e->getMessage()
            );
        }

        $packageData = json_decode($response, true);

        if ($packageData === null) {
            throw new PackageInformationsException(
                "Invalid JSON response from Packagist for package {$package}"
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

        if (!isset($packageData['packages'][$package])) {
            return [];
        }

        $versions = $packageData['packages'][$package];
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
     * Get repository URL for a package from Packagist.
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

        if (!isset($packageData['packages'][$package])) {
            return null;
        }

        $versions = $packageData['packages'][$package];

        // Get the most recent version's repository URL
        $firstVersion = reset($versions);

        if ($firstVersion === false) {
            return null;
        }

        return $firstVersion['source']['url'] ?? $firstVersion['dist']['url'] ?? null;
    }

    /**
     * Extract authentication credentials from URL.
     *
     * @param string $url URL that may contain credentials
     * @return array{url: string, options: array<string, mixed>} Clean URL and extracted options
     */
    private function extractAuthFromUrl(string $url): array
    {
        $parsedUrl = parse_url($url);
        $options = [];

        if (isset($parsedUrl['user']) && isset($parsedUrl['pass'])) {
            $options['auth'] = [
                'username' => urldecode($parsedUrl['user']),
                'password' => urldecode($parsedUrl['pass']),
            ];

            // Rebuild URL without auth
            $cleanUrl = $parsedUrl['scheme'] . '://';
            $cleanUrl .= $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $cleanUrl .= ':' . $parsedUrl['port'];
            }
            $cleanUrl .= $parsedUrl['path'] ?? '';
            if (isset($parsedUrl['query'])) {
                $cleanUrl .= '?' . $parsedUrl['query'];
            }
            if (isset($parsedUrl['fragment'])) {
                $cleanUrl .= '#' . $parsedUrl['fragment'];
            }

            return ['url' => $cleanUrl, 'options' => $options];
        }

        return ['url' => $url, 'options' => []];
    }

    /**
     * Load authentication credentials from auth.json files.
     * Checks both local (project) and global (home directory) auth.json files.
     * Local auth.json takes precedence over global.
     *
     * @return array<string, mixed> Auth configuration with 'http-basic' key
     */
    private function loadAuthJson(): array
    {
        $currentDir = getcwd() ?: '';
        $localAuthPath = $currentDir . DIRECTORY_SEPARATOR . 'auth.json';

        $HOME = getenv('HOME') ?: getenv('USERPROFILE');
        $globalAuthPath = $HOME . DIRECTORY_SEPARATOR . '.composer/auth.json';

        $localAuth = [];
        $globalAuth = [];

        if (file_exists($localAuthPath)) {
            $content = file_get_contents($localAuthPath);
            if ($content !== false) {
                $localAuth = json_decode($content, true) ?: [];
            }
        }

        if (file_exists($globalAuthPath)) {
            $content = file_get_contents($globalAuthPath);
            if ($content !== false) {
                $globalAuth = json_decode($content, true) ?: [];
            }
        }

        return collect($globalAuth)->merge($localAuth)->only('http-basic')->toArray();
    }
}
