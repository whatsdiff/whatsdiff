<?php

declare(strict_types=1);

namespace Whatsdiff\Services\ReleaseNotes\Fetchers;

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Services\HttpService;
use Whatsdiff\Services\ReleaseNotes\ChangelogParser;
use Whatsdiff\Services\ReleaseNotes\ReleaseNotesFetcherInterface;

/**
 * Fetches release notes from CHANGELOG.md files on GitHub.
 *
 * Falls back to this when GitHub Releases API doesn't have meaningful content.
 * Tries multiple common changelog filenames.
 */
class GithubChangelogFetcher implements ReleaseNotesFetcherInterface
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
        private readonly HttpService $httpService,
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
        $ownerRepo = $this->extractOwnerRepo($repositoryUrl);

        if ($ownerRepo === null) {
            return null;
        }

        [$owner, $repo] = $ownerRepo;

        // Try fetching changelog from various locations
        $content = $this->fetchChangelogContent($owner, $repo, $toVersion);

        if ($content === null) {
            return null;
        }

        try {
            return $this->parser->parse($content, $fromVersion, $toVersion, $includePrerelease);
        } catch (\Exception $e) {
            // Failed to parse changelog
            return null;
        }
    }

    public function supports(string $repositoryUrl, ?string $localPath): bool
    {
        return str_contains($repositoryUrl, 'github.com');
    }

    /**
     * Extract owner and repository name from GitHub URL.
     *
     * @return array{0: string, 1: string}|null [owner, repo] or null if not a valid GitHub URL
     */
    private function extractOwnerRepo(string $url): ?array
    {
        // Remove .git suffix if present
        $url = preg_replace('/\.git$/', '', $url);

        // Match GitHub URL patterns
        // https://github.com/owner/repo
        // git@github.com:owner/repo
        if (preg_match('#github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#', $url, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return null;
    }

    /**
     * Fetch changelog content from GitHub raw URL.
     *
     * Tries multiple common filenames and falls back to main/master branch.
     */
    private function fetchChangelogContent(string $owner, string $repo, string $version): ?string
    {
        // Normalize version and also keep version without prefix
        $versionWithV = $this->normalizeVersion($version);  // Adds 'v' prefix
        $versionWithoutV = ltrim($version, 'vV');  // Removes 'v' prefix

        // Try with the specific version tag first (both with and without 'v'), then fall back to default branches
        // GitHub repos may use either 'v7.10.0' or '7.10.0' as tag names
        $branches = [$versionWithV, $versionWithoutV, 'main', 'master'];

        foreach ($branches as $branch) {
            foreach (self::CHANGELOG_FILENAMES as $filename) {
                $url = "https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}/{$filename}";

                try {
                    $content = $this->httpService->get($url);
                    if (!empty($content)) {
                        return $content;
                    }
                } catch (\Exception $e) {
                    // Try next filename/branch
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Normalize version for tag lookup.
     *
     * Ensures version has 'v' prefix if it looks like a semver version.
     */
    private function normalizeVersion(string $version): string
    {
        $version = ltrim($version, 'vV');

        // If it looks like a semver version (starts with digit), add 'v' prefix
        if (preg_match('/^\d/', $version)) {
            return 'v' . $version;
        }

        return $version;
    }
}
