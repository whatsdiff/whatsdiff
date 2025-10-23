<?php

declare(strict_types=1);

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Whatsdiff\Analyzers\Registries\NpmRegistry;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;
use Whatsdiff\Analyzers\ReleaseNotes\ReleaseNotesResolver;
use Whatsdiff\Commands\ChangelogCommand;
use Whatsdiff\Services\CacheService;
use Whatsdiff\Services\GitRepository;

beforeEach(function () {
    $this->gitRepository = Mockery::mock(GitRepository::class);
    $this->packagistRegistry = Mockery::mock(PackagistRegistry::class);
    $this->npmRegistry = Mockery::mock(NpmRegistry::class);
    $this->cacheService = Mockery::mock(CacheService::class);
    $this->releaseNotesResolver = Mockery::mock(ReleaseNotesResolver::class);

    $this->command = new ChangelogCommand(
        $this->gitRepository,
        $this->packagistRegistry,
        $this->npmRegistry,
        $this->cacheService,
        $this->releaseNotesResolver
    );

    $application = new Application();
    $application->add($this->command);

    $this->commandTester = new CommandTester($this->command);
});

it('shows error when version argument and --from flag are both provided', function () {
    $exitCode = $this->commandTester->execute([
        'package' => 'symfony/console',
        'version' => '5.1.0',
        '--from' => 'abc123',
    ]);

    expect($exitCode)->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('Cannot use version argument with --from or --to options');
});

it('shows error when version argument and --to flag are both provided', function () {
    $exitCode = $this->commandTester->execute([
        'package' => 'symfony/console',
        'version' => '5.1.0',
        '--to' => 'abc123',
    ]);

    expect($exitCode)->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('Cannot use version argument with --from or --to options');
});

it('shows error when version argument and --ignore-last are both provided', function () {
    $exitCode = $this->commandTester->execute([
        'package' => 'symfony/console',
        'version' => '5.1.0',
        '--ignore-last' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('Cannot use version argument with --ignore-last option');
});

it('shows error when --from/--to and --ignore-last are both provided', function () {
    $exitCode = $this->commandTester->execute([
        'package' => 'symfony/console',
        '--from' => 'abc123',
        '--ignore-last' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('Cannot use --ignore-last with --from or --to options');
});

// Note: More comprehensive integration tests for full command execution
// are handled in Feature tests. These unit tests focus on validation logic.
