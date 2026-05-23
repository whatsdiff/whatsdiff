<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\SecurityAdvisories\Fetchers;

use Whatsdiff\Analyzers\SecurityAdvisories\SeverityFetcherInterface;
use Whatsdiff\Enums\Severity;
use Whatsdiff\Services\HttpService;

/**
 * Fallback severity source that queries OSV.dev (Google's unified vulnerability DB).
 *
 * No auth required. OSV aggregates GHSA, NVD, and ecosystem-specific feeds, so it
 * occasionally has a severity for CVEs that GHSA does not. Severity is read from
 * `database_specific.severity` (for GHSA-imported records) or derived from a
 * numeric CVSS base score when present in the `severity[]` array. CVSS *vector*
 * strings are not parsed in v1 (would require implementing the CVSS scoring algo).
 */
class OsvSeverityFetcher implements SeverityFetcherInterface
{
    public function __construct(private readonly HttpService $httpService) {}

    public function fetch(string $cveId): ?Severity
    {
        $url = 'https://api.osv.dev/v1/vulns/'.urlencode($cveId);

        try {
            $response = $this->httpService->get($url);
        } catch (\Exception $e) {
            return null;
        }

        $data = json_decode($response, true);
        if (! is_array($data)) {
            return null;
        }

        $databaseSpecific = $data['database_specific']['severity'] ?? null;
        if (is_string($databaseSpecific)) {
            $severity = Severity::fromString($databaseSpecific);
            if ($severity !== Severity::Unknown) {
                return $severity;
            }
        }

        $entries = $data['severity'] ?? [];
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                $score = $entry['score'] ?? null;
                if (! is_string($score) && ! is_numeric($score)) {
                    continue;
                }

                if (is_numeric($score)) {
                    return $this->fromCvssBaseScore((float) $score);
                }
            }
        }

        return null;
    }

    private function fromCvssBaseScore(float $score): Severity
    {
        return match (true) {
            $score >= 9.0 => Severity::Critical,
            $score >= 7.0 => Severity::High,
            $score >= 4.0 => Severity::Medium,
            $score > 0.0 => Severity::Low,
            default => Severity::Unknown,
        };
    }
}
