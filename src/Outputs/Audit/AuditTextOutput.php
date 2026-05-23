<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\Audit;

use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Data\AuditResult;
use Whatsdiff\Data\PackageAudit;
use Whatsdiff\Data\SecurityAdvisory;
use Whatsdiff\Enums\Severity;

class AuditTextOutput
{
    public function __construct(private readonly bool $useAnsi = true) {}

    public function format(AuditResult $result, OutputInterface $output): void
    {
        if (! $result->hasVulnerabilities()) {
            $output->writeln($this->colorize('No known security advisories affect your installed dependencies.', "\033[32m"));

            return;
        }

        $output->writeln($this->buildHeader($result));
        $output->writeln('');

        $nameWidth = (int) $result->audits->max(fn (PackageAudit $audit) => mb_strlen($audit->name));

        foreach ($result->audits as $audit) {
            $this->writeAuditBlock($audit, $nameWidth, $output);
        }

        $output->writeln('');
        $output->writeln($this->buildLegend($result->countBySeverity()));
    }

    private function writeAuditBlock(PackageAudit $audit, int $nameWidth, OutputInterface $output): void
    {
        $name = $this->colorize(str_pad($audit->name, $nameWidth), "\033[1m");
        $installed = $this->colorize($audit->installedVersion, "\033[2m");

        if ($audit->suggestedFixVersion !== null) {
            $fix = $this->colorize($audit->suggestedFixVersion, "\033[32m");
            $output->writeln("  {$name}  {$installed} → fixed in {$fix}");
        } else {
            $note = $this->colorize('(no safe upgrade)', "\033[2m");
            $output->writeln("  {$name}  {$installed} {$note}");
        }

        foreach ($audit->advisories as $advisory) {
            $bullet = $this->severityBullet($advisory->severity);
            $idText = $advisory->cve ?? $advisory->advisoryId;
            $cveId = $this->hyperlink($idText, $advisory->link);
            $title = $this->cleanTitle($advisory);
            $titleStyled = $this->colorize($title, "\033[2m");

            $output->writeln("     {$bullet} {$cveId}  {$titleStyled}");
        }
    }

    private function buildHeader(AuditResult $result): string
    {
        $packages = $result->audits->count();
        $advisories = $result->totalAdvisories();
        $packageLabel = $packages === 1 ? 'vulnerable package' : 'vulnerable packages';
        $advisoryLabel = $advisories === 1 ? 'security advisory' : 'security advisories';

        $suffix = match (true) {
            $result->isDiffMode => $this->buildDiffSuffix($result),
            $result->fromCommit !== null => ' at '.substr($result->fromCommit, 0, 7),
            default => '',
        };

        $line = "{$packages} {$packageLabel}, {$advisories} {$advisoryLabel}{$suffix}";

        return $this->colorize($line, "\033[1m");
    }

    private function buildDiffSuffix(AuditResult $result): string
    {
        $from = $result->fromCommit !== null ? substr($result->fromCommit, 0, 7) : 'none';
        $to = $result->toCommit !== null ? substr($result->toCommit, 0, 7) : 'HEAD';

        return " newly introduced between {$from} and {$to}";
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function buildLegend(array $counts): string
    {
        $parts = [];

        foreach ([Severity::Critical, Severity::High, Severity::Medium, Severity::Low] as $severity) {
            $count = $counts[$severity->value] ?? 0;
            if ($count === 0) {
                continue;
            }
            $bullet = $this->severityBullet($severity);
            $parts[] = "{$bullet} ".strtolower($severity->label())." ({$count})";
        }

        $unrated = $counts[Severity::Unknown->value] ?? 0;
        if ($unrated > 0) {
            $bullet = $this->severityBullet(Severity::Unknown);
            $parts[] = "{$bullet} rating pending ({$unrated})";
        }

        $prefix = $this->colorize('Legend:', "\033[2m");

        return "{$prefix} ".implode('  ', $parts);
    }

    private function severityBullet(Severity $severity): string
    {
        $glyph = $severity === Severity::Unknown ? '○' : '●';

        if (! $this->useAnsi) {
            return $glyph;
        }

        $color = match ($severity) {
            Severity::Critical => "\033[1;31m",
            Severity::High => "\033[31m",
            Severity::Medium => "\033[33m",
            Severity::Low => "\033[36m",
            Severity::Unknown => "\033[2;37m",
        };

        return $color.$glyph."\033[0m";
    }

    /**
     * Wrap text in an OSC 8 hyperlink escape so terminals like iTerm2, WezTerm,
     * Kitty, Ghostty and recent VS Code render it clickable in place. Terminals
     * that don't understand OSC 8 silently ignore the escape and display the
     * text plainly.
     */
    private function hyperlink(string $text, string $url): string
    {
        if (! $this->useAnsi || $url === '') {
            return $text;
        }

        $st = "\033\\";

        return "\033]8;;{$url}{$st}{$text}\033]8;;{$st}";
    }

    private function cleanTitle(SecurityAdvisory $advisory): string
    {
        $title = $advisory->title;

        if ($advisory->cve !== null && $advisory->cve !== '') {
            $prefix = $advisory->cve.':';
            if (str_starts_with($title, $prefix)) {
                $title = ltrim(substr($title, strlen($prefix)));
            }
        }

        return $title;
    }

    private function colorize(string $text, string $ansi): string
    {
        if (! $this->useAnsi) {
            return $text;
        }

        return $ansi.$text."\033[0m";
    }
}
