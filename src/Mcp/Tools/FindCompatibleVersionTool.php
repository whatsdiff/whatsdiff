<?php

declare(strict_types=1);

namespace Whatsdiff\Mcp\Tools;

use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use PhpMcp\Server\Attributes\McpTool;
use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\Registries\NpmRegistry;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;

class FindCompatibleVersionTool
{
    private VersionParser $versionParser;

    public function __construct(
        private PackagistRegistry $packagistRegistry,
        private NpmRegistry $npmRegistry
    ) {
        $this->versionParser = new VersionParser();
    }

    #[McpTool(
        name: 'find_compatible_versions',
        description: 'Find which major versions of a package are compatible with a given dependency constraint. Examples: which livewire versions work with illuminate/support ^11.0? Which illuminate/support versions work with PHP ^8.2? Which orchestra/testbench versions work with laravel/framework ^11.0?'
    )]
    public function findCompatibleVersions(string $package, string $dependency_package, string $dependency_constraint, string $package_manager = 'composer'): array
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
                'dependency_package' => $dependency_package,
                'dependency_constraint' => $dependency_constraint,
            ];
        }

        try {
            // Normalize the constraint
            $normalizedConstraint = $this->versionParser->parseConstraints($dependency_constraint);
        } catch (\Exception $e) {
            return [
                'error' => "Invalid version constraint: {$e->getMessage()}",
                'package' => $package,
                'dependency_package' => $dependency_package,
                'dependency_constraint' => $dependency_constraint,
            ];
        }

        // Fetch all package data from the appropriate registry
        try {
            $packageData = match ($type) {
                PackageManagerType::COMPOSER => $this->packagistRegistry->getPackageMetadata($package),
                PackageManagerType::NPM => $this->npmRegistry->getPackageMetadata($package),
            };
        } catch (PackageInformationsException $e) {
            return [
                'error' => $e->getMessage(),
                'package' => $package,
                'dependency_package' => $dependency_package,
                'dependency_constraint' => $dependency_constraint,
            ];
        }

        // Extract versions based on package manager type
        $versions = match ($type) {
            PackageManagerType::COMPOSER => $packageData['packages'][$package] ?? [],
            PackageManagerType::NPM => $packageData['versions'] ?? [],
        };

        $majorVersions = [];

        // Group versions by major version and check compatibility
        foreach ($versions as $versionData) {
            $version = $versionData['version'];

            // Skip dev versions
            if (str_contains($version, 'dev')) {
                continue;
            }

            // Check if this version requires the dependency
            $requires = match ($type) {
                PackageManagerType::COMPOSER => $versionData['require'] ?? [],
                PackageManagerType::NPM => $versionData['dependencies'] ?? [],
            };

            if (!isset($requires[$dependency_package])) {
                continue;
            }

            $requiredVersion = $requires[$dependency_package];

            // Parse the major version (handle versions with or without 'v' prefix)
            if (preg_match('/^v?(\d+)\./', $version, $matches)) {
                $majorVersion = (int) $matches[1];
            } else {
                continue;
            }

            // Check if the required version satisfies our constraint
            try {
                $requiredConstraint = $this->versionParser->parseConstraints($requiredVersion);

                // Check if the constraints intersect
                if ($this->constraintsIntersect($requiredConstraint, $normalizedConstraint)) {
                    if (!isset($majorVersions[$majorVersion])) {
                        $majorVersions[$majorVersion] = [
                            'major_version' => $majorVersion,
                            'example_version' => $version,
                            'requires' => $requiredVersion,
                        ];
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Sort by major version
        ksort($majorVersions);

        return [
            'package' => $package,
            'dependency_package' => $dependency_package,
            'dependency_constraint' => $dependency_constraint,
            'package_manager' => $package_manager,
            'compatible_versions' => array_values($majorVersions),
            'count' => count($majorVersions),
        ];
    }

    private function constraintsIntersect($constraint1, $constraint2): bool
    {
        // Generate test versions from the constraint strings
        $c1String = $constraint1->__toString();
        $c2String = $constraint2->__toString();

        // Extract version numbers from constraints
        $testVersions = [];
        if (preg_match_all('/(\d+\.\d+\.\d+)/', $c1String . ' ' . $c2String, $matches)) {
            $testVersions = array_merge($testVersions, $matches[1]);
        }

        // Generate additional test versions based on major versions
        for ($major = 0; $major <= 20; $major++) {
            $testVersions[] = "{$major}.0.0";
            $testVersions[] = "{$major}.1.0";
        }

        // Check if any test version satisfies both constraints
        foreach ($testVersions as $testVersion) {
            try {
                // Use VersionParser to properly normalize the version
                $testConstraint = $this->versionParser->parseConstraints($testVersion);
                if ($constraint1->matches($testConstraint) && $constraint2->matches($testConstraint)) {
                    return true;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return false;
    }
}
