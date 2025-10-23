<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers;

use Whatsdiff\Analyzers\LockFile\LockFileInterface;
use Whatsdiff\Analyzers\LockFile\NpmPackageLockFile;
use Whatsdiff\Analyzers\Registries\NpmRegistry;

/**
 * Analyzer for NPM dependency files (package-lock.json).
 *
 * Provides specific implementation for NPM lock file parsing.
 */
class NpmAnalyzer extends BaseAnalyzer
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
        return PackageManagerType::NPM;
    }

    /**
     * Create an NPM lock file parser.
     */
    protected function createLockFileParser(string $content): LockFileInterface
    {
        return new NpmPackageLockFile($content);
    }

    /**
     * NPM packages don't need additional fields like infos_url.
     * Return empty array to use base implementation.
     */
    protected function getAdditionalPackageFields(string $packageName, array $lastLockArray, array $previousLockArray): array
    {
        return [];
    }
}
