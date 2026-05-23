<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\SecurityAdvisories;

use Whatsdiff\Enums\Severity;

/**
 * Chain-of-responsibility resolver for advisory severity.
 *
 * Iterates registered fetchers in order and returns the first non-null,
 * non-Unknown severity. Mirrors the ReleaseNotesResolver pattern.
 */
class SeverityResolver
{
    /**
     * @var array<SeverityFetcherInterface>
     */
    private array $fetchers = [];

    public function addFetcher(SeverityFetcherInterface $fetcher): self
    {
        $this->fetchers[] = $fetcher;

        return $this;
    }

    public function resolve(string $cveId): ?Severity
    {
        if ($cveId === '') {
            return null;
        }

        foreach ($this->fetchers as $fetcher) {
            $severity = $fetcher->fetch($cveId);
            if ($severity !== null && $severity !== Severity::Unknown) {
                return $severity;
            }
        }

        return null;
    }
}
