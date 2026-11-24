#!/usr/bin/env bash
# Gerbang CI yang sama untuk lokal dan GitHub Actions: tes PHP, gaya kode
# pada file yang berubah, dan build aset. E2E Playwright: scripts/e2e.sh.
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --ansi

echo "== PHPUnit"
php artisan test --compact

echo "== Pint (file yang berubah terhadap ${CI_BASE:-main})"
changed=$(git diff --name-only --diff-filter=ACMR "${CI_BASE:-main}"...HEAD -- '*.php' || true)
[ -z "$changed" ] || vendor/bin/pint --test $changed

echo "== Build aset"
npm run build
