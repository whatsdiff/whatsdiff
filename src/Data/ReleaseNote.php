<?php

declare(strict_types=1);

namespace Whatsdiff\Data;

use DateTimeImmutable;

final readonly class ReleaseNote
{
    public function __construct(
        public string $tagName,
        public string $title,
        public string $body,
        public DateTimeImmutable $date,
        public ?string $url = null,
    ) {
    }

    /**
     * Extract "Changes" or "Added" section from markdown body.
     *
     * @return array<int, string>
     */
    public function getChanges(): array
    {
        $changes = $this->extractSection([
            '## Changes',
            '## Added',
            '## What\'s Changed',
            '## New Features',
            '## Features',
            '## Enhancements',
            '### Changes',
            '### Added',
            '### What\'s Changed',
            '### New Features',
            '### Features',
            '### Enhancements',
        ]);

        // Fallback: If no recognized sections found and body is not empty, return entire body as changes
        if (empty($changes) && !empty(trim($this->body))) {
            return $this->extractAllBulletPoints();
        }

        return $changes;
    }

    /**
     * Extract "Fixes" or "Fixed" section from markdown body.
     *
     * @return array<int, string>
     */
    public function getFixes(): array
    {
        return $this->extractSection([
            '## Fixes',
            '## Fixed',
            '## Bug Fixes',
            '## Bugfixes',
            '### Fixes',
            '### Fixed',
            '### Bug Fixes',
            '### Bugfixes',
        ]);
    }

    /**
     * Extract "Breaking Changes" section from markdown body.
     *
     * @return array<int, string>
     */
    public function getBreakingChanges(): array
    {
        return $this->extractSection([
            '## Breaking Changes',
            '## BREAKING CHANGES',
            '## Breaking',
            '### Breaking Changes',
            '### Breaking',
        ]);
    }

    /**
     * Get the full markdown body.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get the release title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Extract all bullet points from the body regardless of sections.
     * Used as a fallback when no recognized section headings are found.
     *
     * @return array<int, string>
     */
    private function extractAllBulletPoints(): array
    {
        $lines = explode("\n", $this->body);
        $items = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Collect all bullet points
            if (str_starts_with($trimmedLine, '- ') || str_starts_with($trimmedLine, '* ')) {
                $items[] = substr($trimmedLine, 2); // Remove bullet point
            }
        }

        return $items;
    }

    /**
     * Extract bullet points from specific markdown sections.
     *
     * @param array<int, string> $headings Possible heading variations to look for
     * @return array<int, string>
     */
    private function extractSection(array $headings): array
    {
        $lines = explode("\n", $this->body);
        $items = [];
        $inSection = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Check if we're starting a section we care about
            $matchedHeading = false;
            foreach ($headings as $heading) {
                if (stripos($trimmedLine, $heading) === 0) {
                    $inSection = true;
                    $matchedHeading = true;
                    break;
                }
            }

            if ($matchedHeading) {
                continue; // Skip the heading line itself
            }

            // Check if we're starting a different section (stop collecting for this section)
            if ($inSection && (str_starts_with($trimmedLine, '## ') || str_starts_with($trimmedLine, '### '))) {
                // Check if this new section is also one we care about
                $isRelevantSection = false;
                foreach ($headings as $heading) {
                    if (stripos($trimmedLine, $heading) === 0) {
                        $isRelevantSection = true;
                        break;
                    }
                }

                // If it's not a relevant section, stop collecting
                if (!$isRelevantSection) {
                    $inSection = false;
                }
                continue;
            }

            // Collect bullet points
            if ($inSection && (str_starts_with($trimmedLine, '- ') || str_starts_with($trimmedLine, '* '))) {
                $items[] = substr($trimmedLine, 2); // Remove bullet point
            }
        }

        return $items;
    }
}
