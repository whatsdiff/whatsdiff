<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\ReleaseNotes\Fetchers\GithubReleaseFetcher;
use Whatsdiff\Services\HttpService;
use Whatsdiff\Services\VersionNormalizer;

beforeEach(function () {
    $this->httpService = Mockery::mock(HttpService::class);
    $this->versionNormalizer = Mockery::mock(VersionNormalizer::class);
    $this->fetcher = new GithubReleaseFetcher($this->httpService, $this->versionNormalizer);
});

it('supports github.com URLs', function () {
    expect($this->fetcher->supports('https://github.com/symfony/console', null))->toBe(true)
        ->and($this->fetcher->supports('git@github.com:symfony/console.git', null))->toBe(true)
        ->and($this->fetcher->supports('https://github.com/owner/repo.git', null))->toBe(true);
});

it('does not support non-github URLs', function () {
    expect($this->fetcher->supports('https://gitlab.com/owner/repo', null))->toBe(false)
        ->and($this->fetcher->supports('https://bitbucket.org/owner/repo', null))->toBe(false);
});

it('fetches releases from github api', function () {
    $apiResponse = [
        [
            'tag_name' => 'v1.1.0',
            'name' => 'Version 1.1.0',
            'body' => '## Changes\n- Feature A',
            'published_at' => '2024-01-15T10:00:00Z',
            'html_url' => 'https://github.com/symfony/console/releases/tag/v1.1.0',
            'draft' => false,
            'prerelease' => false,
        ],
        [
            'tag_name' => 'v1.0.0',
            'name' => 'Version 1.0.0',
            'body' => '## Changes\n- Initial release',
            'published_at' => '2024-01-01T10:00:00Z',
            'html_url' => 'https://github.com/symfony/console/releases/tag/v1.0.0',
            'draft' => false,
            'prerelease' => false,
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://api.github.com/repos/symfony/console/releases', [
            'headers' => ['Accept' => 'application/vnd.github+json'],
        ])
        ->andReturn(json_encode($apiResponse));

    $this->versionNormalizer
        ->shouldReceive('normalize')
        ->andReturnUsing(fn ($v) => str_replace('v', '', $v) . '.0.0');

    $result = $this->fetcher->fetch(
        package: 'symfony/console',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://github.com/symfony/console',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(1) // Only v1.1.0 (v1.0.0 is excluded as it's the 'from' version)
        ->and($result->getReleases()[0]->tagName)->toBe('v1.1.0');
});

it('filters releases by version range', function () {
    $apiResponse = [
        [
            'tag_name' => 'v2.0.0',
            'name' => 'v2.0.0',
            'body' => 'Release 2.0.0',
            'published_at' => '2024-03-01T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v2.0.0',
            'draft' => false,
            'prerelease' => false,
        ],
        [
            'tag_name' => 'v1.5.0',
            'name' => 'v1.5.0',
            'body' => 'Release 1.5.0',
            'published_at' => '2024-02-15T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.5.0',
            'draft' => false,
            'prerelease' => false,
        ],
        [
            'tag_name' => 'v1.2.0',
            'name' => 'v1.2.0',
            'body' => 'Release 1.2.0',
            'published_at' => '2024-02-01T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.2.0',
            'draft' => false,
            'prerelease' => false,
        ],
        [
            'tag_name' => 'v1.0.0',
            'name' => 'v1.0.0',
            'body' => 'Release 1.0.0',
            'published_at' => '2024-01-01T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.0.0',
            'draft' => false,
            'prerelease' => false,
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($apiResponse));

    $this->versionNormalizer
        ->shouldReceive('normalize')
        ->andReturnUsing(fn ($v) => str_replace('v', '', $v) . '.0.0');

    $result = $this->fetcher->fetch(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.5.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(2) // v1.2.0 and v1.5.0
        ->and($result->getReleases()[0]->tagName)->toBe('v1.5.0')
        ->and($result->getReleases()[1]->tagName)->toBe('v1.2.0');
});

it('excludes draft releases', function () {
    $apiResponse = [
        [
            'tag_name' => 'v1.1.0',
            'name' => 'v1.1.0',
            'body' => 'Release',
            'published_at' => '2024-01-15T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.1.0',
            'draft' => true, // Draft
            'prerelease' => false,
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($apiResponse));

    $this->versionNormalizer
        ->shouldReceive('normalize')
        ->andReturnUsing(fn ($v) => str_replace('v', '', $v) . '.0.0');

    $result = $this->fetcher->fetch(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->not->toBeNull()
        ->and($result->isEmpty())->toBe(true);
});

it('excludes prerelease when not included', function () {
    $apiResponse = [
        [
            'tag_name' => 'v1.1.0-beta.1',
            'name' => 'v1.1.0-beta.1',
            'body' => 'Beta release',
            'published_at' => '2024-01-15T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.1.0-beta.1',
            'draft' => false,
            'prerelease' => true,
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($apiResponse));

    $this->versionNormalizer
        ->shouldReceive('normalize')
        ->andReturnUsing(fn ($v) => str_replace('v', '', $v) . '.0.0');

    $result = $this->fetcher->fetch(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->not->toBeNull()
        ->and($result->isEmpty())->toBe(true);
});

it('includes prerelease when requested', function () {
    $apiResponse = [
        [
            'tag_name' => 'v1.1.0-beta.1',
            'name' => 'v1.1.0-beta.1',
            'body' => 'Beta release',
            'published_at' => '2024-01-15T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.1.0-beta.1',
            'draft' => false,
            'prerelease' => true,
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($apiResponse));

    $this->versionNormalizer
        ->shouldReceive('normalize')
        ->andReturnUsing(fn ($v) => str_replace('v', '', $v) . '.0.0');

    $result = $this->fetcher->fetch(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: true
    );

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(1)
        ->and($result->getReleases()[0]->tagName)->toBe('v1.1.0-beta.1');
});

it('returns null when repository URL is not github', function () {
    $result = $this->fetcher->fetch(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://gitlab.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->toBeNull();
});

it('returns null when http request fails', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andThrow(new Exception('Network error'));

    $result = $this->fetcher->fetch(
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

it('handles git@github.com URL format', function () {
    $apiResponse = [
        [
            'tag_name' => 'v1.1.0',
            'name' => 'v1.1.0',
            'body' => 'Release',
            'published_at' => '2024-01-15T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.1.0',
            'draft' => false,
            'prerelease' => false,
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://api.github.com/repos/owner/repo/releases', Mockery::any())
        ->andReturn(json_encode($apiResponse));

    $this->versionNormalizer
        ->shouldReceive('normalize')
        ->andReturnUsing(fn ($v) => str_replace('v', '', $v) . '.0.0');

    $result = $this->fetcher->fetch(
        package: 'owner/repo',
        fromVersion: 'v1.0.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'git@github.com:owner/repo.git',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(1);
});

it('handles versions without v prefix', function () {
    $apiResponse = [
        [
            'tag_name' => '1.1.0',
            'name' => '1.1.0',
            'body' => 'Release',
            'published_at' => '2024-01-15T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/1.1.0',
            'draft' => false,
            'prerelease' => false,
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($apiResponse));

    $this->versionNormalizer
        ->shouldReceive('normalize')
        ->andReturnUsing(fn ($v) => str_replace('v', '', $v) . '.0.0');

    $result = $this->fetcher->fetch(
        package: 'owner/repo',
        fromVersion: '1.0.0',
        toVersion: '1.1.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(1);
});

it('returns null when api response is invalid json', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn('invalid json');

    $result = $this->fetcher->fetch(
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

it('fetches exact version when from and to are the same', function () {
    $apiResponse = [
        [
            'tag_name' => 'v1.1.0',
            'name' => 'Version 1.1.0',
            'body' => 'Release 1.1.0',
            'published_at' => '2024-01-15T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.1.0',
            'draft' => false,
            'prerelease' => false,
        ],
        [
            'tag_name' => 'v1.0.0',
            'name' => 'Version 1.0.0',
            'body' => 'Release 1.0.0',
            'published_at' => '2024-01-01T10:00:00Z',
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.0.0',
            'draft' => false,
            'prerelease' => false,
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($apiResponse));

    $this->versionNormalizer
        ->shouldReceive('normalize')
        ->andReturnUsing(fn ($v) => str_replace('v', '', $v) . '.0.0');

    $result = $this->fetcher->fetch(
        package: 'owner/repo',
        fromVersion: 'v1.1.0',
        toVersion: 'v1.1.0',
        repositoryUrl: 'https://github.com/owner/repo',
        packageManagerType: PackageManagerType::COMPOSER,
        localPath: null,
        includePrerelease: false
    );

    expect($result)->not->toBeNull()
        ->and($result->count())->toBe(1)
        ->and($result->getReleases()[0]->tagName)->toBe('v1.1.0');
});
