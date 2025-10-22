<?php

declare(strict_types=1);

namespace Tests\Mocks;

use League\Container\Container;
use Psr\Container\ContainerInterface;
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

        // Register mock HttpService
        // League\Container will use this instead of creating a real one via autowiring
        $container->addShared(HttpService::class, fn () => $this->mockHttpService);
    }
}
