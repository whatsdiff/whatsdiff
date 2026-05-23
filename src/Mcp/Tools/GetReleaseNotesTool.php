<?php

declare(strict_types=1);

namespace Whatsdiff\Mcp\Tools;

use PhpMcp\Server\Attributes\McpTool;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\Registries\NpmRegistry;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;
use Whatsdiff\Analyzers\ReleaseNotes\ReleaseNotesResolver;

class GetReleaseNotesTool
{
    public function __construct(
        private ReleaseNotesResolver $releaseNotesResolver,
        private PackagistRegistry $packagistRegistry,
        private NpmRegistry $npmRegistry
    ) {
    }

    #[McpTool(
        name: 'get_release_notes',
        description: 'Fetch aggregated release notes for a package between two versions from GitHub releases.'
    )]
    public function getReleaseNotes(string $package, string $from_version, string $to_version, string $package_manager = 'composer'): array
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
            ];
        }

        // Get repository URL from registry
        try {
            $repositoryUrl = match ($type) {
                PackageManagerType::COMPOSER => $this->packagistRegistry->getRepositoryUrl($package),
                PackageManagerType::NPM => $this->npmRegistry->getRepositoryUrl($package),
            };

            if ($repositoryUrl === null) {
                return [
                    'error' => "Could not find repository URL for package '{$package}'",
                    'package' => $package,
                ];
            }
        } catch (\Exception $e) {
            return [
                'error' => "Failed to fetch repository information: {$e->getMessage()}",
                'package' => $package,
            ];
        }

        // Fetch release notes using the resolver
        try {
            $releaseNotesCollection = $this->releaseNotesResolver->resolve(
                package: $package,
                fromVersion: $from_version,
                toVersion: $to_version,
                repositoryUrl: $repositoryUrl,
                packageManagerType: $type,
                localPath: null,
                includePrerelease: false
            );

            if ($releaseNotesCollection === null || $releaseNotesCollection->isEmpty()) {
                return [
                    'package' => $package,
                    'repository' => $repositoryUrl,
                    'from_version' => $from_version,
                    'to_version' => $to_version,
                    'releases' => [],
                    'count' => 0,
                    'message' => 'No releases found between the specified versions',
                ];
            }

            // Convert release notes to array format
            $releases = [];
            foreach ($releaseNotesCollection->getReleases() as $releaseNote) {
                $releases[] = [
                    'version' => $releaseNote->tagName,
                    'title' => $releaseNote->title,
                    'body' => $releaseNote->body,
                    'date' => $releaseNote->date->format('Y-m-d H:i:s'),
                    'url' => $releaseNote->url,
                ];
            }

            return [
                'package' => $package,
                'repository' => $repositoryUrl,
                'from_version' => $from_version,
                'to_version' => $to_version,
                'releases' => $releases,
                'count' => count($releases),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'package' => $package,
                'repository' => $repositoryUrl,
            ];
        }
    }
}
