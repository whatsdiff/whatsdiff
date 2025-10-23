<?php

declare(strict_types=1);

use Symfony\Component\Console\Output\BufferedOutput;
use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Outputs\ReleaseNotes\ReleaseNotesJsonOutput;

it('formats empty collection as json', function () {
    $collection = new ReleaseNotesCollection();
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesJsonOutput();

    $formatter->format($collection, $output);

    $result = json_decode($output->fetch(), true);

    expect($result)->toHaveKey('total_releases')
        ->and($result['total_releases'])->toBe(0)
        ->and($result)->toHaveKey('releases')
        ->and($result['releases'])->toBe([]);
});

it('formats releases as json', function () {
    $release = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Initial Release',
        body: "## Changes\n- Added feature A\n\n## Fixes\n- Fixed bug B",
        date: new DateTimeImmutable('2024-01-15T10:00:00Z'),
        url: 'https://github.com/owner/repo/releases/tag/v1.0.0'
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesJsonOutput();

    $formatter->format($collection, $output);

    $result = json_decode($output->fetch(), true);

    expect($result['total_releases'])->toBe(1)
        ->and($result['releases'])->toHaveCount(1)
        ->and($result['releases'][0])->toHaveKeys(['tag_name', 'title', 'date', 'url', 'body', 'changes', 'fixes', 'breaking_changes'])
        ->and($result['releases'][0]['tag_name'])->toBe('v1.0.0')
        ->and($result['releases'][0]['title'])->toBe('Initial Release')
        ->and($result['releases'][0]['url'])->toBe('https://github.com/owner/repo/releases/tag/v1.0.0')
        ->and($result['releases'][0]['changes'])->toBe(['Added feature A'])
        ->and($result['releases'][0]['fixes'])->toBe(['Fixed bug B']);
});

it('formats multiple releases as json', function () {
    $release1 = new ReleaseNote(
        tagName: 'v1.0.0',
        title: 'Release 1',
        body: "## Changes\n- Change A",
        date: new DateTimeImmutable('2024-01-01')
    );

    $release2 = new ReleaseNote(
        tagName: 'v1.1.0',
        title: 'Release 2',
        body: "## Fixes\n- Fix A",
        date: new DateTimeImmutable('2024-02-01')
    );

    $collection = new ReleaseNotesCollection([$release1, $release2]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesJsonOutput();

    $formatter->format($collection, $output);

    $result = json_decode($output->fetch(), true);

    expect($result['total_releases'])->toBe(2)
        ->and($result['releases'])->toHaveCount(2)
        ->and($result['releases'][0]['tag_name'])->toBe('v1.0.0')
        ->and($result['releases'][1]['tag_name'])->toBe('v1.1.0');
});

it('includes breaking changes in json', function () {
    $release = new ReleaseNote(
        tagName: 'v2.0.0',
        title: 'Major Release',
        body: "## Breaking Changes\n- Removed API\n- Changed behavior",
        date: new DateTimeImmutable('2024-03-01')
    );

    $collection = new ReleaseNotesCollection([$release]);
    $output = new BufferedOutput();
    $formatter = new ReleaseNotesJsonOutput();

    $formatter->format($collection, $output);

    $result = json_decode($output->fetch(), true);

    expect($result['releases'][0]['breaking_changes'])->toBe(['Removed API', 'Changed behavior']);
});
