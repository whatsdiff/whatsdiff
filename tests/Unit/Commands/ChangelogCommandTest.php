<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\Registries\NpmRegistry;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;
use Whatsdiff\Analyzers\ReleaseNotes\ReleaseNotesResolver;
use Whatsdiff\Commands\ChangelogCommand;
use Whatsdiff\Data\DependencyDiff;
use Whatsdiff\Data\DiffResult;
use Whatsdiff\Data\PackageChange;
use Whatsdiff\Services\CacheService;
use Whatsdiff\Services\DiffCalculator;
use Whatsdiff\Services\GitRepository;

beforeEach(function () {
    $this->gitRepository = Mockery::mock(GitRepository::class);
    $this->packagistRegistry = Mockery::mock(PackagistRegistry::class);
    $this->npmRegistry = Mockery::mock(NpmRegistry::class);
    $this->cacheService = Mockery::mock(CacheService::class);
    $this->releaseNotesResolver = Mockery::mock(ReleaseNotesResolver::class);
    $this->diffCalculator = Mockery::mock(DiffCalculator::class);

    $this->command = new ChangelogCommand(
        $this->gitRepository,
        $this->packagistRegistry,
        $this->npmRegistry,
        $this->cacheService,
        $this->releaseNotesResolver,
        $this->diffCalculator,
    );

    $application = new Application;
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

it('shows error when version argument is given without a package', function () {
    $exitCode = $this->commandTester->execute([
        'version' => '5.1.0',
    ]);

    expect($exitCode)->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('Version argument requires a package name');
});

it('shows error when --include and --exclude are both provided', function () {
    $exitCode = $this->commandTester->execute([
        '--include' => 'composer',
        '--exclude' => 'npmjs',
    ]);

    expect($exitCode)->toBe(1)
        ->and($this->commandTester->getDisplay())->toContain('Cannot use both --include and --exclude options');
});

it('reports no updates when changelog runs without a package and nothing changed', function () {
    $this->cacheService->shouldReceive('disableCache')->zeroOrMoreTimes();

    $this->diffCalculator->shouldReceive('for')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('fromCommit')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('toCommit')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('ignoreLastCommit')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('skipReleaseCount')->andReturnSelf();
    $this->diffCalculator->shouldReceive('run')->andReturn(new DiffResult(collect()));

    $exitCode = $this->commandTester->execute([]);

    expect($exitCode)->toBe(0)
        ->and($this->commandTester->getDisplay())->toContain('No updated packages found.');
});

it('returns empty json structure when no updated packages and --format=json', function () {
    $this->cacheService->shouldReceive('disableCache')->zeroOrMoreTimes();

    $this->diffCalculator->shouldReceive('for')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('fromCommit')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('toCommit')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('ignoreLastCommit')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('skipReleaseCount')->andReturnSelf();
    $this->diffCalculator->shouldReceive('run')->andReturn(new DiffResult(collect()));

    $exitCode = $this->commandTester->execute([
        '--format' => 'json',
    ]);

    expect($exitCode)->toBe(0);

    $decoded = json_decode($this->commandTester->getDisplay(), true);
    expect($decoded)
        ->toMatchArray([
            'total_packages' => 0,
            'packages' => [],
        ]);
});

it('iterates updated packages and outputs JSON containing each package', function () {
    $this->cacheService->shouldReceive('disableCache')->zeroOrMoreTimes();

    $changes = new Collection([
        PackageChange::updated(
            name: 'vendor/foo',
            type: PackageManagerType::COMPOSER,
            fromVersion: '1.0.0',
            toVersion: '1.1.0',
        ),
        PackageChange::added(
            name: 'vendor/bar',
            type: PackageManagerType::COMPOSER,
            version: '2.0.0',
        ),
    ]);

    $diff = new DependencyDiff(
        filename: 'composer.lock',
        type: PackageManagerType::COMPOSER,
        fromCommit: null,
        toCommit: null,
        changes: $changes,
    );

    $this->diffCalculator->shouldReceive('for')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('fromCommit')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('toCommit')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('ignoreLastCommit')->zeroOrMoreTimes()->andReturnSelf();
    $this->diffCalculator->shouldReceive('skipReleaseCount')->andReturnSelf();
    $this->diffCalculator->shouldReceive('run')->andReturn(new DiffResult(collect([$diff])));

    // Repository lookup fails for the updated package, so we just verify the
    // packages list filters out added packages and produces a JSON entry.
    $this->packagistRegistry->shouldReceive('getRepositoryUrl')
        ->with('vendor/foo')
        ->andReturn(null);

    $exitCode = $this->commandTester->execute([
        '--format' => 'json',
    ]);

    expect($exitCode)->toBe(0);

    $decoded = json_decode($this->commandTester->getDisplay(), true);
    expect($decoded['total_packages'])->toBe(1)
        ->and($decoded['packages'][0]['package'])->toBe('vendor/foo')
        ->and($decoded['packages'][0]['from_version'])->toBe('1.0.0')
        ->and($decoded['packages'][0]['to_version'])->toBe('1.1.0');
});

// Note: More comprehensive integration tests for full command execution
// are handled in Feature tests. These unit tests focus on validation logic.
