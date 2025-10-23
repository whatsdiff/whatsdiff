<?php

declare(strict_types=1);

namespace Whatsdiff\Commands;

use Symfony\Component\Console\Input\InputOption;

/**
 * Trait containing commonly used command options.
 *
 * This trait provides helper methods to define options that are shared across multiple commands,
 * reducing duplication and ensuring consistent option definitions.
 */
trait SharedCommandOptions
{
    /**
     * Add the --ignore-last option to the command.
     *
     * This option allows users to ignore the last uncommitted changes.
     */
    protected function addIgnoreLastOption(): self
    {
        return $this->addOption(
            'ignore-last',
            null,
            InputOption::VALUE_NONE,
            'Ignore last uncommitted changes'
        );
    }

    /**
     * Add the --no-cache option to the command.
     *
     * This option allows users to disable caching for the current request.
     */
    protected function addNoCacheOption(): self
    {
        return $this->addOption(
            'no-cache',
            null,
            InputOption::VALUE_NONE,
            'Disable caching for this request'
        );
    }

    /**
     * Add the --format option to the command.
     *
     * This option allows users to specify the output format (text, json, markdown).
     *
     * @param string $default Default format (typically 'text')
     */
    protected function addFormatOption(string $default = 'text'): self
    {
        return $this->addOption(
            'format',
            'f',
            InputOption::VALUE_REQUIRED,
            'Output format (text, json, markdown)',
            $default
        );
    }

    /**
     * Add the --from option to the command.
     *
     * This option allows users to specify a starting commit/branch/tag.
     *
     * @param string $description Custom description (optional)
     */
    protected function addFromOption(string $description = 'Git commit, branch, or tag to get the starting version from'): self
    {
        return $this->addOption(
            'from',
            null,
            InputOption::VALUE_REQUIRED,
            $description
        );
    }

    /**
     * Add the --to option to the command.
     *
     * This option allows users to specify an ending commit/branch/tag.
     *
     * @param string $description Custom description (optional)
     */
    protected function addToOption(string $description = 'Git commit, branch, or tag to get the ending version from (defaults to HEAD)'): self
    {
        return $this->addOption(
            'to',
            null,
            InputOption::VALUE_REQUIRED,
            $description
        );
    }

    /**
     * Add the --include option to the command.
     *
     * This option allows filtering to specific package manager types.
     */
    protected function addIncludeOption(): self
    {
        return $this->addOption(
            'include',
            null,
            InputOption::VALUE_REQUIRED,
            'Include only specific package manager types (comma-separated: composer,npmjs)'
        );
    }

    /**
     * Add the --exclude option to the command.
     *
     * This option allows excluding specific package manager types.
     */
    protected function addExcludeOption(): self
    {
        return $this->addOption(
            'exclude',
            null,
            InputOption::VALUE_REQUIRED,
            'Exclude specific package manager types (comma-separated: composer,npmjs)'
        );
    }

    /**
     * Add the --no-progress option to the command.
     *
     * This option allows disabling the progress bar.
     */
    protected function addNoProgressOption(): self
    {
        return $this->addOption(
            'no-progress',
            null,
            InputOption::VALUE_NONE,
            'Disable progress bar'
        );
    }
}
