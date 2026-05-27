<?php

declare(strict_types=1);

use Whatsdiff\Analyzers\BaseAnalyzer;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\Registries\RegistryInterface;
use Whatsdiff\Analyzers\SecurityAdvisories\SeverityFetcherInterface;
use Whatsdiff\Analyzers\SecurityAdvisories\SeverityResolver;
use Whatsdiff\Data\SecurityAdvisory;
use Whatsdiff\Enums\Severity;
use Whatsdiff\Services\AnalyzerRegistry;
use Whatsdiff\Services\AuditCalculator;
use Whatsdiff\Services\FixSuggestionResolver;
use Whatsdiff\Services\GitRepository;

beforeEach(function () {
    $this->workingDir = sys_get_temp_dir().'/whatsdiff-audit-test-'.bin2hex(random_bytes(5));
    mkdir($this->workingDir);
    $this->originalDir = getcwd();
    chdir($this->workingDir);
});

afterEach(function () {
    chdir($this->originalDir);
    $files = glob($this->workingDir.'/*') ?: [];
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($this->workingDir);
    Mockery::close();
});

function buildAuditCalculator(
    array $advisoriesByPackage,
    ?RegistryInterface $registryOverride = null,
    ?SeverityResolver $severityResolver = null,
): AuditCalculator {
    $registry = $registryOverride ?? Mockery::mock(RegistryInterface::class);
    if ($registryOverride === null) {
        $registry->shouldReceive('getSecurityAdvisories')
            ->andReturn($advisoriesByPackage);
    }

    $analyzer = Mockery::mock(BaseAnalyzer::class);
    $analyzer->shouldReceive('getRegistry')->andReturn($registry);

    $analyzerRegistry = Mockery::mock(AnalyzerRegistry::class);
    $analyzerRegistry->shouldReceive('get')->andReturn($analyzer);

    $fixResolver = new FixSuggestionResolver($analyzerRegistry);
    $git = Mockery::mock(GitRepository::class);

    // Default resolver has no fetchers — leaves advisories untouched
    $severityResolver = $severityResolver ?? new SeverityResolver;

    return new AuditCalculator($analyzerRegistry, $git, $fixResolver, $severityResolver);
}

it('reports installed packages affected by an advisory', function () {
    $composerLock = generateComposerLock(['guzzlehttp/guzzle' => '6.5.0']);
    file_put_contents($this->workingDir.'/composer.lock', $composerLock);

    $advisory = new SecurityAdvisory(
        advisoryId: 'GHSA-test-1',
        cve: 'CVE-2022-0000',
        title: 'Affects 6.x',
        link: 'https://example.com',
        affectedVersions: '>=6.0.0,<7.0.0',
        severity: Severity::High,
    );

    $calculator = buildAuditCalculator(['guzzlehttp/guzzle' => [$advisory]]);
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->withFixSuggestions(false)
        ->run();

    expect($result->hasVulnerabilities())->toBeTrue();
    expect($result->audits)->toHaveCount(1);
    $audit = $result->audits->first();
    expect($audit->name)->toBe('guzzlehttp/guzzle');
    expect($audit->advisories)->toHaveCount(1);
    expect($audit->maxSeverity())->toBe(Severity::High);
});

it('ignores advisories whose affectedVersions do not match the installed version', function () {
    $composerLock = generateComposerLock(['guzzlehttp/guzzle' => '7.8.0']);
    file_put_contents($this->workingDir.'/composer.lock', $composerLock);

    $advisory = new SecurityAdvisory(
        advisoryId: 'GHSA-test-2',
        cve: null,
        title: 'Affects 6.x only',
        link: '',
        affectedVersions: '>=6.0.0,<7.0.0',
        severity: Severity::High,
    );

    $calculator = buildAuditCalculator(['guzzlehttp/guzzle' => [$advisory]]);
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->withFixSuggestions(false)
        ->run();

    expect($result->hasVulnerabilities())->toBeFalse();
    expect($result->totalAdvisories())->toBe(0);
});

it('suggests the lowest safe upgrade version', function () {
    $composerLock = generateComposerLock(['foo/bar' => '1.0.0']);
    file_put_contents($this->workingDir.'/composer.lock', $composerLock);

    $advisory = new SecurityAdvisory(
        advisoryId: 'GHSA-test-3',
        cve: null,
        title: 'Affects 1.x up to 1.2.0',
        link: '',
        affectedVersions: '>=1.0.0,<1.2.1',
        severity: Severity::Medium,
    );

    $registry = Mockery::mock(RegistryInterface::class);
    $registry->shouldReceive('getSecurityAdvisories')
        ->andReturn(['foo/bar' => [$advisory]]);
    $registry->shouldReceive('getVersions')
        ->andReturn(['1.1.0', '1.2.0', '1.2.1', '1.3.0', '2.0.0']);

    $calculator = buildAuditCalculator([], $registry);
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->run();

    expect($result->audits)->toHaveCount(1);
    expect($result->audits->first()->suggestedFixVersion)->toBe('1.2.1');
});

it('returns null suggested fix when no safe version exists', function () {
    $composerLock = generateComposerLock(['foo/bar' => '1.0.0']);
    file_put_contents($this->workingDir.'/composer.lock', $composerLock);

    $advisory = new SecurityAdvisory(
        advisoryId: 'GHSA-test-4',
        cve: null,
        title: 'All versions vulnerable',
        link: '',
        affectedVersions: '>=0.0.1',
        severity: Severity::Critical,
    );

    $registry = Mockery::mock(RegistryInterface::class);
    $registry->shouldReceive('getSecurityAdvisories')
        ->andReturn(['foo/bar' => [$advisory]]);
    $registry->shouldReceive('getVersions')
        ->andReturn(['1.1.0', '1.2.0', '2.0.0']);

    $calculator = buildAuditCalculator([], $registry);
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->run();

    expect($result->audits->first()->suggestedFixVersion)->toBeNull();
});

it('returns empty result when no lockfile exists', function () {
    $calculator = buildAuditCalculator([]);
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->run();

    expect($result->hasVulnerabilities())->toBeFalse();
    expect($result->audits)->toBeEmpty();
});

it('counts advisories by severity', function () {
    $composerLock = generateComposerLock([
        'pkg/critical' => '1.0.0',
        'pkg/low' => '1.0.0',
    ]);
    file_put_contents($this->workingDir.'/composer.lock', $composerLock);

    $criticalAdvisory = new SecurityAdvisory('A1', null, 'crit', '', '>=1.0.0', Severity::Critical);
    $lowAdvisory = new SecurityAdvisory('A2', null, 'low', '', '>=1.0.0', Severity::Low);

    $calculator = buildAuditCalculator([
        'pkg/critical' => [$criticalAdvisory],
        'pkg/low' => [$lowAdvisory],
    ]);
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->withFixSuggestions(false)
        ->run();

    $counts = $result->countBySeverity();
    expect($counts[Severity::Critical->value])->toBe(1);
    expect($counts[Severity::Low->value])->toBe(1);
    expect($result->hasAnyAtOrAbove(Severity::High))->toBeTrue();
    expect($result->hasAnyAtOrAbove(Severity::Critical))->toBeTrue();
});

it('treats unrated advisories as meeting any threshold by default (fail-safe for CI)', function () {
    $composerLock = generateComposerLock(['pkg/unrated' => '1.0.0']);
    file_put_contents($this->workingDir.'/composer.lock', $composerLock);

    $unrated = new SecurityAdvisory('A1', 'CVE-2026-9', 't', '', '>=1.0.0', Severity::Unknown);
    $calculator = buildAuditCalculator(['pkg/unrated' => [$unrated]]);
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->withFixSuggestions(false)
        ->run();

    expect($result->hasAnyAtOrAbove(Severity::Low))->toBeTrue();
    expect($result->hasAnyAtOrAbove(Severity::Critical))->toBeTrue();
});

it('excludes unrated advisories from --fail-on when countUnrated is false', function () {
    $composerLock = generateComposerLock([
        'pkg/unrated' => '1.0.0',
        'pkg/low' => '1.0.0',
    ]);
    file_put_contents($this->workingDir.'/composer.lock', $composerLock);

    $unrated = new SecurityAdvisory('A1', 'CVE-2026-9', 't', '', '>=1.0.0', Severity::Unknown);
    $low = new SecurityAdvisory('A2', 'CVE-2026-10', 't', '', '>=1.0.0', Severity::Low);
    $calculator = buildAuditCalculator([
        'pkg/unrated' => [$unrated],
        'pkg/low' => [$low],
    ]);
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->withFixSuggestions(false)
        ->run();

    // With --allow-unrated equivalent: Unknown ignored, Low present
    expect($result->hasAnyAtOrAbove(Severity::Low, countUnrated: false))->toBeTrue();
    expect($result->hasAnyAtOrAbove(Severity::High, countUnrated: false))->toBeFalse();
});

it('backfills Unknown severity from the SeverityResolver chain', function () {
    $composerLock = generateComposerLock(['foo/bar' => '1.0.0']);
    file_put_contents($this->workingDir.'/composer.lock', $composerLock);

    $advisory = new SecurityAdvisory(
        advisoryId: 'PKSA-x',
        cve: 'CVE-2026-99999',
        title: 'unrated by packagist',
        link: '',
        affectedVersions: '>=1.0.0,<2.0.0',
        severity: Severity::Unknown,
    );

    $fetcher = Mockery::mock(SeverityFetcherInterface::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->with('CVE-2026-99999')
        ->andReturn(Severity::High);

    $resolver = new SeverityResolver;
    $resolver->addFetcher($fetcher);

    $calculator = buildAuditCalculator(
        ['foo/bar' => [$advisory]],
        null,
        $resolver,
    );
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->withFixSuggestions(false)
        ->run();

    $audit = $result->audits->first();
    expect($audit->advisories[0]->severity)->toBe(Severity::High);
});

it('caches resolved severities per CVE within a single run', function () {
    $composerLock = generateComposerLock([
        'pkg/a' => '1.0.0',
        'pkg/b' => '1.0.0',
    ]);
    file_put_contents($this->workingDir.'/composer.lock', $composerLock);

    // Same CVE shared by both packages — should only trigger one resolver call
    $advisoryA = new SecurityAdvisory('A1', 'CVE-2026-1', 't', '', '>=1.0.0', Severity::Unknown);
    $advisoryB = new SecurityAdvisory('A2', 'CVE-2026-1', 't', '', '>=1.0.0', Severity::Unknown);

    $fetcher = Mockery::mock(SeverityFetcherInterface::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->with('CVE-2026-1')
        ->andReturn(Severity::Medium);

    $resolver = new SeverityResolver;
    $resolver->addFetcher($fetcher);

    $calculator = buildAuditCalculator(
        ['pkg/a' => [$advisoryA], 'pkg/b' => [$advisoryB]],
        null,
        $resolver,
    );
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->withFixSuggestions(false)
        ->run();

    expect($result->audits)->toHaveCount(2);
    foreach ($result->audits as $audit) {
        expect($audit->advisories[0]->severity)->toBe(Severity::Medium);
    }
});

it('reports installed pnpm packages affected by an advisory', function () {
    $pnpmLock = generatePnpmLock(['lodash' => '4.17.20']);
    file_put_contents($this->workingDir.'/pnpm-lock.yaml', $pnpmLock);

    $advisory = new SecurityAdvisory(
        advisoryId: 'GHSA-pnpm-1',
        cve: 'CVE-2022-0001',
        title: 'Prototype pollution in lodash',
        link: 'https://example.com',
        affectedVersions: '>=4.0.0,<4.17.21',
        severity: Severity::High,
    );

    $calculator = buildAuditCalculator(['lodash' => [$advisory]]);
    $result = $calculator
        ->for(PackageManagerType::PNPM)
        ->withFixSuggestions(false)
        ->run();

    expect($result->hasVulnerabilities())->toBeTrue();
    expect($result->audits)->toHaveCount(1);
    $audit = $result->audits->first();
    expect($audit->name)->toBe('lodash');
    expect($audit->maxSeverity())->toBe(Severity::High);
});

it('sorts audits by severity descending', function () {
    $composerLock = generateComposerLock([
        'pkg/low' => '1.0.0',
        'pkg/critical' => '1.0.0',
    ]);
    file_put_contents($this->workingDir.'/composer.lock', $composerLock);

    $low = new SecurityAdvisory('A1', null, 'low', '', '>=1.0.0', Severity::Low);
    $critical = new SecurityAdvisory('A2', null, 'crit', '', '>=1.0.0', Severity::Critical);

    $calculator = buildAuditCalculator([
        'pkg/low' => [$low],
        'pkg/critical' => [$critical],
    ]);
    $result = $calculator
        ->for(PackageManagerType::COMPOSER)
        ->withFixSuggestions(false)
        ->run();

    expect($result->audits->first()->name)->toBe('pkg/critical');
});
