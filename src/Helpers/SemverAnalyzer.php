<?php

declare(strict_types=1);

namespace Whatsdiff\Helpers;

use Composer\Semver\VersionParser;
use Whatsdiff\Enums\Semver;

/**
 * Helper class for analyzing semantic versioning changes.
 */
final class SemverAnalyzer
{
    private static ?VersionParser $versionParser = null;

    /**
     * Get the shared VersionParser instance.
     */
    private static function getParser(): VersionParser
    {
        if (self::$versionParser === null) {
            self::$versionParser = new VersionParser();
        }

        return self::$versionParser;
    }

    /**
     * Determine the type of semantic version change between two versions.
     *
     * @param string $fromVersion Starting version
     * @param string $toVersion Ending version
     * @return Semver|null Major, Minor, Patch, or null if cannot be determined
     */
    public static function determineSemverChangeType(string $fromVersion, string $toVersion): ?Semver
    {
        // Check for dev versions that shouldn't be analyzed
        if (self::isDevVersion($fromVersion) || self::isDevVersion($toVersion)) {
            return null;
        }

        $fromParts = self::parseVersion($fromVersion);
        $toParts = self::parseVersion($toVersion);

        if ($fromParts === null || $toParts === null) {
            return null;
        }

        if ($fromParts['major'] !== $toParts['major']) {
            return Semver::Major;
        }

        if ($fromParts['minor'] !== $toParts['minor']) {
            return Semver::Minor;
        }

        if ($fromParts['patch'] !== $toParts['patch']) {
            return Semver::Patch;
        }

        return null;
    }

    /**
     * Parse a version string into major, minor, and patch components.
     *
     * @param string $version Version string
     * @return array{major: int, minor: int, patch: int}|null
     */
    private static function parseVersion(string $version): ?array
    {
        try {
            $normalized = self::getParser()->normalize($version);
        } catch (\UnexpectedValueException) {
            return null;
        }

        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)(?:\.(\d+))?(?:-(.+))?(?:\+(.+))?$/', $normalized, $matches)) {
            return null;
        }

        return [
            'major' => (int) $matches[1],
            'minor' => (int) $matches[2],
            'patch' => (int) $matches[3],
        ];
    }

    /**
     * Check if a version is a development version.
     *
     * @param string $version Version string
     * @return bool
     */
    private static function isDevVersion(string $version): bool
    {
        return self::getParser()->parseStability($version) === 'dev';
    }
}
