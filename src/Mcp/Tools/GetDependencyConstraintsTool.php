<?php

declare(strict_types=1);

namespace Whatsdiff\Mcp\Tools;

use PhpMcp\Server\Attributes\McpTool;
use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\Registries\NpmRegistry;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;

class GetDependencyConstraintsTool
{
    public function __construct(
        private PackagistRegistry $packagistRegistry,
        private NpmRegistry $npmRegistry
    ) {
    }

    #[McpTool(
        name: 'get_dependency_constraints',
        description: 'Get all dependencies required by a specific package version. Examples: what does livewire/livewire v3.0.0 require? What does illuminate/support v11.0.0 need?'
    )]
    public function getDependencyConstraints(string $package, string $version, string $package_manager = 'composer'): array
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
                'version' => $version,
            ];
        }

        // Normalize version (remove 'v' prefix for searching)
        $searchVersion = ltrim($version, 'vV');
        $vVersion = 'v' . $searchVersion;

        // Fetch package metadata
        try {
            $packageData = match ($type) {
                PackageManagerType::COMPOSER => $this->packagistRegistry->getPackageMetadata($package),
                PackageManagerType::NPM => $this->npmRegistry->getPackageMetadata($package),
            };
        } catch (PackageInformationsException $e) {
            return [
                'error' => $e->getMessage(),
                'package' => $package,
                'version' => $version,
            ];
        }

        // Extract versions based on package manager type
        $versionsData = match ($type) {
            PackageManagerType::COMPOSER => $packageData['packages'][$package] ?? [],
            PackageManagerType::NPM => $packageData['versions'] ?? [],
        };

        // Find the specific version (try both with and without 'v' prefix)
        $versionInfo = null;
        foreach ($versionsData as $versionData) {
            $v = $versionData['version'];
            if ($v === $version || $v === $searchVersion || $v === $vVersion) {
                $versionInfo = $versionData;
                break;
            }
        }

        if ($versionInfo === null) {
            return [
                'error' => "Version {$version} not found for package {$package}",
                'package' => $package,
                'version' => $version,
            ];
        }

        // Extract dependencies based on package manager type
        if ($type === PackageManagerType::COMPOSER) {
            return [
                'package' => $package,
                'version' => $versionInfo['version'],
                'package_manager' => $package_manager,
                'dependencies' => [
                    'require' => $versionInfo['require'] ?? [],
                    'require-dev' => $versionInfo['require-dev'] ?? [],
                ],
            ];
        } else { // NPM
            return [
                'package' => $package,
                'version' => $versionInfo['version'],
                'package_manager' => $package_manager,
                'dependencies' => [
                    'dependencies' => $versionInfo['dependencies'] ?? [],
                    'devDependencies' => $versionInfo['devDependencies'] ?? [],
                    'peerDependencies' => $versionInfo['peerDependencies'] ?? [],
                ],
            ];
        }
    }
}
