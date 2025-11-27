# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v2.1.0 - 2025-11-27

### What's Changed

* feat: add PHP 8.5 support by @SRWieZ in https://github.com/whatsdiff/whatsdiff/pull/37

**Full Changelog**: https://github.com/whatsdiff/whatsdiff/compare/v2.0.0...v2.1.0

## v2.0.0 - 2025-10-29

Happy to announce **whatsdiff v2.0.0**, the next version of the tool that helps you keep an eye on your dependencies.

Doc and screenshots on the website: https://whatsdiff.app

### What's New

#### 🤖 MCP Server Integration

whatsdiff now includes a **Model Context Protocol (MCP) server**, enabling AI assistants to intelligently analyze your dependencies with tools for finding compatible versions, fetching release notes, discovering upgrades, and analyzing dependency constraints.

#### 🖥️ New Terminal UI

The Terminal UI (TUI) command provides an interactive way to browse changelogs and release notes for your dependencies directly in your terminal. Navigate through packages, view detailed release notes, and get insights on changes with ease.

#### 📋 whatsdiff changelog

View aggregated release notes for any package version range. whatsdiff fetches from multiple sources with intelligent fallback: local CHANGELOG.md files, GitHub Releases API, and repository changelog files, with support for Deprecated, Removed, and Security sections.

#### ✅ whatsdiff check

Perfect for CI/CD pipelines - verify if specific packages changed with exit codes (0 for true, 1 for false, 2 for errors) and quiet mode for script-friendly output.

### Release notes

#### Added

* feat: add MCP binary builds in [#35](https://github.com/whatsdiff/whatsdiff/pull/35)
* feat: MCP server in [#34](https://github.com/whatsdiff/whatsdiff/pull/34)
* feat: enhance URL formatting with clickable hyperlinks and truncation support in [`a96c7bc`](https://github.com/whatsdiff/whatsdiff/commit/a96c7bc0fd5a00104e24e035e97ba761bc8e34ad)
* feat: update composer dependencies and add patch for emoji display width in [`1465b51`](https://github.com/whatsdiff/whatsdiff/commit/1465b51cccc40f005b5ade567885eea66a4ff7be)
* feat: add support for Deprecated, Removed, and Security sections in release notes in [`56ccf8c`](https://github.com/whatsdiff/whatsdiff/commit/56ccf8cb91cdda5e05d281cef902b64b32a23a6e)
* feat: save mode preference in [`3ffd680`](https://github.com/whatsdiff/whatsdiff/commit/3ffd680e4a5e8556329330b1440ae27f3c3a1510)
* feat: add sidebar navigation with TAB and SHIFT+TAB keys in [`dff3d5c`](https://github.com/whatsdiff/whatsdiff/commit/dff3d5c9d053db170793d998c0195a3d26f0b613)
* feat: add URL formatting for improved changelog display in TUI in [`79f454e`](https://github.com/whatsdiff/whatsdiff/commit/79f454ec4c1e411d6eb6fffee5f45f282c8371da)
* feat: enhance JSON output formatting with summary option in [`5d7b49f`](https://github.com/whatsdiff/whatsdiff/commit/5d7b49fea654518990d77be283d6bbf7d6af06d6)
* feat: enhance ReleaseNote class with structure and bullet points detection in [`389d4c6`](https://github.com/whatsdiff/whatsdiff/commit/389d4c69ee4b9fd30a18eb30ec157fa17b363788)
* feat: add interactive changelog viewing to Terminal UI in [`e5561f2`](https://github.com/whatsdiff/whatsdiff/commit/e5561f27274d1f1746c22b7530b85f47b3ad2df6)
* feat: option to disable alt screen and improve error handling in [`b93eb2d`](https://github.com/whatsdiff/whatsdiff/commit/b93eb2d607f33729968a4a8cdf7012101529eb1f)
* feat: add GitHub URL formatting helpers, separate helper classes in [`9700fa9`](https://github.com/whatsdiff/whatsdiff/commit/9700fa9bbc1bf8a4991aaab803f5897a06617c2c)
* feat: implement AnalyzerRegistry for lazy loading of package manager analyzers in [`152c02c`](https://github.com/whatsdiff/whatsdiff/commit/152c02c695be2495e91f71a25afb049f02a448c4)
* feat: local changelog fetchers + github changelog.md in [`d3c1971`](https://github.com/whatsdiff/whatsdiff/commit/d3c19718bbd4cb2f1bae1937e127e60d698430ef)
* feat: enhance package manager detection strategies and add description extraction for release notes in [`679fdcc`](https://github.com/whatsdiff/whatsdiff/commit/679fdcc67a2e0584acec9a6b7404a88c1849d621)
* feat: add ReleaseNote and ReleaseNotesCollection classes for managing release notes in [`ce84c36`](https://github.com/whatsdiff/whatsdiff/commit/ce84c36c16af7b50bd2ed72dddf673de7b59b1e8)
* feat: add ReleaseNote and ReleaseNotesCollection classes for managing release notes in [`a9e93d4`](https://github.com/whatsdiff/whatsdiff/commit/a9e93d49c105216735ee00d260558dbe2568ae79)
* feat: implement lock file parsers and registry clients in [`02e338a`](https://github.com/whatsdiff/whatsdiff/commit/02e338a925102ca4e1c6ea09866bf489f048d74d)
* feat: streamline changelog update process with automated commit action in [`a71da95`](https://github.com/whatsdiff/whatsdiff/commit/a71da95359fb84fa785bb54bf91f971f603a827a)
* feat: add workflow to automatically update changelog on release in [`3e8dae8`](https://github.com/whatsdiff/whatsdiff/commit/3e8dae842206a692d05387422e602216dc92e698)
* feat: progress bar show after a certain elapsed time in [`e9515ab`](https://github.com/whatsdiff/whatsdiff/commit/e9515abec08416c7dff5366639bbe91361eb2882)
* feat: update MarkdownOutput to enhance semantic versioning display in [`eab2941`](https://github.com/whatsdiff/whatsdiff/commit/eab2941fb19c791f6491701af0a43c3929ba8a00)
* feat: enhance TextOutput to support semantic versioning in change symbols in [`575e770`](https://github.com/whatsdiff/whatsdiff/commit/575e7706df0291ee3a540d5ecf015911b39bdcbb)
* feat: add SemverAnalyzer for version change detection and update related classes in [`5850b5c`](https://github.com/whatsdiff/whatsdiff/commit/5850b5cbb1560c705241d410a1e42e34b5250320)
* feat: pint runs un parallel in [`888be81`](https://github.com/whatsdiff/whatsdiff/commit/888be81e927f5d5586d633c45d09d710be6e308f)
* feat: ensure valid DiffResult is returned in DiffCalculator and initialize with empty result in [`726cfa1`](https://github.com/whatsdiff/whatsdiff/commit/726cfa13bbad4007cb94252bcc14c76cb4607260)
* feat: implement PSR-11 compliant container and refactor commands to use dependency injection in [`587765c`](https://github.com/whatsdiff/whatsdiff/commit/587765ca40651884a3b83aef86f15132c883f076)
* feat: add include/exclude options to AnalyseCommand and refactor shared options for reuse in [`8ad80a1`](https://github.com/whatsdiff/whatsdiff/commit/8ad80a198f87e180692ac30c920071d383019f12)
* feat: add tests for include/exclude options in dependency analysis in [`c4943fd`](https://github.com/whatsdiff/whatsdiff/commit/c4943fd438a543350ee3e6f713af360355652cc3)
* feat: rename DiffCommand to AnalyseCommand and add include/exclude options for package manager types in [`e9b9a89`](https://github.com/whatsdiff/whatsdiff/commit/e9b9a89c36c530d235e317e58317a9a9b4be7687)
* feat: add --no-progress option to DiffCommand and refactor diff calculation methods for improved clarity in [`73bcd61`](https://github.com/whatsdiff/whatsdiff/commit/73bcd619e0ae36a6f9c5a3d20fa69fcdb4426dea)
* feat: enhance diff calculation to yield PackageChange objects and improve progress reporting in [`3b10551`](https://github.com/whatsdiff/whatsdiff/commit/3b10551a51278df954863b8f994da63f959efd37)
* feat: implement fluent interface for DiffCalculator and enhance diff calculation methods in [`fb1bfeb`](https://github.com/whatsdiff/whatsdiff/commit/fb1bfebc87d4c5d12ec736d4c00ac508b8630a40)
* feat: add 'between' command to compare dependency changes between commits, branches, or tags in [`2544ea0`](https://github.com/whatsdiff/whatsdiff/commit/2544ea01c38d7090f9a93865da0e4382db0d0a7b)
* feat: add a config and cache service in [#19](https://github.com/whatsdiff/whatsdiff/pull/19)
* feat: refactor DiffCalculator to use DependencyFile class  in [#18](https://github.com/whatsdiff/whatsdiff/pull/18)
* feat: refactor test setup and cleanup for improved readability and maintainability in [#16](https://github.com/whatsdiff/whatsdiff/pull/16)
* feat: add 'check' command to verify package changes in dependencies in [#15](https://github.com/whatsdiff/whatsdiff/pull/15)
* feat: add a bunch of tests in [#14](https://github.com/whatsdiff/whatsdiff/pull/14)
* feat: introduce analyzers for Composer and NPM package management in [#13](https://github.com/whatsdiff/whatsdiff/pull/13)
* feat: update PHP version matrix in test configuration in [`38a18a1`](https://github.com/whatsdiff/whatsdiff/commit/38a18a1f7b1990d00d0fa85190e81d0cc04fe2c1)
* feat: add output format options for dependency diffs in [`2030713`](https://github.com/whatsdiff/whatsdiff/commit/2030713854f6834fa51b0e6832230d95ed2dfbfd)
* feat: add TUI command for browsing dependency changes in [`3207793`](https://github.com/whatsdiff/whatsdiff/commit/320779308242a04724a970e0c7606880fccb86fb)
* feat: use symfony/process for git operations in [#11](https://github.com/whatsdiff/whatsdiff/pull/11)
* feat: implement Symfony Console integration in [#10](https://github.com/whatsdiff/whatsdiff/pull/10)

#### Changed

* build: ready for v2 (ext, php, deps, etc..) in [`b76fddb`](https://github.com/whatsdiff/whatsdiff/commit/b76fddb14fcbf9c28b52bca7b23f1b271989a7e5)
* test: fix test suite in [`cecc5f0`](https://github.com/whatsdiff/whatsdiff/commit/cecc5f05f95245812e50e7f5715d20eb0adf686e)
* style: new tui header in [`472a46e`](https://github.com/whatsdiff/whatsdiff/commit/472a46eba6f845077903359d57154df63f305550)
* refactor: simplify version comparison logic in ChangelogCommand in [`a22c11e`](https://github.com/whatsdiff/whatsdiff/commit/a22c11e0228fd8b55b8e48695ec735c4b06c6112)
* chore: update dependencies in [`426086e`](https://github.com/whatsdiff/whatsdiff/commit/426086ea15f379488f16bf6f2a80e0a17cbf6678)
* Integrate GitHub Token in HTTP Service in [#33](https://github.com/whatsdiff/whatsdiff/pull/33)
* style: improve spacing in release notes formatting in [`5349f63`](https://github.com/whatsdiff/whatsdiff/commit/5349f63419622f2c54dcaf01da9f770436e35b54)
* style: bottom UI changes in [`467916f`](https://github.com/whatsdiff/whatsdiff/commit/467916f061ced6b02a6f76025775de5214eb6972)
* style: some header UI chages in [`b2727d5`](https://github.com/whatsdiff/whatsdiff/commit/b2727d5605ae4ea3ec2431536d1f5d6bbbaba388)
* refactor: reorganizing the codebase in [`8356cfe`](https://github.com/whatsdiff/whatsdiff/commit/8356cfe95cae69cf75c40174d1b9356436cfb520)
* test: update changelog fetcher tests to remove version-specific refs in [`d15033d`](https://github.com/whatsdiff/whatsdiff/commit/d15033daae648351ca88199f9e6a9539be260a69)
* refactor: improve changelog fetcher methods to use GitHub API in [`248609c`](https://github.com/whatsdiff/whatsdiff/commit/248609c20079a6b6e125e11c1372c25ba609a440)
* refactor: initialize GitRepository properties on first use to support dependency injection in [`6968a50`](https://github.com/whatsdiff/whatsdiff/commit/6968a50037b2f3e3674f348ed02189ce1d5c63b1)
* refactor: add stateful parsers for composer.lock and package-lock.json files in [`9cc5777`](https://github.com/whatsdiff/whatsdiff/commit/9cc5777897881c84778fbcd245633d2afcc86a3c)
* refactor: instantiate GitRepository on-demand in DiffCalculator in [`93790f4`](https://github.com/whatsdiff/whatsdiff/commit/93790f45c7f607059c2cd3679d9a2d9749f1e977)
* refactor: simplify test setup by using a class property for temporary directory in [`2ec6da0`](https://github.com/whatsdiff/whatsdiff/commit/2ec6da0c1e80003b400d21307ef523ad9cb41a85)
* refactor: replace custom container with League Container in [`dcfe739`](https://github.com/whatsdiff/whatsdiff/commit/dcfe739ec444cc4b8cec1f50d45b64c961a523da)
* refactor: change package org in [`32f3940`](https://github.com/whatsdiff/whatsdiff/commit/32f3940ad90320f454aa3d7c8e007d1aa4695f1b)
* refactor: enhance test suite with mock HTTP service and improved private package handling in [`8ff9273`](https://github.com/whatsdiff/whatsdiff/commit/8ff9273bcb315a88ecf4877bd719c36d234ad0bb)
* refactor: streamline test setup by using helper functions for lock file generation in [`9b9679e`](https://github.com/whatsdiff/whatsdiff/commit/9b9679ecddd059859f0bbf779a3f0a48b274213d)
* test: cleaning and refactoring tests in [`b7994ae`](https://github.com/whatsdiff/whatsdiff/commit/b7994aed8b6ce3a4b159f6dadd1253e803fbbce9)
* refactor: simplify release count methods and update version comparison logic in [`a1b9691`](https://github.com/whatsdiff/whatsdiff/commit/a1b9691d39b5d80a0ed6724882533fbff706557a)
* Refactor to enum in [#17](https://github.com/whatsdiff/whatsdiff/pull/17)

#### Fixed

* fix: autoload path on composer global install in [`63a8454`](https://github.com/whatsdiff/whatsdiff/commit/63a84542083fe74333a453fafe260a29de13bb52)
* fix: enhance repository URL extraction and normalization logic in [`4922fef`](https://github.com/whatsdiff/whatsdiff/commit/4922fefb1099063af1b4abfa778ec2cd09c2ceeb)
* fix: adjust URL truncation logic for better display in changelog in [`8489664`](https://github.com/whatsdiff/whatsdiff/commit/8489664c9907ef1ae58fa03488b7faebe6646a9d)
* fix: GithubReleaseFetcher pagination in [#32](https://github.com/whatsdiff/whatsdiff/pull/32)
* fix: update expected output format for empty release notes in tests in [`bc1c8e5`](https://github.com/whatsdiff/whatsdiff/commit/bc1c8e55023a54625e5eeab01200a0c9f93bf873)
* fix: between command not showing progress bar by default in [`fae9a02`](https://github.com/whatsdiff/whatsdiff/commit/fae9a027982982cc5eae822c746fb8c0de76129c)
* fix: composer type in [`0c71be0`](https://github.com/whatsdiff/whatsdiff/commit/0c71be0659747e21fff84d5f7ce2eb67b4fc35e0) [`17b0f43`](https://github.com/whatsdiff/whatsdiff/commit/17b0f43b6de8431081e865d21ce32a216fc7fe82)
* fix: simplify BetweenCommandTest by replacing ProcessService with runWhatsDiff helper in [`e597b60`](https://github.com/whatsdiff/whatsdiff/commit/e597b6070cc79ecc40b66e2d1929bbd83f1c3236)
* revert: formatter inside the container in [`b3eaba6`](https://github.com/whatsdiff/whatsdiff/commit/b3eaba618108a1d316665e5ca5b2d8fc733f0ded)

**Full Changelog**: https://github.com/whatsdiff/whatsdiff/compare/v1.6.0..v1.7.0

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
