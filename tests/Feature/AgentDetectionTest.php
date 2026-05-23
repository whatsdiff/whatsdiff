<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process as SymfonyProcess;

beforeEach(function () {
    $this->tempDir = initTempDirectory();

    // Build a 2-commit history with a composer.lock change so `analyse` has output.
    $initial = generateComposerLock(['symfony/console' => 'v5.4.0']);
    file_put_contents($this->tempDir.'/composer.lock', $initial);
    runCommand('git add composer.lock', $this->tempDir);
    runCommand('git commit -m "Initial composer.lock"', $this->tempDir);

    $updated = generateComposerLock(['symfony/console' => 'v6.0.0']);
    file_put_contents($this->tempDir.'/composer.lock', $updated);
    runCommand('git add composer.lock', $this->tempDir);
    runCommand('git commit -m "Update symfony/console"', $this->tempDir);
});

afterEach(function () {
    cleanupTempDirectory($this->tempDir);
});

function runWhatsDiffWithEnv(array $args, string $cwd, array $env): SymfonyProcess
{
    $binPath = realpath(__DIR__.'/../../bin/whatsdiff');
    $phpBinary = (new ExecutableFinder())->find('php');

    if (! $phpBinary) {
        throw new RuntimeException('PHP executable not found');
    }

    $process = new SymfonyProcess(
        array_merge([$phpBinary, $binPath, '--no-interaction'], $args),
        $cwd,
        $env + ['PATH' => getenv('PATH') ?: ''],
    );
    $process->setTimeout(120);
    $process->run();

    return $process;
}

it('defaults to JSON output when CLAUDECODE is set', function () {
    $process = runWhatsDiffWithEnv([], $this->tempDir, ['CLAUDECODE' => '1']);

    expect($process->getExitCode())->toBe(0);

    $output = trim($process->getOutput());
    expect($output)->not->toBe('');

    $decoded = json_decode($output, true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($decoded)->toBeArray();
});

it('explicit --format=text overrides agent JSON default', function () {
    $process = runWhatsDiffWithEnv(
        ['--format=text', '--no-progress'],
        $this->tempDir,
        ['CLAUDECODE' => '1'],
    );

    expect($process->getExitCode())->toBe(0);

    $output = $process->getOutput();
    expect(json_decode($output, true))->toBeNull();
    expect($output)->toContain('symfony/console');
});

it('stays in text mode when no agent env var is present', function () {
    $process = runWhatsDiffWithEnv(['--no-progress'], $this->tempDir, []);

    expect($process->getExitCode())->toBe(0);

    $output = $process->getOutput();
    expect(json_decode($output, true))->toBeNull();
    expect($output)->toContain('symfony/console');
});
