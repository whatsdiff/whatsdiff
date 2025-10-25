<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\Tui;

use Chewie\Art;
use Chewie\Concerns\Aligns;
use Chewie\Concerns\DrawsHotkeys;
use Chewie\Concerns\HasMinimumDimensions;
use Chewie\Output\Lines;
use Illuminate\Support\Collection;
use Laravel\Prompts\Themes\Contracts\Scrolling;
use Laravel\Prompts\Themes\Default\Concerns\DrawsScrollbars;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use Laravel\Prompts\Themes\Default\Renderer;

class TerminalUIRenderer extends Renderer implements Scrolling
{
    use Aligns;
    use DrawsHotkeys;
    use DrawsScrollbars;
    use InteractsWithStrings;
    use HasMinimumDimensions;

    public int $rightPaneWidth;
    public int $sideBarWidth;
    protected int $uiWidth;
    protected int $uiHeight;

    protected TerminalUI $terminalUI;
    protected int $contentHeight;

    public function __invoke(TerminalUI $prompt): static
    {
        Art::$dir = __DIR__.'/../../arts';

        $this->minDimensions(
            render: function () use ($prompt) {
                $this->finalRender($prompt);

                return '';
            },
            width: 95,
            height: 25,
            // width: 10,
            // height: 4,
        );

        return $this;
    }

    protected function finalRender(TerminalUI $prompt): void
    {
        $this->terminalUI = $prompt;
        $this->uiWidth = $prompt->terminal()->cols();
        $this->uiHeight = $prompt->terminal()->lines();

        $header = $this->layoutHeader();
        $footer = $this->layoutFooter();

        // Calculate the content height
        $this->contentHeight = $this->uiHeight - $header->count() - $footer->count();
        $this->terminalUI->setScroll('sidebar', $this->contentHeight);
        $this->terminalUI->setScroll('content', $this->contentHeight);

        // Let's make the SideBar 1/3 of the terminal width
        $this->sideBarWidth = intval(ceil($this->uiWidth / 3));
        $this->rightPaneWidth = $this->uiWidth - $this->sideBarWidth;


        // Render the sidebar and the content
        $sidebar = $this->layoutSidebar();
        $content = $this->rightPaneContent();

        // Merge the sidebar and the content
        $lines = Lines::fromColumns([$sidebar, $content])
            ->spacing(2)
            ->alignTop()
            ->lines();

        // Render all the layout
        $header->each($this->line(...));
        $lines->each($this->line(...));
        $this->renderBottom($this->uiHeight, $footer);

    }


    protected function renderBottom(int $height, $bottom)
    {
        // Count line breaks in current string
        $lineBreaks = substr_count($this->output, PHP_EOL);

        $padding = $height - $lineBreaks - count($bottom);

        if ($padding > 0) {
            $this->newLine($padding);
        }

        $bottom->each($this->line(...));
    }

    public function reservedLines(): int
    {
        return 0;
    }

    private function layoutHeader(): Collection
    {
        $logo = $this->blue('Δ') . ' ' . $this->green('⊕') . ' ' . $this->red('⊖');
        $leftText = "What's Diff?";
        $left = ' '.$logo . ' ' . $leftText;

        $githubText = 'Source Code';
        $websiteText = 'whatsdiff.app';
        $github = "\e]8;;https://github.com/whatsdiff/whatsdiff\e\\" . $this->dim($githubText) . "\e]8;;\e\\";
        $website = "\e]8;;https://whatsdiff.app\e\\" . $this->dim($websiteText) . "\e]8;;\e\\";
        $right = $github . ' ' . $website. ' ';

        // Calculate spacing: total width - left text length - right text length
        $leftLength = mb_strwidth('Δ ⊕ ⊖ ' . $leftText);
        $rightLength = mb_strwidth(' '.$githubText . ' ' . $websiteText);
        $spacing = max(1, $this->uiWidth - 2 - $leftLength - $rightLength);

        $headerLine = $left . str_repeat(' ', $spacing) . $right;

        return collect([
            '',
            $this->dim(str_repeat('─', $this->uiWidth)),
            $headerLine,
            $this->dim(str_repeat('─', $this->uiWidth)),
        ]);
    }

    private function layoutFooter(): Collection
    {

        // Hotkeys
        $this->hotkey('↑', 'Up');
        $this->hotkey('↓', 'Down');
        $this->hotkey('Enter', 'Select');
        $this->hotkey('Esc', 'Back', active: ($this->terminalUI->selected > -1));
        $this->hotkey('T', 'Toggle View', active: $this->terminalUI->isPackageSelected());
        $this->hotkeyQuit();

        $name = '';
        $versions = '';
        $semverBadge = '';
        if ($this->terminalUI->getHighlighted('sidebar') !== null) {
            $highlighted = $this->terminalUI->sidebarPackages()[$this->terminalUI->getHighlighted('sidebar')];
            $name = $highlighted['name'] ?? '';
            $status = $highlighted['status'] ?? '';
            $from = $highlighted['from'] ?? '';
            $to = $highlighted['to'] ?? '';

            // Format version display based on status
            $versions = match ($status) {
                'added' => $to,
                'removed' => $from,
                default => $from . ' → ' . $to,
            };

            // Format semver/status badge
            $semverType = $highlighted['semver'] ?? null;
            $semverBadge = $this->formatSemverBadge($status, $semverType);
        }

        $footer = [
            // Bottom border
            $this->dim(str_repeat('─', $this->uiWidth)),

            // Package info and mode indicator
            $this->spaceBetween($this->uiWidth, ...[
                $semverBadge . ' ' . $name,
                $versions,
                ($this->terminalUI->isPackageSelected() ? ($this->terminalUI->summaryMode ? 'Mode: Summary' : 'Mode: Detailed') : '') . ' ',
            ]),

            // Another border
            $this->dim(str_repeat('─', $this->uiWidth)),

            // Show all hotkeys centered
        ];
        $footer = array_merge($footer, $this->centerHorizontally($this->hotkeys(), $this->uiWidth)->toArray());

        return collect($footer);
    }

    public function layoutSidebar(): Collection
    {

        return collect($this->scrollbar(
            visible: array_map(function ($package, $key) {

                $name = $package['name'];
                $type = rand(0, 1) ? 'PHP' : 'JS';

                // // Add Icon before the name
                // $icon = str_pad($type, 5, ' ', STR_PAD_BOTH);
                // $icon = match ($type) {
                //     'PHP' => $this->bgBlue($this->white($icon)),
                //     'JS' => $this->bgYellow($this->black($icon)),
                // };
                //
                // $name = $icon.' '.$name;
                $label = $name;


                $index = array_search($key, array_keys($this->terminalUI->sidebarPackages()));


                // Cursor represented by an arrow
                // $name = $this->terminalUI->highlighted === $index ? '➤'.$name : ' '.$name;
                $label = '  '.$label;

                // Truncate the name to fit in the sidebar and his scrollbar
                $innerWidth = $this->sideBarWidth - 1;
                $label = $this->truncate($label, $innerWidth);
                $label = $this->pad($label, $innerWidth + 1, ' ');


                // If nothing is selected and the cursor is on it, highlight it
                if ($this->terminalUI->selected === null && $this->terminalUI->getHighlighted('sidebar') === $index) {

                    return $this->bgWhite($this->black('›')).' '.$this->white(mb_strcut($label, 2));
                }

                // If it's selected, highlight it with a white background
                if ($this->terminalUI->selected === $index && $this->terminalUI->getHighlighted('sidebar') === $index) {
                    return $this->reset($this->bgWhite($this->black('› '.mb_strcut($label, 2))));
                }

                return $this->gray($label);

            }, $visible = $this->terminalUI->sidebarVisiblePackages(), array_keys($visible)),
            firstVisible: $this->terminalUI->getFirstVisible('sidebar'),
            height: $this->terminalUI->getScroll('sidebar'),
            total: count($this->terminalUI->sidebarPackages()),
            // width: min($this->longest($this->terminalUI->sidebarPackages(), padding: 4), $this->uiWidth - 6)
            width: $this->sideBarWidth,
        ));
    }

    private function rightPaneContent(): Collection
    {
        if (! $this->terminalUI->isPackageSelected()) {
            return collect();
        }

        $content = $this->terminalUI->rightPaneVisible();

        // Put a carret in the highlighted line and space for other lines
        $highlighted = $this->terminalUI->getHighlighted('content');
        $content = array_map(function ($line, $key) use ($highlighted) {
            // Remove any embedded newlines or carriage returns that might cause display issues
            $line = str_replace(["\r\n", "\r", "\n"], '', $line);
            return $key === $highlighted ? '➤ '.$line : '  '.$line;
        }, $content, array_keys($content));

        return collect($this->scrollbar(
            visible: $content,
            firstVisible: $this->terminalUI->getFirstVisible('content'),
            height: $this->terminalUI->getScroll('content'),
            total: count($this->terminalUI->rightPane()),
            width: $this->uiWidth - $this->sideBarWidth - 3,
        ));
    }

    private function formatSemverBadge(string $status, ?string $semverType): string
    {
        // For added/removed packages, show status badge
        if ($status === 'added') {
            return ' ' . $this->bgGreen($this->black(' ADDED '));
        }

        if ($status === 'removed') {
            return ' ' . $this->bgRed($this->white(' REMOVED '));
        }

        // For updated/downgraded packages, show semver badge if available
        if ($semverType) {
            $badge = strtoupper($semverType);
            return match ($semverType) {
                'major' => ' ' . $this->bgRed($this->white(' ' . $badge . ' ')),
                'minor' => ' ' . $this->bgBlue($this->white(' ' . $badge . ' ')),
                'patch' => ' ' . $this->bgGreen($this->black(' ' . $badge . ' ')),
                default => '',
            };
        }

        return '';
    }

}
