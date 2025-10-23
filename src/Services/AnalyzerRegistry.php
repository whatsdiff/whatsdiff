<?php

declare(strict_types=1);

namespace Whatsdiff\Services;

use Psr\Container\ContainerInterface;
use Whatsdiff\Analyzers\AnalyzerInterface;
use Whatsdiff\Analyzers\PackageManagerType;

/**
 * Registry for managing package manager analyzers.
 *
 * Provides lazy loading of analyzers - they are only instantiated
 * when first requested via get(). This improves performance by
 * not loading all analyzers upfront.
 */
class AnalyzerRegistry
{
    /**
     * @var array<string, string> Map of PackageManagerType value => analyzer class name
     */
    private array $analyzers = [];

    /**
     * @var array<string, AnalyzerInterface> Cache of instantiated analyzers
     */
    private array $instances = [];

    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * Register an analyzer for a specific package manager type.
     *
     * @param PackageManagerType $type Package manager type
     * @param string $analyzerClass Fully qualified analyzer class name
     * @return self For method chaining
     */
    public function register(PackageManagerType $type, string $analyzerClass): self
    {
        $this->analyzers[$type->value] = $analyzerClass;

        return $this;
    }

    /**
     * Get an analyzer for the specified package manager type.
     *
     * Lazy loads the analyzer on first access and caches it for subsequent calls.
     *
     * @param PackageManagerType $type Package manager type
     * @return AnalyzerInterface The analyzer instance
     * @throws \RuntimeException If no analyzer is registered for the type
     */
    public function get(PackageManagerType $type): AnalyzerInterface
    {
        $key = $type->value;

        // Return cached instance if available
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        // Check if analyzer is registered
        if (! $this->has($type)) {
            throw new \RuntimeException("No analyzer registered for package manager type: {$type->value}");
        }

        // Lazy load the analyzer from the container
        $analyzerClass = $this->analyzers[$key];
        $analyzer = $this->container->get($analyzerClass);

        if (! $analyzer instanceof AnalyzerInterface) {
            throw new \RuntimeException("Analyzer class {$analyzerClass} must implement AnalyzerInterface");
        }

        // Cache the instance
        $this->instances[$key] = $analyzer;

        return $analyzer;
    }

    /**
     * Check if an analyzer is registered for the specified type.
     *
     * @param PackageManagerType $type Package manager type
     * @return bool True if an analyzer is registered
     */
    public function has(PackageManagerType $type): bool
    {
        return isset($this->analyzers[$type->value]);
    }
}
