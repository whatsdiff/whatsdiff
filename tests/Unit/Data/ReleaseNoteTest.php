<?php

declare(strict_types=1);

use Whatsdiff\Data\ReleaseNote;

it('creates a release note with all properties', function () {
    $date = new DateTimeImmutable('2024-01-15');
    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Initial Release',
        body: 'This is the release body',
        date: $date,
        url: 'https://github.com/owner/repo/releases/tag/v1.0.0'
    );

    expect($releaseNote->tagName)->toBe('v1.0.0')
        ->and($releaseNote->title)->toBe('Initial Release')
        ->and($releaseNote->body)->toBe('This is the release body')
        ->and($releaseNote->date)->toBe($date)
        ->and($releaseNote->url)->toBe('https://github.com/owner/repo/releases/tag/v1.0.0');
});

it('extracts changes from markdown body', function () {
    $body = <<<'MD'
# Release Notes

## Changes

- Added new feature X
- Updated component Y
- Improved performance

## Fixes

- Fixed bug A
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    $changes = $releaseNote->getChanges();

    expect($changes)->toBe([
        'Added new feature X',
        'Updated component Y',
        'Improved performance',
    ]);
});

it('extracts fixes from markdown body', function () {
    $body = <<<'MD'
## Fixes

- Fixed critical bug in authentication
- Fixed typo in documentation
- Fixed memory leak

## Changes

- Some change
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    $fixes = $releaseNote->getFixes();

    expect($fixes)->toBe([
        'Fixed critical bug in authentication',
        'Fixed typo in documentation',
        'Fixed memory leak',
    ]);
});

it('extracts breaking changes from markdown body', function () {
    $body = <<<'MD'
## Breaking Changes

- Removed deprecated API
- Changed default behavior of X
- Renamed method Y to Z

## Changes

- Some other change
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    $breakingChanges = $releaseNote->getBreakingChanges();

    expect($breakingChanges)->toBe([
        'Removed deprecated API',
        'Changed default behavior of X',
        'Renamed method Y to Z',
    ]);
});

it('handles alternative heading formats for changes', function () {
    $body = <<<'MD'
### Added

- Feature A
- Feature B
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    $changes = $releaseNote->getChanges();

    expect($changes)->toBe(['Feature A', 'Feature B']);
});

it('handles alternative heading formats for fixes', function () {
    $body = <<<'MD'
### Fixed

- Bug A
- Bug B

### Bug Fixes

- Bug C
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    $fixes = $releaseNote->getFixes();

    expect($fixes)->toBe(['Bug A', 'Bug B', 'Bug C']);
});

it('uses fallback to extract all bullet points when no recognized sections found', function () {
    $body = <<<'MD'
## Some Other Section

- Item A
- Item B
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    // Changes should use fallback and return all bullet points
    expect($releaseNote->getChanges())->toBe(['Item A', 'Item B'])
        ->and($releaseNote->getFixes())->toBe([])
        ->and($releaseNote->getBreakingChanges())->toBe([]);
});

it('handles asterisk bullet points', function () {
    $body = <<<'MD'
## Changes

* Feature A
* Feature B
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    $changes = $releaseNote->getChanges();

    expect($changes)->toBe(['Feature A', 'Feature B']);
});

it('stops extracting when different section starts', function () {
    $body = <<<'MD'
## Changes

- Change A
- Change B

## Fixes

- Fix A
- Fix B
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    $changes = $releaseNote->getChanges();

    expect($changes)->toBe(['Change A', 'Change B'])
        ->and($releaseNote->getFixes())->toBe(['Fix A', 'Fix B']);
});

it('provides getter methods', function () {
    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'My Release',
        body: 'Body content',
        date: new DateTimeImmutable()
    );

    expect($releaseNote->getTitle())->toBe('My Release')
        ->and($releaseNote->getBody())->toBe('Body content');
});

it('handles case-insensitive section matching', function () {
    $body = <<<'MD'
## BREAKING CHANGES

- Major change A
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v2.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    expect($releaseNote->getBreakingChanges())->toBe(['Major change A']);
});

it('recognizes "What\'s Changed" as changes section', function () {
    $body = <<<'MD'
## What's Changed

- New feature X
- Enhancement Y
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    expect($releaseNote->getChanges())->toBe(['New feature X', 'Enhancement Y']);
});

it('recognizes "New Features" as changes section', function () {
    $body = <<<'MD'
## New Features

- Feature A
- Feature B
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    expect($releaseNote->getChanges())->toBe(['Feature A', 'Feature B']);
});

it('recognizes "Features" as changes section', function () {
    $body = <<<'MD'
### Features

- Feature A
- Feature B
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    expect($releaseNote->getChanges())->toBe(['Feature A', 'Feature B']);
});

it('recognizes "Enhancements" as changes section', function () {
    $body = <<<'MD'
## Enhancements

- Enhancement A
- Enhancement B
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    expect($releaseNote->getChanges())->toBe(['Enhancement A', 'Enhancement B']);
});

it('recognizes "Bugfixes" as fixes section', function () {
    $body = <<<'MD'
## Bugfixes

- Fixed issue A
- Fixed issue B
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    expect($releaseNote->getFixes())->toBe(['Fixed issue A', 'Fixed issue B']);
});

it('recognizes "Breaking" as breaking changes section', function () {
    $body = <<<'MD'
## Breaking

- Removed API X
- Changed behavior Y
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v2.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    expect($releaseNote->getBreakingChanges())->toBe(['Removed API X', 'Changed behavior Y']);
});

it('returns empty array for changes when body has no bullet points', function () {
    $body = <<<'MD'
This is a plain text release note without any bullet points.

Just plain paragraphs.
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    expect($releaseNote->getChanges())->toBe([]);
});

it('handles multiple section types with different heading variations', function () {
    $body = <<<'MD'
## What's Changed

- New feature A
- New feature B

## Bugfixes

- Fixed bug X
- Fixed bug Y

## Breaking

- Removed old API
MD;

    $releaseNote = new ReleaseNote(
        tagName: 'v2.0.0',
        title: 'Release',
        body: $body,
        date: new DateTimeImmutable()
    );

    expect($releaseNote->getChanges())->toBe(['New feature A', 'New feature B'])
        ->and($releaseNote->getFixes())->toBe(['Fixed bug X', 'Fixed bug Y'])
        ->and($releaseNote->getBreakingChanges())->toBe(['Removed old API']);
});
