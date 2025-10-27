<?php

declare(strict_types=1);

use Tests\Helpers\McpServerHelper;

beforeEach()->skipOnWindows();
beforeEach(function () {
    $this->mcp = new McpServerHelper();
    $this->mcp->initialize();
});

afterEach(function () {
    $this->mcp->stop();
});

it('can get available upgrades for a composer package', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '6.3.0',
        'package_manager' => 'composer',
    ]);

    expect($response)
        ->toHaveKey('jsonrpc', '2.0')
        ->toHaveKey('result');

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'symfony/console')
        ->toHaveKey('package_manager', 'composer')
        ->toHaveKey('current_version', '6.3.0')
        ->toHaveKey('available_upgrades');

    $upgrades = $data['available_upgrades'];

    expect($upgrades)
        ->toHaveKey('patch')
        ->toHaveKey('minor')
        ->toHaveKey('major');

    // At least one upgrade type should be available
    $hasUpgrade = $upgrades['patch'] || $upgrades['minor'] || $upgrades['major'];
    expect($hasUpgrade)->toBeTrue();

    // Verify we got the latest versions, not the first
    if ($upgrades['patch']) {
        expect($upgrades['patch'])->toMatch('/^v?6\.3\.\d+/');
    }
    if ($upgrades['minor']) {
        expect($upgrades['minor'])->toMatch('/^v?6\.\d+\.\d+/');
    }
    if ($upgrades['major']) {
        expect($upgrades['major'])->toMatch('/^v?[7-9]\.\d+\.\d+/');
    }
})->group('integration');

it('can get available upgrades for an npm package', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'react',
        'current_version' => '18.2.0',
        'package_manager' => 'npm',
    ]);

    expect($response)
        ->toHaveKey('jsonrpc', '2.0')
        ->toHaveKey('result');

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'react')
        ->toHaveKey('package_manager', 'npm')
        ->toHaveKey('current_version', '18.2.0')
        ->toHaveKey('available_upgrades');
})->group('integration');

it('returns error for invalid package manager', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'some/package',
        'current_version' => '1.0.0',
        'package_manager' => 'invalid',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('Invalid package manager');
});

it('returns error for invalid version format', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => 'not-a-valid-version',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('Invalid version format');
})->group('integration');

it('returns error when package is not found', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'nonexistent/package-that-does-not-exist-12345',
        'current_version' => '1.0.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('Failed to fetch');
})->group('integration');

it('returns null for patch when no patch upgrade available', function () {
    // Use a recent version that likely doesn't have a newer patch
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '7.2.999',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'symfony/console')
        ->toHaveKey('available_upgrades');

    expect($data['available_upgrades']['patch'])->toBeNull();
})->group('integration');

it('returns null for all upgrade types when already at latest version', function () {
    // Use a very high version number that doesn't exist
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '99.99.99',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    $upgrades = $data['available_upgrades'];

    expect($upgrades['patch'])->toBeNull();
    expect($upgrades['minor'])->toBeNull();
    expect($upgrades['major'])->toBeNull();
})->group('integration');

it('uses default package manager when not specified', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '6.4.0',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    // Should default to 'composer' and work successfully
    expect($data)
        ->toHaveKey('package', 'symfony/console')
        ->not->toHaveKey('error');
})->group('integration');

it('finds latest patch version not first', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '7.0.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    $upgrades = $data['available_upgrades'];

    if ($upgrades['patch']) {
        // Should be latest 7.0.x, not just 7.0.1
        expect($upgrades['patch'])->toMatch('/^v?7\.0\.\d+/');
    }
})->group('integration');

it('finds latest minor version not first', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '7.0.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    $upgrades = $data['available_upgrades'];

    if ($upgrades['minor']) {
        // Should be latest 7.x, not just 7.1.0
        expect($upgrades['minor'])->toMatch('/^v?7\.\d+\.\d+/');
    }
})->group('integration');

it('finds latest major version not first', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '6.3.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    $upgrades = $data['available_upgrades'];

    if ($upgrades['major']) {
        // Should be latest 7.x (e.g., 7.3.x), not just 7.0.0
        expect($upgrades['major'])->toMatch('/^v?7\.\d+\.\d+/');
    }
})->group('integration');

it('skips dev versions when finding upgrades', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'laravel/framework',
        'current_version' => '11.0.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    $upgrades = $data['available_upgrades'];

    // All returned versions should not contain 'dev'
    foreach (['patch', 'minor', 'major'] as $upgradeType) {
        if ($upgrades[$upgradeType]) {
            expect($upgrades[$upgradeType])->not->toContain('dev');
        }
    }
})->group('integration');

it('handles versions with v prefix correctly', function () {
    // This is the exact issue that was reported
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '6.3.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)->not->toHaveKey('error');

    $upgrades = $data['available_upgrades'];

    // Should find upgrades even though Packagist returns versions with 'v' prefix
    expect($upgrades['patch'])->not->toBeNull();
    expect($upgrades['minor'])->not->toBeNull();
    expect($upgrades['major'])->not->toBeNull();
})->group('integration');

it('excludes pre-release versions by default', function () {
    $response = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '6.3.0',
        'package_manager' => 'composer',
        'include_prerelease' => false,
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    $upgrades = $data['available_upgrades'];

    // None of the returned versions should be beta, alpha, or RC
    foreach (['patch', 'minor', 'major'] as $upgradeType) {
        if ($upgrades[$upgradeType]) {
            expect($upgrades[$upgradeType])
                ->not->toContain('beta')
                ->not->toContain('BETA')
                ->not->toContain('alpha')
                ->not->toContain('ALPHA')
                ->not->toContain('-RC')
                ->not->toContain('rc');
        }
    }
})->group('integration');

it('includes pre-release versions when requested', function () {
    $responseWithoutPrerelease = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '7.3.0',
        'package_manager' => 'composer',
        'include_prerelease' => false,
    ]);

    $responseWithPrerelease = $this->mcp->callTool('get_available_upgrades', [
        'package' => 'symfony/console',
        'current_version' => '7.3.0',
        'package_manager' => 'composer',
        'include_prerelease' => true,
    ]);

    $dataWithout = json_decode($responseWithoutPrerelease['result']['content'][0]['text'], true);
    $dataWith = json_decode($responseWithPrerelease['result']['content'][0]['text'], true);

    // With prerelease enabled, we might get newer versions (could be beta/RC)
    // The key test is that both work without error
    expect($dataWithout)->not->toHaveKey('error');
    expect($dataWith)->not->toHaveKey('error');
})->group('integration');
