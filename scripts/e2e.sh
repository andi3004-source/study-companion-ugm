#!/usr/bin/env bash
# Menjalankan E2E Playwright dengan SQLite sementara, fixture kecil, dan stub AI.
set -euo pipefail
cd "$(dirname "$0")/.."

PORT="${PORT:-8000}"
BASE_URL="http://127.0.0.1:${PORT}"
TEMP_DB="database/e2e_test_$$.sqlite"
SEED_DIR="storage/app/seed-data"
SERVER_PID=""

cleanup() {
    local exit_code=$?
    echo "== Membersihkan proses dan berkas sementara (exit code: $exit_code)"

    if [ -n "$SERVER_PID" ]; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi

    # Bersihkan file DB sementara
    rm -f "$TEMP_DB"

    # Pulihkan file korpus asli bila sebelumnya ada
    for file in mandarin_hsk1to6_complete.json irregular_verbs_183_complete.json english_vocab_cefr_10674.json; do
        rm -f "$SEED_DIR/$file"
        if [ -f "$SEED_DIR/$file.bak_e2e" ]; then
            mv "$SEED_DIR/$file.bak_e2e" "$SEED_DIR/$file"
        fi
    done

    exit "$exit_code"
}
trap cleanup EXIT INT TERM

# ponytail: hapus file AppleDouble jika di macOS
[ "$(uname)" = Darwin ] && find app bootstrap config database resources routes tests scripts -name '._*' -type f -delete 2>/dev/null || true

# Pastikan .env dan APP_KEY siap
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --ansi

# Siapkan fixture kecil kosakata untuk seeding cepat
mkdir -p "$SEED_DIR"
for file in mandarin_hsk1to6_complete.json irregular_verbs_183_complete.json english_vocab_cefr_10674.json; do
    if [ -f "$SEED_DIR/$file" ]; then
        mv "$SEED_DIR/$file" "$SEED_DIR/$file.bak_e2e"
    fi
done
cp tests/Fixtures/vocabulary/mandarin_hsk_small.json "$SEED_DIR/mandarin_hsk1to6_complete.json"
cp tests/Fixtures/vocabulary/irregular_verbs_small.json "$SEED_DIR/irregular_verbs_183_complete.json"
cp tests/Fixtures/vocabulary/english_vocab_small.json "$SEED_DIR/english_vocab_cefr_10674.json"

# Buat SQLite sementara dan migrasi + seed
touch "$TEMP_DB"
export DB_CONNECTION=sqlite
export DB_DATABASE="$PWD/$TEMP_DB"
export AI_PROVIDER=fake
export APP_URL="$BASE_URL"

echo "== Migrasi & Seed basis data SQLite sementara"
php artisan migrate:fresh --force
php artisan db:seed --force

echo "== Build aset Vite"
npm run build

echo "== Memulai server Laravel di $BASE_URL"
php artisan serve --host=127.0.0.1 --port="$PORT" &
SERVER_PID=$!

echo "Menunggu server siap..."
for i in $(seq 1 30); do
    if curl -s -o /dev/null "$BASE_URL/"; then
        echo "Server siap di $BASE_URL"
        break
    fi
    sleep 0.5
done

echo "== Menjalankan Playwright"
export PLAYWRIGHT_SKIP_WEBSERVER=1
export PLAYWRIGHT_TEST_BASE_URL="$BASE_URL"

npx playwright test "$@"
