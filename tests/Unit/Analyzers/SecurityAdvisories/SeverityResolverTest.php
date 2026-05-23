<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\SecurityAdvisories\SeverityFetcherInterface;
use Whatsdiff\Analyzers\SecurityAdvisories\SeverityResolver;
use Whatsdiff\Enums\Severity;

afterEach(function () {
    Mockery::close();
});

it('returns null when no fetchers are registered', function () {
    $resolver = new SeverityResolver();

    expect($resolver->resolve('CVE-2026-1'))->toBeNull();
});

it('returns null for empty cve', function () {
    $resolver = new SeverityResolver();
    $resolver->addFetcher(Mockery::mock(SeverityFetcherInterface::class));

    expect($resolver->resolve(''))->toBeNull();
});

it('returns the first non-null non-Unknown result', function () {
    $first = Mockery::mock(SeverityFetcherInterface::class);
    $first->shouldReceive('fetch')->once()->andReturn(null);

    $second = Mockery::mock(SeverityFetcherInterface::class);
    $second->shouldReceive('fetch')->once()->andReturn(Severity::High);

    $third = Mockery::mock(SeverityFetcherInterface::class);
    $third->shouldNotReceive('fetch');

    $resolver = new SeverityResolver();
    $resolver->addFetcher($first);
    $resolver->addFetcher($second);
    $resolver->addFetcher($third);

    expect($resolver->resolve('CVE-2026-1'))->toBe(Severity::High);
});

it('treats Unknown from a fetcher as no answer and falls through', function () {
    $first = Mockery::mock(SeverityFetcherInterface::class);
    $first->shouldReceive('fetch')->once()->andReturn(Severity::Unknown);

    $second = Mockery::mock(SeverityFetcherInterface::class);
    $second->shouldReceive('fetch')->once()->andReturn(Severity::Critical);

    $resolver = new SeverityResolver();
    $resolver->addFetcher($first);
    $resolver->addFetcher($second);

    expect($resolver->resolve('CVE-2026-1'))->toBe(Severity::Critical);
});

it('returns null when all fetchers exhaust without an answer', function () {
    $first = Mockery::mock(SeverityFetcherInterface::class);
    $first->shouldReceive('fetch')->once()->andReturn(null);

    $second = Mockery::mock(SeverityFetcherInterface::class);
    $second->shouldReceive('fetch')->once()->andReturn(Severity::Unknown);

    $resolver = new SeverityResolver();
    $resolver->addFetcher($first);
    $resolver->addFetcher($second);

    expect($resolver->resolve('CVE-2026-1'))->toBeNull();
});
