<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers;

use Whatsdiff\Analyzers\LockFile\ComposerLockFile;
use Whatsdiff\Analyzers\LockFile\LockFileInterface;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;

/**
 * Analyzer for Composer dependency files (composer.lock).
 *
 * Provides specific implementations for Composer lock file parsing and
 * adds support for private Composer repositories via infos_url field.
 */
class ComposerAnalyzer extends BaseAnalyzer
{
    public function __construct(PackagistRegistry $registry)
    {
        parent::__construct($registry);
    }

    /**
     * Create a Composer lock file parser.
     */
    protected function createLockFileParser(string $content): LockFileInterface
    {
        return new ComposerLockFile($content);
    }

    /**
     * Add infos_url field for Composer packages to support private repositories.
     */
    protected function getAdditionalPackageFields(string $packageName, array $lastLockArray, array $previousLockArray): array
    {
        return [
            'infos_url' => $this->getPackageUrl($packageName, $lastLockArray),
        ];
    }

    /**
     * Get the package information URL, detecting private repositories.
     *
     * @param string $name Package name
     * @param array $composerLock Composer lock file content as array
     * @return string Package information URL
     */
    private function getPackageUrl(string $name, array $composerLock): string
    {
        // Default packagist url
        $url = PackageManagerType::COMPOSER->getRegistryUrl($name);

        $packageInfo = collect($composerLock['packages'] ?? [])
            ->merge($composerLock['packages-dev'] ?? [])
            ->first(fn ($package) => $package['name'] === $name);

        if (!$packageInfo) {
            return $url;
        }

        $distUrlDomain = parse_url($packageInfo['dist']['url'] ?? '', PHP_URL_HOST);

        // If it's a private repository (not repo.packagist.org), use that domain
        if ($distUrlDomain && $distUrlDomain !== 'repo.packagist.org' && $distUrlDomain !== 'api.github.com') {
            return "https://{$distUrlDomain}/p2/{$name}.json";
        }

        return $url;
    }

    /**
     * Get the number of releases between two versions for Composer packages.
     *
     * Accepts URL as array context for compatibility with BaseAnalyzer.
     *
     * @param string $package Package name
     * @param string $from Starting version
     * @param string $to Ending version
     * @param array $context Context array containing 'url' key for private repos
     * @return int|null Number of releases, or null on error
     */
    public function getReleasesCount(string $package, string $from, string $to, array $context = []): ?int
    {
        return parent::getReleasesCount($package, $from, $to, $context);
    }

    /**
     * Convenience method for getting release count with URL string.
     *
     * @param string $package Package name
     * @param string $from Starting version
     * @param string $to Ending version
     * @param string $url Package information URL (for private repos)
     * @return int|null Number of releases, or null on error
     */
    public function getReleasesCountWithUrl(string $package, string $from, string $to, string $url): ?int
    {
        return $this->getReleasesCount($package, $from, $to, ['url' => $url]);
    }
}
