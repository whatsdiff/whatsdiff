<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\ReleaseNotes;

use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Data\PackageReleaseNotes;

class MultiPackageReleaseNotesJsonOutput
{
    public function __construct(
        private bool $summary = false,
    ) {}

    /**
     * @param  array<PackageReleaseNotes>  $packages
     */
    public function format(array $packages, OutputInterface $output): void
    {
        $data = [
            'total_packages' => count($packages),
            'packages' => [],
        ];

        foreach ($packages as $packageNotes) {
            $collection = $packageNotes->releaseNotes;
            $packageEntry = [
                'package' => $packageNotes->package,
                'type' => $packageNotes->type->value,
                'from_version' => $packageNotes->fromVersion,
                'to_version' => $packageNotes->toVersion,
                'total_releases' => $collection?->count() ?? 0,
                'releases' => [],
            ];

            if ($collection !== null && ! $collection->isEmpty()) {
                $releases = $collection->getReleases();
                $packageEntry['first_tag'] = $releases[0]->tagName;
                $packageEntry['last_tag'] = $releases[count($releases) - 1]->tagName;

                $isStructured = ! $collection->hasUnstructuredReleases();

                if ($this->summary) {
                    $packageEntry['summary'] = [
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

                foreach ($collection as $release) {
                    $packageEntry['releases'][] = [
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
            }

            $data['packages'][] = $packageEntry;
        }

        $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
