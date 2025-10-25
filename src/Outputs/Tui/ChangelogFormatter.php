<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\Tui;

use Laravel\Prompts\Concerns\Colors;
use Whatsdiff\Data\ReleaseNote;
use Whatsdiff\Data\ReleaseNotesCollection;

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
                '',
                $this->gray('No release notes available for this package.'),
                '',
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
        $lines[] = '';
        $lines[] = $this->cyan($this->bold('Release Notes'));
        $lines[] = $this->gray(str_repeat('─', min($maxWidth, 60)));
        $lines[] = '';

        foreach ($collection as $release) {
            $lines = array_merge($lines, $this->formatRelease($release, $maxWidth));
        }

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

        $lines[] = '';

        // Description (if any)
        $description = $release->getDescription();
        if (!empty($description)) {
            $descriptionLines = $this->wrapText($description, $maxWidth);
            foreach ($descriptionLines as $line) {
                $lines[] = $line;
            }
            $lines[] = '';
        }

        // Breaking changes
        $breakingChanges = $release->getBreakingChanges();
        if (!empty($breakingChanges)) {
            $lines[] = $this->red($this->bold('Breaking Changes:'));
            foreach ($breakingChanges as $change) {
                $wrappedLines = $this->wrapText($change, $maxWidth - 4);
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
        $changes = $release->getChanges();
        if (!empty($changes)) {
            $lines[] = $this->green($this->bold('Changes:'));
            foreach ($changes as $change) {
                $wrappedLines = $this->wrapText($change, $maxWidth - 4);
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
        $fixes = $release->getFixes();
        if (!empty($fixes)) {
            $lines[] = $this->blue($this->bold('Fixes:'));
            foreach ($fixes as $fix) {
                $wrappedLines = $this->wrapText($fix, $maxWidth - 4);
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

        $lines[] = $this->gray(str_repeat('─', min($maxWidth, 60)));
        $lines[] = '';

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
        $lines[] = '';
        $lines[] = $this->cyan($this->bold('Release Notes Summary'));
        $lines[] = $this->gray(str_repeat('─', min($maxWidth, 60)));
        $lines[] = '';
        $lines[] = $this->gray('Total Releases: ' . $collection->count());
        $lines[] = '';

        // Breaking changes
        $breakingChanges = $collection->getBreakingChanges();
        if (!empty($breakingChanges)) {
            $lines[] = $this->red($this->bold('Breaking Changes:'));
            foreach ($breakingChanges as $change) {
                $wrappedLines = $this->wrapText($change, $maxWidth - 4);
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
                $wrappedLines = $this->wrapText($change, $maxWidth - 4);
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
                $wrappedLines = $this->wrapText($fix, $maxWidth - 4);
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
     * Strip ANSI escape sequences from a string.
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
}
