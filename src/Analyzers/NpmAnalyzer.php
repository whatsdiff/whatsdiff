<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers;

use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Analyzers\LockFile\NpmPackageLockFile;
use Whatsdiff\Analyzers\Registries\NpmRegistry;

class NpmAnalyzer
{
    private NpmRegistry $registry;

    public function __construct(NpmRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function extractPackageVersions(array $packageLockContent): array
    {
        // Backward compatibility: convert array to JSON and use parser
        $json = json_encode($packageLockContent);
        $parser = new NpmPackageLockFile($json);

        return $parser->getAllVersions();
    }

    public function calculateDiff(string $lastLockContent, ?string $previousLockContent): array
    {
        // Create stateful parsers
        $current = new NpmPackageLockFile($lastLockContent);
        $previous = new NpmPackageLockFile($previousLockContent ?? '{}');

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
                ],
            ])
            ->toArray();

        return $diff->merge($newPackages)
            ->filter(fn ($el) => $el['from'] !== $el['to'])
            ->sortKeys()
            ->toArray();
    }

    public function getReleasesCount(string $package, string $from, string $to): ?int
    {
        try {
            $releases = $this->registry->getVersions($package, $from, $to);
        } catch (PackageInformationsException $e) {
            return  null;
        }

        return count($releases);
    }
}
