<?php

declare(strict_types=1);

use Symfony\Component\Console\Output\BufferedOutput;
use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Outputs\ReleaseNotes\ReleaseNotesTextOutput;

it('formats empty collection', function () {
    $collection = new ReleaseNotesCollection();
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesTextOutput();

    $formatter->format($collection, $output);

    expect($output->fetch())->toContain('No release notes available.');
});

it('formats detailed output with releases', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Initial Release',
        body: "## Changes\n- Added feature A\n\n## Fixes\n- Fixed bug B",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesTextOutput(summary: false, useAnsi: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('Release Notes')
        ->and($result)->toContain('v1.0.0 - Initial Release')
        ->and($result)->toContain('Date: 2024-01-15')
        ->and($result)->toContain('Changes:')
        ->and($result)->toContain('Added feature A')
        ->and($result)->toContain('Fixes:')
        ->and($result)->toContain('Fixed bug B');
});

it('formats summary output', function () {
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
    $formatter = new ReleaseNotesTextOutput(summary: true, useAnsi: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('Release Notes Summary')
        ->and($result)->toContain('Releases: v1.0.0 → v1.1.0 (2 versions)')
        ->and($result)->toContain('Breaking Changes:')
        ->and($result)->toContain('Breaking A')
        ->and($result)->toContain('Changes:')
        ->and($result)->toContain('Change A')
        ->and($result)->toContain('Change B')
        ->and($result)->toContain('Fixes:')
        ->and($result)->toContain('Fix A');
});

it('handles releases with breaking changes', function () {
    $release = new ReleaseNote(
        tagName: 'v2.0.0',
        title: 'Major Release',
        body: "## Breaking Changes\n- Removed old API\n- Changed behavior of X",
        date: new DateTimeImmutable('2024-03-01')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesTextOutput(summary: false, useAnsi: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('Breaking Changes:')
        ->and($result)->toContain('Removed old API')
        ->and($result)->toContain('Changed behavior of X');
});

it('includes release URL when available', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: 'Body',
        date: new DateTimeImmutable(),
        url: 'https://github.com/owner/repo/releases/tag/v1.0.0'
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesTextOutput(summary: false, useAnsi: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    expect($result)->toContain('URL: https://github.com/owner/repo/releases/tag/v1.0.0');
});
