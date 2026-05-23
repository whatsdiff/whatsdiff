<?php

declare(strict_types=1);

use Whatsdiff\Services\AgentEnvironment;

afterEach(function () {
    foreach (['CLAUDECODE', 'CLAUDE_CODE', 'CURSOR_AGENT', 'GEMINI_CLI', 'AI_AGENT'] as $envVar) {
        putenv($envVar);
        unset($_ENV[$envVar], $_SERVER[$envVar]);
    }
});

test('returns no-agent when no known env var is set', function () {
    $env = AgentEnvironment::detect();

    expect($env->isAgent)->toBeFalse();
    expect($env->agentName)->toBeNull();
    expect($env->defaultFormat())->toBe('text');
});

test('detects Claude Code via CLAUDECODE env var', function () {
    putenv('CLAUDECODE=1');

    $env = AgentEnvironment::detect();

    expect($env->isAgent)->toBeTrue();
    expect($env->agentName)->toBe('claude');
    expect($env->defaultFormat())->toBe('json');
});

test('detects Cursor via CURSOR_AGENT env var', function () {
    putenv('CURSOR_AGENT=1');

    $env = AgentEnvironment::detect();

    expect($env->isAgent)->toBeTrue();
    expect($env->agentName)->toBe('cursor');
    expect($env->defaultFormat())->toBe('json');
});

test('AI_AGENT env var maps github-copilot to copilot', function () {
    putenv('AI_AGENT=github-copilot');

    $env = AgentEnvironment::detect();

    expect($env->isAgent)->toBeTrue();
    expect($env->agentName)->toBe('copilot');
    expect($env->defaultFormat())->toBe('json');
});

test('noAgent static constructor produces a non-agent env', function () {
    $env = AgentEnvironment::noAgent();

    expect($env->isAgent)->toBeFalse();
    expect($env->agentName)->toBeNull();
    expect($env->defaultFormat())->toBe('text');
});
