<?php

declare(strict_types=1);

use Tests\Helpers\McpServerHelper;

beforeEach()->skipOnWindows();
beforeEach(function () {
    $this->mcp = new McpServerHelper();
});

afterEach(function () {
    $this->mcp->stop();
});

it('can initialize the MCP server', function () {
    $response = $this->mcp->initialize();

    expect($response)
        ->toHaveKey('jsonrpc', '2.0')
        ->toHaveKey('id')
        ->toHaveKey('result');

    expect($response['result'])
        ->toHaveKey('protocolVersion')
        ->toHaveKey('capabilities')
        ->toHaveKey('serverInfo');

    expect($response['result']['serverInfo'])
        ->toHaveKey('name', 'whatsdiff')
        ->toHaveKey('version');
});

it('can list all available tools', function () {
    $this->mcp->initialize();

    $response = $this->mcp->listTools();

    expect($response)
        ->toHaveKey('jsonrpc', '2.0')
        ->toHaveKey('result');

    expect($response['result'])
        ->toHaveKey('tools')
        ->and($response['result']['tools'])->toBeArray();

    $tools = $response['result']['tools'];

    expect($tools)->toHaveCount(4);
});

it('lists find_compatible_versions tool with correct schema', function () {
    $this->mcp->initialize();

    $response = $this->mcp->listTools();
    $tools = $response['result']['tools'];

    $findCompatibleTool = collect($tools)->firstWhere('name', 'find_compatible_versions');

    expect($findCompatibleTool)
        ->not->toBeNull()
        ->toHaveKey('name', 'find_compatible_versions')
        ->toHaveKey('description')
        ->toHaveKey('inputSchema');

    expect($findCompatibleTool['description'])
        ->toContain('compatible')
        ->toContain('constraint');

    expect($findCompatibleTool['inputSchema'])
        ->toHaveKey('type', 'object')
        ->toHaveKey('properties')
        ->toHaveKey('required');
});

it('lists get_release_notes tool with correct schema', function () {
    $this->mcp->initialize();

    $response = $this->mcp->listTools();
    $tools = $response['result']['tools'];

    $getReleaseNotesTool = collect($tools)->firstWhere('name', 'get_release_notes');

    expect($getReleaseNotesTool)
        ->not->toBeNull()
        ->toHaveKey('name', 'get_release_notes')
        ->toHaveKey('description')
        ->toHaveKey('inputSchema');

    expect($getReleaseNotesTool['description'])
        ->toContain('release notes')
        ->toContain('versions');

    expect($getReleaseNotesTool['inputSchema'])
        ->toHaveKey('type', 'object')
        ->toHaveKey('properties')
        ->toHaveKey('required');
});

it('lists get_available_upgrades tool with correct schema', function () {
    $this->mcp->initialize();

    $response = $this->mcp->listTools();
    $tools = $response['result']['tools'];

    $getAvailableUpgradesTool = collect($tools)->firstWhere('name', 'get_available_upgrades');

    expect($getAvailableUpgradesTool)
        ->not->toBeNull()
        ->toHaveKey('name', 'get_available_upgrades')
        ->toHaveKey('description')
        ->toHaveKey('inputSchema');

    expect($getAvailableUpgradesTool['description'])
        ->toContain('available')
        ->toContain('upgrade');

    expect($getAvailableUpgradesTool['inputSchema'])
        ->toHaveKey('type', 'object')
        ->toHaveKey('properties')
        ->toHaveKey('required');
});

it('lists get_dependency_constraints tool with correct schema', function () {
    $this->mcp->initialize();

    $response = $this->mcp->listTools();
    $tools = $response['result']['tools'];

    $getDependencyConstraintsTool = collect($tools)->firstWhere('name', 'get_dependency_constraints');

    expect($getDependencyConstraintsTool)
        ->not->toBeNull()
        ->toHaveKey('name', 'get_dependency_constraints')
        ->toHaveKey('description')
        ->toHaveKey('inputSchema');

    expect($getDependencyConstraintsTool['description'])
        ->toContain('dependencies')
        ->toContain('require');

    expect($getDependencyConstraintsTool['inputSchema'])
        ->toHaveKey('type', 'object')
        ->toHaveKey('properties')
        ->toHaveKey('required');
});
