<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers;

use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Analyzers\LockFile\ComposerLockFile;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;

class ComposerAnalyzer
{
    private PackagistRegistry $registry;

    public function __construct(PackagistRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function extractPackageVersions(array $composerLockContent): array
    {
        // Backward compatibility: convert array to JSON and use parser
        $json = json_encode($composerLockContent);
        $parser = new ComposerLockFile($json);

        return $parser->getAllVersions();
    }

    public function calculateDiff(string $lastLockContent, ?string $previousLockContent): array
    {
        // Parse lock file to detect private repositories
        $lastLock = json_decode($lastLockContent, true) ?? [];
        $previousLock = json_decode($previousLockContent ?? '{}', true) ?? [];

        // Create stateful parsers
        $current = new ComposerLockFile($lastLockContent);
        $previous = new ComposerLockFile($previousLockContent ?? '{}');

        // Get versions
        $currentVersions = $current->getAllVersions();
        $previousVersions = $previous->getAllVersions();

        // Build diff: packages that existed before
        $diff = collect($previousVersions)
            ->mapWithKeys(fn ($version, $name) => [
                $name => [
                    'name' => $name,
                    'from' => $version,
                    'to' => $currentVersions[$name] ?? null,
                    'infos_url' => $this->getPackageUrl($name, $lastLock),
                ],
            ]);

        // Add new packages
        $newPackages = collect($currentVersions)
            ->diffKeys($previousVersions)
            ->mapWithKeys(fn ($version, $name) => [
                $name => [
                    'name' => $name,
                    'from' => null,
                    'to' => $version,
                    'infos_url' => $this->getPackageUrl($name, $lastLock),
                ],
            ])
            ->toArray();

        return $diff->merge($newPackages)
            ->filter(fn ($el) => $el['from'] !== $el['to'])
            ->sortKeys()
            ->toArray();
    }

    private function getPackageUrl(string $name, array $composerLock): string
    {
        // Default packagist url
        $url = PackageManagerType::COMPOSER->getRegistryUrl($name);

        $packageInfo = collect($composerLock['packages'] ?? [])
            ->merge($composerLock['packages-dev'] ?? [])
            ->first(fn ($package) => $package['name'] === $name);

        if (!$packageInfo) {
            return $url;
        }

        $distUrlDomain = parse_url($packageInfo['dist']['url'] ?? '', PHP_URL_HOST);

        // If it's a private repository (not repo.packagist.org), use that domain
        if ($distUrlDomain && $distUrlDomain !== 'repo.packagist.org' && $distUrlDomain !== 'api.github.com') {
            return "https://{$distUrlDomain}/p2/{$name}.json";
        }

        return $url;
    }

    public function getReleasesCount(string $package, string $from, string $to, string $url): ?int
    {
        try {
            $releases = $this->registry->getVersions($package, $from, $to, [
                'url' => $url,
            ]);
        } catch (PackageInformationsException $e) {
            return  null;
        }

        return count($releases);
    }
}
