<?php

declare(strict_types=1);

namespace Whatsdiff\Services\ReleaseNotes\Fetchers;

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Services\ReleaseNotes\ChangelogParser;
use Whatsdiff\Services\ReleaseNotes\ReleaseNotesFetcherInterface;

/**
 * Fetches release notes from local CHANGELOG files.
 *
 * Checks vendor/package or node_modules/package directories for common changelog filenames.
 * This is the fastest fetcher as it doesn't require network requests.
 */
class LocalVendorChangelogFetcher implements ReleaseNotesFetcherInterface
{
    /**
     * Common changelog filenames to check, in order of preference.
     */
    private const CHANGELOG_FILENAMES = [
        'CHANGELOG.md',
        'CHANGELOG',
        'HISTORY.md',
        'HISTORY',
        'CHANGES.md',
        'CHANGES',
        'NEWS.md',
        'NEWS',
    ];

    public function __construct(
        private readonly ChangelogParser $parser
    ) {
    }

    public function fetch(
        string $package,
        string $fromVersion,
        string $toVersion,
        string $repositoryUrl,
        PackageManagerType $packageManagerType,
        ?string $localPath,
        bool $includePrerelease
    ): ?ReleaseNotesCollection {
        // Cannot fetch from local if no local path provided
        if ($localPath === null || !is_dir($localPath)) {
            return null;
        }

        // Try to find a changelog file
        $changelogPath = $this->findChangelogFile($localPath);
        if ($changelogPath === null) {
            return null;
        }

        try {
            $content = file_get_contents($changelogPath);
            if ($content === false || empty($content)) {
                return null;
            }

            $result = $this->parser->parse($content, $fromVersion, $toVersion, $includePrerelease);

            // If the local CHANGELOG doesn't include the toVersion we're looking for,
            // return null so other fetchers can try fetching from GitHub
            // This handles cases where the local vendor has an older version than requested
            $normalizedToVersion = ltrim($toVersion, 'vV');
            $hasToVersion = false;
            foreach ($result as $release) {
                $releaseVersion = ltrim($release->tagName, 'vV');
                if ($releaseVersion === $normalizedToVersion) {
                    $hasToVersion = true;
                    break;
                }
            }

            if (!$hasToVersion && !$result->isEmpty()) {
                // Local CHANGELOG has some releases but not the one we want
                // Let GitHub fetchers try instead
                return null;
            }

            return $result;
        } catch (\Exception $e) {
            // Failed to read or parse changelog
            return null;
        }
    }

    public function supports(string $repositoryUrl, ?string $localPath): bool
    {
        // This fetcher supports any repository as long as there's a local path
        return $localPath !== null && is_dir($localPath);
    }

    /**
     * Find a changelog file in the package directory.
     *
     * @return string|null Path to changelog file or null if not found
     */
    private function findChangelogFile(string $directory): ?string
    {
        foreach (self::CHANGELOG_FILENAMES as $filename) {
            $path = $directory . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($path) && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
