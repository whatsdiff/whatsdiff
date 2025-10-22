<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\LockFile\NpmPackageLockFile;

it('parses valid package lock content', function () {
    $lockContent = json_encode([
        'packages' => [
            '' => [
                'name' => 'my-project',
                'version' => '1.0.0',
            ],
            'node_modules/lodash' => [
                'version' => '4.17.21',
                'resolved' => 'https://registry.npmjs.org/lodash/-/lodash-4.17.21.tgz',
            ],
            'node_modules/axios' => [
                'version' => '1.5.0',
                'resolved' => 'https://registry.npmjs.org/axios/-/axios-1.5.0.tgz',
            ],
        ],
    ]);

    $parser = new NpmPackageLockFile($lockContent);
    $result = $parser->getPackages();

    expect($result)->toHaveCount(2);
    expect($result->get('lodash'))->toBe([
        'version' => '4.17.21',
        'repository' => 'https://registry.npmjs.org/lodash/-/lodash-4.17.21.tgz',
    ]);
    expect($result->get('axios'))->toBe([
        'version' => '1.5.0',
        'repository' => 'https://registry.npmjs.org/axios/-/axios-1.5.0.tgz',
    ]);
});

it('parses package lock with invalid json', function () {
    $parser = new NpmPackageLockFile('invalid json');
    $result = $parser->getPackages();

    expect($result)->toBeEmpty();
});

it('parses package lock with empty content', function () {
    $parser = new NpmPackageLockFile('{}');
    $result = $parser->getPackages();

    expect($result)->toBeEmpty();
});

it('filters out empty package keys', function () {
    $lockContent = json_encode([
        'packages' => [
            '' => [
                'name' => 'my-project',
                'version' => '1.0.0',
            ],
            'node_modules/lodash' => [
                'version' => '4.17.21',
            ],
        ],
    ]);

    $parser = new NpmPackageLockFile($lockContent);
    $result = $parser->getPackages();

    expect($result)->toHaveCount(1);
    expect($result->has('lodash'))->toBeTrue();
});

it('filters out packages without version', function () {
    $lockContent = json_encode([
        'packages' => [
            'node_modules/lodash' => [
                'version' => '4.17.21',
            ],
            'node_modules/broken' => [
                // No version
            ],
        ],
    ]);

    $parser = new NpmPackageLockFile($lockContent);
    $result = $parser->getPackages();

    expect($result)->toHaveCount(1);
    expect($result->has('lodash'))->toBeTrue();
    expect($result->has('broken'))->toBeFalse();
});

it('gets all package versions', function () {
    $lockContent = json_encode([
        'packages' => [
            '' => [
                'name' => 'my-project',
                'version' => '1.0.0',
            ],
            'node_modules/lodash' => [
                'version' => '4.17.21',
            ],
            'node_modules/axios' => [
                'version' => '1.5.0',
            ],
        ],
    ]);

    $parser = new NpmPackageLockFile($lockContent);
    $result = $parser->getAllVersions();

    expect($result)->toBe([
        'lodash' => '4.17.21',
        'axios' => '1.5.0',
    ]);
});

it('gets version for specific package', function () {
    $lockContent = json_encode([
        'packages' => [
            'node_modules/lodash' => [
                'version' => '4.17.21',
            ],
        ],
    ]);

    $parser = new NpmPackageLockFile($lockContent);

    expect($parser->getVersion('lodash'))->toBe('4.17.21');
    expect($parser->getVersion('non-existent'))->toBeNull();
});

it('gets repository url', function () {
    $lockContent = json_encode([
        'packages' => [
            'node_modules/lodash' => [
                'version' => '4.17.21',
                'resolved' => 'https://registry.npmjs.org/lodash/-/lodash-4.17.21.tgz',
            ],
        ],
    ]);

    $parser = new NpmPackageLockFile($lockContent);
    $result = $parser->getRepositoryUrl('lodash');

    expect($result)->toBe('https://registry.npmjs.org/lodash/-/lodash-4.17.21.tgz');
});

it('returns null for non-existent package', function () {
    $lockContent = json_encode([
        'packages' => [
            'node_modules/lodash' => [
                'version' => '4.17.21',
            ],
        ],
    ]);

    $parser = new NpmPackageLockFile($lockContent);
    $result = $parser->getRepositoryUrl('non-existent');

    expect($result)->toBeNull();
});

it('returns null when package has no resolved url', function () {
    $lockContent = json_encode([
        'packages' => [
            'node_modules/lodash' => [
                'version' => '4.17.21',
            ],
        ],
    ]);

    $parser = new NpmPackageLockFile($lockContent);
    $result = $parser->getRepositoryUrl('lodash');

    expect($result)->toBeNull();
});

it('handles scoped packages correctly', function () {
    $lockContent = json_encode([
        'packages' => [
            'node_modules/@angular/core' => [
                'version' => '16.0.0',
                'resolved' => 'https://registry.npmjs.org/@angular/core/-/core-16.0.0.tgz',
            ],
        ],
    ]);

    $parser = new NpmPackageLockFile($lockContent);
    $result = $parser->getPackages();

    expect($result)->toHaveCount(1);
    expect($result->get('@angular/core'))->toBe([
        'version' => '16.0.0',
        'repository' => 'https://registry.npmjs.org/@angular/core/-/core-16.0.0.tgz',
    ]);
});
