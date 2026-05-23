<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\SecurityAdvisories\Fetchers\GithubAdvisorySeverityFetcher;
use Whatsdiff\Enums\Severity;
use Whatsdiff\Services\HttpService;

beforeEach(function () {
    $this->httpService = Mockery::mock(HttpService::class);
    $this->fetcher = new GithubAdvisorySeverityFetcher($this->httpService);
});

afterEach(function () {
    Mockery::close();
});

it('queries the GitHub Advisory Database by CVE id', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://api.github.com/advisories?cve_id=CVE-2026-45073')
        ->andReturn(json_encode([
            ['severity' => 'high', 'cve_id' => 'CVE-2026-45073'],
        ]));

    expect($this->fetcher->fetch('CVE-2026-45073'))->toBe(Severity::High);
});

it('returns the highest severity when GHSA returns multiple matches', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode([
            ['severity' => 'low'],
            ['severity' => 'critical'],
            ['severity' => 'medium'],
        ]));

    expect($this->fetcher->fetch('CVE-2026-1'))->toBe(Severity::Critical);
});

it('returns null when GHSA returns an empty array', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn('[]');

    expect($this->fetcher->fetch('CVE-2026-1'))->toBeNull();
});

it('returns null on http failure', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andThrow(new RuntimeException('boom'));

    expect($this->fetcher->fetch('CVE-2026-1'))->toBeNull();
});

it('returns null when all GHSA matches have an unparseable severity', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode([
            ['severity' => null],
            ['severity' => 'mystery'],
        ]));

    expect($this->fetcher->fetch('CVE-2026-1'))->toBeNull();
});
