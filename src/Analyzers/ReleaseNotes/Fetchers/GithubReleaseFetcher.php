<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\ReleaseNotes\Fetchers;

use Composer\Semver\Comparator;
use DateTimeImmutable;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\ReleaseNotes\ReleaseNotesFetcherInterface;
use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Services\HttpService;
use Whatsdiff\Services\VersionNormalizer;

/**
 * Fetches release notes from GitHub Releases API.
 */
class GithubReleaseFetcher implements ReleaseNotesFetcherInterface
{
    private const GITHUB_API_URL = 'https://api.github.com';

    public function __construct(
        private HttpService $httpService,
        private VersionNormalizer $versionNormalizer
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

        try {
            $apiUrl = self::GITHUB_API_URL . "/repos/{$owner}/{$repo}/releases";
            $response = $this->httpService->get($apiUrl, [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                ],
            ]);

            $releases = json_decode($response, true);

            if (!is_array($releases)) {
                return null;
            }

            return $this->buildReleaseNotesCollection(
                $releases,
                $fromVersion,
                $toVersion,
                $includePrerelease
            );
        } catch (\Exception $e) {
            // Failed to fetch releases
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
     * Build ReleaseNotesCollection from GitHub API response.
     *
     * @param array<int, mixed> $releases GitHub releases data
     * @param string $fromVersion Starting version (exclusive)
     * @param string $toVersion Ending version (inclusive)
     * @param bool $includePrerelease Whether to include pre-release versions
     */
    private function buildReleaseNotesCollection(
        array $releases,
        string $fromVersion,
        string $toVersion,
        bool $includePrerelease
    ): ReleaseNotesCollection {
        $releaseNotes = [];

        foreach ($releases as $release) {
            // Skip drafts
            if ($release['draft'] ?? false) {
                continue;
            }

            // Skip pre-releases if not included
            if (!$includePrerelease && ($release['prerelease'] ?? false)) {
                continue;
            }

            $tagName = $release['tag_name'] ?? '';
            if (empty($tagName)) {
                continue;
            }

            // Normalize version for comparison (remove 'v' prefix)
            $version = $this->versionNormalizer->normalize($tagName);

            // Filter by version range: fromVersion < version <= toVersion
            if (!$this->isVersionInRange($version, $fromVersion, $toVersion)) {
                continue;
            }

            // Parse the date
            $publishedAt = $release['published_at'] ?? $release['created_at'] ?? null;
            $date = $publishedAt ? new DateTimeImmutable($publishedAt) : new DateTimeImmutable();

            $releaseNotes[] = new ReleaseNote(
                tagName: $tagName,
                title: $release['name'] ?? $tagName,
                body: $release['body'] ?? '',
                date: $date,
                url: $release['html_url'] ?? null
            );
        }

        return new ReleaseNotesCollection($releaseNotes);
    }

    /**
     * Check if version is within range: fromVersion < version <= toVersion.
     * Special case: if fromVersion == toVersion, match only that exact version.
     */
    private function isVersionInRange(string $version, string $fromVersion, string $toVersion): bool
    {
        $normalizedFrom = $this->versionNormalizer->normalize($fromVersion);
        $normalizedTo = $this->versionNormalizer->normalize($toVersion);

        try {
            // Special case: if from == to, we want exactly that version
            if ($normalizedFrom === $normalizedTo) {
                return $version === $normalizedFrom;
            }

            return Comparator::greaterThan($version, $normalizedFrom) && Comparator::lessThanOrEqualTo($version, $normalizedTo);
        } catch (\Exception $e) {
            // Invalid version format, skip
            return false;
        }
    }
}
