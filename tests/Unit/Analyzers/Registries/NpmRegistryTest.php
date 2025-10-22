<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Analyzers\Registries\NpmRegistry;
use Whatsdiff\Services\HttpService;

beforeEach(function () {
    $this->httpService = Mockery::mock(HttpService::class);
    $this->registry = new NpmRegistry($this->httpService);
});

it('gets package metadata successfully', function () {
    $packageData = [
        'name' => 'lodash',
        'versions' => [
            [
                'version' => '4.17.20',
            ],
            [
                'version' => '4.17.21',
            ],
        ],
        'repository' => [
            'url' => 'git+https://github.com/lodash/lodash.git',
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://registry.npmjs.org/lodash')
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getPackageMetadata('lodash');

    expect($result)->toBe($packageData);
});

it('gets package metadata with custom url', function () {
    $packageData = [
        'name' => '@company/private-package',
        'versions' => [
            [
                'version' => '1.0.0',
            ],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://npm.company.com/@company/private-package')
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getPackageMetadata('@company/private-package', [
        'url' => 'https://npm.company.com/@company/private-package',
    ]);

    expect($result)->toBe($packageData);
});

it('throws exception on http error', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andThrow(new \Exception('Network error'));

    $this->registry->getPackageMetadata('lodash');
})->throws(PackageInformationsException::class, 'Failed to fetch package information for lodash');

it('throws exception on invalid json response', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn('invalid json');

    $this->registry->getPackageMetadata('lodash');
})->throws(PackageInformationsException::class, 'Invalid JSON response from npm registry');

it('gets versions between two constraints', function () {
    $packageData = [
        'versions' => [
            [
                'version' => '4.17.19',
            ],
            [
                'version' => '4.17.20',
            ],
            [
                'version' => '4.17.21',
            ],
            [
                'version' => '5.0.0',
            ],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getVersions('lodash', '4.17.19', '4.17.21');

    expect($result)->toBe(['4.17.20', '4.17.21']);
});

it('returns empty array when versions not found in metadata', function () {
    $packageData = [
        'name' => 'lodash',
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getVersions('lodash', '4.17.19', '4.17.21');

    expect($result)->toBe([]);
});

it('gets repository url from repository object', function () {
    $packageData = [
        'name' => 'lodash',
        'repository' => [
            'type' => 'git',
            'url' => 'git+https://github.com/lodash/lodash.git',
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getRepositoryUrl('lodash');

    expect($result)->toBe('https://github.com/lodash/lodash');
});

it('gets repository url from repository string', function () {
    $packageData = [
        'name' => 'lodash',
        'repository' => 'git+https://github.com/lodash/lodash.git',
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getRepositoryUrl('lodash');

    expect($result)->toBe('https://github.com/lodash/lodash');
});

it('normalizes git protocol urls', function () {
    $packageData = [
        'name' => 'lodash',
        'repository' => [
            'url' => 'git://github.com/lodash/lodash.git',
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getRepositoryUrl('lodash');

    expect($result)->toBe('https://github.com/lodash/lodash');
});

it('removes git suffix from repository url', function () {
    $packageData = [
        'name' => 'lodash',
        'repository' => [
            'url' => 'https://github.com/lodash/lodash.git',
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getRepositoryUrl('lodash');

    expect($result)->toBe('https://github.com/lodash/lodash');
});

it('returns null when repository url not available', function () {
    $packageData = [
        'name' => 'lodash',
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getRepositoryUrl('lodash');

    expect($result)->toBeNull();
});

it('returns null when package metadata fetch fails', function () {
    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->andThrow(new \Exception('Network error'));

    $result = $this->registry->getRepositoryUrl('lodash');

    expect($result)->toBeNull();
});

it('handles scoped packages correctly', function () {
    $packageData = [
        'name' => '@angular/core',
        'versions' => [
            [
                'version' => '16.0.0',
            ],
        ],
    ];

    $this->httpService
        ->shouldReceive('get')
        ->once()
        ->with('https://registry.npmjs.org/@angular/core')
        ->andReturn(json_encode($packageData));

    $result = $this->registry->getPackageMetadata('@angular/core', [
        'url' => 'https://registry.npmjs.org/@angular/core',
    ]);

    expect($result)->toBe($packageData);
});
