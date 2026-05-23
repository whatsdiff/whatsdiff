<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\SecurityAdvisories;

use Whatsdiff\Enums\Severity;

interface SeverityFetcherInterface
{
    /**
     * Resolve the severity of a CVE from an external source.
     * Return null when the source has no rating for this CVE or the lookup failed.
     */
    public function fetch(string $cveId): ?Severity;
}
