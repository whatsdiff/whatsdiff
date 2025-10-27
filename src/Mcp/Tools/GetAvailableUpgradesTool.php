<?php

declare(strict_types=1);

namespace Whatsdiff\Mcp\Tools;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use PhpMcp\Server\Attributes\McpTool;
use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\Registries\NpmRegistry;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;

class GetAvailableUpgradesTool
{
    private VersionParser $versionParser;

    public function __construct(
        private PackagistRegistry $packagistRegistry,
        private NpmRegistry $npmRegistry
    ) {
        $this->versionParser = new VersionParser();
    }

    #[McpTool(
        name: 'get_available_upgrades',
        description: 'Get the latest available patch, minor, and major version upgrades for a package to help determine composer.json constraints.'
    )]
    public function getAvailableUpgrades(string $package, string $current_version, string $package_manager = 'composer', bool $include_prerelease = false): array
    {
        // Validate package manager
        $type = match (strtolower($package_manager)) {
            'composer' => PackageManagerType::COMPOSER,
            'npm' => PackageManagerType::NPM,
            default => null,
        };

        if ($type === null) {
            return [
                'error' => 'Invalid package manager. Must be "composer" or "npm"',
                'package' => $package,
                'current_version' => $current_version,
                'available_upgrades' => null,
            ];
        }

        // Normalize current version (remove 'v' prefix for comparison)
        $normalizedCurrentVersion = ltrim($current_version, 'vV');

        // Parse current version to extract major, minor, patch
        try {
            $normalized = $this->versionParser->normalize($normalizedCurrentVersion);
        } catch (\Exception $e) {
            return [
                'error' => "Invalid version format: {$e->getMessage()}",
                'package' => $package,
                'current_version' => $current_version,
                'available_upgrades' => null,
            ];
        }

        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $normalized, $matches)) {
            return [
                'error' => 'Could not parse version number',
                'package' => $package,
                'current_version' => $current_version,
                'available_upgrades' => null,
            ];
        }

        $currentMajor = (int) $matches[1];
        $currentMinor = (int) $matches[2];
        $currentPatch = (int) $matches[3];

        // Fetch all available versions from the registry
        try {
            $packageData = match ($type) {
                PackageManagerType::COMPOSER => $this->packagistRegistry->getPackageMetadata($package),
                PackageManagerType::NPM => $this->npmRegistry->getPackageMetadata($package),
            };
        } catch (PackageInformationsException $e) {
            return [
                'error' => $e->getMessage(),
                'package' => $package,
                'current_version' => $current_version,
                'available_upgrades' => null,
            ];
        }

        // Extract versions based on package manager type
        $versionsData = match ($type) {
            PackageManagerType::COMPOSER => $packageData['packages'][$package] ?? [],
            PackageManagerType::NPM => $packageData['versions'] ?? [],
        };

        // Extract version strings
        $versions = [];
        foreach ($versionsData as $versionData) {
            $version = $versionData['version'];

            // Skip dev versions
            if (str_contains($version, 'dev')) {
                continue;
            }

            // Skip pre-release versions unless explicitly requested
            if (!$include_prerelease) {
                try {
                    $stability = $this->versionParser->parseStability($version);
                    if ($stability !== 'stable') {
                        continue;
                    }
                } catch (\Exception $e) {
                    // If we can't parse stability, skip it to be safe
                    continue;
                }
            }

            $versions[] = $version;
        }

        // Find latest patch, minor, and major versions
        $latestPatch = null;
        $latestMinor = null;
        $latestMajor = null;

        foreach ($versions as $version) {
            try {
                // Normalize version for comparison (remove 'v' prefix)
                $normalizedVersion = ltrim($version, 'vV');

                // Skip if version is not greater than current
                if (!Comparator::greaterThan($normalizedVersion, $normalizedCurrentVersion)) {
                    continue;
                }

                // Parse version
                $vNormalized = $this->versionParser->normalize($normalizedVersion);
                if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $vNormalized, $vMatches)) {
                    continue;
                }

                $vMajor = (int) $vMatches[1];
                $vMinor = (int) $vMatches[2];
                $vPatch = (int) $vMatches[3];

                // Check for latest patch (same major.minor, higher patch)
                if ($vMajor === $currentMajor && $vMinor === $currentMinor && $vPatch > $currentPatch) {
                    if ($latestPatch === null || Comparator::greaterThan($normalizedVersion, ltrim($latestPatch, 'vV'))) {
                        $latestPatch = $version;
                    }
                }

                // Check for latest minor (same major, higher minor)
                if ($vMajor === $currentMajor && $vMinor > $currentMinor) {
                    if ($latestMinor === null || Comparator::greaterThan($normalizedVersion, ltrim($latestMinor, 'vV'))) {
                        $latestMinor = $version;
                    }
                }

                // Check for latest major (higher major)
                if ($vMajor > $currentMajor) {
                    if ($latestMajor === null || Comparator::greaterThan($normalizedVersion, ltrim($latestMajor, 'vV'))) {
                        $latestMajor = $version;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return [
            'package' => $package,
            'package_manager' => $package_manager,
            'current_version' => $current_version,
            'available_upgrades' => [
                'patch' => $latestPatch,
                'minor' => $latestMinor,
                'major' => $latestMajor,
            ],
        ];
    }
}
