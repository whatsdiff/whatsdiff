<?php

declare(strict_types=1);

namespace Whatsdiff\Services;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Centralized error handling service for Symfony Console commands.
 *
 * This service provides consistent error formatting across different output formats
 * and command contexts, reducing code duplication in command error handling.
 */
class CommandErrorHandler
{
    /**
     * Handle an exception and write formatted output.
     *
     * Automatically formats error messages based on the output format:
     * - JSON format: Returns structured JSON with error message
     * - Text format: Returns styled console error message
     *
     * @param \Exception $exception The exception that was caught
     * @param OutputInterface $output Symfony Console output interface
     * @param string|null $format Output format (json, text, markdown, etc.)
     * @param int $exitCode The command exit code to return (default: Command::FAILURE)
     * @return int The exit code to return from the command
     */
    public function handle(
        \Exception $exception,
        OutputInterface $output,
        ?string $format = null,
        int $exitCode = Command::FAILURE
    ): int {
        $message = $exception->getMessage();

        // Format error based on output format
        if ($format === 'json') {
            $output->writeln(json_encode([
                'error' => $message
            ], JSON_PRETTY_PRINT));
        } else {
            $output->writeln("<error>Error: {$message}</error>");
        }

        return $exitCode;
    }

    /**
     * Handle an exception with quiet mode support.
     *
     * Useful for commands that support a --quiet flag where errors should
     * only be displayed when quiet mode is not enabled.
     *
     * @param \Exception $exception The exception that was caught
     * @param OutputInterface $output Symfony Console output interface
     * @param bool $quiet Whether quiet mode is enabled
     * @param int $exitCode The command exit code to return (default: Command::INVALID)
     * @return int The exit code to return from the command
     */
    public function handleQuiet(
        \Exception $exception,
        OutputInterface $output,
        bool $quiet,
        int $exitCode = Command::INVALID
    ): int {
        if (!$quiet) {
            $output->writeln("<error>Error: {$exception->getMessage()}</error>");
        }

        return $exitCode;
    }

    /**
     * Handle an exception with custom error message formatting.
     *
     * Allows for more control over the error message format and structure.
     *
     * @param \Exception $exception The exception that was caught
     * @param OutputInterface $output Symfony Console output interface
     * @param callable $formatter Custom formatter function (receives exception, returns string)
     * @param int $exitCode The command exit code to return (default: Command::FAILURE)
     * @return int The exit code to return from the command
     */
    public function handleWithFormatter(
        \Exception $exception,
        OutputInterface $output,
        callable $formatter,
        int $exitCode = Command::FAILURE
    ): int {
        $formattedMessage = $formatter($exception);
        $output->writeln($formattedMessage);

        return $exitCode;
    }
}
