<?php

declare(strict_types=1);

use Whatsdiff\Outputs\Tui\ChangelogFormatter;

it('formats clickable URL without truncation when URL is short', function () {
    $formatter = new ChangelogFormatter();
    $reflection = new ReflectionClass($formatter);
    $method = $reflection->getMethod('formatClickableUrl');
    $method->setAccessible(true);

    $url = 'https://example.com/short';
    $maxWidth = 50;

    $result = $method->invoke($formatter, $url, $maxWidth);

    // Should contain OSC 8 hyperlink codes
    expect($result)->toContain("\e]8;;{$url}\007");
    expect($result)->toContain("\e]8;;\007");
    // Should contain the full URL in display text
    expect($result)->toContain($url);
})->skip('URLs are broken in TUI for now');

it('formats clickable URL with truncation when URL is long', function () {
    $formatter = new ChangelogFormatter();
    $reflection = new ReflectionClass($formatter);
    $method = $reflection->getMethod('formatClickableUrl');
    $method->setAccessible(true);

    $url = 'https://github.com/anthropics/claude-code/releases/tag/v1.2.3-beta-very-long-name';
    $maxWidth = 30;

    $result = $method->invoke($formatter, $url, $maxWidth);

    // Should contain OSC 8 hyperlink codes with full URL
    expect($result)->toContain("\e]8;;{$url}\007");
    expect($result)->toContain("\e]8;;\007");
    // Should contain ellipsis in display text
    expect($result)->toContain('...');
})->skip('URLs are broken in TUI for now');

it('strips OSC 8 hyperlink codes when calculating visible length', function () {
    $formatter = new ChangelogFormatter();
    $reflection = new ReflectionClass($formatter);
    $stripMethod = $reflection->getMethod('stripAnsiCodes');
    $stripMethod->setAccessible(true);

    $url = 'https://example.com';
    $textWithHyperlink = "\e]8;;{$url}\007Example Link\e]8;;\007";

    $stripped = $stripMethod->invoke($formatter, $textWithHyperlink);

    // Should only contain the visible text
    expect($stripped)->toBe('Example Link');
    expect($stripped)->not->toContain("\e]8");
});
