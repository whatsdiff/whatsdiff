<?php

declare(strict_types=1);

namespace Whatsdiff\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Analyzers\Registries\NpmRegistry;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;
use Whatsdiff\Analyzers\ReleaseNotes\ReleaseNotesResolver;
use Whatsdiff\Outputs\Tui\TerminalUI;
use Whatsdiff\Services\CacheService;
use Whatsdiff\Services\DiffCalculator;
use Whatsdiff\Services\GitRepository;

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
        private readonly GitRepository $gitRepository,
        private readonly PackagistRegistry $packagistRegistry,
        private readonly NpmRegistry $npmRegistry,
        private readonly ReleaseNotesResolver $releaseNotesResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command launches an interactive TUI to browse changes in your project dependencies')
            ->addIgnoreLastOption()
            ->addFromOption('Commit hash, branch, or tag to compare from (older version)')
            ->addToOption('Commit hash, branch, or tag to compare to (newer version, defaults to HEAD)')
            ->addNoCacheOption()
            ->addOption('no-alt-screen', null, InputOption::VALUE_NONE, 'Disable alternate screen (useful for debugging)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ignoreLast = (bool) $input->getOption('ignore-last');
        $noCache = (bool) $input->getOption('no-cache');
        $noAltScreen = (bool) $input->getOption('no-alt-screen');
        $fromCommit = $input->getOption('from');
        $toCommit = $input->getOption('to');

        // Validate options
        if (($fromCommit || $toCommit) && $ignoreLast) {
            $output->writeln('<error>Cannot use --ignore-last with --from or --to options</error>');

            return Command::FAILURE;
        }

        try {
            // Disable alternate screen if requested (for debugging)
            if ($noAltScreen) {
                putenv('NO_ALT_SCREEN=1');
            }

            // Disable cache if requested
            if ($noCache) {
                $this->cacheService->disableCache();
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

            $result = $this->diffCalculator->run();

            if (!$result->hasAnyChanges()) {
                $output->writeln('<info>No dependency changes detected.</info>');
                return Command::SUCCESS;
            }

            // Convert to flat array for TUI
            $packageDiffs = $this->convertToTuiFormat($result);

            // Launch TUI
            $tui = new TerminalUI(
                packages: $packageDiffs,
                gitRepository: $this->gitRepository,
                packagistRegistry: $this->packagistRegistry,
                npmRegistry: $this->npmRegistry,
                releaseNotesResolver: $this->releaseNotesResolver,
            );
            $tui->prompt();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            return $this->handleTuiError($e, $output, $tui ?? null);
        }
    }

    private function handleTuiError(\Exception $e, OutputInterface $output, ?TerminalUI $tui): int
    {
        // Clean up alt screen properly
        if ($tui !== null) {
            try {
                $tui->exitAltScreen();
            } catch (\Exception $cleanupException) {
                // Ignore cleanup errors
            }

            // Give terminal time to return to normal state
            usleep(150000); // 150ms
        }

        // Clear screen and show error prominently
        $output->write("\033[2J\033[H"); // Clear screen and move cursor to top
        $output->writeln("<error>{$e->getMessage()}</error>");
        $output->writeln('');

        if ($output->isVerbose()) {
            $output->writeln('<comment>Stack trace:</comment>');
            $output->writeln($e->getTraceAsString());
            $output->writeln('');
        }

        // Tell the user that he can create a GitHub issue
        $output->writeln('<comment>If you believe this is a bug, please report it at:</comment>');
        $output->writeln('<comment><href=https://github.com/whatsdiff/whatsdiff/issues>https://github.com/whatsdiff/whatsdiff/issues</></comment>');
        $output->writeln('');

        $output->writeln('<comment>Press Enter to exit...</comment>');
        fgets(STDIN);

        return Command::FAILURE;
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
                    'semver' => $change->semver?->value,
                    'filename' => $diff->filename,
                ];
            }
        }

        return $packages;
    }
}
