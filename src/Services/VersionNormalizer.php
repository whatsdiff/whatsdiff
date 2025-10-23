<?php

declare(strict_types=1);

namespace Whatsdiff\Services;

use Composer\Semver\VersionParser;

/**
 * Centralized service for normalizing version strings.
 *
 * Handles common version string operations like:
 * - Removing 'v' or 'V' prefixes
 * - Normalizing to semver format using Composer's VersionParser
 * - Providing consistent version comparison preparation
 */
class VersionNormalizer
{
    private readonly VersionParser $versionParser;

    public function __construct()
    {
        $this->versionParser = new VersionParser();
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
    public function normalize(string $version): string
    {
        return $this->versionParser->normalize($version);
    }

    /**
     * Get the underlying VersionParser instance.
     *
     * Useful for advanced version operations like constraints and comparisons.
     *
     * @return VersionParser The Composer VersionParser instance
     */
    public function getParser(): VersionParser
    {
        return $this->versionParser;
    }
}
