# whatsdiff

![GitHub release (with filter)](https://img.shields.io/github/v/release/whatsdiff/whatsdiff)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/whatsdiff/whatsdiff/php)
![Packagist License (custom server)](https://img.shields.io/packagist/l/whatsdiff/whatsdiff)
![GitHub Workflow Status (with event)](https://img.shields.io/github/actions/workflow/status/whatsdiff/whatsdiff/test.yml)

What's diff is a CLI tool to help you inspect what has changed in your dependencies after a `composer update` or `npm update`.

![Capture d’écran 2024-09-19 à 20.00.47.png](assets/Capture%20d%E2%80%99%C3%A9cran%202024-09-19%20%C3%A0%2020.00.47.png)
> Outdated screenshot.. Coming soon!


Main features are:
- Show what has been added, removed or updated in your dependencies
- Show how many releases have been made since your last update
- Support for composer.lock and package-lock.json (PHP & JS)
- Show changelog and release notes of the dependencies
- Compare between commits, branches, or tags
- Interactive Terminal UI with keyboard navigation
- Multiple output formats (text, JSON, markdown)

## 🚀 Installation
Via [Composer](https://getcomposer.org/) global require command
```bash
composer global require whatsdiff/whatsdiff
```

By [downloading binaries](https://github.com/whatsdiff/whatsdiff/releases/latest) on the latest release, currently only these binaries are compiled on the CI:
- macOS x86_64
- macOS arm64
- linux x86_64
- linux arm64
- windows x64

## 📚 Usage

For complete documentation, visit [whatsdiff.app/docs](https://whatsdiff.app/docs)

### [Analyse Command](https://whatsdiff.app/docs/cli-analyse)
Show what changed after your last `composer update` or `npm update`:
```bash
whatsdiff
# or explicitly
whatsdiff analyse
```

### [Between Command](https://whatsdiff.app/docs/cli-between)
Compare dependencies between two commits, branches, or tags:
```bash
# Compare between two tags
whatsdiff between v1.0.0 v2.0.0

# Compare between commits
whatsdiff between abc123 def456

# Compare from a commit to HEAD
whatsdiff between abc123
```

### [Terminal UI Mode](https://whatsdiff.app/docs/cli-tui)
Launch an interactive Terminal UI with keyboard navigation:
```bash
whatsdiff tui
```

### [Check Command](https://whatsdiff.app/docs/cli-check)
Check if a specific package has changed (useful for CI/CD):
```bash
# Check if a package was updated
whatsdiff check livewire/livewire --is-updated

# Check if a package was added
whatsdiff check new/package --is-added

# Use in scripts with exit codes
if whatsdiff check critical/package --is-updated --quiet; then
  echo "Critical package updated, running extra tests..."
fi
```

### [Changelog Command](https://whatsdiff.app/docs/cli-changelog)
View release notes for updated packages:
```bash
whatsdiff changelog
```

### [Configuration](https://whatsdiff.app/docs/cli-configuration)
Manage cache and other settings:
```bash
# View all configuration
whatsdiff config

# Disable cache
whatsdiff config cache.enabled false

# Set cache time limits (in seconds)
whatsdiff config cache.min-time 600
```

### Output Formats
All commands support multiple output formats:
```bash
# JSON output
whatsdiff --format=json

# Markdown output
whatsdiff --format=markdown

# Disable cache for a single run
whatsdiff --no-cache
```

## 🔧 Contributing
This project follows PSR coding style. You can use `composer pint` to apply.

All tests are executed with pest. Use `composer pest`

It's recommended to execute `composer qa` before commiting (alias for executing Pint and Pest)

### Testing
This project use [Pest](https://pestphp.com/) for testing.
```bash
composer test
```
### Build from sources
This project use [box](https://github.com/box-project/box), [php-static-cli](https://github.com/crazywhalecc/static-php-cli) and [php-micro](https://github.com/dixyes/phpmicro).
A build script has been created to build the project. (tested only on macOS x86_64)

```bash
composer build
```
Then you can build the binary that you can retrieve in `build/bin/`

## 👥 Credits

**whatsdiff** was created by Eser DENIZ.

## 📝 License

**whatsdiff** PHP is licensed under the MIT License. See LICENSE for more information.