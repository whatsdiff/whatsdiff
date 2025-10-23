<?php

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\ReleaseNotes\ChangelogParser;
use Whatsdiff\Analyzers\ReleaseNotes\Fetchers\LocalVendorChangelogFetcher;
use Whatsdiff\Services\VersionNormalizer;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/whatsdiff-test-' . uniqid();
    mkdir($this->tempDir);
    $this->versionNormalizer = new VersionNormalizer();
    $this->parser = new ChangelogParser($this->versionNormalizer);
    $this->fetcher = new LocalVendorChangelogFetcher($this->parser, $this->versionNormalizer);
});

afterEach(function () {
    // Clean up temp directory
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob("$this->tempDir/*"));
        rmdir($this->tempDir);
    }
});

test('it supports paths with existing local directories', function () {
    expect($this->fetcher->supports('https://github.com/test/repo', $this->tempDir))->toBeTrue();
});

test('it does not support when local path is null', function () {
    expect($this->fetcher->supports('https://github.com/test/repo', null))->toBeFalse();
});

test('it does not support when local path does not exist', function () {
    expect($this->fetcher->supports('https://github.com/test/repo', '/nonexistent/path'))->toBeFalse();
});

test('it fetches changelog from CHANGELOG.md', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01
### Added
- New feature
MD;

    file_put_contents($this->tempDir . '/CHANGELOG.md', $changelog);

    $result = $this->fetcher->fetch(
        'test/package',
        '1.0.0',
        '2.0.0',
        'https://github.com/test/package',
        PackageManagerType::COMPOSER,
        $this->tempDir,
        false
    );

    expect($result)->not->toBeNull();
    expect($result->count())->toBe(1);
    expect($result->getReleases()[0]->tagName)->toBe('2.0.0');
});

test('it tries multiple changelog filenames', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01
### Added
- New feature
MD;

    // Use CHANGELOG instead of CHANGELOG.md
    file_put_contents($this->tempDir . '/CHANGELOG', $changelog);

    $result = $this->fetcher->fetch(
        'test/package',
        '1.0.0',
        '2.0.0',
        'https://github.com/test/package',
        PackageManagerType::COMPOSER,
        $this->tempDir,
        false
    );

    expect($result)->not->toBeNull();
    expect($result->count())->toBe(1);
});

test('it returns null when no changelog file exists', function () {
    $result = $this->fetcher->fetch(
        'test/package',
        '1.0.0',
        '2.0.0',
        'https://github.com/test/package',
        PackageManagerType::COMPOSER,
        $this->tempDir,
        false
    );

    expect($result)->toBeNull();
});

test('it returns null when local path is null', function () {
    $result = $this->fetcher->fetch(
        'test/package',
        '1.0.0',
        '2.0.0',
        'https://github.com/test/package',
        PackageManagerType::COMPOSER,
        null,
        false
    );

    expect($result)->toBeNull();
});

test('it returns null for empty changelog file', function () {
    file_put_contents($this->tempDir . '/CHANGELOG.md', '');

    $result = $this->fetcher->fetch(
        'test/package',
        '1.0.0',
        '2.0.0',
        'https://github.com/test/package',
        PackageManagerType::COMPOSER,
        $this->tempDir,
        false
    );

    expect($result)->toBeNull();
});

test('it prefers CHANGELOG.md over other filenames', function () {
    file_put_contents($this->tempDir . '/CHANGELOG.md', '## 2.0.0 - 2023-06-01' . PHP_EOL . '### Added' . PHP_EOL . '- From CHANGELOG.md');
    file_put_contents($this->tempDir . '/HISTORY.md', '## 2.0.0 - 2023-06-01' . PHP_EOL . '### Added' . PHP_EOL . '- From HISTORY.md');

    $result = $this->fetcher->fetch(
        'test/package',
        '1.0.0',
        '2.0.0',
        'https://github.com/test/package',
        PackageManagerType::COMPOSER,
        $this->tempDir,
        false
    );

    expect($result)->not->toBeNull();
    expect($result->getReleases()[0]->getChanges()[0])->toBe('From CHANGELOG.md');
});
