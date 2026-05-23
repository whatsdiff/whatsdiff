<?php

declare(strict_types=1);

namespace Whatsdiff\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Data\AuditResult;
use Whatsdiff\Enums\Severity;
use Whatsdiff\Helpers\CommandErrorHandler;
use Whatsdiff\Outputs\Audit\AuditJsonOutput;
use Whatsdiff\Outputs\Audit\AuditMarkdownOutput;
use Whatsdiff\Outputs\Audit\AuditTextOutput;
use Whatsdiff\Services\AuditCalculator;
use Whatsdiff\Services\CacheService;

#[AsCommand(
    name: 'audit',
    description: 'List known security advisories affecting installed dependencies',
    hidden: false,
)]
class AuditCommand extends Command
{
    use SharedCommandOptions;

    public function __construct(
        private readonly AuditCalculator $auditCalculator,
        private readonly CacheService $cacheService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Inspect installed dependencies for known security advisories. '
                .'Defaults to a current-state audit of composer.lock and package-lock.json. '
                .'Use --from/--to to report advisories newly introduced between two refs, '
                .'or --at to audit the lockfile at a specific commit.'
            )
            ->addFormatOption()
            ->addNoCacheOption()
            ->addIncludeOption()
            ->addExcludeOption()
            ->addFromOption('Commit, branch, or tag to compare from (diff mode)')
            ->addToOption('Commit, branch, or tag to compare to (diff mode, defaults to HEAD)')
            ->addOption(
                'at',
                null,
                InputOption::VALUE_REQUIRED,
                'Audit the lockfile at a specific commit/tag/branch instead of the working tree'
            )
            ->addOption(
                'fail-on',
                null,
                InputOption::VALUE_REQUIRED,
                'Severity threshold for non-zero exit (low, medium, high, critical, none)',
                'low'
            )
            ->addOption(
                'no-fix',
                null,
                InputOption::VALUE_NONE,
                'Skip the suggested-fix version lookup (faster, no extra registry calls)'
            )
            ->addOption(
                'allow-unrated',
                null,
                InputOption::VALUE_NONE,
                'Do not trip --fail-on for advisories whose severity has not been rated upstream yet (default: unrated counts as meeting any threshold, fail-safe)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = (string) $input->getOption('format');
        $noCache = (bool) $input->getOption('no-cache');
        $includeTypes = $input->getOption('include');
        $excludeTypes = $input->getOption('exclude');
        $fromCommit = $input->getOption('from');
        $toCommit = $input->getOption('to');
        $atCommit = $input->getOption('at');
        $noFix = (bool) $input->getOption('no-fix');
        $allowUnrated = (bool) $input->getOption('allow-unrated');
        $failOnRaw = strtolower((string) $input->getOption('fail-on'));
        $noAnsi = ! $output->isDecorated();

        if ($atCommit !== null && ($fromCommit !== null || $toCommit !== null)) {
            $output->writeln('<error>Cannot use --at together with --from or --to</error>');

            return Command::FAILURE;
        }

        if ($includeTypes && $excludeTypes) {
            $output->writeln('<error>Cannot use both --include and --exclude options</error>');

            return Command::FAILURE;
        }

        $failOn = $this->parseFailOn($failOnRaw);
        if ($failOn === false) {
            $output->writeln("<error>Invalid --fail-on value: '{$failOnRaw}'. Use one of: low, medium, high, critical, none</error>");

            return Command::FAILURE;
        }

        try {
            if ($noCache) {
                $this->cacheService->disableCache();
            }

            $dependencyTypes = $this->parseDependencyTypes($includeTypes, $excludeTypes, $output);
            if ($dependencyTypes === null) {
                return Command::FAILURE;
            }

            foreach ($dependencyTypes as $type) {
                $this->auditCalculator->for($type);
            }

            $this->auditCalculator
                ->atCommit($atCommit)
                ->fromCommit($fromCommit)
                ->toCommit($toCommit)
                ->withFixSuggestions(! $noFix);

            $result = $this->auditCalculator->run();

            $this->renderResult($result, $format, $noAnsi, $output);

            return $this->resolveExitCode($result, $failOn, $allowUnrated);

        } catch (\Exception $e) {
            return CommandErrorHandler::handle($e, $output, $format);
        }
    }

    private function renderResult(AuditResult $result, string $format, bool $noAnsi, OutputInterface $output): void
    {
        match ($format) {
            'json' => (new AuditJsonOutput)->format($result, $output),
            'markdown' => (new AuditMarkdownOutput)->format($result, $output),
            default => (new AuditTextOutput(! $noAnsi))->format($result, $output),
        };
    }

    private function resolveExitCode(AuditResult $result, ?Severity $failOn, bool $allowUnrated): int
    {
        if ($failOn === null) {
            return Command::SUCCESS;
        }

        return $result->hasAnyAtOrAbove($failOn, ! $allowUnrated) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Returns a Severity threshold, null for "never fail", or false for invalid input.
     */
    private function parseFailOn(string $value): Severity|false|null
    {
        return match ($value) {
            'none', 'never' => null,
            'low' => Severity::Low,
            'medium', 'moderate' => Severity::Medium,
            'high' => Severity::High,
            'critical' => Severity::Critical,
            default => false,
        };
    }

    /**
     * @return array<PackageManagerType>|null
     */
    private function parseDependencyTypes(?string $includeTypes, ?string $excludeTypes, OutputInterface $output): ?array
    {
        $allTypes = PackageManagerType::cases();

        if (! $includeTypes && ! $excludeTypes) {
            return $allTypes;
        }

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

        $kept = [];
        foreach ($allTypes as $type) {
            if (! in_array($type, $excludeTypesArray, true)) {
                $kept[] = $type;
            }
        }

        return $kept;
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
