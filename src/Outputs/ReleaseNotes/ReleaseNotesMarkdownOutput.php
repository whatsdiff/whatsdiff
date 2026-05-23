<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\ReleaseNotes;

use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Data\ReleaseNotesCollection;

class ReleaseNotesMarkdownOutput
{
    public function __construct(
        private bool $summary = false,
    ) {
    }

    public function format(ReleaseNotesCollection $collection, OutputInterface $output): void
    {
        if ($collection->isEmpty()) {
            $output->writeln('No release notes available.');

            return;
        }

        if ($this->summary) {
            $output->writeln($collection->toSummarizedMarkdown());
        } else {
            $output->writeln($collection->toMarkdown());
        }
    }
}
