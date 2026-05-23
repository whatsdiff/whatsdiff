<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Symfony\Component\Console\Output\BufferedOutput;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Data\AuditResult;
use Whatsdiff\Data\PackageAudit;
use Whatsdiff\Data\SecurityAdvisory;
use Whatsdiff\Enums\Severity;
use Whatsdiff\Outputs\Audit\AuditTextOutput;

function makeAudit(string $name, string $version, ?string $fix, array $advisories): PackageAudit
{
    return new PackageAudit(
        name: $name,
        type: PackageManagerType::COMPOSER,
        installedVersion: $version,
        advisories: $advisories,
        suggestedFixVersion: $fix,
    );
}

it('renders a clean bullet-list with no [UNKNOWN] tags or bucket headers', function () {
    $audit = makeAudit(
        'symfony/cache',
        'v8.0.5',
        'v8.0.12',
        [
            new SecurityAdvisory(
                advisoryId: 'PKSA-1',
                cve: 'CVE-2026-45073',
                title: 'CVE-2026-45073: SQL Injection in PdoAdapter::doClear()',
                link: 'https://symfony.com/cve-2026-45073',
                affectedVersions: '>=8.0.0,<8.0.12',
                severity: Severity::Unknown,
            ),
        ]
    );

    $result = new AuditResult(audits: new Collection([$audit]));
    $output = new BufferedOutput();

    (new AuditTextOutput(useAnsi: false))->format($result, $output);
    $text = $output->fetch();

    // No bucket header noise
    expect($text)->not->toContain('UNKNOWN');
    expect($text)->not->toContain('UNRATED (');
    // The duplicate "CVE-XXX:" prefix is stripped from titles
    expect($text)->toContain('SQL Injection in PdoAdapter::doClear()');
    expect($text)->not->toContain('CVE-2026-45073: SQL');
    // CVE id still appears as the bullet line label
    expect($text)->toContain('CVE-2026-45073');
    // Hollow bullet for unrated severity
    expect($text)->toContain('○');
    // Legend reports rating pending
    expect($text)->toContain('rating pending (1)');
});

it('uses a filled colored bullet for rated severities', function () {
    $audit = makeAudit(
        'pkg/critical',
        'v1.0.0',
        'v1.1.0',
        [
            new SecurityAdvisory(
                advisoryId: 'GHSA-x',
                cve: 'CVE-2024-1111',
                title: 'Remote code execution',
                link: '',
                affectedVersions: '>=1.0.0,<1.1.0',
                severity: Severity::Critical,
            ),
        ]
    );

    $result = new AuditResult(audits: new Collection([$audit]));
    $output = new BufferedOutput();

    (new AuditTextOutput(useAnsi: false))->format($result, $output);
    $text = $output->fetch();

    expect($text)->toContain('●');
    expect($text)->toContain('critical (1)');
    expect($text)->not->toContain('CRITICAL (');
});

it('wraps the CVE id in an OSC 8 hyperlink when ANSI is enabled', function () {
    $audit = makeAudit(
        'pkg/x',
        'v1.0.0',
        null,
        [
            new SecurityAdvisory(
                advisoryId: 'GHSA-y',
                cve: 'CVE-2024-2222',
                title: 'Something',
                link: 'https://example.com/advisory',
                affectedVersions: '>=1.0.0',
                severity: Severity::High,
            ),
        ]
    );

    $result = new AuditResult(audits: new Collection([$audit]));
    $output = new BufferedOutput();

    (new AuditTextOutput(useAnsi: true))->format($result, $output);
    $text = $output->fetch();

    // OSC 8 escape opens with ESC]8;;URL ST and closes with ESC]8;; ST
    expect($text)->toContain("\033]8;;https://example.com/advisory");
    // URL no longer printed on its own line
    $urlLines = array_filter(
        explode("\n", $text),
        fn (string $line) => trim($line) === 'https://example.com/advisory'
    );
    expect($urlLines)->toBeEmpty();
});

it('reports a happy message when there are no vulnerabilities', function () {
    $result = new AuditResult(audits: new Collection());
    $output = new BufferedOutput();

    (new AuditTextOutput(useAnsi: false))->format($result, $output);

    expect($output->fetch())->toContain('No known security advisories');
});
