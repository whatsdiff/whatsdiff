<?php

declare(strict_types=1);

namespace Whatsdiff;

use League\Container\Container;
use League\Container\ReflectionContainer;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application as BaseApplication;
use Whatsdiff\Commands\AnalyseCommand;
use Whatsdiff\Commands\BetweenCommand;
use Whatsdiff\Commands\ChangelogCommand;
use Whatsdiff\Commands\CheckCommand;
use Whatsdiff\Commands\ConfigCommand;
use Whatsdiff\Commands\TuiCommand;
use Whatsdiff\Services\ReleaseNotes\Fetchers\GithubReleaseFetcher;
use Whatsdiff\Services\ReleaseNotes\ReleaseNotesResolver;

class Application extends BaseApplication
{
    private const VERSION = '@git_tag@';

    private ContainerInterface $container;

    public function __construct()
    {
        // Set up error handling
        if (class_exists('\NunoMaduro\Collision\Provider')) {
            (new \NunoMaduro\Collision\Provider())->register();
        } else {
            error_reporting(0);
        }

        parent::__construct('whatsdiff', self::getVersionString());

        // Initialize container with autowiring and service configuration
        $this->container = self::instantiateContainer();

        $this->add($this->container->get(AnalyseCommand::class));
        $this->add($this->container->get(BetweenCommand::class));
        $this->add($this->container->get(TuiCommand::class));
        $this->add($this->container->get(CheckCommand::class));
        $this->add($this->container->get(ConfigCommand::class));
        $this->add($this->container->get(ChangelogCommand::class));
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

        // Configure ReleaseNotesResolver with fetchers
        $container->add(ReleaseNotesResolver::class)
            ->addMethodCall('addFetcher', [GithubReleaseFetcher::class]);

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
