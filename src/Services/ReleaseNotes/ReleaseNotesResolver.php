<?php

declare(strict_types=1);

namespace Whatsdiff\Services\ReleaseNotes;

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Data\ReleaseNotesCollection;

/**
 * Resolves release notes using a chain of responsibility pattern.
 *
 * Tries each registered fetcher in order until one succeeds.
 */
class ReleaseNotesResolver
{
    /**
     * @var array<int, ReleaseNotesFetcherInterface>
     */
    private array $fetchers = [];

    /**
     * Add a fetcher to the chain.
     *
     * Fetchers are tried in the order they are added.
     */
    public function addFetcher(ReleaseNotesFetcherInterface $fetcher): void
    {
        $this->fetchers[] = $fetcher;
    }

    /**
     * Resolve release notes by trying each fetcher in sequence.
     *
     * @param string $package Package name (e.g., "symfony/console")
     * @param string $fromVersion Starting version (exclusive)
     * @param string $toVersion Ending version (inclusive)
     * @param string $repositoryUrl Repository URL (e.g., "https://github.com/symfony/console")
     * @param PackageManagerType $packageManagerType Package manager type (COMPOSER or NPM)
     * @param string|null $localPath Local filesystem path to package (vendor/package or node_modules/package)
     * @param bool $includePrerelease Whether to include pre-release versions
     * @return ReleaseNotesCollection|null Release notes collection or null if all fetchers fail
     */
    public function resolve(
        string $package,
        string $fromVersion,
        string $toVersion,
        string $repositoryUrl,
        PackageManagerType $packageManagerType,
        ?string $localPath,
        bool $includePrerelease
    ): ?ReleaseNotesCollection {
        foreach ($this->fetchers as $fetcher) {
            // Check if this fetcher supports the source
            if (!$fetcher->supports($repositoryUrl, $localPath)) {
                continue;
            }

            // Try to fetch release notes
            $result = $fetcher->fetch(
                $package,
                $fromVersion,
                $toVersion,
                $repositoryUrl,
                $packageManagerType,
                $localPath,
                $includePrerelease
            );

            // Return the first successful result
            if ($result !== null && !$result->isEmpty()) {
                return $result;
            }
        }

        // All fetchers failed
        return null;
    }

    /**
     * Get all registered fetchers.
     *
     * @return array<int, ReleaseNotesFetcherInterface>
     */
    public function getFetchers(): array
    {
        return $this->fetchers;
    }
}
