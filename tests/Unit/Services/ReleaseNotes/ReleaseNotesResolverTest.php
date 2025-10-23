<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Services\ReleaseNotes\ReleaseNotesFetcherInterface;
use Whatsdiff\Services\ReleaseNotes\ReleaseNotesResolver;

beforeEach(function () {
    $this->resolver = new ReleaseNotesResolver();
});

it('adds fetchers to the chain', function () {
    $fetcher1 = Mockery::mock(ReleaseNotesFetcherInterface::class);
    $fetcher2 = Mockery::mock(ReleaseNotesFetcherInterface::class);

    $this->resolver->addFetcher($fetcher1);
    $this->resolver->addFetcher($fetcher2);

    expect($this->resolver->getFetchers())->toHaveCount(2);
});

it('tries fetchers in order until one succeeds', function () {
    $fetcher1 = Mockery::mock(ReleaseNotesFetcherInterface::class);
    $fetcher2 = Mockery::mock(ReleaseNotesFetcherInterface::class);

    $collection = new ReleaseNotesCollection([
        new ReleaseNote(
            tagName: 'v1.1.0',
            title: 'Release',
            body: 'Body',
            date: new DateTimeImmutable()
        ),
    ]);

    // First fetcher supports but returns null
    $fetcher1->shouldReceive('supports')
        ->once()
        ->with('https://github.com/owner/repo', null)
        ->andReturn(true);

    $fetcher1->shouldReceive('fetch')
        ->once()
        ->andReturn(null);

    // Second fetcher supports and returns result
    $fetcher2->shouldReceive('supports')
        ->once()
        ->with('https://github.com/owner/repo', null)
        ->andReturn(true);

    $fetcher2->shouldReceive('fetch')
        ->once()
        ->with(
            'owner/repo',
            'v1.0.0',
            'v1.1.0',
            'https://github.com/owner/repo',
            PackageManagerType::COMPOSER,
            null,
            false
        )
        ->andReturn($collection);

    $this->resolver->addFetcher($fetcher1);
    $this->resolver->addFetcher($fetcher2);

    $result = $this->resolver->resolve(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->toBe($collection);
});

it('skips fetchers that do not support the source', function () {
    $fetcher1 = Mockery::mock(ReleaseNotesFetcherInterface::class);
    $fetcher2 = Mockery::mock(ReleaseNotesFetcherInterface::class);

    $collection = new ReleaseNotesCollection([
        new ReleaseNote(
            tagName: 'v1.1.0',
            title: 'Release',
            body: 'Body',
            date: new DateTimeImmutable()
        ),
    ]);

    // First fetcher does not support
    $fetcher1->shouldReceive('supports')
        ->once()
        ->with('https://github.com/owner/repo', null)
        ->andReturn(false);

    $fetcher1->shouldReceive('fetch')
        ->never();

    // Second fetcher supports and returns result
    $fetcher2->shouldReceive('supports')
        ->once()
        ->with('https://github.com/owner/repo', null)
        ->andReturn(true);

    $fetcher2->shouldReceive('fetch')
        ->once()
        ->andReturn($collection);

    $this->resolver->addFetcher($fetcher1);
    $this->resolver->addFetcher($fetcher2);

    $result = $this->resolver->resolve(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->toBe($collection);
});

it('returns null when all fetchers fail', function () {
    $fetcher1 = Mockery::mock(ReleaseNotesFetcherInterface::class);
    $fetcher2 = Mockery::mock(ReleaseNotesFetcherInterface::class);

    // First fetcher returns null
    $fetcher1->shouldReceive('supports')->once()->andReturn(true);
    $fetcher1->shouldReceive('fetch')->once()->andReturn(null);

    // Second fetcher returns null
    $fetcher2->shouldReceive('supports')->once()->andReturn(true);
    $fetcher2->shouldReceive('fetch')->once()->andReturn(null);

    $this->resolver->addFetcher($fetcher1);
    $this->resolver->addFetcher($fetcher2);

    $result = $this->resolver->resolve(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->toBeNull();
});

it('returns null when no fetchers are registered', function () {
    $result = $this->resolver->resolve(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->toBeNull();
});

it('skips empty results and continues to next fetcher', function () {
    $fetcher1 = Mockery::mock(ReleaseNotesFetcherInterface::class);
    $fetcher2 = Mockery::mock(ReleaseNotesFetcherInterface::class);

    $emptyCollection = new ReleaseNotesCollection();
    $validCollection = new ReleaseNotesCollection([
        new ReleaseNote(
            tagName: 'v1.1.0',
            title: 'Release',
            body: 'Body',
            date: new DateTimeImmutable()
        ),
    ]);

    // First fetcher returns empty collection
    $fetcher1->shouldReceive('supports')->once()->andReturn(true);
    $fetcher1->shouldReceive('fetch')->once()->andReturn($emptyCollection);

    // Second fetcher returns valid collection
    $fetcher2->shouldReceive('supports')->once()->andReturn(true);
    $fetcher2->shouldReceive('fetch')->once()->andReturn($validCollection);

    $this->resolver->addFetcher($fetcher1);
    $this->resolver->addFetcher($fetcher2);

    $result = $this->resolver->resolve(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->toBe($validCollection);
});

it('passes all parameters to fetchers correctly', function () {
    $fetcher = Mockery::mock(ReleaseNotesFetcherInterface::class);

    $collection = new ReleaseNotesCollection([
        new ReleaseNote(
            tagName: 'v2.1.0',
            title: 'Release',
            body: 'Body',
            date: new DateTimeImmutable()
        ),
    ]);

    $fetcher->shouldReceive('supports')
        ->once()
        ->with('https://github.com/owner/repo', '/path/to/vendor/owner/repo')
        ->andReturn(true);

    $fetcher->shouldReceive('fetch')
        ->once()
        ->with(
            'owner/repo',
            'v2.0.0',
            'v3.0.0',
            'https://github.com/owner/repo',
            PackageManagerType::NPM,
            '/path/to/vendor/owner/repo',
            true
        )
        ->andReturn($collection);

    $this->resolver->addFetcher($fetcher);

    $result = $this->resolver->resolve(
        package: 'owner/repo',
        fromVersion: 'v2.0.0',
        toVersion: 'v3.0.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::NPM,
        localPath: '/path/to/vendor/owner/repo',
        includePrerelease: true
    );

    expect($result)->toBe($collection);
});
