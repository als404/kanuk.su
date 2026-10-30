#!/usr/bin/env bash
# Одноразовые ручные запросы на XML-выгрузку, поступающие из админки.
set -Eeuo pipefail
umask 027

SITE=/var/www/discount-tools/public
STATE_DIR=/var/lib/discount-tools/dealer-xml
REQUEST="$STATE_DIR/manual.request"
PROCESSING="$STATE_DIR/manual.processing"
STATUS="$STATE_DIR/manual.status"
LOG="$STATE_DIR/manual.log"
LOCK="$STATE_DIR/manual.lock"
SCRIPT="$SITE/local/tools/dealer-export/dealer_export.py"
CONFIG="$SITE/local/tools/dealer-export/dealer_export.json"

mkdir -p "$STATE_DIR"
exec 9>"$LOCK"
flock -n 9 || exit 0

# Если процесс был прерван, ручной запрос можно повторить через админку
# после разбирательства; не повторяем потенциально тяжёлый запуск автоматически.
if [[ -e "$PROCESSING" ]]; then
    exit 0
fi
[[ -f "$REQUEST" ]] || exit 0
mv -- "$REQUEST" "$PROCESSING"

printf 'RUNNING | %s\n' "$(date '+%d.%m.%Y %H:%M:%S %z')" > "$STATUS"
printf '%s START manual XML export\n' "$(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG"

rc=0
/usr/bin/timeout 600 /usr/bin/python3 "$SCRIPT" --config "$CONFIG" >> "$LOG" 2>&1 || rc=$?

if [[ "$rc" -eq 0 ]]; then
    printf 'OK | %s\n' "$(date '+%d.%m.%Y %H:%M:%S %z')" > "$STATUS"
else
    printf 'ERROR | %s | exit=%s, подробности в %s\n' "$(date '+%d.%m.%Y %H:%M:%S %z')" "$rc" "$LOG" > "$STATUS"
fi
printf '%s END manual XML export exit=%s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$rc" >> "$LOG"
rm -f -- "$PROCESSING"
exit 0
