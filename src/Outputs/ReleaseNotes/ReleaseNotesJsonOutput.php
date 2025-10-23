<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\ReleaseNotes;

use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Data\ReleaseNotesCollection;

class ReleaseNotesJsonOutput
{
    public function format(ReleaseNotesCollection $collection, OutputInterface $output): void
    {
        $data = [
            'total_releases' => $collection->count(),
            'releases' => [],
        ];

        foreach ($collection as $release) {
            $data['releases'][] = [
                'tag_name' => $release->tagName,
                'title' => $release->title,
                'date' => $release->date->format('Y-m-d\TH:i:s\Z'),
                'url' => $release->url,
                'body' => $release->body,
                'description' => $release->getDescription(),
                'changes' => $release->getChanges(),
                'fixes' => $release->getFixes(),
                'breaking_changes' => $release->getBreakingChanges(),
            ];
        }

        $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
