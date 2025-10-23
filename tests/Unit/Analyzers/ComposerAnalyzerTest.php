<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\ComposerAnalyzer;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;

beforeEach(function () {
    $this->registry = Mockery::mock(PackagistRegistry::class);
    $this->analyzer = new ComposerAnalyzer($this->registry);
});

it('extracts package versions from valid composer lock', function () {
    $composerLockContent = [
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
            ],
            [
                'name' => 'illuminate/collections',
                'version' => 'v9.0.0',
            ],
        ],
        'packages-dev' => [
            [
                'name' => 'phpunit/phpunit',
                'version' => '9.5.0',
            ],
        ],
    ];

    $result = $this->analyzer->extractPackageVersions($composerLockContent);

    expect($result)->toBe([
        'symfony/console' => 'v5.4.0',
        'illuminate/collections' => 'v9.0.0',
        'phpunit/phpunit' => '9.5.0',
    ]);
});

it('extracts package versions with missing packages key', function () {
    $composerLockContent = [
        'packages-dev' => [
            [
                'name' => 'phpunit/phpunit',
                'version' => '9.5.0',
            ],
        ],
    ];

    $result = $this->analyzer->extractPackageVersions($composerLockContent);

    expect($result)->toBe([
        'phpunit/phpunit' => '9.5.0',
    ]);
});

it('extracts package versions with empty content', function () {
    $result = $this->analyzer->extractPackageVersions([]);

    expect($result)->toBe([]);
});

it('calculates diff with valid json', function () {
    $previousLock = json_encode([
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
                'dist' => ['url' => 'https://api.github.com/repos/symfony/console/zipball/abc123'],
            ],
            [
                'name' => 'monolog/monolog',
                'version' => '2.7.0',
                'dist' => ['url' => 'https://api.github.com/repos/Seldaek/monolog/zipball/def456'],
            ],
        ],
    ]);

    $currentLock = json_encode([
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v6.0.0', // Updated
                'dist' => ['url' => 'https://api.github.com/repos/symfony/console/zipball/xyz789'],
            ],
            [
                'name' => 'illuminate/collections',
                'version' => 'v9.0.0', // Added
                'dist' => ['url' => 'https://api.github.com/repos/illuminate/collections/zipball/new123'],
            ],
        ],
    ]);

    $result = $this->analyzer->calculateDiff($currentLock, $previousLock);

    expect($result)->toHaveCount(3);

    // Updated package
    expect($result['symfony/console'])->toBe([
        'name' => 'symfony/console',
        'from' => 'v5.4.0',
        'to' => 'v6.0.0',
        'infos_url' => 'https://repo.packagist.org/p2/symfony/console.json',
    ]);

    // Removed package
    expect($result['monolog/monolog'])->toBe([
        'name' => 'monolog/monolog',
        'from' => '2.7.0',
        'to' => null,
        'infos_url' => 'https://repo.packagist.org/p2/monolog/monolog.json',
    ]);

    // Added package
    expect($result['illuminate/collections'])->toBe([
        'name' => 'illuminate/collections',
        'from' => null,
        'to' => 'v9.0.0',
        'infos_url' => 'https://repo.packagist.org/p2/illuminate/collections.json',
    ]);
});

it('calculates diff with invalid json', function () {
    $result = $this->analyzer->calculateDiff('invalid json', null);

    expect($result)->toBe([]);
});

it('calculates diff with null previous', function () {
    $currentLock = json_encode([
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
                'dist' => ['url' => 'https://api.github.com/repos/symfony/console/zipball/abc123'],
            ],
        ],
    ]);

    $result = $this->analyzer->calculateDiff($currentLock, null);

    expect($result)->toHaveCount(1);
    expect($result['symfony/console'])->toBe([
        'name' => 'symfony/console',
        'from' => null,
        'to' => 'v5.4.0',
        'infos_url' => 'https://repo.packagist.org/p2/symfony/console.json',
    ]);
});

it('filters unchanged packages in diff', function () {
    $lock = json_encode([
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
                'dist' => ['url' => 'https://api.github.com/repos/symfony/console/zipball/abc123'],
            ],
        ],
    ]);

    $result = $this->analyzer->calculateDiff($lock, $lock);

    expect($result)->toBe([]);
});

it('gets releases count successfully', function () {
    $this->registry
        ->shouldReceive('getVersions')
        ->once()
        ->with('symfony/console', 'v5.4.0', 'v6.0.0', [
            'url' => 'https://repo.packagist.org/p2/symfony/console.json',
        ])
        ->andReturn(['v5.4.1', 'v5.4.2', 'v6.0.0']);

    $result = $this->analyzer->getReleasesCount('symfony/console', 'v5.4.0', 'v6.0.0', ['url' => 'https://repo.packagist.org/p2/symfony/console.json']);

    expect($result)->toBe(3);
});
