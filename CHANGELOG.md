# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.0] - 2025-03-31
- feat: initial support for private packagist

## [1.5.0] - 2025-02-12
### Changed
- chore: support illuminate/collections v12

## [1.4.3] - 2025-01-14
### Fixed
- fix: Uncommitted changes weren't shown if the last commits of both files were different
- fix: ci bug

## [1.4.2] - 2024-12-24
### Fixed
- fix: packages-json weird format
- fix: root directory false change detection

## [1.4.1] - 2024-12-23
### Fixed
- fix: forgot a dump() 🙈
- fix: windows build

## [1.4.0] - 2024-12-23
### Added
- feat: can analyse package-lock.json in a subdirectory
### Fixed
- fix: build with dependencies updated

## [1.3.0] - 2024-12-23
### Added
- feat: phpstan static analysis
### Changed
- chore: test against Windows and MacOs
- chore: test against PHP 8.4
- chore: update dependencies
### Fixed
- fix: php 8.1 compatibility
- fix: Windows build action

## [1.2.0] - 2024-11-20
### Added
- feat: only compare the same commit for both files
### Security
- cleared some dependency advisories

## [1.1.0] - 2024-09-19
### Added
- Supports for NPMJS package-lock.json
### Changed
- New colourful output
### Fixed
- Fixed a bug when composer.lock has just been created
- Fixed a strlen bug when there is only new packages
- Supports a wider ranges of git status --porcelain


