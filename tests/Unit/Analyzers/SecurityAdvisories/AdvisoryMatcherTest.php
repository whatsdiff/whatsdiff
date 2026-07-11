<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\SecurityAdvisories\AdvisoryMatcher;
use Whatsdiff\Data\SecurityAdvisory;
use Whatsdiff\Enums\Severity;

function makeAdvisory(string $affectedVersions, string $id = 'GHSA-test'): SecurityAdvisory
{
    return new SecurityAdvisory(
        advisoryId: $id,
        cve: 'CVE-2024-0001',
        title: 'Test advisory',
        link: 'https://example.com/advisory',
        affectedVersions: $affectedVersions,
        severity: Severity::High,
    );
}

it('matches advisories affecting the installed version', function () {
    $advisories = [
        makeAdvisory('>=2.0.0,<2.4.5', 'GHSA-hit'),
        makeAdvisory('>=3.0.0', 'GHSA-miss'),
    ];

    $affecting = AdvisoryMatcher::affecting($advisories, '2.4.0');

    expect($affecting)->toHaveCount(1)
        ->and($affecting[0]->advisoryId)->toBe('GHSA-hit');
});

it('skips advisories with an empty affected range', function () {
    expect(AdvisoryMatcher::affecting([makeAdvisory('')], '1.0.0'))->toBe([]);
});

it('skips advisories whose range cannot be parsed', function () {
    $advisories = [
        makeAdvisory('not a constraint !!!', 'GHSA-bad'),
        makeAdvisory('>=1.0.0,<2.0.0', 'GHSA-good'),
    ];

    $affecting = AdvisoryMatcher::affecting($advisories, '1.5.0');

    expect($affecting)->toHaveCount(1)
        ->and($affecting[0]->advisoryId)->toBe('GHSA-good');
});

it('reports advisories fixed between two versions', function () {
    $advisories = [
        makeAdvisory('>=2.0.0,<2.4.5', 'GHSA-fixed'),
        makeAdvisory('>=2.0.0,<3.0.0', 'GHSA-still-affected'),
    ];

    $fixed = AdvisoryMatcher::fixedBetween($advisories, '2.4.0', '2.4.5');

    expect($fixed)->toHaveCount(1)
        ->and($fixed[0]->advisoryId)->toBe('GHSA-fixed');
});

it('does not report a fixed advisory when the old version was not affected', function () {
    $fixed = AdvisoryMatcher::fixedBetween([makeAdvisory('>=1.0.0,<2.0.0')], '2.1.0', '2.2.0');

    expect($fixed)->toBe([]);
});

it('reports advisories introduced by a version change', function () {
    $advisories = [
        makeAdvisory('>=2.5.0,<2.6.0', 'GHSA-introduced'),
        makeAdvisory('>=2.0.0,<2.6.0', 'GHSA-already-affected'),
    ];

    $introduced = AdvisoryMatcher::introducedBetween($advisories, '2.4.0', '2.5.1');

    expect($introduced)->toHaveCount(1)
        ->and($introduced[0]->advisoryId)->toBe('GHSA-introduced');
});

it('treats every affecting advisory as introduced for an added package', function () {
    $advisories = [
        makeAdvisory('>=2.0.0,<2.6.0', 'GHSA-a'),
        makeAdvisory('>=9.0.0', 'GHSA-b'),
    ];

    $introduced = AdvisoryMatcher::introducedBetween($advisories, null, '2.5.0');

    expect($introduced)->toHaveCount(1)
        ->and($introduced[0]->advisoryId)->toBe('GHSA-a');
});

it('treats an unparsable from-version as previously unaffected', function () {
    // The range parses against `to` but the dev `from` version cannot be
    // evaluated — diff-mode semantics count the advisory as newly affecting.
    $introduced = AdvisoryMatcher::introducedBetween(
        [makeAdvisory('>=2.0.0,<2.6.0')],
        'dev-main',
        '2.5.0',
    );

    expect($introduced)->toHaveCount(1);
});
