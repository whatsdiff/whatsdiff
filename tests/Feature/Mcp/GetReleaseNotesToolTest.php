<?php

declare(strict_types=1);

use Tests\Helpers\McpServerHelper;

beforeEach(function () {
    $this->mcp = new McpServerHelper();
    $this->mcp->initialize();
});

afterEach(function () {
    $this->mcp->stop();
});

it('can fetch release notes for a composer package', function () {
    $response = $this->mcp->callTool('get_release_notes', [
        'package'         => 'symfony/console',
        'from_version'    => '7.0.0',
        'to_version'      => '7.0.3',
        'package_manager' => 'composer',
    ]);

    expect($response)
        ->toHaveKey('jsonrpc', '2.0')
        ->toHaveKey('result');

    $result = $response['result'];

    expect($result)
        ->toHaveKey('content')
        ->and($result['content'])->toBeArray();

    $content = $result['content'][0];

    expect($content)
        ->toHaveKey('type', 'text')
        ->toHaveKey('text');

    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'symfony/console')
        ->toHaveKey('repository')
        ->toHaveKey('from_version', '7.0.0')
        ->toHaveKey('to_version', '7.0.3')
        ->toHaveKey('releases')
        ->toHaveKey('count');

    // Should have releases between 7.0.0 and 7.0.3
    expect($data['count'])->toBeGreaterThan(0);
})->group('mcp')->skipOnWindows();

it('can fetch release notes for an npm package', function () {
    $response = $this->mcp->callTool('get_release_notes', [
        'package'         => 'react',
        'from_version'    => '18.2.0',
        'to_version'      => '18.3.0',
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
        ->toHaveKey('repository')
        ->toHaveKey('from_version', '18.2.0')
        ->toHaveKey('to_version', '18.3.0')
        ->toHaveKey('releases');
})->group('mcp')->skipOnWindows();

it('returns error for invalid package manager', function () {
    $response = $this->mcp->callTool('get_release_notes', [
        'package'         => 'some/package',
        'from_version'    => '1.0.0',
        'to_version'      => '2.0.0',
        'package_manager' => 'invalid',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('Invalid package manager');
});

it('returns error when package repository is not found', function () {
    $response = $this->mcp->callTool('get_release_notes', [
        'package'         => 'nonexistent/package-that-does-not-exist-12345',
        'from_version'    => '1.0.0',
        'to_version'      => '2.0.0',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('error')
        ->and($data['error'])->toContain('repository');
})->group('mcp')->skipOnWindows();

it('returns empty releases when no releases found in range', function () {
    $response = $this->mcp->callTool('get_release_notes', [
        'package'         => 'symfony/console',
        'from_version'    => '99.0.0',
        'to_version'      => '99.9.9',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    expect($data)
        ->toHaveKey('package', 'symfony/console')
        ->toHaveKey('releases', [])
        ->toHaveKey('count', 0)
        ->toHaveKey('message');
})->group('mcp')->skipOnWindows();

it('includes release details when releases are found', function () {
    $response = $this->mcp->callTool('get_release_notes', [
        'package'         => 'laravel/framework',
        'from_version'    => '11.0.0',
        'to_version'      => '11.0.8',
        'package_manager' => 'composer',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    if ($data['count'] > 0) {
        $firstRelease = $data['releases'][0];

        expect($firstRelease)
            ->toHaveKey('version')
            ->toHaveKey('title')
            ->toHaveKey('body')
            ->toHaveKey('date')
            ->toHaveKey('url');
    }
})->group('mcp')->skipOnWindows();

it('uses default package manager when not specified', function () {
    $response = $this->mcp->callTool('get_release_notes', [
        'package'      => 'symfony/console',
        'from_version' => '7.0.0',
        'to_version'   => '7.0.1',
    ]);

    $result = $response['result'];
    $content = $result['content'][0];
    $data = json_decode($content['text'], true);

    // Should default to 'composer' and work successfully
    expect($data)
        ->toHaveKey('package', 'symfony/console')
        ->not->toHaveKey('error');
})->group('mcp')->skipOnWindows();
