<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;

beforeEach(function () {
    $this->tempDir = initTempDirectory();
});

afterEach(function () {
    cleanupTempDirectory($this->tempDir);
});

it('rejects invalid fail-on values', function () {
    file_put_contents($this->tempDir.'/composer.lock', generateComposerLock([]));

    $process = runWhatsDiff(['audit', '--fail-on=invalid'], $this->tempDir);

    expect($process->getExitCode())->toBe(Command::FAILURE);
    expect($process->getErrorOutput().$process->getOutput())->toContain('Invalid --fail-on value');
});

it('rejects combining --at with --from', function () {
    file_put_contents($this->tempDir.'/composer.lock', generateComposerLock([]));

    $process = runWhatsDiff(['audit', '--at=HEAD', '--from=HEAD~1'], $this->tempDir);

    expect($process->getExitCode())->toBe(Command::FAILURE);
    expect($process->getErrorOutput().$process->getOutput())->toContain('Cannot use --at together with --from or --to');
});

it('rejects combining --include with --exclude', function () {
    file_put_contents($this->tempDir.'/composer.lock', generateComposerLock([]));

    $process = runWhatsDiff(['audit', '--include=composer', '--exclude=npmjs'], $this->tempDir);

    expect($process->getExitCode())->toBe(Command::FAILURE);
    expect($process->getErrorOutput().$process->getOutput())->toContain('Cannot use both --include and --exclude');
});

it('rejects unknown package manager type', function () {
    file_put_contents($this->tempDir.'/composer.lock', generateComposerLock([]));

    $process = runWhatsDiff(['audit', '--include=pip'], $this->tempDir);

    expect($process->getExitCode())->toBe(Command::FAILURE);
    expect($process->getErrorOutput().$process->getOutput())->toContain("Invalid package manager type: 'pip'");
});
