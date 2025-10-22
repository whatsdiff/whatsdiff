<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\Exceptions\PackageInformationsException;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;
use Whatsdiff\Services\HttpService;

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

it('loads auth from local auth.json and applies automatically', function () {
    // Create temporary directory with auth.json
    $tempDir = sys_get_temp_dir() . '/whatsdiff-test-' . uniqid();
    mkdir($tempDir);

    $authContent = [
        'http-basic' => [
            'repo.packagist.com' => [
                'username' => 'test-user',
                'password' => 'test-pass',
            ],
        ],
    ];

    file_put_contents($tempDir . '/auth.json', json_encode($authContent));

    $originalDir = getcwd();
    chdir($tempDir);

    try {
        $packageData = [
            'packages' => [
                'livewire/flux-pro' => [
                    ['version' => 'v1.0.0'],
                ],
            ],
        ];

        // Auth should be automatically applied from auth.json
        $this->httpService
            ->shouldReceive('get')
            ->once()
            ->with('https://repo.packagist.com/p2/livewire/flux-pro.json', [
                'auth' => [
                    'username' => 'test-user',
                    'password' => 'test-pass',
                ],
            ])
            ->andReturn(json_encode($packageData));

        $result = $this->registry->getPackageMetadata('livewire/flux-pro', [
            'url' => 'https://repo.packagist.com/p2/livewire/flux-pro.json',
        ]);

        expect($result)->toBe($packageData);
    } finally {
        chdir($originalDir);
        unlink($tempDir . '/auth.json');
        rmdir($tempDir);
    }
});

it('loads auth from both local and global with local taking precedence', function () {
    // Create temporary directories
    $tempDir = sys_get_temp_dir() . '/whatsdiff-test-' . uniqid();
    $homeDir = sys_get_temp_dir() . '/whatsdiff-home-' . uniqid();
    mkdir($tempDir);
    mkdir($homeDir);
    mkdir($homeDir . '/.composer');

    $localAuth = [
        'http-basic' => [
            'local.example.com' => [
                'username' => 'local-user',
                'password' => 'local-pass',
            ],
        ],
    ];

    $globalAuth = [
        'http-basic' => [
            'global.example.com' => [
                'username' => 'global-user',
                'password' => 'global-pass',
            ],
            'local.example.com' => [
                'username' => 'should-be-overridden',
                'password' => 'should-be-overridden',
            ],
        ],
    ];

    file_put_contents($tempDir . '/auth.json', json_encode($localAuth));
    file_put_contents($homeDir . '/.composer/auth.json', json_encode($globalAuth));

    // Set HOME environment variable
    $originalHome = getenv('HOME') ?: getenv('USERPROFILE');
    putenv("HOME={$homeDir}");

    $originalDir = getcwd();
    chdir($tempDir);

    try {
        $packageData = ['packages' => ['test/package' => [['version' => 'v1.0.0']]]];

        // Local auth should be used (overriding global)
        $this->httpService
            ->shouldReceive('get')
            ->once()
            ->with('https://local.example.com/p2/test/package.json', [
                'auth' => [
                    'username' => 'local-user',
                    'password' => 'local-pass',
                ],
            ])
            ->andReturn(json_encode($packageData));

        $result = $this->registry->getPackageMetadata('test/package', [
            'url' => 'https://local.example.com/p2/test/package.json',
        ]);

        expect($result)->toBe($packageData);
    } finally {
        chdir($originalDir);
        putenv("HOME={$originalHome}");
        unlink($tempDir . '/auth.json');
        unlink($homeDir . '/.composer/auth.json');
        rmdir($homeDir . '/.composer');
        rmdir($homeDir);
        rmdir($tempDir);
    }
});

it('uses global auth when local auth.json does not exist', function () {
    // Create temporary directories
    $tempDir = sys_get_temp_dir() . '/whatsdiff-test-' . uniqid();
    $homeDir = sys_get_temp_dir() . '/whatsdiff-home-' . uniqid();
    mkdir($tempDir);
    mkdir($homeDir);
    mkdir($homeDir . '/.composer');

    $globalAuth = [
        'http-basic' => [
            'global.example.com' => [
                'username' => 'global-user',
                'password' => 'global-pass',
            ],
        ],
    ];

    file_put_contents($homeDir . '/.composer/auth.json', json_encode($globalAuth));

    // Set HOME environment variable
    $originalHome = getenv('HOME') ?: getenv('USERPROFILE');
    putenv("HOME={$homeDir}");

    $originalDir = getcwd();
    chdir($tempDir);

    try {
        $packageData = ['packages' => ['test/package' => [['version' => 'v1.0.0']]]];

        // Global auth should be used
        $this->httpService
            ->shouldReceive('get')
            ->once()
            ->with('https://global.example.com/p2/test/package.json', [
                'auth' => [
                    'username' => 'global-user',
                    'password' => 'global-pass',
                ],
            ])
            ->andReturn(json_encode($packageData));

        $result = $this->registry->getPackageMetadata('test/package', [
            'url' => 'https://global.example.com/p2/test/package.json',
        ]);

        expect($result)->toBe($packageData);
    } finally {
        chdir($originalDir);
        putenv("HOME={$originalHome}");
        unlink($homeDir . '/.composer/auth.json');
        rmdir($homeDir . '/.composer');
        rmdir($homeDir);
        rmdir($tempDir);
    }
});

it('explicit auth options override auth.json', function () {
    // Create temporary directory with auth.json
    $tempDir = sys_get_temp_dir() . '/whatsdiff-test-' . uniqid();
    mkdir($tempDir);

    $authContent = [
        'http-basic' => [
            'private.example.com' => [
                'username' => 'auth-json-user',
                'password' => 'auth-json-pass',
            ],
        ],
    ];

    file_put_contents($tempDir . '/auth.json', json_encode($authContent));

    $originalDir = getcwd();
    chdir($tempDir);

    try {
        $packageData = ['packages' => ['test/package' => [['version' => 'v1.0.0']]]];

        // Explicit auth should override auth.json
        $this->httpService
            ->shouldReceive('get')
            ->once()
            ->with('https://private.example.com/p2/test/package.json', [
                'auth' => [
                    'username' => 'explicit-user',
                    'password' => 'explicit-pass',
                ],
            ])
            ->andReturn(json_encode($packageData));

        $result = $this->registry->getPackageMetadata('test/package', [
            'url' => 'https://private.example.com/p2/test/package.json',
            'auth' => [
                'username' => 'explicit-user',
                'password' => 'explicit-pass',
            ],
        ]);

        expect($result)->toBe($packageData);
    } finally {
        chdir($originalDir);
        unlink($tempDir . '/auth.json');
        rmdir($tempDir);
    }
});

it('does not apply auth when domain does not match auth.json', function () {
    // Create temporary directory with auth.json
    $tempDir = sys_get_temp_dir() . '/whatsdiff-test-' . uniqid();
    mkdir($tempDir);

    $authContent = [
        'http-basic' => [
            'other.example.com' => [
                'username' => 'other-user',
                'password' => 'other-pass',
            ],
        ],
    ];

    file_put_contents($tempDir . '/auth.json', json_encode($authContent));

    $originalDir = getcwd();
    chdir($tempDir);

    try {
        $packageData = ['packages' => ['symfony/console' => [['version' => 'v1.0.0']]]];

        // No auth should be applied (domain doesn't match)
        $this->httpService
            ->shouldReceive('get')
            ->once()
            ->with('https://repo.packagist.org/p2/symfony/console.json', [])
            ->andReturn(json_encode($packageData));

        $result = $this->registry->getPackageMetadata('symfony/console');

        expect($result)->toBe($packageData);
    } finally {
        chdir($originalDir);
        unlink($tempDir . '/auth.json');
        rmdir($tempDir);
    }
});
