<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\LockFile\ComposerLockFile;

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

    $parser = new ComposerLockFile($lockContent);
    $result = $parser->getPackages();

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
    $parser = new ComposerLockFile('invalid json');
    $result = $parser->getPackages();

    expect($result)->toBeEmpty();
});

it('parses composer lock with empty content', function () {
    $parser = new ComposerLockFile('{}');
    $result = $parser->getPackages();

    expect($result)->toBeEmpty();
});

it('gets all package versions', function () {
    $lockContent = json_encode([
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
    ]);

    $parser = new ComposerLockFile($lockContent);
    $result = $parser->getAllVersions();

    expect($result)->toBe([
        'symfony/console' => 'v5.4.0',
        'illuminate/collections' => 'v9.0.0',
        'phpunit/phpunit' => '9.5.0',
    ]);
});

it('gets version for specific package', function () {
    $lockContent = json_encode([
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
            ],
        ],
    ]);

    $parser = new ComposerLockFile($lockContent);

    expect($parser->getVersion('symfony/console'))->toBe('v5.4.0');
    expect($parser->getVersion('non/existent'))->toBeNull();
});

it('gets repository url from source', function () {
    $lockContent = json_encode([
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
    ]);

    $parser = new ComposerLockFile($lockContent);
    $result = $parser->getRepositoryUrl('symfony/console');

    expect($result)->toBe('https://github.com/symfony/console.git');
});

it('gets repository url from dist when source not available', function () {
    $lockContent = json_encode([
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
                'dist' => [
                    'url' => 'https://api.github.com/repos/symfony/console/zipball/abc123',
                ],
            ],
        ],
    ]);

    $parser = new ComposerLockFile($lockContent);
    $result = $parser->getRepositoryUrl('symfony/console');

    expect($result)->toBe('https://api.github.com/repos/symfony/console/zipball/abc123');
});

it('returns null for non-existent package repository', function () {
    $lockContent = json_encode([
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
            ],
        ],
    ]);

    $parser = new ComposerLockFile($lockContent);
    $result = $parser->getRepositoryUrl('non/existent');

    expect($result)->toBeNull();
});

it('returns null when package has no repository urls', function () {
    $lockContent = json_encode([
        'packages' => [
            [
                'name' => 'symfony/console',
                'version' => 'v5.4.0',
            ],
        ],
    ]);

    $parser = new ComposerLockFile($lockContent);
    $result = $parser->getRepositoryUrl('symfony/console');

    expect($result)->toBeNull();
});

it('searches in packages-dev for repository url', function () {
    $lockContent = json_encode([
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
    ]);

    $parser = new ComposerLockFile($lockContent);
    $result = $parser->getRepositoryUrl('phpunit/phpunit');

    expect($result)->toBe('https://github.com/sebastianbergmann/phpunit.git');
});
