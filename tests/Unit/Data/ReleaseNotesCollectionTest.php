<?php

declare(strict_types=1);

use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;

it('creates an empty collection', function () {
    $collection = new ReleaseNotesCollection();

    expect($collection->count())->toBe(0)
        ->and($collection->isEmpty())->toBe(true)
        ->and($collection->getReleases())->toBe([]);
});

it('creates collection with release notes', function () {
    $release1 = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'First Release',
        body: '## Changes\n- Feature A',
        date: new DateTimeImmutable('2024-01-01')
    );

    $release2 = new ReleaseNote(
        tagName: 'v1.1.0',
        title: 'Second Release',
        body: '## Changes\n- Feature B',
        date: new DateTimeImmutable('2024-02-01')
    );

    $collection = new ReleaseNotesCollection([$release1, $release2]);

    expect($collection->count())->toBe(2)
        ->and($collection->isEmpty())->toBe(false)
        ->and($collection->getReleases())->toHaveCount(2);
});

it('collects all changes from multiple releases', function () {
    $release1 = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release 1',
        body: "## Changes\n- Feature A\n- Feature B",
        date: new DateTimeImmutable()
    );

    $release2 = new ReleaseNote(
        tagName: 'v1.1.0',
        title: 'Release 2',
        body: "## Changes\n- Feature C\n- Feature D",
        date: new DateTimeImmutable()
    );

    $collection = new ReleaseNotesCollection([$release1, $release2]);

    $changes = $collection->getChanges();

    expect($changes)->toBe([
        'Feature A',
        'Feature B',
        'Feature C',
        'Feature D',
    ]);
});

it('collects all fixes from multiple releases', function () {
    $release1 = new ReleaseNote(
        tagName: 'v1.0.1',
        title: 'Patch 1',
        body: "## Fixes\n- Bug A\n- Bug B",
        date: new DateTimeImmutable()
    );

    $release2 = new ReleaseNote(
        tagName: 'v1.0.2',
        title: 'Patch 2',
        body: "## Fixes\n- Bug C",
        date: new DateTimeImmutable()
    );

    $collection = new ReleaseNotesCollection([$release1, $release2]);

    $fixes = $collection->getFixes();

    expect($fixes)->toBe(['Bug A', 'Bug B', 'Bug C']);
});

it('collects all breaking changes from multiple releases', function () {
    $release1 = new ReleaseNote(
        tagName: 'v2.0.0',
        title: 'Major Release',
        body: "## Breaking Changes\n- Breaking A\n- Breaking B",
        date: new DateTimeImmutable()
    );

    $release2 = new ReleaseNote(
        tagName: 'v3.0.0',
        title: 'Another Major',
        body: "## Breaking Changes\n- Breaking C",
        date: new DateTimeImmutable()
    );

    $collection = new ReleaseNotesCollection([$release1, $release2]);

    $breakingChanges = $collection->getBreakingChanges();

    expect($breakingChanges)->toBe(['Breaking A', 'Breaking B', 'Breaking C']);
});

it('generates markdown from collection', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Initial Release',
        body: 'This is the release body',
        date: new DateTimeImmutable('2024-01-15'),
        url: 'https://github.com/owner/repo/releases/tag/v1.0.0'
    );

    $collection = new ReleaseNotesCollection([$release]);

    $markdown = $collection->toMarkdown();

    expect($markdown)->toContain('## v1.0.0 - Initial Release')
        ->and($markdown)->toContain('**Release URL:** https://github.com/owner/repo/releases/tag/v1.0.0')
        ->and($markdown)->toContain('**Date:** 2024-01-15')
        ->and($markdown)->toContain('This is the release body');
});

it('generates markdown with multiple releases', function () {
    $release1 = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'v1.0.0',
        body: 'First release',
        date: new DateTimeImmutable('2024-01-01')
    );

    $release2 = new ReleaseNote(
        tagName: 'v1.1.0',
        title: 'v1.1.0',
        body: 'Second release',
        date: new DateTimeImmutable('2024-02-01')
    );

    $collection = new ReleaseNotesCollection([$release1, $release2]);

    $markdown = $collection->toMarkdown();

    expect($markdown)->toContain('## v1.0.0')
        ->and($markdown)->toContain('First release')
        ->and($markdown)->toContain('## v1.1.0')
        ->and($markdown)->toContain('Second release')
        ->and($markdown)->toContain('---');
});

it('returns empty string when no releases in collection', function () {
    $collection = new ReleaseNotesCollection();

    expect($collection->toMarkdown())->toBe('')
        ->and($collection->toSummarizedMarkdown())->toBe('');
});

it('generates summarized markdown with combined sections', function () {
    $release1 = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release 1',
        body: "## Changes\n- Feature A\n## Fixes\n- Bug A",
        date: new DateTimeImmutable()
    );

    $release2 = new ReleaseNote(
        tagName: 'v1.1.0',
        title: 'Release 2',
        body: "## Changes\n- Feature B\n## Breaking Changes\n- Breaking A",
        date: new DateTimeImmutable()
    );

    $collection = new ReleaseNotesCollection([$release1, $release2]);

    $markdown = $collection->toSummarizedMarkdown();

    expect($markdown)->toContain('# Release Notes Summary')
        ->and($markdown)->toContain('**Releases:** 2')
        ->and($markdown)->toContain('## Breaking Changes')
        ->and($markdown)->toContain('- Breaking A')
        ->and($markdown)->toContain('## Changes')
        ->and($markdown)->toContain('- Feature A')
        ->and($markdown)->toContain('- Feature B')
        ->and($markdown)->toContain('## Fixes')
        ->and($markdown)->toContain('- Bug A');
});

it('is iterable', function () {
    $release1 = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release 1',
        body: 'Body 1',
        date: new DateTimeImmutable()
    );

    $release2 = new ReleaseNote(
        tagName: 'v1.1.0',
        title: 'Release 2',
        body: 'Body 2',
        date: new DateTimeImmutable()
    );

    $collection = new ReleaseNotesCollection([$release1, $release2]);

    $tags = [];
    foreach ($collection as $release) {
        $tags[] = $release->tagName;
    }

    expect($tags)->toBe(['v1.0.0', 'v1.1.0']);
});

it('handles empty sections in summarized markdown', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: 'Just some text without structured sections',
        date: new DateTimeImmutable()
    );

    $collection = new ReleaseNotesCollection([$release]);

    $markdown = $collection->toSummarizedMarkdown();

    expect($markdown)->toContain('# Release Notes Summary')
        ->and($markdown)->toContain('**Releases:** 1')
        ->and($markdown)->not->toContain('## Breaking Changes')
        ->and($markdown)->not->toContain('## Changes')
        ->and($markdown)->not->toContain('## Fixes');
});

it('does not duplicate title when tag name equals title in markdown', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'v1.0.0',
        body: 'Release body',
        date: new DateTimeImmutable()
    );

    $collection = new ReleaseNotesCollection([$release]);

    $markdown = $collection->toMarkdown();

    expect($markdown)->toContain('## v1.0.0')
        ->and($markdown)->not->toContain('## v1.0.0 - v1.0.0');
});
