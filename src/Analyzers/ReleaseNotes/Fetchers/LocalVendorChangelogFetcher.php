<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\ReleaseNotes\Fetchers;

use Composer\Semver\VersionParser;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\ReleaseNotes\ChangelogParser;
use Whatsdiff\Analyzers\ReleaseNotes\ReleaseNotesFetcherInterface;
use Whatsdiff\Data\ReleaseNotesCollection;

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
        // 'HISTORY.md',
        // 'HISTORY',
        // 'CHANGES.md',
        // 'CHANGES',
        // 'NEWS.md',
        // 'NEWS',
    ];

    private readonly VersionParser $versionParser;

    public function __construct(
        private readonly ChangelogParser $parser
    ) {
        $this->versionParser = new VersionParser();
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
        if ($localPath === null || ! is_dir($localPath)) {
            return null;
        }

        // Try to find a changelog file
        $changelogPath = $this->findChangelogFile($localPath);
        if ($changelogPath === null) {
            return null;
        }

        $content = file_get_contents($changelogPath);
        if ($content === false || empty($content)) {
            return null;
        }

        $result = $this->parser->parse($content, $fromVersion, $toVersion, $includePrerelease);

        // If the local CHANGELOG doesn't include the toVersion we're looking for,
        // return null so other fetchers can try fetching from GitHub
        // This handles cases where the local vendor has an older version than requested
        // TODO: In the future, we take everything and we complete with GitHub data
        $normalizedToVersion = $this->versionParser->normalize($toVersion);
        $hasToVersion = false;
        foreach ($result as $release) {
            $releaseVersion = $this->versionParser->normalize($release->tagName);
            if ($releaseVersion === $normalizedToVersion) {
                $hasToVersion = true;
                break;
            }
        }

        if (! $hasToVersion && ! $result->isEmpty()) {
            // Local CHANGELOG has some releases but not the one we want
            // Let GitHub fetchers try instead
            return null;
        }

        return $result;
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
            $path = $directory.DIRECTORY_SEPARATOR.$filename;
            if (file_exists($path) && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
