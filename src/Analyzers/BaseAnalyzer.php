<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers;

use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Analyzers\LockFile\LockFileInterface;
use Whatsdiff\Analyzers\Registries\RegistryInterface;

/**
 * Base analyzer providing common functionality for dependency file analyzers.
 *
 * Uses Template Method pattern to allow subclasses to customize specific behaviors
 * while sharing the common diff calculation logic.
 */
abstract class BaseAnalyzer
{
    public function __construct(
        protected readonly RegistryInterface $registry
    ) {
    }

    /**
     * Extract package versions from lock file content array.
     *
     * @param array $lockContent Lock file content as an associative array
     * @return array<string, string> Package name => version map
     */
    public function extractPackageVersions(array $lockContent): array
    {
        // Backward compatibility: convert array to JSON and use parser
        $json = json_encode($lockContent);
        $parser = $this->createLockFileParser($json);

        return $parser->getAllVersions();
    }

    /**
     * Calculate the difference between two lock file versions.
     *
     * @param string $lastLockContent Current/latest lock file content (JSON)
     * @param string|null $previousLockContent Previous lock file content (JSON), null if no previous version
     * @return array Array of package changes with name, from, to versions, and optional metadata
     */
    public function calculateDiff(string $lastLockContent, ?string $previousLockContent): array
    {
        // Parse additional metadata if needed by subclass
        $lastLockArray = json_decode($lastLockContent, true) ?? [];
        $previousLockArray = json_decode($previousLockContent ?? '{}', true) ?? [];

        // Create stateful parsers
        $current = $this->createLockFileParser($lastLockContent);
        $previous = $this->createLockFileParser($previousLockContent ?? '{}');

        // Get versions
        $currentVersions = $current->getAllVersions();
        $previousVersions = $previous->getAllVersions();

        // Build diff: packages that existed before
        $diff = collect($previousVersions)
            ->mapWithKeys(fn ($version, $name) => [
                $name => array_merge(
                    [
                        'name' => $name,
                        'from' => $version,
                        'to' => $currentVersions[$name] ?? null,
                    ],
                    $this->getAdditionalPackageFields($name, $lastLockArray, $previousLockArray)
                ),
            ]);

        // Add new packages
        $newPackages = collect($currentVersions)
            ->diffKeys($previousVersions)
            ->mapWithKeys(fn ($version, $name) => [
                $name => array_merge(
                    [
                        'name' => $name,
                        'from' => null,
                        'to' => $version,
                    ],
                    $this->getAdditionalPackageFields($name, $lastLockArray, $previousLockArray)
                ),
            ])
            ->toArray();

        return $diff->merge($newPackages)
            ->filter(fn ($el) => $el['from'] !== $el['to'])
            ->sortKeys()
            ->toArray();
    }

    /**
     * Get the number of releases between two versions.
     *
     * @param string $package Package name
     * @param string $from Starting version
     * @param string $to Ending version
     * @param array<string, mixed> $context Additional context (e.g., registry URL)
     * @return int|null Number of releases, or null on error
     */
    public function getReleasesCount(string $package, string $from, string $to, array $context = []): ?int
    {
        try {
            $releases = $this->registry->getVersions($package, $from, $to, $context);
        } catch (PackageInformationsException $e) {
            return null;
        }

        return count($releases);
    }

    /**
     * Create a lock file parser instance.
     *
     * Subclasses must implement this to return their specific lock file parser.
     *
     * @param string $content Lock file content (JSON)
     * @return LockFileInterface Lock file parser instance
     */
    abstract protected function createLockFileParser(string $content): LockFileInterface;

    /**
     * Get additional fields to include in the package diff result.
     *
     * Allows subclasses to add custom fields (e.g., infos_url for private repos).
     * Default implementation returns an empty array.
     *
     * @param string $packageName Package name
     * @param array $lastLockArray Current lock file as array
     * @param array $previousLockArray Previous lock file as array
     * @return array<string, mixed> Additional fields to merge into package diff
     */
    protected function getAdditionalPackageFields(string $packageName, array $lastLockArray, array $previousLockArray): array
    {
        return [];
    }
}
