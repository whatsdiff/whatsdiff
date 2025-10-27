<?php

declare(strict_types=1);

use Tests\Helpers\McpServerHelper;

beforeEach(function () {
    $this->mcp = new McpServerHelper();
    $this->mcp->initialize();
})->skipOnWindows();

afterEach(function () {
    $this->mcp->stop();
});

it('can find compatible versions for a composer package', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'phpunit/phpunit',
        'dependency_package' => 'php',
        'dependency_constraint' => '^8.2',
        'package_manager' => 'composer',
    ]);

    expect($response)
        ->toHaveKey('jsonrpc', '2.0')
        ->toHaveKey('result');

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'phpunit/phpunit')
        ->toHaveKey('dependency_package', 'php')
        ->toHaveKey('dependency_constraint', '^8.2')
        ->toHaveKey('package_manager', 'composer')
        ->toHaveKey('compatible_versions')
        ->toHaveKey('count');

    expect($data['compatible_versions'])->toBeArray();
    expect($data['count'])->toBeGreaterThan(0);
})->group('integration');

it('can find compatible versions for an npm package', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => '@types/node',
        'dependency_package' => 'typescript',
        'dependency_constraint' => '^5.0.0',
        'package_manager' => 'npm',
    ]);

    expect($response)
        ->toHaveKey('jsonrpc', '2.0')
        ->toHaveKey('result');

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', '@types/node')
        ->toHaveKey('dependency_package', 'typescript')
        ->toHaveKey('package_manager', 'npm')
        ->toHaveKey('compatible_versions');
})->group('integration');

it('returns error for invalid package manager', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'some/package',
        'dependency_package' => 'php',
        'dependency_constraint' => '^8.0',
        'package_manager' => 'invalid',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('Invalid package manager');
});

it('returns error for invalid version constraint', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'symfony/console',
        'dependency_package' => 'php',
        'dependency_constraint' => 'not-a-valid-constraint',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('Invalid version constraint');
})->group('integration');

it('returns error when package is not found', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'nonexistent/package-that-does-not-exist-12345',
        'dependency_package' => 'php',
        'dependency_constraint' => '^8.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('Failed to fetch');
})->group('integration');

it('returns empty compatible versions when no matches found', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'symfony/console',
        'dependency_package' => 'nonexistent/dependency',
        'dependency_constraint' => '^1.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'symfony/console')
        ->toHaveKey('compatible_versions', [])
        ->toHaveKey('count', 0);
})->group('integration');

it('includes example version and requires information for each compatible version', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'laravel/framework',
        'dependency_package' => 'php',
        'dependency_constraint' => '^8.2',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    if ($data['count'] > 0) {
        $firstCompatibleVersion = $data['compatible_versions'][0];

        expect($firstCompatibleVersion)
            ->toHaveKey('major_version')
            ->toHaveKey('example_version')
            ->toHaveKey('requires');

        expect($firstCompatibleVersion['major_version'])->toBeInt();
        expect($firstCompatibleVersion['example_version'])->toBeString();
        expect($firstCompatibleVersion['requires'])->toBeString();
    }
})->group('integration');

it('uses default package manager when not specified', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'symfony/console',
        'dependency_package' => 'php',
        'dependency_constraint' => '^8.2',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    // Should default to 'composer' and work successfully
    expect($data)
        ->toHaveKey('package', 'symfony/console')
        ->not->toHaveKey('error');
})->group('integration');

it('sorts compatible versions by major version', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'symfony/console',
        'dependency_package' => 'php',
        'dependency_constraint' => '^8.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    if ($data['count'] > 1) {
        $majorVersions = array_column($data['compatible_versions'], 'major_version');
        $sortedMajorVersions = $majorVersions;
        sort($sortedMajorVersions);

        expect($majorVersions)->toBe($sortedMajorVersions);
    }
})->group('integration');

it('finds livewire versions compatible with illuminate/support', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'livewire/livewire',
        'dependency_package' => 'illuminate/support',
        'dependency_constraint' => '^11.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'livewire/livewire')
        ->toHaveKey('dependency_package', 'illuminate/support')
        ->toHaveKey('dependency_constraint', '^11.0')
        ->toHaveKey('compatible_versions')
        ->toHaveKey('count');

    expect($data['count'])->toBeGreaterThan(0);

    // Livewire v3 should be compatible with illuminate/support ^11.0
    $majorVersions = array_column($data['compatible_versions'], 'major_version');
    expect($majorVersions)->toContain(3);
})->group('integration');

it('finds illuminate/support versions compatible with PHP', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'illuminate/support',
        'dependency_package' => 'php',
        'dependency_constraint' => '^8.2',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'illuminate/support')
        ->toHaveKey('dependency_package', 'php')
        ->toHaveKey('dependency_constraint', '^8.2')
        ->toHaveKey('compatible_versions')
        ->toHaveKey('count');

    expect($data['count'])->toBeGreaterThan(0);

    // illuminate/support v10, v11, v12 should all work with PHP 8.2+
    $majorVersions = array_column($data['compatible_versions'], 'major_version');
    expect($majorVersions)->toContain(11);
    expect($majorVersions)->toContain(12);
})->group('integration');

it('finds orchestra/testbench versions compatible with laravel/framework', function () {
    $response = $this->mcp->callTool('find_compatible_versions', [
        'package' => 'orchestra/testbench',
        'dependency_package' => 'laravel/framework',
        'dependency_constraint' => '^11.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'orchestra/testbench')
        ->toHaveKey('dependency_package', 'laravel/framework')
        ->toHaveKey('dependency_constraint', '^11.0')
        ->toHaveKey('compatible_versions')
        ->toHaveKey('count');

    expect($data['count'])->toBeGreaterThan(0);

    // orchestra/testbench v9 should be compatible with Laravel 11
    $majorVersions = array_column($data['compatible_versions'], 'major_version');
    expect($majorVersions)->toContain(9);
})->group('integration');
