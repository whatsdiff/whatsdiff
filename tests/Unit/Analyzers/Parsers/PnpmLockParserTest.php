<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\LockFile\PnpmLockFile;

it('parses valid pnpm-lock.yaml v9 content', function () {
    $lockContent = generatePnpmLock(['lodash' => '4.17.21', 'axios' => '1.5.0']);

    $parser = new PnpmLockFile($lockContent);
    $result = $parser->getPackages();

    expect($result)->toHaveCount(2);
    expect($result->get('lodash'))->toBe(['version' => '4.17.21']);
    expect($result->get('axios'))->toBe(['version' => '1.5.0']);
});

it('handles scoped packages in v9 format', function () {
    $lockContent = generatePnpmLock(['@angular/core' => '14.0.0']);

    $parser = new PnpmLockFile($lockContent);
    $result = $parser->getPackages();

    expect($result)->toHaveCount(1);
    expect($result->get('@angular/core'))->toBe(['version' => '14.0.0']);
});

it('strips peer-dep suffixes from package keys', function () {
    $lockContent = "lockfileVersion: '9.0'\n\npackages:\n\n  'react-dom@18.2.0(react@18.2.0)':\n    resolution: {integrity: sha512-abc}\n";

    $parser = new PnpmLockFile($lockContent);
    $result = $parser->getPackages();

    expect($result)->toHaveCount(1);
    expect($result->get('react-dom'))->toBe(['version' => '18.2.0']);
});

it('deduplicates packages with different peer-dep variants', function () {
    $lockContent = "lockfileVersion: '9.0'\n\npackages:\n\n  'react-dom@18.2.0(react@18.2.0)':\n    resolution: {integrity: sha512-abc}\n  'react-dom@18.2.0(react@17.0.0)':\n    resolution: {integrity: sha512-def}\n";

    $parser = new PnpmLockFile($lockContent);
    $result = $parser->getPackages();

    expect($result)->toHaveCount(1);
    expect($result->get('react-dom'))->toBe(['version' => '18.2.0']);
});

it('returns lockfile version as float for quoted string version', function () {
    $lockContent = generatePnpmLock(['lodash' => '4.17.21']);

    $parser = new PnpmLockFile($lockContent);

    expect($parser->getLockfileVersion())->toBe(9.0);
});

it('returns lockfile version as float for unquoted numeric version', function () {
    $lockContent = "lockfileVersion: 5.4\n\npackages:\n\n  /lodash/4.17.21:\n    resolution: {integrity: sha512-abc}\n";

    $parser = new PnpmLockFile($lockContent);

    expect($parser->getLockfileVersion())->toBe(5.4);
});

it('returns null lockfile version when absent', function () {
    $parser = new PnpmLockFile('{}');

    expect($parser->getLockfileVersion())->toBeNull();
});

it('filters out package keys without a version specifier', function () {
    $lockContent = <<<'YAML'
lockfileVersion: '9.0'

packages:

  lodash@4.17.21:
    resolution: {integrity: sha512-abc}
  broken-no-version:
    resolution: {integrity: sha512-def}
YAML;

    $parser = new PnpmLockFile($lockContent);
    $result = $parser->getPackages();

    expect($result)->toHaveCount(1);
    expect($result->has('lodash'))->toBeTrue();
    expect($result->has('broken-no-version'))->toBeFalse();
});

it('returns empty collection for invalid yaml', function () {
    $parser = new PnpmLockFile(":\t:\ninvalid\t\tyaml: [unclosed");
    $result = $parser->getPackages();

    expect($result)->toBeEmpty();
});

it('returns empty collection for empty content', function () {
    $parser = new PnpmLockFile('');
    $result = $parser->getPackages();

    expect($result)->toBeEmpty();
});

it('returns empty collection when packages section is absent', function () {
    $lockContent = "lockfileVersion: '9.0'\n\nsettings:\n  autoInstallPeers: true\n";

    $parser = new PnpmLockFile($lockContent);
    $result = $parser->getPackages();

    expect($result)->toBeEmpty();
});

it('gets all package versions as array', function () {
    $lockContent = generatePnpmLock(['lodash' => '4.17.21', 'axios' => '1.5.0']);

    $parser = new PnpmLockFile($lockContent);
    $result = $parser->getAllVersions();

    expect($result)->toBe([
        'lodash' => '4.17.21',
        'axios' => '1.5.0',
    ]);
});

it('gets version for specific package', function () {
    $lockContent = generatePnpmLock(['lodash' => '4.17.21']);

    $parser = new PnpmLockFile($lockContent);

    expect($parser->getVersion('lodash'))->toBe('4.17.21');
    expect($parser->getVersion('non-existent'))->toBeNull();
});

it('returns null for repository url', function () {
    $lockContent = generatePnpmLock(['lodash' => '4.17.21']);

    $parser = new PnpmLockFile($lockContent);

    expect($parser->getRepositoryUrl('lodash'))->toBeNull();
    expect($parser->getRepositoryUrl('non-existent'))->toBeNull();
});

it('ignores snapshots section and only parses packages section', function () {
    $lockContent = <<<'YAML'
lockfileVersion: '9.0'

packages:

  lodash@4.17.21:
    resolution: {integrity: sha512-abc}

snapshots:

  lodash@4.17.21: {}
  react@18.2.0: {}
YAML;

    $parser = new PnpmLockFile($lockContent);
    $result = $parser->getAllVersions();

    expect($result)->toBe(['lodash' => '4.17.21']);
    expect($result)->not->toHaveKey('react');
});
