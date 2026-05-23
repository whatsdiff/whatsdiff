<?php

declare(strict_types=1);

use Whatsdiff\Enums\Severity;

it('parses known severity strings', function (?string $input, Severity $expected) {
    expect(Severity::fromString($input))->toBe($expected);
})->with([
    ['low', Severity::Low],
    ['LOW', Severity::Low],
    ['medium', Severity::Medium],
    ['moderate', Severity::Medium],
    ['high', Severity::High],
    ['critical', Severity::Critical],
    [null, Severity::Unknown],
    ['', Severity::Unknown],
    ['weird-value', Severity::Unknown],
]);

it('orders severities by rank', function () {
    expect(Severity::Critical->rank())->toBeGreaterThan(Severity::High->rank());
    expect(Severity::High->rank())->toBeGreaterThan(Severity::Medium->rank());
    expect(Severity::Medium->rank())->toBeGreaterThan(Severity::Low->rank());
    expect(Severity::Low->rank())->toBeGreaterThan(Severity::Unknown->rank());
});

it('meets threshold correctly', function () {
    expect(Severity::Critical->meetsThreshold(Severity::Low))->toBeTrue();
    expect(Severity::Critical->meetsThreshold(Severity::High))->toBeTrue();
    expect(Severity::High->meetsThreshold(Severity::Critical))->toBeFalse();
    expect(Severity::Low->meetsThreshold(Severity::Medium))->toBeFalse();
    expect(Severity::Medium->meetsThreshold(Severity::Medium))->toBeTrue();
});
