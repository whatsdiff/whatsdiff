<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;

beforeEach(function () {
    $this->tempDir = initTempDirectory();
});

afterEach(function () {
    cleanupTempDirectory($this->tempDir);
});

it('accepts pnpm as a valid --include type', function () {
    file_put_contents($this->tempDir.'/pnpm-lock.yaml', generatePnpmLock([]));

    $process = runWhatsDiff(['changelog', '--include=pnpm', '--format=json'], $this->tempDir);

    expect($process->getExitCode())->toBe(Command::SUCCESS);
    expect($process->getErrorOutput().$process->getOutput())->not->toContain('Invalid package manager type');
});

it('auto-detects pnpm from the lock file when no --type is given (strategy 2)', function () {
    // Strategy 2 scans lock files at HEAD and should identify pnpm.
    $package = 'whatsdiff-strategy4-lockfile-test-only';

    $lockContent = generatePnpmLock([$package => '1.0.0']);
    file_put_contents($this->tempDir.'/pnpm-lock.yaml', $lockContent);
    runCommand('git add pnpm-lock.yaml', $this->tempDir);
    runCommand('git commit -m "Add pnpm-lock.yaml"', $this->tempDir);

    // Explicit version range avoids git-history version detection after the type is found.
    $process = runWhatsDiff(
        ['changelog', $package, '1.0.0...2.0.0'],
        $this->tempDir
    );

    // The command must not fail at the package-manager detection stage.
    $combined = $process->getErrorOutput().$process->getOutput();
    expect($combined)->not->toContain('not found in lock files');
    expect($combined)->not->toContain('Try specifying --type=(composer|npm|pnpm)');
});

it('accepts pnpm as a valid --type value when a package and version range are given', function () {
    // Set up a git repo with a pnpm-lock.yaml that has two versions of axios
    $initialLock = generatePnpmLock(['axios' => '1.5.0']);
    file_put_contents($this->tempDir.'/pnpm-lock.yaml', $initialLock);
    runCommand('git add pnpm-lock.yaml', $this->tempDir);
    runCommand('git commit -m "Initial pnpm-lock.yaml"', $this->tempDir);

    $updatedLock = generatePnpmLock(['axios' => '1.6.0']);
    file_put_contents($this->tempDir.'/pnpm-lock.yaml', $updatedLock);
    runCommand('git add pnpm-lock.yaml', $this->tempDir);
    runCommand('git commit -m "Update axios"', $this->tempDir);

    // Passing a version range directly avoids network calls to registries
    $process = runWhatsDiff(
        ['changelog', 'axios', '1.5.0...1.6.0', '--type=pnpm', '--format=json'],
        $this->tempDir
    );

    // The command should not fail with a "package manager type" or "not found" error
    $combined = $process->getErrorOutput().$process->getOutput();
    expect($combined)->not->toContain("Invalid package manager type: 'pnpm'");
    expect($combined)->not->toContain('Try specifying --type=(composer|npm)');
});
