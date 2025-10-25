<?php

declare(strict_types=1);

use Symfony\Component\Console\Output\BufferedOutput;
use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Outputs\ReleaseNotes\ReleaseNotesMarkdownOutput;

it('formats empty collection as markdown', function () {
    $collection = new ReleaseNotesCollection();
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesMarkdownOutput();

    $formatter->format($collection, $output);

    expect($output->fetch())->toBe('No release notes available.' . PHP_EOL);
});

it('formats detailed markdown output', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Initial Release',
        body: 'Release body content',
        date: new DateTimeImmutable('2024-01-15'),
        url: 'https://github.com/owner/repo/releases/tag/v1.0.0'
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesMarkdownOutput(summary: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('## v1.0.0 - Initial Release')
        ->and($result)->toContain('**Release URL:** https://github.com/owner/repo/releases/tag/v1.0.0')
        ->and($result)->toContain('**Date:** 2024-01-15')
        ->and($result)->toContain('Release body content');
});

it('formats summarized markdown output', function () {
    $release1 = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release 1',
        body: "## Changes\n- Change A\n\n## Fixes\n- Fix A",
        date: new DateTimeImmutable('2024-01-01')
    );

    $release2 = new ReleaseNote(
        tagName: 'v1.1.0',
        title: 'Release 2',
        body: "## Changes\n- Change B\n\n## Breaking Changes\n- Breaking A",
        date: new DateTimeImmutable('2024-02-01')
    );

    $collection = new ReleaseNotesCollection([$release1, $release2]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesMarkdownOutput(summary: true);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('# Release Notes Summary')
        ->and($result)->toContain('**Releases:** v1.0.0 → v1.1.0 (2 versions)')
        ->and($result)->toContain('## Breaking Changes')
        ->and($result)->toContain('- Breaking A')
        ->and($result)->toContain('## Changes')
        ->and($result)->toContain('- Change A')
        ->and($result)->toContain('- Change B')
        ->and($result)->toContain('## Fixes')
        ->and($result)->toContain('- Fix A');
});

it('formats multiple releases in detailed mode', function () {
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
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesMarkdownOutput(summary: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('## v1.0.0')
        ->and($result)->toContain('First release')
        ->and($result)->toContain('## v1.1.0')
        ->and($result)->toContain('Second release')
        ->and($result)->toContain('---');
});

it('converts GitHub PR URLs to compact markdown links in detailed mode', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: "Fix bug in https://github.com/laravel/framework/pull/57207",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesMarkdownOutput(summary: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('[#57207](https://github.com/laravel/framework/pull/57207)')
        ->and($result)->not->toContain('Fix bug in https://github.com/laravel/framework/pull/57207');
});

it('converts GitHub issue URLs to compact markdown links in detailed mode', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: "Resolves https://github.com/symfony/symfony/issues/12345",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesMarkdownOutput(summary: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('[#12345](https://github.com/symfony/symfony/issues/12345)')
        ->and($result)->not->toContain('Resolves https://github.com/symfony/symfony/issues/12345');
});

it('converts GitHub URLs in summary mode', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: "## Changes\n- Fix https://github.com/owner/repo/pull/100\n\n## Fixes\n- Resolves https://github.com/owner/repo/issues/200",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesMarkdownOutput(summary: true);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('[#100](https://github.com/owner/repo/pull/100)')
        ->and($result)->toContain('[#200](https://github.com/owner/repo/issues/200)');
});

it('preserves existing markdown links with GitHub URLs', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: "[Add feature](https://github.com/owner/repo/pull/123) by @author",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesMarkdownOutput(summary: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    // Should preserve the markdown link text, not convert to #123
    expect($result)->toContain('[Add feature](https://github.com/owner/repo/pull/123)')
        ->and($result)->not->toContain('[#123]');
});

it('keeps non-GitHub URLs unchanged', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: "See https://laravel-news.com/article for details",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesMarkdownOutput(summary: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('https://laravel-news.com/article')
        ->and($result)->not->toContain('[#');
});
