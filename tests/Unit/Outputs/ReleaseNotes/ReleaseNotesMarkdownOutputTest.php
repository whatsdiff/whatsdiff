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

    expect($output->fetch())->toBe("No release notes available.\n");
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
        ->and($result)->toContain('**Releases:** 2')
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
