<?php

declare(strict_types=1);

namespace Whatsdiff\Outputs\Tui;

use Chewie\Concerns\CreatesAnAltScreen;
use Chewie\Concerns\RegistersThemes;
use Chewie\Input\KeyPressListener;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Whatsdiff\Analyzers\PackageManagerType;
use Whatsdiff\Analyzers\Registries\NpmRegistry;
use Whatsdiff\Analyzers\Registries\PackagistRegistry;
use Whatsdiff\Analyzers\ReleaseNotes\ReleaseNotesResolver;
use Whatsdiff\Data\ReleaseNotesCollection;
use Whatsdiff\Services\GitRepository;

class TerminalUI extends Prompt
{
    use RegistersThemes;
    use CreatesAnAltScreen;
    use MultipleScrolling;

    public array $packages;
    public ?int $selected = null;
    public bool $summaryMode = false;

    private array $changelogCache = [];

    public function __construct(
        array $packages,
        private readonly GitRepository $gitRepository,
        private readonly PackagistRegistry $packagistRegistry,
        private readonly NpmRegistry $npmRegistry,
        private readonly ReleaseNotesResolver $releaseNotesResolver,
    ) {
        // Initialize Laravel Prompts required property
        $this->required = false;

        // Initialize data we are working with
        $this->packages = array_values($packages);
        // Remove duplication - was used for testing only
        // $this->packages = array_merge($this->packages, $this->packages);

        // Register the theme
        $this->registerTheme(TerminalUIRenderer::class);

        // Set the scroll area
        $this->setScroll('sidebar', 5); // Default one, recalculated later
        $this->initializeMultipleScrolling('sidebar', 0);

        $this->setScroll('content', 5); // Default one, recalculated later
        $this->initializeMultipleScrolling('content', 0);

        $this->createAltScreen();

        // This actions will trigger a re-rendering
        KeyPressListener::for($this)
            ->listenForQuit()
            ->onUp(fn () => $this->previous())
            ->onDown(fn () => $this->next())
            ->on(Key::ENTER, fn () => $this->enter())
            ->on(Key::ESCAPE, fn () => $this->escape())
            ->on(['t', 'T'], fn () => $this->toggleSummaryMode())
            // ->on([Key::HOME, Key::CTRL_A], fn() => $this->highlighted !== null ? $this->highlight(0) : null)
            // ->on([Key::END, Key::CTRL_E],
            //     fn() => $this->highlighted !== null ? $this->highlight(count($this->packages) - 1) : null)
            ->listen();
    }

    public function sidebarPackages()
    {
        return collect($this->packages)
            ->toArray();
    }

    public function sidebarVisiblePackages(): array
    {
        return $this->sliceVisible('sidebar', $this->sidebarPackages());
    }

    public function rightPane(): array
    {
        if (! $this->isPackageSelected()) {
            return [];
        }

        // Get the selected package
        $package = $this->packages[$this->selected];

        // Fetch changelog if not already cached
        if (!isset($this->changelogCache[$package['name']])) {
            $this->changelogCache[$package['name']] = $this->fetchChangelogForPackage($package);
        }

        $changelog = $this->changelogCache[$package['name']];

        if ($changelog === null || $changelog->isEmpty()) {
            return [
                '',
                'No changelog available for this package.',
                '',
                'This could be because:',
                '- The package has no releases on GitHub',
                '- The repository URL is not available',
                '- The package versions could not be determined',
                '',
            ];
        }

        // Calculate right pane width
        $uiWidth = self::terminal()->cols();
        $rightPaneWidth = intval(
            $uiWidth
            - ceil($uiWidth / 3)  // sidebar width
            - 5                    // scrollbar and spacing
            - 2                    // borders/padding
            - 3                    // prefix ('➤ ' or '  ') added by TerminalUIRenderer
        );

        // Format the changelog
        $formatter = new ChangelogFormatter();
        return $formatter->format($changelog, $this->summaryMode, $rightPaneWidth);
    }

    /**
     * Fetch changelog for a package.
     */
    private function fetchChangelogForPackage(array $package): ?ReleaseNotesCollection
    {
        try {
            // Determine package manager type
            $packageManagerType = PackageManagerType::from($package['type']);

            // Get repository URL from registry
            $registry = $packageManagerType === PackageManagerType::COMPOSER
                ? $this->packagistRegistry
                : $this->npmRegistry;

            $repositoryUrl = $registry->getRepositoryUrl($package['name']);
            if ($repositoryUrl === null) {
                return null;
            }

            // Get from and to versions
            $fromVersion = $package['from'];
            $toVersion = $package['to'];

            // Handle removed packages (no "to" version) - can't show changelog
            if ($toVersion === null) {
                return null;
            }

            // Handle added packages (no "from" version) - show the added version's changelog
            if ($fromVersion === null) {
                $fromVersion = $toVersion;
            }

            // Strip 'v' prefix from versions
            $fromVersion = ltrim($fromVersion, 'vV');
            $toVersion = ltrim($toVersion, 'vV');

            // Get local path to package
            $basePath = $this->gitRepository->getGitRoot();
            $relativePath = $packageManagerType === PackageManagerType::COMPOSER
                ? "vendor/{$package['name']}"
                : "node_modules/{$package['name']}";
            $localPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;
            if (!file_exists($localPath)) {
                $localPath = null;
            }

            // Fetch release notes
            return $this->releaseNotesResolver->resolve(
                package: $package['name'],
                fromVersion: $fromVersion,
                toVersion: $toVersion,
                repositoryUrl: $repositoryUrl,
                packageManagerType: $packageManagerType,
                localPath: $localPath,
                includePrerelease: false
            );
        } catch (\Exception $e) {
            return null;
        }
    }


    public function rightPaneVisible()
    {
        return $this->sliceVisible('content', $this->rightPane());
    }

    protected function next()
    {
        if ($this->isPackageSelected()) {
            $this->highlightNext('content', count($this->rightPane()), true);

            return;
        }

        $this->highlightNext('sidebar', count($this->packages), true);
    }

    protected function previous()
    {
        if ($this->isPackageSelected()) {
            $this->highlightPrevious('content', count($this->rightPane()), true);

            return;
        }
        $this->highlightPrevious('sidebar', count($this->packages), true);
    }

    public function isPackageSelected(): bool
    {
        return $this->selected !== null;
    }

    public function value(): mixed
    {
        return null;
    }

    private function enter(): void
    {
        if (! $this->isPackageSelected()) {
            $this->selected = $this->getHighlighted('sidebar');
            // Reset content scroll when selecting a new package
            $this->initializeMultipleScrolling('content', 0);
        }
    }

    private function escape(): void
    {
        if ($this->isPackageSelected()) {
            $this->selected = null;
            // Reset to detailed mode when going back to sidebar
            $this->summaryMode = false;
        }
    }

    private function toggleSummaryMode(): void
    {
        // Only toggle if a package is selected
        if ($this->isPackageSelected()) {
            $this->summaryMode = !$this->summaryMode;
            // Reset scroll position when toggling
            $this->initializeMultipleScrolling('content', 0);
        }
    }
}
