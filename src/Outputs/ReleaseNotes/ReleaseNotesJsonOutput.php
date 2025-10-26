<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\ReleaseNotes;

use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Data\ReleaseNotesCollection;

class ReleaseNotesJsonOutput
{
    public function __construct(
        private bool $summary = false
    ) {
    }

    public function format(ReleaseNotesCollection $collection, OutputInterface $output): void
    {
        $releases = $collection->getReleases();
        $isStructured = !$collection->hasUnstructuredReleases();

        $data = [
            'total_releases' => $collection->count(),
        ];

        // Add version range and summary only if there are releases
        if (!empty($releases)) {
            $data['first_tag'] = $releases[0]->tagName;
            $data['last_tag'] = $releases[count($releases) - 1]->tagName;

            // Only add summary object if summary mode is enabled
            if ($this->summary) {
                $data['summary'] = [
                    'is_structured' => $isStructured,
                    'breaking_changes' => $isStructured ? $collection->getBreakingChanges() : [],
                    'changes' => $isStructured ? $collection->getChanges() : [],
                    'fixes' => $isStructured ? $collection->getFixes() : [],
                    'deprecated' => $isStructured ? $collection->getDeprecated() : [],
                    'removed' => $isStructured ? $collection->getRemoved() : [],
                    'security' => $isStructured ? $collection->getSecurity() : [],
                    'all_bullet_points' => $collection->getAllBulletPoints(),
                ];
            }
        }

        $data['releases'] = [];

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
                'deprecated' => $release->getDeprecated(),
                'removed' => $release->getRemoved(),
                'security' => $release->getSecurity(),
                'all_bullet_points' => $release->getAllBulletPoints(),
            ];
        }

        $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
