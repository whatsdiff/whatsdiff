<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\PnpmAnalyzer;
use Whatsdiff\Analyzers\Registries\NpmRegistry;

beforeEach(function () {
    $this->registry = Mockery::mock(NpmRegistry::class);
    $this->analyzer = new PnpmAnalyzer($this->registry);
});

it('returns pnpm package manager type', function () {
    expect($this->analyzer->getType())->toBe(PackageManagerType::PNPM);
});

it('extracts package versions from valid pnpm lock array', function () {
    $pnpmLockContent = [
        'lockfileVersion' => '9.0',
        'packages' => [
            'lodash@4.17.21' => ['resolution' => ['integrity' => 'sha512-abc']],
            'react@18.2.0' => ['resolution' => ['integrity' => 'sha512-def']],
            '@types/node@18.15.0' => ['resolution' => ['integrity' => 'sha512-ghi']],
        ],
    ];

    $result = $this->analyzer->extractPackageVersions($pnpmLockContent);

    expect($result)->toBe([
        'lodash' => '4.17.21',
        'react' => '18.2.0',
        '@types/node' => '18.15.0',
    ]);
});

it('extracts empty versions when packages key is missing', function () {
    $result = $this->analyzer->extractPackageVersions([]);

    expect($result)->toBe([]);
});

it('calculates diff with invalid yaml', function () {
    $result = $this->analyzer->calculateDiff(":\t:\ninvalid\t\tyaml: [unclosed", null);

    expect($result)->toBe([]);
});

it('returns empty diff for unsupported v6 lock format without throwing', function () {
    // v6 keys use /name@version format which is not parsed by the v9 parser,
    // so the diff returns empty rather than incorrect data.
    $v6Lock = "lockfileVersion: '6.0'\n\npackages:\n\n  /lodash@4.17.21:\n    resolution: {integrity: sha512-abc}\n";

    $result = $this->analyzer->calculateDiff($v6Lock, null);

    expect($result)->toBeArray();
});

it('returns empty diff for unsupported v5 lock format without throwing', function () {
    // v5 keys use /name/version format (no @), which parsePackageKey skips entirely.
    $v5Lock = "lockfileVersion: 5.4\n\npackages:\n\n  /lodash/4.17.21:\n    resolution: {integrity: sha512-abc}\n";

    $result = $this->analyzer->calculateDiff($v5Lock, null);

    expect($result)->toBe([]);
});

it('calculates diff with valid v9 yaml', function () {
    $previousLock = generatePnpmLock([
        'lodash' => '4.17.15',
        'moment' => '2.29.1',
    ]);

    $currentLock = generatePnpmLock([
        'lodash' => '4.17.21', // Updated
        'react' => '18.2.0',  // Added
        // moment removed
    ]);

    $result = $this->analyzer->calculateDiff($currentLock, $previousLock);

    expect($result)->toHaveCount(3);

    expect($result['lodash'])->toBe([
        'name' => 'lodash',
        'from' => '4.17.15',
        'to' => '4.17.21',
    ]);

    expect($result['moment'])->toBe([
        'name' => 'moment',
        'from' => '2.29.1',
        'to' => null,
    ]);

    expect($result['react'])->toBe([
        'name' => 'react',
        'from' => null,
        'to' => '18.2.0',
    ]);
});

it('calculates diff with null previous', function () {
    $currentLock = generatePnpmLock([
        'lodash' => '4.17.21',
    ]);

    $result = $this->analyzer->calculateDiff($currentLock, null);

    expect($result)->toHaveCount(1);
    expect($result['lodash'])->toBe([
        'name' => 'lodash',
        'from' => null,
        'to' => '4.17.21',
    ]);
});

it('returns empty diff when lock file is unchanged', function () {
    $lock = generatePnpmLock(['lodash' => '4.17.21']);

    $result = $this->analyzer->calculateDiff($lock, $lock);

    expect($result)->toBe([]);
});

it('handles scoped packages in diff', function () {
    $previousLock = generatePnpmLock([
        '@babel/core' => '7.20.0',
        '@types/node' => '18.0.0',
    ]);

    $currentLock = generatePnpmLock([
        '@babel/core' => '7.22.0', // Updated
        '@types/node' => '18.0.0', // Unchanged
    ]);

    $result = $this->analyzer->calculateDiff($currentLock, $previousLock);

    expect($result)->toHaveCount(1);
    expect($result['@babel/core'])->toBe([
        'name' => '@babel/core',
        'from' => '7.20.0',
        'to' => '7.22.0',
    ]);
});

it('gets releases count successfully', function () {
    $this->registry
        ->shouldReceive('getVersions')
        ->once()
        ->with('lodash', '4.17.15', '4.17.21', [])
        ->andReturn(['4.17.16', '4.17.17', '4.17.18', '4.17.19', '4.17.20', '4.17.21']);

    $result = $this->analyzer->getReleasesCount('lodash', '4.17.15', '4.17.21');

    expect($result)->toBe(6);
});

it('calculates diff with complex changes', function () {
    $previousLock = generatePnpmLock([
        'lodash' => '4.17.15',
        'moment' => '2.29.1',
        'axios' => '0.21.1',
        '@types/node' => '16.0.0',
    ]);

    $currentLock = generatePnpmLock([
        'lodash' => '4.17.21',    // Updated
        'axios' => '0.20.0',      // Downgraded
        '@types/node' => '18.15.0', // Updated
        'react' => '18.2.0',      // Added
        // moment removed
    ]);

    $result = $this->analyzer->calculateDiff($currentLock, $previousLock);

    expect($result)->toHaveCount(5);

    $changes = collect($result);
    expect($changes->where('from', '!=', null)->where('to', '!=', null)->count())->toBe(3);
    expect($changes->where('from', null)->count())->toBe(1);
    expect($changes->where('to', null)->count())->toBe(1);
});
