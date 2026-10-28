#!/usr/bin/env bash
set -euo pipefail
# One-time conversion after deployment. Run as the site upload owner (discount).
# Disable the scheduled cron job first; both modes share the same SQLite DB and flock.
ROOT=/var/www/discount-tools/public
APP=/opt/discount-tools/image-webp/webp_convert.py
PYTHON=/opt/discount-tools/webp-venv/bin/python
STATE=/var/lib/discount-tools/image-webp

exec /usr/bin/nice -n 10 /usr/bin/ionice -c 3 \
  "$PYTHON" "$APP" \
  --root "$ROOT/upload/resize_cache" \
  --root "$ROOT/upload/iblock" \
  --limit 1000000 \
  --lock-file "$STATE/converter.lock" \
  --state-db "$STATE/state.sqlite3" \
  --apply
