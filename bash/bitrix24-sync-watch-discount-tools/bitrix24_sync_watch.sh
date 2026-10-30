#!/usr/bin/env bash
# Bitrix24 <-> discount-tools.ru sync watch: nginx checkauth activity + Postfix notifications.
# Does not change Bitrix24 settings or initiate an exchange.
set -uo pipefail
umask 077

VERSION="1.1-postfix"
CONFIG_FILE="${CONFIG_FILE:-/etc/bitrix24_sync_watch.conf}"

# Defaults: override in the external configuration file.
SITE_NAME="discount-tools.ru"
ACCESS_LOG="/var/log/nginx/access.log"
B24_USER="bx_eshop"
REQUIRE_LOG_USER=1
EXCHANGE_PATH="/bitrix/admin/1c_exchange.php"
SYNC_PERIOD_MIN=10
ALERT_AFTER=1500
STATE_FILE="/var/lib/bitrix24-sync-watch/state"
MONITOR_LOG="/var/log/bitrix24_sync_watch.log"
LOCK_FILE="/run/lock/bitrix24_sync_watch.lock"
SENDMAIL="/usr/sbin/sendmail"
MAIL_ENABLED=1
EMAIL_FROM="info@discount-tools.ru"
EMAIL_TO="adm+site@kanuk.su"
MAX_ROTATED=2

usage() {
    cat <<'HELP'
Usage: bitrix24_sync_watch.sh [--run|--check|--dry-run|--test-email|--help]
  --check       Read-only preflight: show log matching and freshness; no emails/state changes.
  --dry-run     Read-only evaluation: show decision; no emails/state changes.
  --test-email  Send one test message through Postfix/sendmail; no state changes.
  --run         Normal monitoring; send messages only on state changes (default).
HELP
}

case "${1:---run}" in
    --run|--check|--dry-run|--test-email) MODE="${1:---run}" ;;
    --help|-h) usage; exit 0 ;;
    *) usage >&2; exit 2 ;;
esac
[ "$#" -le 1 ] || { usage >&2; exit 2; }

if [ -e "$CONFIG_FILE" ]; then
    [ -r "$CONFIG_FILE" ] || { printf 'ERROR: config unreadable: %s\n' "$CONFIG_FILE" >&2; exit 2; }
    # Trusted root-managed shell config. Never store site or FTP passwords in it.
    # shellcheck disable=SC1090
    . "$CONFIG_FILE"
fi

HOST_NAME="$(hostname -f 2>/dev/null || hostname)"

error() { printf 'ERROR: %s\n' "$*" >&2; return 2; }

log_event() {
    local event="$1"; shift
    mkdir -p -- "$(dirname "$MONITOR_LOG")" || return 1
    printf '%s %s: %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$event" "$*" >> "$MONITOR_LOG"
}

read_state() {
    if [ -r "$STATE_FILE" ]; then
        head -n 1 "$STATE_FILE" 2>/dev/null || true
    fi
}

write_state() {
    local dir tmp
    dir="$(dirname "$STATE_FILE")"
    mkdir -p -- "$dir" || return 1
    tmp="$(mktemp "${dir}/.state.XXXXXXXX")" || return 1
    if ! printf '%s\n' "$1" > "$tmp" || ! mv -f -- "$tmp" "$STATE_FILE"; then
        rm -f -- "$tmp"
        return 1
    fi
}

send_email() {
    local subject="$1" body="$2" subject_b64 rc
    [ "$MAIL_ENABLED" = "1" ] || return 0
    [ -x "$SENDMAIL" ] || { printf 'ERROR: sendmail not executable: %s\n' "$SENDMAIL" >&2; return 1; }
    # Reject header-injection characters from editable config.
    [[ "$EMAIL_FROM" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+$ ]] || return 1
    [[ "$EMAIL_TO" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+$ ]] || return 1
    subject_b64="$(printf '%s' "$subject" | base64 | tr -d '\n')"

    if {
        printf 'From: %s\n' "$EMAIL_FROM"
        printf 'To: %s\n' "$EMAIL_TO"
        printf 'Subject: =?UTF-8?B?%s?=\n' "$subject_b64"
        printf 'MIME-Version: 1.0\n'
        printf 'Content-Type: text/plain; charset=UTF-8\n'
        printf 'Content-Transfer-Encoding: base64\n'
        printf '\n'
        printf '%s\n' "$body" | base64
    } | "$SENDMAIL" -f "$EMAIL_FROM" -t -i; then
        return 0
    else
        rc=$?
        printf 'ERROR: sendmail returned %s\n' "$rc" >&2
        return "$rc"
    fi
}

# Read the last matching nginx combined-log entry. A 200 on checkauth proves
# only an HTTP request, NOT full order/product exchange and NOT response body.
# Only the quoted request and the following HTTP status are inspected.
last_matching_line() {
    local file="$1"
    if [[ "$file" == *.gz ]]; then
        gzip -cd -- "$file"
    else
        cat -- "$file"
    fi | awk -v u="$B24_USER" -v require_user="$REQUIRE_LOG_USER" -v p="$EXCHANGE_PATH" '
        {
            q1=index($0,"\""); if (!q1) next
            rest=substr($0,q1+1); q2=index(rest,"\""); if (!q2) next
            request=substr(rest,1,q2-1)
            after=substr(rest,q2+1)
            if (after !~ /^ 200([[:space:]]|$)/) next
            if (index(request,p)==0 || index(request,"type=crm")==0 || index(request,"mode=checkauth")==0) next
            if (require_user=="1" && index($0," - " u " ")==0) next
            line=$0
        }
        END { if (line!="") print line }
    '
}

line_to_epoch() {
    local line="$1" raw normalized
    if [[ "$line" =~ \[([^][]+)\] ]]; then
        raw="${BASH_REMATCH[1]}"
    else
        return 1
    fi
    normalized="$(printf '%s' "$raw" | sed -E 's#^([0-9]{2})/([A-Za-z]{3})/([0-9]{4}):([0-9]{2}:[0-9]{2}:[0-9]{2}) ([+-][0-9]{4})$#\1 \2 \3 \4 \5#')"
    LC_ALL=C date -d "$normalized" +%s 2>/dev/null
}

get_last_success() {
    local line candidate epoch best_epoch=0 file newest
    local log_dir log_base
    local -a archives=()
    LAST_EPOCH=0
    LAST_SOURCE=""
    [ -r "$ACCESS_LOG" ] || return 2

    line="$(last_matching_line "$ACCESS_LOG")" || return 2
    if [ -n "$line" ]; then
        epoch="$(line_to_epoch "$line")" || return 2
        best_epoch="$epoch"
        LAST_SOURCE="$ACCESS_LOG"
    fi

    # Scan rotated logs only when the current one has no fresh successful request.
    if [ "$best_epoch" -le $(( $(date +%s) - ALERT_AFTER )) ]; then
        log_dir="$(dirname "$ACCESS_LOG")"
        log_base="$(basename "$ACCESS_LOG")"
        mapfile -t archives < <(
            find "$log_dir" -maxdepth 1 -type f \
                \( -name "${log_base}.[0-9]*" -o -name "${log_base}-[0-9]*" \) \
                -printf '%T@ %p\n' 2>/dev/null \
                | sort -nr | head -n "$MAX_ROTATED" | cut -d' ' -f2-
        )
        for file in "${archives[@]}"; do
            [ -r "$file" ] || continue
            candidate="$(last_matching_line "$file")" || continue
            [ -n "$candidate" ] || continue
            epoch="$(line_to_epoch "$candidate")" || continue
            if [ "$epoch" -gt "$best_epoch" ]; then
                best_epoch="$epoch"
                LAST_SOURCE="$file"
            fi
        done
    fi
    LAST_EPOCH="$best_epoch"
}

for cmd in awk sed date base64 hostname find sort head cut cat mktemp; do
    command -v "$cmd" >/dev/null 2>&1 || { error "missing command: $cmd"; exit 2; }
done

[[ "$ALERT_AFTER" =~ ^[1-9][0-9]*$ ]] || { error 'ALERT_AFTER must be a positive integer'; exit 2; }
[[ "$MAX_ROTATED" =~ ^[0-9]+$ ]] || { error 'MAX_ROTATED must be an integer'; exit 2; }
[[ "$REQUIRE_LOG_USER" =~ ^[01]$ ]] || { error 'REQUIRE_LOG_USER must be 0 or 1'; exit 2; }
[[ "$MAIL_ENABLED" =~ ^[01]$ ]] || { error 'MAIL_ENABLED must be 0 or 1'; exit 2; }
if [ "$MAIL_ENABLED" = "1" ] && [ ! -x "$SENDMAIL" ]; then
    error "sendmail is missing/not executable: $SENDMAIL"
    exit 2
fi

if [ "$MODE" = "--test-email" ]; then
    [ "$MAIL_ENABLED" = 1 ] || { error 'MAIL_ENABLED=0, test email disabled'; exit 2; }
    if send_email "[TEST] Bitrix24 sync watch: $SITE_NAME" "Postfix/sendmail test from $HOST_NAME at $(date -Is)."; then
        printf 'OK: test email submitted to sendmail, recipient=%s\n' "$EMAIL_TO"
        exit 0
    fi
    exit 2
fi

if [ ! -r "$ACCESS_LOG" ]; then
    error "nginx access log is missing/unreadable: $ACCESS_LOG (adjust ACCESS_LOG in $CONFIG_FILE)"
    exit 2
fi

# Lock only actual scheduled runs. Read-only modes do not affect state.
if [ "$MODE" = "--run" ]; then
    command -v flock >/dev/null 2>&1 || { error 'flock not found'; exit 2; }
    exec 9>"$LOCK_FILE" || exit 2
    if ! flock -n 9; then
        printf 'INFO: another monitor instance is already running\n'
        exit 0
    fi
fi

if ! get_last_success; then
    error "failed to read/parse nginx log: $ACCESS_LOG"
    exit 2
fi

NOW="$(date +%s)"
AGE_SEC=0
LAST_TEXT="not found"
STATUS="ALERT"
if [ "$LAST_EPOCH" -gt 0 ]; then
    AGE_SEC=$((NOW - LAST_EPOCH))
    [ "$AGE_SEC" -lt 0 ] && AGE_SEC=0
    LAST_TEXT="$(date -d "@$LAST_EPOCH" '+%Y-%m-%d %H:%M:%S %z')"
    [ "$AGE_SEC" -le "$ALERT_AFTER" ] && STATUS="OK"
fi
AGE_MIN=$((AGE_SEC / 60))
PREVIOUS_STATE="$(read_state)"

if [ "$MODE" = "--check" ] || [ "$MODE" = "--dry-run" ]; then
    printf 'bitrix24_sync_watch.sh %s\n' "$VERSION"
    printf 'ACCESS_LOG=%s\nB24_USER=%s\nREQUIRE_LOG_USER=%s\n' "$ACCESS_LOG" "$B24_USER" "$REQUIRE_LOG_USER"
    printf 'SYNC_PERIOD_MIN=%s\nALERT_AFTER=%s\n' "$SYNC_PERIOD_MIN" "$ALERT_AFTER"
    printf 'MAIL_ENABLED=%s\nSENDMAIL=%s\nEMAIL_TO=%s\n' "$MAIL_ENABLED" "$SENDMAIL" "$EMAIL_TO"
    printf 'LAST_CHECKAUTH=%s\nLAST_SOURCE=%s\n' "$LAST_TEXT" "${LAST_SOURCE:-not found}"
    printf 'STATE=%s\nRESULT=%s\n' "${PREVIOUS_STATE:-unset}" "$STATUS"
    if [ "$LAST_EPOCH" -gt 0 ]; then
        printf 'AGE_MIN=%s\n' "$AGE_MIN"
    fi
    if [ "$MODE" = "--dry-run" ]; then
        printf 'DRY-RUN: no emails, state changes, or log writes\n'
        exit 0
    fi
    if [ "$STATUS" = "OK" ]; then exit 0; else exit 1; fi
fi

printf '%s: last checkauth=%s, age=%sm, threshold=%sm\n' "$STATUS" "$LAST_TEXT" \
    "$([ "$LAST_EPOCH" -gt 0 ] && printf '%s' "$AGE_MIN" || printf '?')" "$((ALERT_AFTER / 60))"

if [ "$STATUS" = "$PREVIOUS_STATE" ]; then
    exit "$([ "$STATUS" = "OK" ] && printf 0 || printf 1)"
fi

if [ "$STATUS" = "ALERT" ]; then
    log_event ALERT "last checkauth=$LAST_TEXT; log=$ACCESS_LOG" || true
    if ! send_email "[ALERT] Bitrix24: синхронизация не подтверждается — $SITE_NAME" \
        "Не найден свежий успешный checkauth Битрикс24.
Сайт: $SITE_NAME
Сервер: $HOST_NAME
Последний HTTP 200 checkauth: $LAST_TEXT
Время с последнего запроса: $([ "$LAST_EPOCH" -gt 0 ] && printf '%s минут' "$AGE_MIN" || printf 'неизвестно')
Порог: $((ALERT_AFTER / 60)) минут
Пользователь: $B24_USER
Журнал: $ACCESS_LOG

Проверьте интеграцию в Битрикс24, период синхронизации, авторизацию и nginx/PHP. Монитор проверяет лишь HTTP 200 checkauth, а не полный импорт."; then
        log_event ERROR 'failed to send ALERT email; state unchanged for retry' || true
        exit 2
    fi
elif [ "$PREVIOUS_STATE" = "ALERT" ]; then
    log_event RECOVERED "last checkauth=$LAST_TEXT; age=${AGE_MIN}m" || true
    if ! send_email "[OK] Bitrix24: синхронизация возобновилась — $SITE_NAME" \
        "После тревоги появился свежий HTTP 200 checkauth.
Сайт: $SITE_NAME
Сервер: $HOST_NAME
Последний checkauth: $LAST_TEXT
Возраст: $AGE_MIN минут

Это подтверждает запрос авторизации, но не полный цикл импорта."; then
        log_event ERROR 'failed to send RECOVERED email; state unchanged for retry' || true
        exit 2
    fi
else
    log_event STARTED "monitoring active; last checkauth=$LAST_TEXT" || true
fi

write_state "$STATUS" || { error "cannot write state file: $STATE_FILE"; exit 2; }
[ "$STATUS" = "OK" ] && exit 0 || exit 1
