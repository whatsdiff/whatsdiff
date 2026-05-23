<?php

declare(strict_types=1);

namespace Whatsdiff\Analyzers\SecurityAdvisories\Fetchers;

use Whatsdiff\Analyzers\SecurityAdvisories\SeverityFetcherInterface;
use Whatsdiff\Enums\Severity;
use Whatsdiff\Services\HttpService;

/**
 * Looks up advisory severity from the GitHub Advisory Database.
 *
 * Covers most Composer advisories sourced from FriendsOfPHP because GitHub
 * imports them and assigns a CVSS-based severity, which Packagist does not.
 */
class GithubAdvisorySeverityFetcher implements SeverityFetcherInterface
{
    public function __construct(private readonly HttpService $httpService) {}

    public function fetch(string $cveId): ?Severity
    {
        $url = 'https://api.github.com/advisories?cve_id='.urlencode($cveId);

        try {
            $response = $this->httpService->get($url);
        } catch (\Exception $e) {
            return null;
        }

        $advisories = json_decode($response, true);
        if (! is_array($advisories) || empty($advisories)) {
            return null;
        }

        $max = Severity::Unknown;
        foreach ($advisories as $advisory) {
            if (! is_array($advisory)) {
                continue;
            }

            $severity = Severity::fromString($advisory['severity'] ?? null);
            if ($severity->rank() > $max->rank()) {
                $max = $severity;
            }
        }

        return $max === Severity::Unknown ? null : $max;
    }
}
