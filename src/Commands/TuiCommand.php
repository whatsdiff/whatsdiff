<?php

declare(strict_types=1);

namespace Whatsdiff\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Outputs\Tui\TerminalUI;
use Whatsdiff\Services\CacheService;
use Whatsdiff\Services\CommandErrorHandler;
use Whatsdiff\Services\DiffCalculator;

#[AsCommand(
    name: 'tui',
    description: 'Launch the Terminal User Interface to browse dependency changes',
    hidden: false,
)]
class TuiCommand extends Command
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
            ->setHelp('This command launches an interactive TUI to browse changes in your project dependencies')
            ->addIgnoreLastOption()
            ->addNoCacheOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ignoreLast = (bool) $input->getOption('ignore-last');
        $noCache = (bool) $input->getOption('no-cache');

        try {
            // Disable cache if requested
            if ($noCache) {
                $this->cacheService->disableCache();
            }

            if ($ignoreLast) {
                $this->diffCalculator->ignoreLastCommit();
            }

            $result = $this->diffCalculator->run();

            if (!$result->hasAnyChanges()) {
                $output->writeln('<info>No dependency changes detected.</info>');
                return Command::SUCCESS;
            }

            // Convert to flat array for TUI
            $packageDiffs = $this->convertToTuiFormat($result);

            // Launch TUI
            $tui = new TerminalUI($packageDiffs);
            $tui->prompt();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            return $this->errorHandler->handle($e, $output);
        }
    }

    private function convertToTuiFormat(\Whatsdiff\Data\DiffResult $result): array
    {
        $packages = [];

        foreach ($result->diffs as $diff) {
            foreach ($diff->changes as $change) {
                $packages[] = [
                    'name' => $change->name,
                    'type' => $change->type->value,
                    'from' => $change->from,
                    'to' => $change->to,
                    'status' => $change->status->value,
                    'releases' => $change->releaseCount,
                    'filename' => $diff->filename,
                ];
            }
        }

        return $packages;
    }
}
