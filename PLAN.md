# Whatsdiff Architecture - Remaining Work

## Current State

The core architecture has been successfully implemented:

✅ **Lock File Parsers** (`src/Analyzers/LockFile/`)
- `LockFileInterface` - Contract for stateful lock file parsers
- `ComposerLockFile` - Parses composer.lock files
- `NpmPackageLockFile` - Parses package-lock.json files

✅ **Registry Clients** (`src/Analyzers/Registries/`)
- `RegistryInterface` - Contract for package registry communication
- `PackagistRegistry` - Packagist API for Composer packages (with auth.json support)
- `NpmRegistry` - npm registry API

## Remaining Work

### 1. Release Notes Architecture

**Purpose:** Fetch changelog and release information from various sources using a chain of responsibility pattern.

#### 1.1 Individual Fetchers (`src/Services/ReleaseNotes/Fetchers/`)

**Interface:**
```php
interface ReleaseNotesFetcherInterface
{
    public function fetch(string $package, string $fromVersion, string $toVersion, array $context = []): ?array;
    public function supports(array $context): bool;
}
```

**Implementations to create:**
- `LocalVendorChangelogFetcher` - Check vendor/node_modules for CHANGELOG.md
- `GithubReleaseFetcher` - GitHub Releases API
- `GithubChangelogMdFetcher` - Fetch CHANGELOG.md from GitHub repository
- `GitlabReleaseFetcher` - GitLab Releases API (future)
- `GitlabChangelogMdFetcher` - Fetch CHANGELOG.md from GitLab repository (future)

**Responsibilities:**
- Fetch release notes from specific source
- Parse and normalize release information
- Filter releases by version range
- Return null if source not available or fetch fails

#### 1.2 Release Notes Resolver (`src/Services/ReleaseNotes/ReleaseNotesResolver.php`)

**Purpose:** Orchestrate the chain of fetchers using Chain of Responsibility pattern.

```php
class ReleaseNotesResolver
{
    private array $fetchers = [];

    public function addFetcher(ReleaseNotesFetcherInterface $fetcher): void;
    public function resolve(string $package, string $from, string $to, array $context): ?array;
}
```

**How it works:**
1. Accepts an ordered array of fetchers
2. Tries each fetcher in sequence
3. Returns result from first successful fetcher
4. Returns null if all fetchers fail

**Example chain for a Composer package with GitHub:**
```
1. LocalVendorChangelogFetcher (fastest, local filesystem)
2. GithubReleaseFetcher (official releases)
3. GithubChangelogMdFetcher (fallback to CHANGELOG.md)
```

**Benefits:**
- Graceful degradation - falls back to alternative sources
- Performance - tries fastest sources first
- Extensibility - easy to add new fetchers
- Flexibility - different chains for different scenarios

### 2. MCP Server Integration

The Model Context Protocol (MCP) server will leverage existing components.

**MCP Tools:**
- `get_release_notes` - Uses `ReleaseNotesResolver`
- `find_compatible_version` - Uses registry clients
- `get_next_upgrade` - Uses registry clients

**Benefits:**
- No duplication - MCP tools reuse core services
- Consistency - Same logic for CLI and MCP
- Maintainability - Single source of truth

## Implementation Plan

### Phase 1: Release Notes Resolver

**Tasks:**
1. Create `src/Services/ReleaseNotes/Fetchers/` directory
2. Create `ReleaseNotesFetcherInterface`
3. Implement `GithubReleaseFetcher` (using existing Github API client)
4. Create `ReleaseNotesResolver` with chain of responsibility
5. Add unit tests
6. Update documentation

**Files to create:**
- `src/Services/ReleaseNotes/ReleaseNotesFetcherInterface.php`
- `src/Services/ReleaseNotes/ReleaseNotesResolver.php`
- `src/Services/ReleaseNotes/Fetchers/GithubReleaseFetcher.php`
- `tests/Unit/Services/ReleaseNotes/GithubReleaseFetcherTest.php`
- `tests/Unit/Services/ReleaseNotes/ReleaseNotesResolverTest.php`

### Phase 2: Additional Fetchers

**Tasks:**
1. Implement `LocalVendorChangelogFetcher`
2. Implement `GithubChangelogMdFetcher`
3. Configure default fetcher chains
4. Add integration tests

### Phase 3: MCP Server

**Tasks:**
1. Create MCP server entry point
2. Implement `get_release_notes` tool
3. Implement `find_compatible_version` tool
4. Implement `get_next_upgrade` tool
5. Add MCP-specific tests
6. Update documentation

## Configuration

Allow users to configure fetcher chains:

```yaml
# ~/.whatsdiff/config.yaml
release_notes:
  fetchers:
    - LocalVendorChangelogFetcher
    - GithubReleaseFetcher
    - GithubChangelogMdFetcher
  cache_ttl: 3600
```

## Success Criteria

- [ ] ReleaseNotesFetcherInterface defined
- [ ] At least 2 fetchers implemented (GithubReleaseFetcher, LocalVendorChangelogFetcher)
- [ ] ReleaseNotesResolver working with chain
- [ ] MCP server with 3 tools implemented
- [ ] All tests passing (`composer qa`)
- [ ] Documentation updated
- [ ] Performance maintained or improved

## Future Enhancements

### Additional Release Sources
- Changelog parsing with AI
- Release notes aggregation services
- Package manager-specific release APIs
- Custom webhook integrations

### Additional Package Managers
- Python (pip, poetry, pipenv)
- Ruby (bundler)
- Go (go.mod)
- Rust (Cargo)
- .NET (NuGet)

## Timeline

- **Phase 1:** Release Notes Resolver - 1 PR
- **Phase 2:** Additional Fetchers - 1 PR
- **Phase 3:** MCP Server - 1 PR

Total estimated: 3 PRs
