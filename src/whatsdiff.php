<?php

declare(strict_types=1);

use Whatsdiff\Application;

// Autoload dependencies
if (! class_exists('\Composer\InstalledVersions')) {
    require __DIR__.'/../vendor/autoload.php';
}

// Set up error handling
if (class_exists('\NunoMaduro\Collision\Provider')) {
    (new \NunoMaduro\Collision\Provider())->register();
} else {
    error_reporting(0);
}

$application = new Application();
$application->run();
