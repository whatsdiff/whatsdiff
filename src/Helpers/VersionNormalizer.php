<?php

declare(strict_types=1);

namespace Whatsdiff\Helpers;

use Composer\Semver\VersionParser;

/**
 * Helper class for normalizing version strings.
 *
 * Handles common version string operations like:
 * - Removing 'v' or 'V' prefixes
 * - Normalizing to semver format using Composer's VersionParser
 * - Providing consistent version comparison preparation
 */
class VersionNormalizer
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
     * Normalize a version string to full semver format using Composer's VersionParser.
     *
     * This method:
     * - Removes 'v'/'V' prefix
     * - Normalizes to full semver format (e.g., "1.0" -> "1.0.0.0")
     * - Handles stability flags (dev, alpha, beta, RC)
     *
     * Examples:
     * - "v1.2.3" -> "1.2.3.0"
     * - "2.0" -> "2.0.0.0"
     * - "1.0.0-beta1" -> "1.0.0.0-beta1"
     *
     * @param string $version Version string to normalize
     * @return string Fully normalized semver string
     * @throws \UnexpectedValueException If version string is invalid
     */
    public static function normalize(string $version): string
    {
        return self::getParser()->normalize($version);
    }

    /**
     * Strip version prefix ('v' or 'V') from a version string.
     *
     * Examples:
     * - "v1.2.3" -> "1.2.3"
     * - "V2.0.0" -> "2.0.0"
     * - "1.0.0" -> "1.0.0"
     *
     * @param string $version Version string
     * @return string Version string without prefix
     */
    public static function stripPrefix(string $version): string
    {
        return ltrim($version, 'vV');
    }
}
