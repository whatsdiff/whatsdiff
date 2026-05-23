<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers;

use Whatsdiff\Analyzers\LockFile\LockFileInterface;
use Whatsdiff\Analyzers\LockFile\PnpmLockFile;
use Whatsdiff\Analyzers\Registries\NpmRegistry;

/**
 * Analyzer for pnpm dependency files (pnpm-lock.yaml).
 *
 * Provides specific implementation for pnpm lock file parsing.
 * pnpm uses the same npm registry as npm, so NpmRegistry is reused.
 */
class PnpmAnalyzer extends BaseAnalyzer
{
    public function __construct(NpmRegistry $registry)
    {
        parent::__construct($registry);
    }

    /**
     * Get the package manager type this analyzer handles.
     */
    public function getType(): PackageManagerType
    {
        return PackageManagerType::PNPM;
    }

    /**
     * Calculate diff, emitting a warning if the lock file version is below 9.0.
     */
    public function calculateDiff(string $lastLockContent, ?string $previousLockContent): array
    {
        $version = (new PnpmLockFile($lastLockContent))->getLockfileVersion();

        if ($version !== null && $version < PnpmLockFile::MINIMUM_SUPPORTED_VERSION) {
            $detected = number_format($version, 1);
            $minimum = number_format(PnpmLockFile::MINIMUM_SUPPORTED_VERSION, 1);
            fwrite(STDERR, "Warning: pnpm-lock.yaml lockfileVersion {$detected} is not supported (minimum: {$minimum}). This lock file will be ignored.\n");
            return [];
        }

        return parent::calculateDiff($lastLockContent, $previousLockContent);
    }

    /**
     * Create a pnpm lock file parser.
     */
    protected function createLockFileParser(string $content): LockFileInterface
    {
        return new PnpmLockFile($content);
    }

    /**
     * pnpm packages don't need additional fields like infos_url.
     * Return empty array to use base implementation.
     */
    protected function getAdditionalPackageFields(string $packageName, array $lastLockArray, array $previousLockArray): array
    {
        return [];
    }
}
