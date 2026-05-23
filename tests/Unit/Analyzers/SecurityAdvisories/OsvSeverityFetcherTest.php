<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\SecurityAdvisories\Fetchers\OsvSeverityFetcher;
use Whatsdiff\Enums\Severity;
use Whatsdiff\Services\HttpService;

beforeEach(function () {
    $this->httpService = Mockery::mock(HttpService::class);
    $this->fetcher = new OsvSeverityFetcher($this->httpService);
});

afterEach(function () {
    Mockery::close();
});

it('queries OSV by CVE id and reads database_specific.severity', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://api.osv.dev/v1/vulns/CVE-2026-1')
        ->andReturn(json_encode([
            'id' => 'GHSA-foo',
            'database_specific' => ['severity' => 'HIGH'],
        ]));

    expect($this->fetcher->fetch('CVE-2026-1'))->toBe(Severity::High);
});

it('falls back to numeric CVSS base score when database_specific is missing', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode([
            'id' => 'CVE-2026-1',
            'severity' => [
                ['type' => 'CVSS_V3', 'score' => '9.8'],
            ],
        ]));

    expect($this->fetcher->fetch('CVE-2026-1'))->toBe(Severity::Critical);
});

it('skips CVSS vector strings that are not numeric', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode([
            'id' => 'CVE-2026-1',
            'severity' => [
                ['type' => 'CVSS_V3', 'score' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'],
            ],
        ]));

    expect($this->fetcher->fetch('CVE-2026-1'))->toBeNull();
});

it('maps base scores to severity bands', function (float $score, Severity $expected) {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode([
            'severity' => [['type' => 'CVSS_V3', 'score' => (string) $score]],
        ]));

    expect($this->fetcher->fetch('CVE-2026-1'))->toBe($expected);
})->with([
    [9.0, Severity::Critical],
    [9.8, Severity::Critical],
    [7.0, Severity::High],
    [8.9, Severity::High],
    [4.0, Severity::Medium],
    [6.9, Severity::Medium],
    [0.1, Severity::Low],
    [3.9, Severity::Low],
]);

it('returns null on http failure', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andThrow(new RuntimeException('boom'));

    expect($this->fetcher->fetch('CVE-2026-1'))->toBeNull();
});

it('returns null when OSV payload is not an object', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn('null');

    expect($this->fetcher->fetch('CVE-2026-1'))->toBeNull();
});
