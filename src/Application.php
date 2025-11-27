<?php

declare(strict_types=1);

namespace Whatsdiff;

use League\Container\Container;
use League\Container\ReflectionContainer;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application as BaseApplication;
use Whatsdiff\Analyzers\ComposerAnalyzer;
use Whatsdiff\Analyzers\NpmAnalyzer;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\ReleaseNotes\Fetchers\GithubChangelogFetcher;
use Whatsdiff\Analyzers\ReleaseNotes\Fetchers\GithubReleaseFetcher;
use Whatsdiff\Analyzers\ReleaseNotes\Fetchers\LocalVendorChangelogFetcher;
use Whatsdiff\Analyzers\ReleaseNotes\ReleaseNotesResolver;
use Whatsdiff\Commands\AnalyseCommand;
use Whatsdiff\Commands\BetweenCommand;
use Whatsdiff\Commands\ChangelogCommand;
use Whatsdiff\Commands\CheckCommand;
use Whatsdiff\Commands\ConfigCommand;
use Whatsdiff\Commands\TuiCommand;
use Whatsdiff\Services\AnalyzerRegistry;

class Application extends BaseApplication
{
    private const VERSION = '@git_tag@';

    private ContainerInterface $container;

    public function __construct()
    {
        parent::__construct('whatsdiff', self::getVersionString());

        // Initialize container with autowiring and service configuration
        $this->container = self::instantiateContainer();

        $this->addCommand($this->container->get(AnalyseCommand::class));
        $this->addCommand($this->container->get(BetweenCommand::class));
        $this->addCommand($this->container->get(TuiCommand::class));
        $this->addCommand($this->container->get(CheckCommand::class));
        $this->addCommand($this->container->get(ConfigCommand::class));
        $this->addCommand($this->container->get(ChangelogCommand::class));
        $this->setDefaultCommand('analyse');
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Create and configure the dependency injection container.
     * This method is shared between the CLI application and MCP server.
     */
    public static function instantiateContainer(): ContainerInterface
    {
        $container = new Container();

        // Enable autowiring via ReflectionContainer delegate (with caching for performance)
        $container->delegate(new ReflectionContainer(true));

        // Register the container itself for services that need it
        $container->add(ContainerInterface::class, $container);

        // Configure AnalyzerRegistry with package manager analyzers
        // Analyzers are lazy-loaded only when needed
        $container->add(AnalyzerRegistry::class, function () use ($container) {
            $registry = new AnalyzerRegistry($container);
            $registry->register(PackageManagerType::COMPOSER, ComposerAnalyzer::class);
            $registry->register(PackageManagerType::NPM, NpmAnalyzer::class);

            return $registry;
        });

        // Configure ReleaseNotesResolver with fetchers
        // Order matters: try fastest/most reliable sources first
        $container->add(ReleaseNotesResolver::class)
            ->addMethodCall('addFetcher', [LocalVendorChangelogFetcher::class])
            ->addMethodCall('addFetcher', [GithubReleaseFetcher::class])
            ->addMethodCall('addFetcher', [GithubChangelogFetcher::class]);

        return $container;
    }

    public function getLongVersion(): string
    {
        $version = parent::getLongVersion();

        $version .= PHP_EOL.PHP_EOL;
        $version .= 'PHP version: '.phpversion().PHP_EOL;

        if (self::getVersion() !== 'dev') {
            $version .= 'Built with https://github.com/box-project/box'.PHP_EOL;
        }

        if (php_sapi_name() === 'micro') {
            $version .= 'Compiled with https://github.com/crazywhalecc/static-php-cli'.PHP_EOL;
        }

        return $version;
    }

    public static function getVersionString(): string
    {
        if (! str_starts_with(self::VERSION, '@git_tag')) {
            return self::VERSION;
        }

        return 'dev';
    }
}
