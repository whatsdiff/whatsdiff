<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\Tui;

use Laravel\Prompts\Concerns\Colors;
use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Helpers\GithubUrlFormatter;

/**
 * Formats changelog/release notes for display in the TUI right pane.
 */
class ChangelogFormatter
{
    use Colors;

    /**
     * Format release notes collection as an array of lines for TUI display.
     *
     * @param ReleaseNotesCollection $collection The release notes to format
     * @param bool $summary Whether to show summary view (true) or detailed view (false)
     * @param int $maxWidth Maximum width for line wrapping
     * @return array<int, string> Array of formatted lines ready for TUI display
     */
    public function format(ReleaseNotesCollection $collection, bool $summary, int $maxWidth): array
    {
        if ($collection->isEmpty()) {
            return [
                $this->gray('No release notes available for this package.'),
            ];
        }

        return $summary
            ? $this->formatSummary($collection, $maxWidth)
            : $this->formatDetailed($collection, $maxWidth);
    }

    /**
     * Format release notes in detailed mode (each release separately).
     *
     * @param ReleaseNotesCollection $collection
     * @param int $maxWidth
     * @return array<int, string>
     */
    private function formatDetailed(ReleaseNotesCollection $collection, int $maxWidth): array
    {
        $lines = [];
        $lines[] = $this->cyan($this->bold('Release Notes'));
        $lines[] = $this->gray(str_repeat('─', min($maxWidth, 60)));

        foreach ($collection as $release) {
            $lines = array_merge($lines, $this->formatRelease($release, $maxWidth));
        }


        $lines[] = '';
        $lines[] = '';
        $lines[] = '';
        $lines[] = '';

        return $lines;
    }

    /**
     * Format a single release.
     *
     * @param ReleaseNote $release
     * @param int $maxWidth
     * @return array<int, string>
     */
    private function formatRelease(ReleaseNote $release, int $maxWidth): array
    {
        $lines = [];

        // Release header
        $header = $release->tagName;
        if ($release->title !== $release->tagName) {
            $header .= ' - ' . $release->title;
        }
        $lines[] = $this->yellow($this->bold($header));

        // Date
        $lines[] = $this->gray('Date: ' . $release->date->format('Y-m-d'));

        // URL (if available)
        if ($release->url) {
            $lines[] = $this->gray('URL: ') . $this->dim($release->url);
        }

        // If changelog is not structured, display raw body
        if (!$release->isStructured()) {
            $lines[] = '';
            $body = $release->getBody();
            if (!empty($body)) {
                // Format links before wrapping
                $formattedBody = $this->formatTextWithLinks($body);
                $bodyLines = $this->wrapText($formattedBody, $maxWidth);
                foreach ($bodyLines as $line) {
                    $lines[] = $line;
                }
            }
        } else {
            // Description (if any)
            $description = $release->getDescription();
            if (!empty($description)) {
                $lines[] = '';
                // Format links before wrapping
                $formattedDescription = $this->formatTextWithLinks($description);
                $descriptionLines = $this->wrapText($formattedDescription, $maxWidth);
                foreach ($descriptionLines as $line) {
                    $lines[] = $line;
                }
            }

            // Breaking changes
            $breakingChanges = $release->getBreakingChanges();
            if (!empty($breakingChanges)) {
                $lines[] = '';
                $lines[] = $this->red($this->bold('Breaking Changes:'));
                foreach ($breakingChanges as $change) {
                    // Format links before wrapping
                    $formattedChange = $this->formatTextWithLinks($change);
                    $wrappedLines = $this->wrapText($formattedChange, $maxWidth - 4);
                    foreach ($wrappedLines as $idx => $line) {
                        if ($idx === 0) {
                            $lines[] = '  ' . $this->red('•') . ' ' . $line;
                        } else {
                            $lines[] = '    ' . $line;
                        }
                    }
                }
            }

            // Changes
            $changes = $release->getChanges();
            if (!empty($changes)) {
                $lines[] = '';
                $lines[] = $this->green($this->bold('Changes:'));
                foreach ($changes as $change) {
                    // Format links before wrapping
                    $formattedChange = $this->formatTextWithLinks($change);
                    $wrappedLines = $this->wrapText($formattedChange, $maxWidth - 4);
                    foreach ($wrappedLines as $idx => $line) {
                        if ($idx === 0) {
                            $lines[] = '  ' . $this->green('•') . ' ' . $line;
                        } else {
                            $lines[] = '    ' . $line;
                        }
                    }
                }
            }

            // Fixes
            $fixes = $release->getFixes();
            if (!empty($fixes)) {
                $lines[] = '';
                $lines[] = $this->blue($this->bold('Fixes:'));
                foreach ($fixes as $fix) {
                    // Format links before wrapping
                    $formattedFix = $this->formatTextWithLinks($fix);
                    $wrappedLines = $this->wrapText($formattedFix, $maxWidth - 4);
                    foreach ($wrappedLines as $idx => $line) {
                        if ($idx === 0) {
                            $lines[] = '  ' . $this->blue('•') . ' ' . $line;
                        } else {
                            $lines[] = '    ' . $line;
                        }
                    }
                }
            }
        }
        $lines[] = '';

        $lines[] = $this->gray(str_repeat('─', min($maxWidth, 60)));

        return $lines;
    }

    /**
     * Format release notes in summary mode (all releases combined).
     *
     * @param ReleaseNotesCollection $collection
     * @param int $maxWidth
     * @return array<int, string>
     */
    private function formatSummary(ReleaseNotesCollection $collection, int $maxWidth): array
    {
        $lines = [];
        $lines[] = $this->cyan($this->bold('Release Notes Summary'));
        $lines[] = $this->gray(str_repeat('─', min($maxWidth, 60)));

        // Show version range and count
        $releases = $collection->getReleases();
        $firstTag = $releases[0]->tagName;
        $lastTag = $releases[count($releases) - 1]->tagName;
        $count = $collection->count();

        // Avoid showing duplicate versions when there's only one release
        $releasesInfo = $count === 1
            ? "Release: {$firstTag}"
            : "Releases: {$firstTag} → {$lastTag} ({$count} versions)";

        $lines[] = $this->gray($releasesInfo);
        $lines[] = '';

        // If any release is unstructured, show all bullet points in a flat list
        if ($collection->hasUnstructuredReleases()) {
            $allBulletPoints = $collection->getAllBulletPoints();
            if (!empty($allBulletPoints)) {
                $lines[] = $this->green($this->bold('Changes:'));
                foreach ($allBulletPoints as $bulletPoint) {
                    // Format links before wrapping
                    $formattedBulletPoint = $this->formatTextWithLinks($bulletPoint);
                    $wrappedLines = $this->wrapText($formattedBulletPoint, $maxWidth - 4);
                    foreach ($wrappedLines as $idx => $line) {
                        if ($idx === 0) {
                            $lines[] = '  ' . $this->green('•') . ' ' . $line;
                        } else {
                            $lines[] = '    ' . $line;
                        }
                    }
                }
                $lines[] = '';
            }
            return $lines;
        }

        // All releases are structured - show categorized sections
        // Breaking changes
        $breakingChanges = $collection->getBreakingChanges();
        if (!empty($breakingChanges)) {
            $lines[] = $this->red($this->bold('Breaking Changes:'));
            foreach ($breakingChanges as $change) {
                // Format links before wrapping
                $formattedChange = $this->formatTextWithLinks($change);
                $wrappedLines = $this->wrapText($formattedChange, $maxWidth - 4);
                foreach ($wrappedLines as $idx => $line) {
                    if ($idx === 0) {
                        $lines[] = '  ' . $this->red('•') . ' ' . $line;
                    } else {
                        $lines[] = '    ' . $line;
                    }
                }
            }
            $lines[] = '';
        }

        // Changes
        $changes = $collection->getChanges();
        if (!empty($changes)) {
            $lines[] = $this->green($this->bold('Changes:'));
            foreach ($changes as $change) {
                // Format links before wrapping
                $formattedChange = $this->formatTextWithLinks($change);
                $wrappedLines = $this->wrapText($formattedChange, $maxWidth - 4);
                foreach ($wrappedLines as $idx => $line) {
                    if ($idx === 0) {
                        $lines[] = '  ' . $this->green('•') . ' ' . $line;
                    } else {
                        $lines[] = '    ' . $line;
                    }
                }
            }
            $lines[] = '';
        }

        // Fixes
        $fixes = $collection->getFixes();
        if (!empty($fixes)) {
            $lines[] = $this->blue($this->bold('Fixes:'));
            foreach ($fixes as $fix) {
                // Format links before wrapping
                $formattedFix = $this->formatTextWithLinks($fix);
                $wrappedLines = $this->wrapText($formattedFix, $maxWidth - 4);
                foreach ($wrappedLines as $idx => $line) {
                    if ($idx === 0) {
                        $lines[] = '  ' . $this->blue('•') . ' ' . $line;
                    } else {
                        $lines[] = '    ' . $line;
                    }
                }
            }
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * Strip ANSI color codes from a string.
     *
     * Removes ANSI color codes (e.g., \033[31m for red) to calculate visible text length.
     */
    private function stripAnsiCodes(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    /**
     * Get the visible length of a string (excluding ANSI codes).
     *
     * Uses mb_strwidth() which properly accounts for:
     * - Wide characters (emojis, CJK characters) = 2 columns
     * - Regular characters = 1 column
     * - Zero-width characters = 0 columns
     */
    private function visibleLength(string $text): int
    {
        $stripped = $this->stripAnsiCodes($text);
        return mb_strwidth($stripped);
    }

    /**
     * Wrap text to fit within a maximum width, preserving ANSI codes.
     *
     * This method properly handles text that may contain ANSI escape sequences
     * by measuring only the visible characters when determining line breaks.
     *
     * @param string $text Text to wrap (may contain ANSI codes)
     * @param int $maxWidth Maximum visible width for each line
     * @return array<int, string> Array of wrapped lines with ANSI codes preserved
     */
    private function wrapText(string $text, int $maxWidth): array
    {
        if ($maxWidth <= 0) {
            return [$text];
        }

        $lines = [];
        $paragraphs = explode("\n", $text);

        foreach ($paragraphs as $paragraph) {
            // Preserve empty lines
            if (empty(trim($this->stripAnsiCodes($paragraph)))) {
                $lines[] = '';
                continue;
            }

            // Split into words while preserving spaces
            $words = explode(' ', $paragraph);
            $currentLine = '';

            foreach ($words as $word) {
                // Build test line
                $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;

                // Check visible length (excluding ANSI codes)
                if ($this->visibleLength($testLine) <= $maxWidth) {
                    $currentLine = $testLine;
                } else {
                    // Line would be too long, flush current line
                    if ($currentLine !== '') {
                        $lines[] = $currentLine;
                        $currentLine = $word;
                    } else {
                        // Single word is too long, break it character by character
                        $lines[] = $word;
                    }
                }
            }

            // Add remaining text
            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        return $lines;
    }

    /**
     * Format text by shortening URLs for better display in the TUI.
     * GitHub PR/issue URLs are displayed in compact format (#123).
     * Note: Links are not clickable in TUI mode, only shortened for readability.
     *
     * @param string $text Text containing potential markdown links and URLs
     * @return string Text with shortened URLs
     */
    private function formatTextWithLinks(string $text): string
    {
        // Convert markdown links [text](url) to just the link text
        $text = preg_replace(
            '/\[([^\]]+)\]\([^)]+\)/',
            '$1',
            $text
        );

        // Convert GitHub PR/issue URLs to compact format: https://github.com/owner/repo/pull/123 -> #123
        $text = GithubUrlFormatter::toShortText($text);

        return $text;
    }
}
