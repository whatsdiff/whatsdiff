<?php

declare(strict_types=1);

use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StdioServerTransport;
use Whatsdiff\Application;
use Whatsdiff\Mcp\Tools\FindCompatibleVersionTool;
use Whatsdiff\Mcp\Tools\GetAvailableUpgradesTool;
use Whatsdiff\Mcp\Tools\GetDependencyConstraintsTool;
use Whatsdiff\Mcp\Tools\GetReleaseNotesTool;

// Autoload dependencies
if (! class_exists('\Composer\InstalledVersions')) {
    require __DIR__.'/../vendor/autoload.php';
}

// Set up error handling (simplified for MCP server)
error_reporting(0);

// Initialize container with shared configuration
$container = Application::instantiateContainer();

// Build MCP server using the same container
$server = Server::make()
    ->withServerInfo('whatsdiff', Application::getVersionString())
    ->withContainer($container)
    ->withTool(
        [FindCompatibleVersionTool::class, 'findCompatibleVersions'],
        'find_compatible_versions',
        'Find which major versions of a package are compatible with a given dependency constraint. Examples: which livewire versions work with illuminate/support ^11.0? Which laravel/framework versions work with PHP ^8.2?'
    )
    ->withTool(
        [GetReleaseNotesTool::class, 'getReleaseNotes'],
        'get_release_notes',
        'Fetch aggregated release notes for a package between two versions from GitHub releases.'
    )
    ->withTool(
        [GetAvailableUpgradesTool::class, 'getAvailableUpgrades'],
        'get_available_upgrades',
        'Get the latest available patch, minor, and major version upgrades for a package to help determine new composer.json constraints.'
    )
    ->withTool(
        [GetDependencyConstraintsTool::class, 'getDependencyConstraints'],
        'get_dependency_constraints',
        'Get all dependencies required by a specific package version. Example: what does livewire v3.0.0 require?'
    )
    ->build();

// Create and start stdio transport
$transport = new StdioServerTransport();
$server->listen($transport);
