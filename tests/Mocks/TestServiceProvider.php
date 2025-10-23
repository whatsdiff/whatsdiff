<?php

declare(strict_types=1);

namespace Tests\Mocks;

use League\Container\Container;
use Psr\Container\ContainerInterface;
use Whatsdiff\Analyzers\ComposerAnalyzer;
use Whatsdiff\Analyzers\NpmAnalyzer;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Services\AnalyzerRegistry;
use Whatsdiff\Services\HttpService;

/**
 * Test service provider that allows mocking services for tests.
 *
 * With autowiring enabled, we simply register mock services first,
 * and they will be used instead of the real implementations.
 */
class TestServiceProvider
{
    public function __construct(
        private readonly HttpService $mockHttpService
    ) {
    }

    public function register(ContainerInterface $container): void
    {
        if (!$container instanceof Container) {
            throw new \InvalidArgumentException('Container must be an instance of League\Container\Container');
        }

        // Register the container itself for services that need it
        $container->add(ContainerInterface::class, $container);

        // Register AnalyzerRegistry with lazy loading
        $container->add(AnalyzerRegistry::class, function () use ($container) {
            $registry = new AnalyzerRegistry($container);
            $registry->register(PackageManagerType::COMPOSER, ComposerAnalyzer::class);
            $registry->register(PackageManagerType::NPM, NpmAnalyzer::class);

            return $registry;
        });

        // Register mock HttpService
        // League\Container will use this instead of creating a real one via autowiring
        $container->addShared(HttpService::class, fn () => $this->mockHttpService);
    }
}
