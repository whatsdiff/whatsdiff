<?php

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\ReleaseNotes\ChangelogParser;
use Whatsdiff\Analyzers\ReleaseNotes\Fetchers\GithubChangelogFetcher;
use Whatsdiff\Services\HttpService;

test('it supports github.com URLs', function () {
    $httpService = Mockery::mock(HttpService::class);
    $parser = new ChangelogParser();
    $fetcher = new GithubChangelogFetcher($httpService, $parser);

    expect($fetcher->supports('https://github.com/owner/repo', null))->toBeTrue();
});

test('it does not support non-github URLs', function () {
    $httpService = Mockery::mock(HttpService::class);
    $parser = new ChangelogParser();
    $fetcher = new GithubChangelogFetcher($httpService, $parser);

    expect($fetcher->supports('https://gitlab.com/owner/repo', null))->toBeFalse();
});

test('it fetches changelog from github api', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01
### Added
- New feature from GitHub
MD;

    $httpService = Mockery::mock(HttpService::class);
    $httpService->shouldReceive('get')
        ->with('https://api.github.com/repos/owner/repo/contents/CHANGELOG.md?ref=v2.0.0.0', [
            'headers' => [
                'Accept' => 'application/vnd.github.raw',
            ],
        ])
        ->once()
        ->andReturn($changelog);

    $parser = new ChangelogParser();
    $fetcher = new GithubChangelogFetcher($httpService, $parser);

    $result = $fetcher->fetch(
        'owner/repo',
        '1.0.0',
        '2.0.0',
        'https://github.com/owner/repo',
        PackageManagerType::COMPOSER,
        null,
        false
    );

    expect($result)->not->toBeNull();
    expect($result->count())->toBe(1);
    expect($result->getReleases()[0]->getChanges()[0])->toBe('New feature from GitHub');
});

test('it tries default branch when tag fails', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01
### Added
- Feature
MD;

    $httpService = Mockery::mock(HttpService::class);

    // First attempt with version tag fails
    $httpService->shouldReceive('get')
        ->with('https://api.github.com/repos/owner/repo/contents/CHANGELOG.md?ref=v2.0.0.0', [
            'headers' => [
                'Accept' => 'application/vnd.github.raw',
            ],
        ])
        ->once()
        ->andThrow(new Exception('Not found'));

    // Second attempt with default branch succeeds
    $httpService->shouldReceive('get')
        ->with('https://api.github.com/repos/owner/repo/contents/CHANGELOG.md', [
            'headers' => [
                'Accept' => 'application/vnd.github.raw',
            ],
        ])
        ->once()
        ->andReturn($changelog);

    $parser = new ChangelogParser();
    $fetcher = new GithubChangelogFetcher($httpService, $parser);

    $result = $fetcher->fetch(
        'owner/repo',
        '1.0.0',
        '2.0.0',
        'https://github.com/owner/repo',
        PackageManagerType::COMPOSER,
        null,
        false
    );

    expect($result)->not->toBeNull();
    expect($result->count())->toBe(1);
});

test('it tries multiple changelog filenames', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01
### Added
- Feature
MD;

    $httpService = Mockery::mock(HttpService::class);

    // CHANGELOG.md not found
    $httpService->shouldReceive('get')
        ->with('https://api.github.com/repos/owner/repo/contents/CHANGELOG.md?ref=v2.0.0.0', [
            'headers' => [
                'Accept' => 'application/vnd.github.raw',
            ],
        ])
        ->once()
        ->andThrow(new Exception('Not found'));

    // CHANGELOG not found
    $httpService->shouldReceive('get')
        ->with('https://api.github.com/repos/owner/repo/contents/CHANGELOG?ref=v2.0.0.0', [
            'headers' => [
                'Accept' => 'application/vnd.github.raw',
            ],
        ])
        ->once()
        ->andThrow(new Exception('Not found'));

    // HISTORY.md found
    $httpService->shouldReceive('get')
        ->with('https://api.github.com/repos/owner/repo/contents/HISTORY.md?ref=v2.0.0.0', [
            'headers' => [
                'Accept' => 'application/vnd.github.raw',
            ],
        ])
        ->once()
        ->andReturn($changelog);

    $parser = new ChangelogParser();
    $fetcher = new GithubChangelogFetcher($httpService, $parser);

    $result = $fetcher->fetch(
        'owner/repo',
        '1.0.0',
        '2.0.0',
        'https://github.com/owner/repo',
        PackageManagerType::COMPOSER,
        null,
        false
    );

    expect($result)->not->toBeNull();
    expect($result->count())->toBe(1);
});

test('it handles git@github.com URL format', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01
### Added
- Feature
MD;

    $httpService = Mockery::mock(HttpService::class);
    $httpService->shouldReceive('get')
        ->with('https://api.github.com/repos/owner/repo/contents/CHANGELOG.md?ref=v2.0.0.0', [
            'headers' => [
                'Accept' => 'application/vnd.github.raw',
            ],
        ])
        ->once()
        ->andReturn($changelog);

    $parser = new ChangelogParser();
    $fetcher = new GithubChangelogFetcher($httpService, $parser);

    $result = $fetcher->fetch(
        'owner/repo',
        '1.0.0',
        '2.0.0',
        'git@github.com:owner/repo.git',
        PackageManagerType::COMPOSER,
        null,
        false
    );

    expect($result)->not->toBeNull();
    expect($result->count())->toBe(1);
});

test('it returns null when repository URL is not github', function () {
    $httpService = Mockery::mock(HttpService::class);
    $httpService->shouldNotReceive('get');

    $parser = new ChangelogParser();
    $fetcher = new GithubChangelogFetcher($httpService, $parser);

    $result = $fetcher->fetch(
        'owner/repo',
        '1.0.0',
        '2.0.0',
        'https://gitlab.com/owner/repo',
        PackageManagerType::COMPOSER,
        null,
        false
    );

    expect($result)->toBeNull();
});

test('it returns null when all fetches fail', function () {
    $httpService = Mockery::mock(HttpService::class);
    $httpService->shouldReceive('get')
        ->andThrow(new Exception('Not found'));

    $parser = new ChangelogParser();
    $fetcher = new GithubChangelogFetcher($httpService, $parser);

    $result = $fetcher->fetch(
        'owner/repo',
        '1.0.0',
        '2.0.0',
        'https://github.com/owner/repo',
        PackageManagerType::COMPOSER,
        null,
        false
    );

    expect($result)->toBeNull();
});

test('it normalizes version with VersionParser', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01
### Added
- Feature
MD;

    $httpService = Mockery::mock(HttpService::class);

    // Should try with normalized version (2.0.0 -> 2.0.0.0) with 'v' prefix
    $httpService->shouldReceive('get')
        ->with('https://api.github.com/repos/owner/repo/contents/CHANGELOG.md?ref=v2.0.0.0', [
            'headers' => [
                'Accept' => 'application/vnd.github.raw',
            ],
        ])
        ->once()
        ->andReturn($changelog);

    $parser = new ChangelogParser();
    $fetcher = new GithubChangelogFetcher($httpService, $parser);

    $result = $fetcher->fetch(
        'owner/repo',
        '1.0.0',
        '2.0.0',
        'https://github.com/owner/repo',
        PackageManagerType::COMPOSER,
        null,
        false
    );

    expect($result)->not->toBeNull();
});
