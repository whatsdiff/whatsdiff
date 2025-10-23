<?php

declare(strict_types=1);

namespace Whatsdiff\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Outputs\JsonOutput;
use Whatsdiff\Outputs\MarkdownOutput;
use Whatsdiff\Outputs\OutputFormatterInterface;
use Whatsdiff\Outputs\TextOutput;
use Whatsdiff\Services\CacheService;
use Whatsdiff\Services\CommandErrorHandler;
use Whatsdiff\Services\DiffCalculator;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\progress;

#[AsCommand(
    name: 'analyse',
    description: 'See what\'s changed in your project\'s dependencies',
    hidden: false,
)]
class AnalyseCommand extends Command
{
    use SharedCommandOptions;

    public function __construct(
        private readonly CacheService $cacheService,
        private readonly DiffCalculator $diffCalculator,
        private readonly CommandErrorHandler $errorHandler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command analyzes changes in your project dependencies (composer.lock and package-lock.json). You can compare dependency changes between any two commits using --from and --to options.')
            ->addIgnoreLastOption()
            ->addFromOption('Commit hash, branch, or tag to compare from (older version)')
            ->addToOption('Commit hash, branch, or tag to compare to (newer version, defaults to HEAD)')
            ->addFormatOption()
            ->addNoCacheOption()
            ->addIncludeOption()
            ->addExcludeOption()
            ->addNoProgressOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ignoreLast = (bool) $input->getOption('ignore-last');
        $format = $input->getOption('format');
        $noCache = (bool) $input->getOption('no-cache');
        $fromCommit = $input->getOption('from');
        $toCommit = $input->getOption('to');
        $includeTypes = $input->getOption('include');
        $excludeTypes = $input->getOption('exclude');
        $noAnsi = ! $output->isDecorated();

        // Validate options
        if (($fromCommit || $toCommit) && $ignoreLast) {
            $output->writeln('<error>Cannot use --ignore-last with --from or --to options</error>');

            return Command::FAILURE;
        }

        if ($includeTypes && $excludeTypes) {
            $output->writeln('<error>Cannot use both --include and --exclude options</error>');

            return Command::FAILURE;
        }

        try {
            // Disable cache if requested
            if ($noCache) {
                $this->cacheService->disableCache();
            }

            // Parse dependency types from include/exclude options
            $dependencyTypes = $this->parseDependencyTypes($includeTypes, $excludeTypes, $output);
            if ($dependencyTypes === null) {
                return Command::FAILURE;
            }

            // Configure dependency types if specified
            foreach ($dependencyTypes as $type) {
                $this->diffCalculator->for($type);
            }

            if ($ignoreLast) {
                $this->diffCalculator->ignoreLastCommit();
            }

            if ($fromCommit !== null) {
                $this->diffCalculator->fromCommit($fromCommit);
            }

            if ($toCommit !== null) {
                $this->diffCalculator->toCommit($toCommit);
            }


            if ($this->shouldShowProgress($format, $noAnsi, $input)) {
                [$total, $generator] = $this->diffCalculator->run(withProgress: true);

                // Use Laravel Prompts for progress bar
                if ($total) {
                    $output->writeln('');
                    $this->showProgressBar($total, $generator);
                }

                $result = $this->diffCalculator->getResult();

            } else {
                $result = $this->diffCalculator->run();
            }

            // Get appropriate formatter
            $formatter = $this->getFormatter($format, $noAnsi);

            // Output results
            $formatter->format($result, $output);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            return $this->errorHandler->handle($e, $output, $format);
        }
    }

    private function getFormatter(string $format, bool $noAnsi): OutputFormatterInterface
    {
        return match ($format) {
            'json' => new JsonOutput(),
            'markdown' => new MarkdownOutput(),
            default => new TextOutput(! $noAnsi),
        };
    }

    private function shouldShowProgress($format, bool $noAnsi, InputInterface $input): bool
    {
        // // TODO: We'll put a config for that later
        // return false;

        return $format == 'text' && $noAnsi === false
            && $input->isInteractive()
            && ! $input->getParameterOption('--no-progress', false)
            && ! $input->getParameterOption('--no-interaction', false);
    }

    /**
     * @param  mixed  $total
     * @param  mixed  $generator
     * @return void
     */
    public function showProgressBar(mixed $total, mixed $generator): void
    {
        $startTime = microtime(true);
        $progressStarted = false;
        $progress = null;
        $processedCount = 0;

        foreach ($generator as $package) {
            $processedCount++;

            // Check if 1 second has passed
            $elapsedTime = microtime(true) - $startTime;

            if (!$progressStarted && $elapsedTime >= 1.0) {
                // Start showing the progress bar
                $progress = progress(label: 'Analysing changes..', steps: $total);
                $progress->start();

                // Advance to current position
                for ($i = 0; $i < $processedCount; $i++) {
                    $progress->advance();
                }

                $progressStarted = true;
            } elseif ($progressStarted && $progress) {
                $progress->advance();
            }
        }

        // Finish the progress bar if it was started
        if ($progressStarted && $progress) {
            $progress->finish();
        }
        // clear();
    }

    /**
     * Parse dependency types from include/exclude options
     *
     * @return array<PackageManagerType>|null Returns null on error
     */
    private function parseDependencyTypes(?string $includeTypes, ?string $excludeTypes, OutputInterface $output): ?array
    {
        $allTypes = PackageManagerType::cases();

        // If neither include nor exclude is specified, return all types
        if (!$includeTypes && !$excludeTypes) {
            return $allTypes;
        }

        // Handle include option
        if ($includeTypes) {
            $types = array_map('trim', explode(',', $includeTypes));
            $parsedTypes = [];

            foreach ($types as $typeString) {
                $type = $this->parsePackageManagerType($typeString);
                if ($type === null) {
                    $output->writeln("<error>Invalid package manager type: '{$typeString}'. Valid types: composer, npmjs</error>");
                    return null;
                }
                $parsedTypes[] = $type;
            }

            return $parsedTypes;
        }

        // Handle exclude option
        $excludeTypeStrings = array_map('trim', explode(',', $excludeTypes));
        $excludeTypesArray = [];

        foreach ($excludeTypeStrings as $typeString) {
            $type = $this->parsePackageManagerType($typeString);
            if ($type === null) {
                $output->writeln("<error>Invalid package manager type: '{$typeString}'. Valid types: composer, npmjs</error>");
                return null;
            }
            $excludeTypesArray[] = $type;
        }

        // Return all types except the excluded ones
        return array_filter($allTypes, fn (PackageManagerType $type) => !in_array($type, $excludeTypesArray));
    }

    private function parsePackageManagerType(string $typeString): ?PackageManagerType
    {
        return match (strtolower($typeString)) {
            'composer' => PackageManagerType::COMPOSER,
            'npmjs', 'npm' => PackageManagerType::NPM,
            default => null,
        };
    }
}
