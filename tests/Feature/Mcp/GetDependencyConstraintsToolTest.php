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

it('can get dependency constraints for a composer package version', function () {
    $response = $this->mcp->callTool('get_dependency_constraints', [
        'package' => 'livewire/livewire',
        'version' => 'v3.0.0',
        'package_manager' => 'composer',
    ]);

    expect($response)
        ->toHaveKey('jsonrpc', '2.0')
        ->toHaveKey('result');

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'livewire/livewire')
        ->toHaveKey('version')
        ->toHaveKey('package_manager', 'composer')
        ->toHaveKey('dependencies');

    expect($data['dependencies'])
        ->toHaveKey('require')
        ->toHaveKey('require-dev');

    // Livewire should require illuminate packages
    expect($data['dependencies']['require'])->toBeArray();
})->group('integration');

it('can get dependency constraints for an npm package version', function () {
    $response = $this->mcp->callTool('get_dependency_constraints', [
        'package' => 'react',
        'version' => '18.2.0',
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
        ->toHaveKey('version', '18.2.0')
        ->toHaveKey('package_manager', 'npm')
        ->toHaveKey('dependencies');

    expect($data['dependencies'])
        ->toHaveKey('dependencies')
        ->toHaveKey('devDependencies')
        ->toHaveKey('peerDependencies');
})->group('integration');

it('returns error for invalid package manager', function () {
    $response = $this->mcp->callTool('get_dependency_constraints', [
        'package' => 'some/package',
        'version' => '1.0.0',
        'package_manager' => 'invalid',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('Invalid package manager');
});

it('returns error when package is not found', function () {
    $response = $this->mcp->callTool('get_dependency_constraints', [
        'package' => 'nonexistent/package-that-does-not-exist-12345',
        'version' => '1.0.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('Failed to fetch');
})->group('integration');

it('returns error when version is not found', function () {
    $response = $this->mcp->callTool('get_dependency_constraints', [
        'package' => 'symfony/console',
        'version' => '999.999.999',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('Version')
        ->and($data['error'])->toContain('not found');
})->group('integration');

it('uses default package manager when not specified', function () {
    $response = $this->mcp->callTool('get_dependency_constraints', [
        'package' => 'symfony/console',
        'version' => '6.4.0',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    // Should default to 'composer' and work successfully
    expect($data)
        ->toHaveKey('package', 'symfony/console')
        ->not->toHaveKey('error');
})->group('integration');

it('handles version with or without v prefix', function () {
    $responseWithV = $this->mcp->callTool('get_dependency_constraints', [
        'package' => 'symfony/console',
        'version' => 'v6.4.0',
        'package_manager' => 'composer',
    ]);

    $responseWithoutV = $this->mcp->callTool('get_dependency_constraints', [
        'package' => 'symfony/console',
        'version' => '6.4.0',
        'package_manager' => 'composer',
    ]);

    $dataWithV = json_decode($responseWithV['result']['content'][0]['text'], true);
    $dataWithoutV = json_decode($responseWithoutV['result']['content'][0]['text'], true);

    // Both should work and return the same dependencies
    expect($dataWithV)->not->toHaveKey('error');
    expect($dataWithoutV)->not->toHaveKey('error');
    expect($dataWithV['dependencies'])->toBe($dataWithoutV['dependencies']);
})->group('integration');

it('returns proper dependency structure for packages', function () {
    $response = $this->mcp->callTool('get_dependency_constraints', [
        'package' => 'symfony/console',
        'version' => 'v6.4.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'symfony/console')
        ->toHaveKey('version')
        ->toHaveKey('dependencies')
        ->not->toHaveKey('error');

    // Should have require and require-dev keys
    expect($data['dependencies'])->toHaveKeys(['require', 'require-dev']);

    // symfony/console should have some requirements
    expect($data['dependencies']['require'])->toBeArray();
})->group('integration');
