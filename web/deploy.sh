#!/usr/bin/env bash
# ============================================================
# Sajio web — production deploy script (Next.js standalone)
# Builds the app and stages the standalone runtime for PM2.
# ============================================================
set -euo pipefail

cd "$(dirname "$0")"

echo "==> Installing dependencies (if needed)"
[ -d node_modules ] || npm ci

echo "==> Building Next.js"
npm run build

echo "==> Staging standalone runtime (.next/standalone)"
STANDALONE=".next/standalone"

# Static assets are NOT copied by Next's standalone exporter — stage them manually.
rm -rf "$STANDALONE/public"
cp -r public "$STANDALONE/public" 2>/dev/null || true

rm -rf "$STANDALONE/.next/static"
cp -r .next/static "$STANDALONE/.next/static"

echo "==> Restarting sajio-web via PM2"
pm2 startOrRestart ecosystem.config.cjs
pm2 save --force

echo "==> Done. Sajio web is running on 127.0.0.1:3000"
