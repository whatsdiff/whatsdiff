<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\ReleaseNotes\Fetchers;

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\ReleaseNotes\ChangelogParser;
use Whatsdiff\Analyzers\ReleaseNotes\ReleaseNotesFetcherInterface;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Services\HttpService;

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
        // 'HISTORY.md',
        // 'HISTORY',
        // 'CHANGES.md',
        // 'CHANGES',
        // 'NEWS.md',
        // 'NEWS',
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
        // git@github.com:owner/repo
        // https://github.com/owner/repo
        // https://github.com/owner/repo.git
        if (preg_match('#github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#', $url, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return null;
    }

    /**
     * Fetch changelog content from GitHub Contents API.
     *
     * Tries multiple common filenames until one is found.
     */
    private function fetchChangelogContent(string $owner, string $repo, string $version): ?string
    {
        foreach (self::CHANGELOG_FILENAMES as $filename) {
            $url = "https://api.github.com/repos/{$owner}/{$repo}/contents/{$filename}";

            try {
                $content = $this->httpService->get($url, [
                    'headers' => [
                        'Accept' => 'application/vnd.github.raw',
                    ],
                ]);
                if (! empty($content)) {
                    return $content;
                }
            } catch (\Exception $e) {
                // Try next filename/ref
                continue;
            }
        }

        return null;
    }

}
