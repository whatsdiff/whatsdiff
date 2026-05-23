<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\Audit;

use Symfony\Component\Console\Output\OutputInterface;
use Whatsdiff\Data\AuditResult;
use Whatsdiff\Data\PackageAudit;
use Whatsdiff\Data\SecurityAdvisory;

class AuditJsonOutput
{
    public function format(AuditResult $result, OutputInterface $output): void
    {
        $data = [
            'mode' => $result->isDiffMode ? 'diff' : 'current',
            'from' => $result->fromCommit,
            'to' => $result->toCommit,
            'summary' => [
                'vulnerable_packages' => $result->audits->count(),
                'total_advisories' => $result->totalAdvisories(),
                'by_severity' => $result->countBySeverity(),
                'max_severity' => $result->maxSeverity()->value,
            ],
            'audits' => $result->audits->map(fn (PackageAudit $audit) => [
                'name' => $audit->name,
                'type' => $audit->type->value,
                'installed_version' => $audit->installedVersion,
                'suggested_fix_version' => $audit->suggestedFixVersion,
                'max_severity' => $audit->maxSeverity()->value,
                'advisories' => array_map(fn (SecurityAdvisory $a) => [
                    'advisory_id' => $a->advisoryId,
                    'cve' => $a->cve,
                    'title' => $a->title,
                    'link' => $a->link,
                    'affected_versions' => $a->affectedVersions,
                    'severity' => $a->severity->value,
                ], $audit->advisories),
            ])->values()->toArray(),
        ];

        $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
