<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\ReleaseNotes;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use DateTimeImmutable;
use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Helpers\VersionNormalizer;

/**
 * Parses CHANGELOG.md files in Keep a Changelog format.
 *
 * Supports common changelog formats:
 * - ## VERSION - DATE
 * - ## [VERSION] - DATE
 * - ## VERSION (DATE)
 */
class ChangelogParser
{
    public function __construct()
    {
    }
    /**
     * Parse changelog content and extract releases within version range.
     *
     * @param string $content Changelog markdown content
     * @param string $fromVersion Starting version (exclusive)
     * @param string $toVersion Ending version (inclusive)
     * @param bool $includePrerelease Whether to include pre-release versions
     * @return ReleaseNotesCollection Collection of release notes
     */
    public function parse(
        string $content,
        string $fromVersion,
        string $toVersion,
        bool $includePrerelease = false
    ): ReleaseNotesCollection {
        $releases = [];
        $normalizedFrom = VersionNormalizer::normalize($fromVersion);
        $normalizedTo = VersionNormalizer::normalize($toVersion);

        // Split content into lines for processing
        $lines = explode("\n", $content);
        $currentVersion = null;
        $currentDate = null;
        $currentContent = [];

        foreach ($lines as $line) {
            // Check if this is a version heading
            $versionData = $this->parseVersionHeader($line);

            if ($versionData !== null) {
                // Save the previous version section if it exists
                if ($currentVersion !== null) {
                    $release = $this->createReleaseNote(
                        $currentVersion,
                        $currentDate,
                        implode("\n", $currentContent)
                    );

                    if ($release !== null && $this->isVersionInRange($currentVersion, $normalizedFrom, $normalizedTo, $includePrerelease)) {
                        $releases[] = $release;
                    }
                }

                // Start new version section
                $currentVersion = $versionData['version'];
                $currentDate = $versionData['date'];
                $currentContent = [];
                continue;
            }

            // Accumulate content for current version
            if ($currentVersion !== null && trim($line) !== '') {
                $currentContent[] = $line;
            }
        }

        // Don't forget the last version
        if ($currentVersion !== null) {
            $release = $this->createReleaseNote(
                $currentVersion,
                $currentDate,
                implode("\n", $currentContent)
            );

            if ($release !== null && $this->isVersionInRange($currentVersion, $normalizedFrom, $normalizedTo, $includePrerelease)) {
                $releases[] = $release;
            }
        }

        return new ReleaseNotesCollection($releases);
    }

    /**
     * Parse a version header line and extract version and date.
     *
     * Supported formats:
     * - ## 1.0.0 - 2023-05-21
     * - ## [1.0.0] - 2023-05-21
     * - ## 1.0.0 (2023-05-21)
     * - ## v1.0.0 - 2023-05-21
     *
     * @return array{version: string, date: string|null}|null
     */
    private function parseVersionHeader(string $line): ?array
    {
        $line = trim($line);

        // Pattern for version headers
        // Matches: ## VERSION - DATE or ## [VERSION] - DATE or ## VERSION (DATE)
        $patterns = [
            // ## 1.0.0 - 2023-05-21
            '/^##\s+v?(\d+\.\d+\.\d+(?:[.-][\w.]+)?)\s+-\s+(\d{4}-\d{2}-\d{2})/',
            // ## [1.0.0] - 2023-05-21
            '/^##\s+\[v?(\d+\.\d+\.\d+(?:[.-][\w.]+)?)\]\s+-\s+(\d{4}-\d{2}-\d{2})/',
            // ## 1.0.0 (2023-05-21)
            '/^##\s+v?(\d+\.\d+\.\d+(?:[.-][\w.]+)?)\s+\((\d{4}-\d{2}-\d{2})\)/',
            // ## 1.0.0 - without date
            '/^##\s+v?(\d+\.\d+\.\d+(?:[.-][\w.]+)?)\s*$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                return [
                    'version' => $matches[1],
                    'date' => $matches[2] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Create a ReleaseNote from parsed content.
     */
    private function createReleaseNote(string $version, ?string $dateString, string $content): ?ReleaseNote
    {
        if (empty(trim($content))) {
            return null;
        }

        // Parse date
        $date = $dateString !== null
            ? DateTimeImmutable::createFromFormat('Y-m-d', $dateString) ?: new DateTimeImmutable()
            : new DateTimeImmutable();

        return new ReleaseNote(
            tagName: $version,
            title: $version,
            body: trim($content),
            date: $date,
            url: null
        );
    }

    /**
     * Check if version is within the specified range.
     *
     * Range logic: fromVersion < version <= toVersion
     * Special case: if fromVersion == toVersion, match only that version
     */
    private function isVersionInRange(
        string $version,
        string $fromVersion,
        string $toVersion,
        bool $includePrerelease
    ): bool {
        $normalizedVersion = VersionNormalizer::normalize($version);

        // Skip pre-release versions if not included
        if (!$includePrerelease && $this->isPrerelease($normalizedVersion)) {
            return false;
        }

        try {
            // Special case: if from == to, we want exactly that version
            if ($fromVersion === $toVersion) {
                return $normalizedVersion === $fromVersion;
            }

            return Comparator::greaterThan($normalizedVersion, $fromVersion)
                && Comparator::lessThanOrEqualTo($normalizedVersion, $toVersion);
        } catch (\Exception $e) {
            // Invalid version format, skip
            return false;
        }
    }

    /**
     * Check if a version is a pre-release.
     */
    private function isPrerelease(string $version): bool
    {
        $stability = VersionParser::parseStability($version);
        return in_array($stability, ['alpha', 'beta', 'RC', 'dev'], true);
    }
}
