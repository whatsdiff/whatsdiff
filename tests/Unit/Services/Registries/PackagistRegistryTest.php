<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Services\HttpService;
use Whatsdiff\Services\Registries\PackagistRegistry;

beforeEach(function () {
    $this->httpService = Mockery::mock(HttpService::class);
    $this->registry = new PackagistRegistry($this->httpService);
});

it('gets package metadata successfully', function () {
    $packageData = [
        'packages' => [
            'symfony/console' => [
                [
                    'version' => 'v5.4.0',
                    'source' => ['url' => 'https://github.com/symfony/console.git'],
                ],
                [
                    'version' => 'v6.0.0',
                    'source' => ['url' => 'https://github.com/symfony/console.git'],
                ],
            ],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://repo.packagist.org/p2/symfony/console.json', [])
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getPackageMetadata('symfony/console');

    expect($result)->toBe($packageData);
});

it('gets package metadata with custom url', function () {
    $packageData = [
        'packages' => [
            'livewire/flux-pro' => [
                [
                    'version' => 'v1.0.0',
                ],
            ],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://repo.packagist.com/p2/livewire/flux-pro.json', [])
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getPackageMetadata('livewire/flux-pro', [
        'url' => 'https://repo.packagist.com/p2/livewire/flux-pro.json',
    ]);

    expect($result)->toBe($packageData);
});

it('gets package metadata with authentication', function () {
    $packageData = [
        'packages' => [
            'private/package' => [
                [
                    'version' => 'v1.0.0',
                ],
            ],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://private.repo.com/p2/private/package.json', [
            'auth' => [
                'username' => 'user',
                'password' => 'pass',
            ],
        ])
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getPackageMetadata('private/package', [
        'url' => 'https://private.repo.com/p2/private/package.json',
        'auth' => [
            'username' => 'user',
            'password' => 'pass',
        ],
    ]);

    expect($result)->toBe($packageData);
});

it('extracts authentication from url', function () {
    $packageData = [
        'packages' => [
            'private/package' => [
                [
                    'version' => 'v1.0.0',
                ],
            ],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://private.repo.com/p2/private/package.json', [
            'auth' => [
                'username' => 'user',
                'password' => 'pass',
            ],
        ])
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getPackageMetadata('private/package', [
        'url' => 'https://user:pass@private.repo.com/p2/private/package.json',
    ]);

    expect($result)->toBe($packageData);
});

it('throws exception on http error', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andThrow(new \Exception('Network error'));

    $this->registry->getPackageMetadata('symfony/console');
})->throws(PackageInformationsException::class, 'Failed to fetch package information for symfony/console');

it('throws exception on invalid json response', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn('invalid json');

    $this->registry->getPackageMetadata('symfony/console');
})->throws(PackageInformationsException::class, 'Invalid JSON response from Packagist');

it('gets versions between two constraints', function () {
    $packageData = [
        'packages' => [
            'symfony/console' => [
                [
                    'version' => 'v5.4.0',
                ],
                [
                    'version' => 'v5.4.1',
                ],
                [
                    'version' => 'v5.4.2',
                ],
                [
                    'version' => 'v6.0.0',
                ],
                [
                    'version' => 'v6.1.0',
                ],
            ],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getVersions('symfony/console', 'v5.4.0', 'v6.0.0');

    expect($result)->toBe(['v5.4.1', 'v5.4.2', 'v6.0.0']);
});

it('returns empty array when package not found in metadata', function () {
    $packageData = [
        'packages' => [],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getVersions('symfony/console', 'v5.4.0', 'v6.0.0');

    expect($result)->toBe([]);
});

it('gets repository url from most recent version', function () {
    $packageData = [
        'packages' => [
            'symfony/console' => [
                [
                    'version' => 'v6.0.0',
                    'source' => ['url' => 'https://github.com/symfony/console.git'],
                ],
                [
                    'version' => 'v5.4.0',
                    'source' => ['url' => 'https://github.com/symfony/console.git'],
                ],
            ],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getRepositoryUrl('symfony/console');

    expect($result)->toBe('https://github.com/symfony/console.git');
});

it('gets repository url from dist when source not available', function () {
    $packageData = [
        'packages' => [
            'symfony/console' => [
                [
                    'version' => 'v6.0.0',
                    'dist' => ['url' => 'https://api.github.com/repos/symfony/console/zipball/abc123'],
                ],
            ],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getRepositoryUrl('symfony/console');

    expect($result)->toBe('https://api.github.com/repos/symfony/console/zipball/abc123');
});

it('returns null when repository url cannot be found', function () {
    $packageData = [
        'packages' => [
            'symfony/console' => [],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getRepositoryUrl('symfony/console');

    expect($result)->toBeNull();
});

it('returns null when package metadata fetch fails', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andThrow(new \Exception('Network error'));

    $result = $this->registry->getRepositoryUrl('symfony/console');

    expect($result)->toBeNull();
});
