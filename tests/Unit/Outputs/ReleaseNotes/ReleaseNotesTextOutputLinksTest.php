<?php

declare(strict_types=1);

use Symfony\Component\Console\Output\BufferedOutput;
use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Outputs\ReleaseNotes\ReleaseNotesTextOutput;

it('makes release URL clickable with ANSI', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: 'Body',
        date: new DateTimeImmutable('2024-01-15'),
        url: 'https://github.com/owner/repo/releases/tag/v1.0.0'
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput(decorated: true);
    $formatter = new ReleaseNotesTextOutput(summary: false, useAnsi: true);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    // Check for OSC 8 escape sequence (Symfony converts <href> tags to OSC 8)
    expect($result)->toContain("\e]8;;https://github.com/owner/repo/releases/tag/v1.0.0\e\\");
});

it('does not add hyperlinks when ANSI is disabled', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: 'Body',
        date: new DateTimeImmutable('2024-01-15'),
        url: 'https://github.com/owner/repo/releases/tag/v1.0.0'
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesTextOutput(summary: false, useAnsi: false);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    // Should contain plain URL without <href> tags
    expect($result)->toContain('URL: https://github.com/owner/repo/releases/tag/v1.0.0')
        ->and($result)->not->toContain('<href=');
});

it('converts markdown links in changes', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: "## Changes\n- Docs: [https://example.com/docs](https://example.com/docs)",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput(decorated: true);
    $formatter = new ReleaseNotesTextOutput(summary: false, useAnsi: true);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    // Should contain OSC 8 escape sequence for the markdown link
    expect($result)->toContain("\e]8;;https://example.com/docs\e\\");
});

it('converts bare URLs in changes', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: "## Changes\n- See https://example.com/changelog for details",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput(decorated: true);
    $formatter = new ReleaseNotesTextOutput(summary: false, useAnsi: true);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    // Should contain OSC 8 escape sequence for bare URL
    expect($result)->toContain("\e]8;;https://example.com/changelog\e\\");
});

it('handles markdown links with custom text', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: "## Changes\n- Read the [documentation](https://example.com/docs) for more info",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput(decorated: true);
    $formatter = new ReleaseNotesTextOutput(summary: false, useAnsi: true);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    // Should contain OSC 8 escape sequence with custom text
    expect($result)->toContain("\e]8;;https://example.com/docs\e\\documentation\e]8;;\e\\");
});

it('handles multiple URLs in one line', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: "## Changes\n- See https://example.com/a and https://example.com/b",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput(decorated: true);
    $formatter = new ReleaseNotesTextOutput(summary: false, useAnsi: true);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    // Should contain both OSC 8 escape sequences
    expect($result)->toContain("\e]8;;https://example.com/a\e\\")
        ->and($result)->toContain("\e]8;;https://example.com/b\e\\");
});

it('applies link formatting in summary mode', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release',
        body: "## Changes\n- [Documentation](https://example.com/docs)",
        date: new DateTimeImmutable('2024-01-15')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput(decorated: true);
    $formatter = new ReleaseNotesTextOutput(summary: true, useAnsi: true);

    $formatter->format($collection, $output);

    $result = $output->fetch();

    // Should contain OSC 8 escape sequence in summary mode
    expect($result)->toContain("\e]8;;https://example.com/docs\e\\Documentation\e]8;;\e\\");
});
