<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\Parsers\PackageLockParser;

beforeEach(function () {
    $this->parser = new PackageLockParser();
});

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

    $result = $this->parser->parse($lockContent);

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
    $result = $this->parser->parse('invalid json');

    expect($result)->toBeEmpty();
});

it('parses package lock with empty content', function () {
    $result = $this->parser->parse('{}');

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

    $result = $this->parser->parse($lockContent);

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

    $result = $this->parser->parse($lockContent);

    expect($result)->toHaveCount(1);
    expect($result->has('lodash'))->toBeTrue();
    expect($result->has('broken'))->toBeFalse();
});

it('extracts package versions from parsed data', function () {
    $lockData = [
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
    ];

    $result = $this->parser->extractPackageVersions($lockData);

    expect($result)->toBe([
        'lodash' => '4.17.21',
        'axios' => '1.5.0',
    ]);
});

it('extracts package versions with missing packages key', function () {
    $lockData = [];

    $result = $this->parser->extractPackageVersions($lockData);

    expect($result)->toBe([]);
});

it('extracts package versions filtering empty names', function () {
    $lockData = [
        'packages' => [
            '' => [
                'version' => '1.0.0',
            ],
            'node_modules/lodash' => [
                'version' => '4.17.21',
            ],
        ],
    ];

    $result = $this->parser->extractPackageVersions($lockData);

    expect($result)->toBe([
        'lodash' => '4.17.21',
    ]);
});

it('gets repository url with node_modules prefix', function () {
    $lockData = [
        'packages' => [
            'node_modules/lodash' => [
                'version' => '4.17.21',
                'resolved' => 'https://registry.npmjs.org/lodash/-/lodash-4.17.21.tgz',
            ],
        ],
    ];

    $result = $this->parser->getRepositoryUrl('lodash', $lockData);

    expect($result)->toBe('https://registry.npmjs.org/lodash/-/lodash-4.17.21.tgz');
});

it('gets repository url with exact match', function () {
    $lockData = [
        'packages' => [
            'lodash' => [
                'version' => '4.17.21',
                'resolved' => 'https://registry.npmjs.org/lodash/-/lodash-4.17.21.tgz',
            ],
        ],
    ];

    $result = $this->parser->getRepositoryUrl('lodash', $lockData);

    expect($result)->toBe('https://registry.npmjs.org/lodash/-/lodash-4.17.21.tgz');
});

it('returns null for non-existent package', function () {
    $lockData = [
        'packages' => [
            'node_modules/lodash' => [
                'version' => '4.17.21',
            ],
        ],
    ];

    $result = $this->parser->getRepositoryUrl('non-existent', $lockData);

    expect($result)->toBeNull();
});

it('returns null when package has no resolved url', function () {
    $lockData = [
        'packages' => [
            'node_modules/lodash' => [
                'version' => '4.17.21',
            ],
        ],
    ];

    $result = $this->parser->getRepositoryUrl('lodash', $lockData);

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

    $result = $this->parser->parse($lockContent);

    expect($result)->toHaveCount(1);
    expect($result->get('@angular/core'))->toBe([
        'version' => '16.0.0',
        'repository' => 'https://registry.npmjs.org/@angular/core/-/core-16.0.0.tgz',
    ]);
});
