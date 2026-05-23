<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\Audit;

use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Data\AuditResult;
use Whatsdiff\Data\PackageAudit;
use Whatsdiff\Enums\Severity;

class AuditMarkdownOutput
{
    public function format(AuditResult $result, OutputInterface $output): void
    {
        if (! $result->hasVulnerabilities()) {
            $output->writeln('# Security Audit');
            $output->writeln('');
            $output->writeln('No known security advisories affect your installed dependencies.');

            return;
        }

        $output->writeln('# Security Audit');
        $output->writeln('');

        if ($result->isDiffMode) {
            $from = $result->fromCommit !== null ? substr($result->fromCommit, 0, 7) : 'none';
            $to = $result->toCommit !== null ? substr($result->toCommit, 0, 7) : 'HEAD';
            $output->writeln("*New advisories between `{$from}` and `{$to}`*");
        } elseif ($result->fromCommit !== null) {
            $output->writeln('*Audit at `'.substr($result->fromCommit, 0, 7).'`*');
        }
        $output->writeln('');

        $counts = $result->countBySeverity();
        $output->writeln('## Summary');
        $output->writeln('');
        $output->writeln('| Severity | Count |');
        $output->writeln('|----------|-------|');
        foreach ([Severity::Critical, Severity::High, Severity::Medium, Severity::Low, Severity::Unknown] as $severity) {
            $count = $counts[$severity->value] ?? 0;
            if ($count > 0) {
                $output->writeln("| {$severity->label()} | {$count} |");
            }
        }
        $output->writeln('');

        $grouped = $result->audits->groupBy(fn (PackageAudit $audit) => $audit->maxSeverity()->value);

        foreach ([Severity::Critical, Severity::High, Severity::Medium, Severity::Low, Severity::Unknown] as $severity) {
            $audits = $grouped->get($severity->value);
            if ($audits === null || $audits->isEmpty()) {
                continue;
            }

            $output->writeln("## {$severity->label()}");
            $output->writeln('');

            foreach ($audits as $audit) {
                $fix = $audit->suggestedFixVersion !== null
                    ? "**Fix available:** upgrade to `{$audit->suggestedFixVersion}`"
                    : '**No safe upgrade found**';

                $output->writeln("### {$audit->name} (`{$audit->installedVersion}`)");
                $output->writeln('');
                $output->writeln($fix);
                $output->writeln('');

                $output->writeln('| ID | Severity | Title |');
                $output->writeln('|----|----------|-------|');
                foreach ($audit->advisories as $advisory) {
                    $id = $advisory->cve ?? $advisory->advisoryId;
                    $idCell = $advisory->link !== '' ? "[{$id}]({$advisory->link})" : $id;
                    $title = str_replace('|', '\\|', $advisory->title);
                    $output->writeln("| {$idCell} | {$advisory->severity->label()} | {$title} |");
                }
                $output->writeln('');
            }
        }
    }
}
