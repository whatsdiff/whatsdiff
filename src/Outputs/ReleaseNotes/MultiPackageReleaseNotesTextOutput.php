<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\ReleaseNotes;

use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Data\PackageReleaseNotes;

class MultiPackageReleaseNotesTextOutput
{
    public function __construct(
        private bool $summary = false,
        private bool $useAnsi = true,
    ) {
    }

    /**
     * @param  array<PackageReleaseNotes>  $packages
     */
    public function format(array $packages, OutputInterface $output): void
    {
        if (empty($packages)) {
            $output->writeln('No updated packages found.');

            return;
        }

        $singleFormatter = new ReleaseNotesTextOutput($this->summary, $this->useAnsi, includeHeader: false);
        $rendered = 0;

        foreach ($packages as $packageNotes) {
            $output->writeln('');
            $header = sprintf(
                '%s (%s → %s)',
                $packageNotes->package,
                $packageNotes->fromVersion,
                $packageNotes->toVersion,
            );
            $output->writeln($this->colorize('<fg=bright-magenta;options=bold>'.$header.'</>', $header));
            $output->writeln($this->colorize('<fg=gray>'.str_repeat('═', 80).'</>', str_repeat('=', 80)));

            if (! $packageNotes->hasReleaseNotes()) {
                $output->writeln($this->colorize(
                    '<comment>No release notes available.</comment>',
                    'No release notes available.'
                ));

                continue;
            }

            $singleFormatter->format($packageNotes->releaseNotes, $output);
            $rendered++;
        }

        if ($rendered === 0) {
            $output->writeln('');
            $output->writeln('No release notes could be fetched for the updated packages.');
        }
    }

    private function colorize(string $ansiString, string $plainString): string
    {
        return $this->useAnsi ? $ansiString : $plainString;
    }
}
