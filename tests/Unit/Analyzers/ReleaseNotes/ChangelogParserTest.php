<?php

use Whatsdiff\Analyzers\ReleaseNotes\ChangelogParser;
use Whatsdiff\Data\ReleaseNotesCollection;

test('it parses basic Keep a Changelog format', function () {
    $changelog = <<<'MD'
# Changelog

## 2.0.0 - 2023-06-01

### Added
- New feature A
- New feature B

### Fixed
- Bug fix C

## 1.0.0 - 2023-05-01

### Added
- Initial release
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '1.0.0', '2.0.0');

    expect($result)->toBeInstanceOf(ReleaseNotesCollection::class);
    expect($result->count())->toBe(1);

    $releases = $result->getReleases();
    expect($releases[0]->tagName)->toBe('2.0.0');
    expect($releases[0]->getChanges())->toHaveCount(2);
    expect($releases[0]->getFixes())->toHaveCount(1);
});

test('it handles version with brackets', function () {
    $changelog = <<<'MD'
## [2.0.0] - 2023-06-01
### Added
- Feature
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '1.0.0', '2.0.0');

    expect($result->count())->toBe(1);
    expect($result->getReleases()[0]->tagName)->toBe('2.0.0');
});

test('it handles version with v prefix', function () {
    $changelog = <<<'MD'
## v2.0.0 - 2023-06-01
### Added
- Feature
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '1.0.0', '2.0.0');

    expect($result->count())->toBe(1);
    expect($result->getReleases()[0]->tagName)->toBe('2.0.0');
});

test('it handles version with parentheses date', function () {
    $changelog = <<<'MD'
## 2.0.0 (2023-06-01)
### Added
- Feature
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '1.0.0', '2.0.0');

    expect($result->count())->toBe(1);
    expect($result->getReleases()[0]->tagName)->toBe('2.0.0');
});

test('it filters versions by range', function () {
    $changelog = <<<'MD'
## 3.0.0 - 2023-07-01
### Added
- Version 3

## 2.0.0 - 2023-06-01
### Added
- Version 2

## 1.0.0 - 2023-05-01
### Added
- Version 1
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '1.0.0', '2.0.0');

    expect($result->count())->toBe(1);
    expect($result->getReleases()[0]->tagName)->toBe('2.0.0');
});

test('it returns exact version when from and to are the same', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01
### Added
- Version 2

## 1.0.0 - 2023-05-01
### Added
- Version 1
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '2.0.0', '2.0.0');

    expect($result->count())->toBe(1);
    expect($result->getReleases()[0]->tagName)->toBe('2.0.0');
});

test('it excludes pre-release versions by default', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01
### Added
- Stable release

## 2.0.0-beta.1 - 2023-05-25
### Added
- Beta release

## 1.0.0 - 2023-05-01
### Added
- Version 1
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '1.0.0', '2.0.0', includePrerelease: false);

    expect($result->count())->toBe(1);
    expect($result->getReleases()[0]->tagName)->toBe('2.0.0');
});

test('it includes pre-release versions when requested', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01
### Added
- Stable release

## 2.0.0-beta.1 - 2023-05-25
### Added
- Beta release

## 1.0.0 - 2023-05-01
### Added
- Version 1
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '1.0.0', '2.0.0', includePrerelease: true);

    expect($result->count())->toBe(2);
    expect($result->getReleases()[0]->tagName)->toBe('2.0.0');
    expect($result->getReleases()[1]->tagName)->toBe('2.0.0-beta.1');
});

test('it handles versions without dates', function () {
    $changelog = <<<'MD'
## 2.0.0
### Added
- Feature without date
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '1.0.0', '2.0.0');

    expect($result->count())->toBe(1);
    expect($result->getReleases()[0]->tagName)->toBe('2.0.0');
    expect($result->getReleases()[0]->date)->toBeInstanceOf(DateTimeImmutable::class);
});

test('it returns empty collection for empty changelog', function () {
    $parser = new ChangelogParser();
    $result = $parser->parse('', '1.0.0', '2.0.0');

    expect($result)->toBeInstanceOf(ReleaseNotesCollection::class);
    expect($result->count())->toBe(0);
});

test('it returns empty collection when no versions match', function () {
    $changelog = <<<'MD'
## 1.0.0 - 2023-05-01
### Added
- Version 1
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '2.0.0', '3.0.0');

    expect($result->count())->toBe(0);
});

test('it handles multiple categories', function () {
    $changelog = <<<'MD'
## 2.0.0 - 2023-06-01

### Added
- New feature

### Changed
- Updated feature

### Deprecated
- Old feature

### Removed
- Deleted feature

### Fixed
- Bug fix

### Security
- Security patch
MD;

    $parser = new ChangelogParser();
    $result = $parser->parse($changelog, '1.0.0', '2.0.0');

    $releases = $result->getReleases();
    expect($releases[0]->getBody())->toContain('### Added');
    expect($releases[0]->getBody())->toContain('### Fixed');
    expect($releases[0]->getBody())->toContain('### Security');
});
