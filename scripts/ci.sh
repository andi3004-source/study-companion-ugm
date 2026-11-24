#!/usr/bin/env bash
# Gerbang CI yang sama untuk lokal dan GitHub Actions: tes PHP, gaya kode
# pada file yang berubah, dan build aset. E2E Playwright: scripts/e2e.sh.
set -euo pipefail
cd "$(dirname "$0")/.."

# ponytail: macOS writes AppleDouble "._*" files on exFAT/network drives; PHPUnit
# picks up "._FooTest.php" as a test file and exits non-zero. Remove them before
# running. Drop this once the repo lives on APFS.
[ "$(uname)" = Darwin ] && find app bootstrap config database resources routes tests scripts -name '._*' -type f -delete 2>/dev/null || true

[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --ansi

echo "== PHPUnit"
php artisan test --compact

echo "== Pint (file yang berubah terhadap ${CI_BASE:-main})"
changed=$( { git diff --name-only --diff-filter=ACMR "${CI_BASE:-main}"...HEAD -- '*.php'; git diff --name-only --diff-filter=ACMR HEAD -- '*.php'; git ls-files --others --exclude-standard -- '*.php'; } 2>/dev/null | grep -v '/\._' | sort -u || true)
[ -z "$changed" ] || vendor/bin/pint --test $changed

echo "== Build aset"
npm run build
