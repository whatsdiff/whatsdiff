<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\Parsers\ComposerLockParser;

beforeEach(function () {
    $this->parser = new ComposerLockParser();
});

it('parses valid composer lock content', function () {
    $lockContent = json_encode([
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
                'source' => [
                    'url' => 'https://github.com/symfony/console.git',
                ],
            ],
            [
                'name' => 'illuminate/collections',
                'version' => 'v9.0.0',
                'dist' => [
                    'url' => 'https://api.github.com/repos/illuminate/collections/zipball/abc123',
                ],
            ],
        ],
        'packages-dev' => [
            [
                'name' => 'phpunit/phpunit',
                'version' => '9.5.0',
            ],
        ],
    ]);

    $result = $this->parser->parse($lockContent);

    expect($result)->toHaveCount(3);
    expect($result->get('symfony/console'))->toBe([
        'version' => 'v5.4.0',
        'repository' => 'https://github.com/symfony/console.git',
    ]);
    expect($result->get('illuminate/collections'))->toBe([
        'version' => 'v9.0.0',
        'repository' => 'https://api.github.com/repos/illuminate/collections/zipball/abc123',
    ]);
    expect($result->get('phpunit/phpunit'))->toBe([
        'version' => '9.5.0',
    ]);
});

it('parses composer lock with invalid json', function () {
    $result = $this->parser->parse('invalid json');

    expect($result)->toBeEmpty();
});

it('parses composer lock with empty content', function () {
    $result = $this->parser->parse('{}');

    expect($result)->toBeEmpty();
});

it('extracts package versions from parsed data', function () {
    $lockData = [
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

    $result = $this->parser->extractPackageVersions($lockData);

    expect($result)->toBe([
        'symfony/console' => 'v5.4.0',
        'illuminate/collections' => 'v9.0.0',
        'phpunit/phpunit' => '9.5.0',
    ]);
});

it('extracts package versions with missing packages key', function () {
    $lockData = [
        'packages-dev' => [
            [
                'name' => 'phpunit/phpunit',
                'version' => '9.5.0',
            ],
        ],
    ];

    $result = $this->parser->extractPackageVersions($lockData);

    expect($result)->toBe([
        'phpunit/phpunit' => '9.5.0',
    ]);
});

it('extracts package versions with empty data', function () {
    $result = $this->parser->extractPackageVersions([]);

    expect($result)->toBe([]);
});

it('gets repository url from source', function () {
    $lockData = [
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
                'source' => [
                    'url' => 'https://github.com/symfony/console.git',
                ],
                'dist' => [
                    'url' => 'https://api.github.com/repos/symfony/console/zipball/abc123',
                ],
            ],
        ],
    ];

    $result = $this->parser->getRepositoryUrl('symfony/console', $lockData);

    expect($result)->toBe('https://github.com/symfony/console.git');
});

it('gets repository url from dist when source not available', function () {
    $lockData = [
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
                'dist' => [
                    'url' => 'https://api.github.com/repos/symfony/console/zipball/abc123',
                ],
            ],
        ],
    ];

    $result = $this->parser->getRepositoryUrl('symfony/console', $lockData);

    expect($result)->toBe('https://api.github.com/repos/symfony/console/zipball/abc123');
});

it('returns null for non-existent package', function () {
    $lockData = [
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
            ],
        ],
    ];

    $result = $this->parser->getRepositoryUrl('non/existent', $lockData);

    expect($result)->toBeNull();
});

it('returns null when package has no repository urls', function () {
    $lockData = [
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
            ],
        ],
    ];

    $result = $this->parser->getRepositoryUrl('symfony/console', $lockData);

    expect($result)->toBeNull();
});

it('searches in packages-dev for repository url', function () {
    $lockData = [
        'packages' => [],
        'packages-dev' => [
            [
                'name' => 'phpunit/phpunit',
                'version' => '9.5.0',
                'source' => [
                    'url' => 'https://github.com/sebastianbergmann/phpunit.git',
                ],
            ],
        ],
    ];

    $result = $this->parser->getRepositoryUrl('phpunit/phpunit', $lockData);

    expect($result)->toBe('https://github.com/sebastianbergmann/phpunit.git');
});
