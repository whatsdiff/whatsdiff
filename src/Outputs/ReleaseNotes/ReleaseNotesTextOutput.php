<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\ReleaseNotes;

use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Helpers\GithubUrlFormatter;

class ReleaseNotesTextOutput
{
    public function __construct(
        private bool $summary = false,
        private bool $useAnsi = true,
    ) {
    }

    public function format(ReleaseNotesCollection $collection, OutputInterface $output): void
    {
        if ($collection->isEmpty()) {
            $output->writeln('No release notes available.');

            return;
        }

        if ($this->summary) {
            $this->formatSummary($collection, $output);
        } else {
            $this->formatDetailed($collection, $output);
        }
    }

    private function formatDetailed(ReleaseNotesCollection $collection, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln($this->colorize('<fg=bright-cyan>Release Notes</>', 'Release Notes'));
        $output->writeln($this->colorize('<fg=gray>' . str_repeat('─', 80) . '</>', str_repeat('-', 80)));
        $output->writeln('');

        foreach ($collection as $release) {
            $this->formatRelease($release, $output);
            $output->writeln('');
        }
    }

    private function formatRelease(ReleaseNote $release, OutputInterface $output): void
    {
        // Release header
        $header = $release->tagName;
        if (!empty($release->title) && $release->title !== $release->tagName) {
            $header .= ' - ' . $release->title;
        }
        $output->writeln($this->colorize('<fg=bright-yellow>' . $header . '</>', $header));

        // Date and URL
        $output->writeln($this->colorize('<fg=gray>Date: ' . $release->date->format('Y-m-d') . '</>', 'Date: ' . $release->date->format('Y-m-d')));
        if ($release->url) {
            $urlText = '<href=' . $release->url . '>' . $release->url . '</>';
            $output->writeln($this->colorize('<fg=gray>URL: ' . $urlText . '</>', 'URL: ' . $release->url));
        }
        $output->writeln('');

        // If changelog is not structured, display raw body
        if (!$release->isStructured()) {
            $body = $release->getBody();
            if (!empty($body)) {
                $bodyLines = explode("\n", $body);
                foreach ($bodyLines as $line) {
                    if (trim($line) === '') {
                        $output->writeln('');
                    } else {
                        $formatted = $this->formatTextWithLinks($line);
                        $output->writeln($this->colorize('<fg=default>' . $formatted . '</>', $line));
                    }
                }
                $output->writeln('');
            }
        } else {
            // Description (if any)
            $description = $release->getDescription();
            if (! empty($description)) {
                // Split into lines and format each one
                $descriptionLines = explode("\n", $description);
                foreach ($descriptionLines as $line) {
                    if (trim($line) === '') {
                        $output->writeln('');
                    } else {
                        $formatted = $this->formatTextWithLinks($line);
                        $output->writeln($this->colorize('<fg=default>' . $formatted . '</>', $line));
                    }
                }
                $output->writeln('');
            }

            // Breaking changes (if any)
            $breakingChanges = $release->getBreakingChanges();
            if (! empty($breakingChanges)) {
                $output->writeln($this->colorize('<fg=bright-red>Breaking Changes:</>', 'Breaking Changes:'));
                foreach ($breakingChanges as $change) {
                    $formatted = $this->formatTextWithLinks($change);
                    $output->writeln($this->colorize('  <fg=red>•</> ' . $formatted, '  • ' . $change));
                }
                $output->writeln('');
            }

            // Changes (if any)
            $changes = $release->getChanges();
            if (! empty($changes)) {
                $output->writeln($this->colorize('<fg=bright-green>Changes:</>', 'Changes:'));
                foreach ($changes as $change) {
                    $formatted = $this->formatTextWithLinks($change);
                    $output->writeln($this->colorize('  <fg=green>•</> ' . $formatted, '  • ' . $change));
                }
                $output->writeln('');
            }

            // Fixes (if any)
            $fixes = $release->getFixes();
            if (! empty($fixes)) {
                $output->writeln($this->colorize('<fg=bright-blue>Fixes:</>', 'Fixes:'));
                foreach ($fixes as $fix) {
                    $formatted = $this->formatTextWithLinks($fix);
                    $output->writeln($this->colorize('  <fg=blue>•</> ' . $formatted, '  • ' . $fix));
                }
                $output->writeln('');
            }

            // Deprecated (if any)
            $deprecated = $release->getDeprecated();
            if (! empty($deprecated)) {
                $output->writeln($this->colorize('<fg=bright-yellow>Deprecated:</>', 'Deprecated:'));
                foreach ($deprecated as $item) {
                    $formatted = $this->formatTextWithLinks($item);
                    $output->writeln($this->colorize('  <fg=yellow>•</> ' . $formatted, '  • ' . $item));
                }
                $output->writeln('');
            }

            // Removed (if any)
            $removed = $release->getRemoved();
            if (! empty($removed)) {
                $output->writeln($this->colorize('<fg=bright-red>Removed:</>', 'Removed:'));
                foreach ($removed as $item) {
                    $formatted = $this->formatTextWithLinks($item);
                    $output->writeln($this->colorize('  <fg=red>•</> ' . $formatted, '  • ' . $item));
                }
                $output->writeln('');
            }

            // Security (if any)
            $security = $release->getSecurity();
            if (! empty($security)) {
                $output->writeln($this->colorize('<fg=bright-magenta>Security:</>', 'Security:'));
                foreach ($security as $item) {
                    $formatted = $this->formatTextWithLinks($item);
                    $output->writeln($this->colorize('  <fg=magenta>•</> ' . $formatted, '  • ' . $item));
                }
                $output->writeln('');
            }
        }

        $output->writeln($this->colorize('<fg=gray>' . str_repeat('─', 80) . '</>', str_repeat('-', 80)));
    }

    private function formatSummary(ReleaseNotesCollection $collection, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln($this->colorize('<fg=bright-cyan>Release Notes Summary</>', 'Release Notes Summary'));
        $output->writeln($this->colorize('<fg=gray>' . str_repeat('─', 80) . '</>', str_repeat('-', 80)));
        $output->writeln('');

        // Show version range and count
        $releases = $collection->getReleases();
        $firstTag = $releases[0]->tagName;
        $lastTag = $releases[count($releases) - 1]->tagName;
        $count = $collection->count();
        $releasesInfo = "Changelog of: {$lastTag} → {$firstTag} ({$count} versions)";

        $output->writeln($this->colorize('<fg=gray>' . $releasesInfo . '</>', $releasesInfo));
        $output->writeln('');

        // If any release is unstructured, show all bullet points in a flat list
        if ($collection->hasUnstructuredReleases()) {
            $allBulletPoints = $collection->getAllBulletPoints();
            if (!empty($allBulletPoints)) {
                $output->writeln($this->colorize('<fg=bright-green>Changes:</>', 'Changes:'));
                foreach ($allBulletPoints as $bulletPoint) {
                    $formatted = $this->formatTextWithLinks($bulletPoint);
                    $output->writeln($this->colorize('  <fg=green>•</> ' . $formatted, '  • ' . $bulletPoint));
                }
                $output->writeln('');
            }
            return;
        }

        // All releases are structured - show categorized sections
        // Breaking changes
        $breakingChanges = $collection->getBreakingChanges();
        if (! empty($breakingChanges)) {
            $output->writeln($this->colorize('<fg=bright-red>Breaking Changes:</>', 'Breaking Changes:'));
            foreach ($breakingChanges as $change) {
                $formatted = $this->formatTextWithLinks($change);
                $output->writeln($this->colorize('  <fg=red>•</> ' . $formatted, '  • ' . $change));
            }
            $output->writeln('');
        }

        // Changes
        $changes = $collection->getChanges();
        if (! empty($changes)) {
            $output->writeln($this->colorize('<fg=bright-green>Changes:</>', 'Changes:'));
            foreach ($changes as $change) {
                $formatted = $this->formatTextWithLinks($change);
                $output->writeln($this->colorize('  <fg=green>•</> ' . $formatted, '  • ' . $change));
            }
            $output->writeln('');
        }

        // Fixes
        $fixes = $collection->getFixes();
        if (! empty($fixes)) {
            $output->writeln($this->colorize('<fg=bright-blue>Fixes:</>', 'Fixes:'));
            foreach ($fixes as $fix) {
                $formatted = $this->formatTextWithLinks($fix);
                $output->writeln($this->colorize('  <fg=blue>•</> ' . $formatted, '  • ' . $fix));
            }
            $output->writeln('');
        }

        // Deprecated
        $deprecated = $collection->getDeprecated();
        if (! empty($deprecated)) {
            $output->writeln($this->colorize('<fg=bright-yellow>Deprecated:</>', 'Deprecated:'));
            foreach ($deprecated as $item) {
                $formatted = $this->formatTextWithLinks($item);
                $output->writeln($this->colorize('  <fg=yellow>•</> ' . $formatted, '  • ' . $item));
            }
            $output->writeln('');
        }

        // Removed
        $removed = $collection->getRemoved();
        if (! empty($removed)) {
            $output->writeln($this->colorize('<fg=bright-red>Removed:</>', 'Removed:'));
            foreach ($removed as $item) {
                $formatted = $this->formatTextWithLinks($item);
                $output->writeln($this->colorize('  <fg=red>•</> ' . $formatted, '  • ' . $item));
            }
            $output->writeln('');
        }

        // Security
        $security = $collection->getSecurity();
        if (! empty($security)) {
            $output->writeln($this->colorize('<fg=bright-magenta>Security:</>', 'Security:'));
            foreach ($security as $item) {
                $formatted = $this->formatTextWithLinks($item);
                $output->writeln($this->colorize('  <fg=magenta>•</> ' . $formatted, '  • ' . $item));
            }
            $output->writeln('');
        }
    }

    private function colorize(string $ansiString, string $plainString): string
    {
        return $this->useAnsi ? $ansiString : $plainString;
    }

    /**
     * Format text by converting markdown links and bare URLs to Symfony <href> tags.
     * GitHub PR/issue URLs are displayed in compact format (#123) while remaining clickable.
     */
    private function formatTextWithLinks(string $text): string
    {
        if (! $this->useAnsi) {
            return $text;
        }

        // Convert markdown links [text](url) to <href> tags
        $text = preg_replace(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            '<href=$2>$1</>',
            $text
        );

        // Convert GitHub PR/issue URLs to compact format: https://github.com/owner/repo/pull/123 -> #123
        $text = GithubUrlFormatter::toTerminalLink($text);

        // Convert remaining bare URLs to <href> tags (use negative lookbehind to avoid matching URLs already in href= attributes)
        $text = preg_replace(
            '/(?<!href=)(https?:\/\/[^\s)\]<]+)/',
            '<href=$1>$1</>',
            $text
        );

        return $text;
    }
}
