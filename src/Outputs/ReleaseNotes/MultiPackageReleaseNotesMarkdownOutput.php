<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\ReleaseNotes;

use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Data\PackageReleaseNotes;

class MultiPackageReleaseNotesMarkdownOutput
{
    public function __construct(
        private bool $summary = false,
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

        $sections = [];

        foreach ($packages as $packageNotes) {
            $section = sprintf(
                "# %s (%s → %s)\n\n",
                $packageNotes->package,
                $packageNotes->fromVersion,
                $packageNotes->toVersion,
            );

            if (! $packageNotes->hasReleaseNotes()) {
                $section .= "_No release notes available._\n";
                $sections[] = $section;

                continue;
            }

            $section .= $this->summary
                ? $packageNotes->releaseNotes->toSummarizedMarkdown()
                : $packageNotes->releaseNotes->toMarkdown();

            $sections[] = $section;
        }

        $output->writeln(implode("\n\n", $sections));
    }
}
