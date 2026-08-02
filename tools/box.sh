#!/usr/bin/env bash
#
# Runs Box (the PHAR compiler) through cpx, which installs it into its own
# isolated directory instead of this project's vendor/.
#
# Box must NOT be a Composer dev dependency. It declares
# `replace: {symfony/polyfill-php80, -php81, -php82}`, which lets Composer
# satisfy guzzlehttp/guzzle's `symfony/polyfill-php80` requirement with Box
# itself. Box and its whole tree (php-scoper, amphp, phpstorm-stubs, ...) then
# get resolved into the *production* package set, which:
#
#   - breaks `composer install --no-dev`, because the real polyfill is never
#     locked and guzzle's requirement becomes unsatisfiable,
#   - breaks `box compile`, because Box's internal
#     `composer dump-autoload --classmap-authoritative --no-dev` then scans
#     php-scoper's empty `vendor-hotfix/` classmap directory and dies,
#   - bloats the PHAR from ~1.8k to ~4k files.
#
# Running Box through cpx keeps it out of composer.json entirely.
#
# Usage: ./tools/box.sh compile --config=box.json
# Override the pinned Box version with BOX_VERSION=x.y.z ./tools/box.sh ...

set -euo pipefail

# Pinned to 4.6.7 deliberately. Box 4.6.8 introduced a Windows regression where
# compile dies tearing down its own temp directory:
#   Failed to remove directory "C:\...\Temp\box\BoxNNNNN":
#   rmdir(...): Resource temporarily unavailable
# See https://github.com/box-project/box/issues/1574 (still open). 4.6.7 and
# 4.7.0 produce byte-identical output on Linux/macOS, so pinning costs nothing.
# Revisit once 1574 is fixed.
BOX_VERSION="${BOX_VERSION:-4.6.7}"

if ! command -v cpx >/dev/null 2>&1; then
    echo "cpx not found, installing it globally..."
    composer global require cpx/cpx --no-interaction --prefer-dist --no-progress
    export PATH="$(composer config --global home)/vendor/bin:$PATH"
fi

exec cpx "humbug/box:$BOX_VERSION" "$@"
