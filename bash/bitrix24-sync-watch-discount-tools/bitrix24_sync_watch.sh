#!/usr/bin/env bash
#
# bitrix24_sync_watch.sh
# Monitoring Bitrix24 <-> discount-tools.ru synchronization by successful
# checkauth requests in the nginx access log.
#
# The script does NOT change Bitrix24 settings and does NOT restart services.
# It only reads nginx logs, keeps a small state file and sends notifications.
#

set -u
umask 077

VERSION="1.0.0"

CONFIG_FILE="${CONFIG_FILE:-/etc/bitrix24_sync_watch.conf}"

SITE_NAME="discount-tools.ru"

ACCESS_LOG="/var/log/nginx/discount-tools.ru_access.log"
B24_USER="bx_eshop"
EXCHANGE_PATH="/bitrix/admin/1c_exchange.php"

SYNC_PERIOD_MIN=10
ALERT_AFTER=1500

STATE_FILE="/var/tmp/bitrix24_sync_watch.state"
MONITOR_LOG="/var/log/bitrix24_sync_watch.log"

MSMTP="/usr/bin/msmtp"
MSMTP_CONFIG="/home/bitrix/.msmtprc"
MSMTP_ACCOUNT="discount-tools.ru"

EMAIL_FROM="info@discount-tools.ru"
EMAIL_TO="adm+site@kanuk.su"

if [ -r "$CONFIG_FILE" ]; then
    # shellcheck disable=SC1090
    . "$CONFIG_FILE"
fi

HOST_NAME="$(hostname -f 2>/dev/null || hostname)"

log_event() {
    local event="$1"
    shift
    mkdir -p "$(dirname "$MONITOR_LOG")" 2>/dev/null || true
    printf '%s %s: %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$event" "$*" >> "$MONITOR_LOG"
}

write_state() {
    printf '%s\n' "$1" > "$STATE_FILE"
}

read_state() {
    if [ -r "$STATE_FILE" ]; then
        head -n 1 "$STATE_FILE" 2>/dev/null || true
    fi
}

send_email() {
    local subject="$1"
    local body="$2"
    local subject_b64
    local rc

    if [ ! -x "$MSMTP" ]; then
        log_event "ERROR" "msmtp not found or not executable: $MSMTP"
        return 1
    fi

    if [ ! -r "$MSMTP_CONFIG" ]; then
        log_event "ERROR" "msmtp config is not readable: $MSMTP_CONFIG"
        return 1
    fi

    subject_b64="$(printf '%s' "$subject" | base64 | tr -d '\n')"

    {
        printf 'From: %s\n' "$EMAIL_FROM"
        printf 'To: %s\n' "$EMAIL_TO"
        printf 'Subject: =?UTF-8?B?%s?=\n' "$subject_b64"
        printf 'MIME-Version: 1.0\n'
        printf 'Content-Type: text/plain; charset=UTF-8\n'
        printf 'Content-Transfer-Encoding: base64\n'
        printf '\n'
        printf '%s' "$body" | base64
    } | "$MSMTP" -C "$MSMTP_CONFIG" -a "$MSMTP_ACCOUNT" -t

    rc=$?
    if [ "$rc" -ne 0 ]; then
        log_event "ERROR" "msmtp exited with code $rc while sending: $subject"
        return "$rc"
    fi

    return 0
}

last_success_from_plain() {
    local file="$1"

    [ -r "$file" ] || return 0

    awk \
        -v user=" - ${B24_USER} " \
        -v path="$EXCHANGE_PATH" '
        index($0, user) &&
        index($0, path) &&
        index($0, "type=crm") &&
        index($0, "mode=checkauth") &&
        $0 ~ /" 200 / {
            line=$0
        }
        END {
            if (line != "") print line
        }
    ' "$file"
}

last_success_from_gzip() {
    local file="$1"

    [ -r "$file" ] || return 0

    gzip -cd -- "$file" 2>/dev/null | awk \
        -v user=" - ${B24_USER} " \
        -v path="$EXCHANGE_PATH" '
        index($0, user) &&
        index($0, path) &&
        index($0, "type=crm") &&
        index($0, "mode=checkauth") &&
        $0 ~ /" 200 / {
            line=$0
        }
        END {
            if (line != "") print line
        }
    '
}

line_to_epoch() {
    local line="$1"
    local raw
    local normalized

    raw="$(printf '%s\n' "$line" | sed -n 's/.*\[\([^]]*\)\].*/\1/p')"
    [ -n "$raw" ] || return 1

    normalized="$(printf '%s\n' "$raw" | sed -E \
        's#^([0-9]{2})/([A-Za-z]{3})/([0-9]{4}):([0-9]{2}:[0-9]{2}:[0-9]{2}) ([+-][0-9]{4})$#\1 \2 \3 \4 \5#')"

    LC_ALL=C date -d "$normalized" '+%s' 2>/dev/null
}

find_last_success() {
    local current_line=""
    local archive_line=""
    local current_epoch=0
    local archive_epoch=0
    local latest_archive=""

    current_line="$(last_success_from_plain "$ACCESS_LOG")"

    latest_archive="$(ls -1t "${ACCESS_LOG}"-*.gz 2>/dev/null | head -n 1 || true)"
    if [ -n "$latest_archive" ]; then
        archive_line="$(last_success_from_gzip "$latest_archive")"
    fi

    if [ -n "$current_line" ]; then
        current_epoch="$(line_to_epoch "$current_line" || printf '0')"
    fi

    if [ -n "$archive_line" ]; then
        archive_epoch="$(line_to_epoch "$archive_line" || printf '0')"
    fi

    if [ "$current_epoch" -ge "$archive_epoch" ] && [ "$current_epoch" -gt 0 ]; then
        printf '%s\n' "$current_line"
        return 0
    fi

    if [ "$archive_epoch" -gt 0 ]; then
        printf '%s\n' "$archive_line"
        return 0
    fi

    return 1
}

alert_body() {
    local last_text="$1"
    local age_text="$2"

    cat <<EOF
На сервере ${HOST_NAME} не обнаружено свежей успешной синхронизации Bitrix24 с сайтом ${SITE_NAME}.

Последний успешный checkauth: ${last_text}
Без успешной синхронизации: ${age_text}

Штатный период синхронизации: ${SYNC_PERIOD_MIN} минут
Порог тревоги: $((ALERT_AFTER / 60)) минут
Пользователь интеграции: ${B24_USER}
Контролируемый URL: ${EXCHANGE_PATH}?type=crm&mode=checkauth
Ожидаемый HTTP status: 200

Проверьте интеграцию сайта в Bitrix24. В частности, убедитесь, что период синхронизации не стал равен 0 (ручной режим), а также проверьте доступность сайта, авторизацию и HTTP-ошибки.

Монитор: ${0}
Лог монитора: ${MONITOR_LOG}
Nginx access-log: ${ACCESS_LOG}
EOF
}

recovered_body() {
    local last_text="$1"
    local age_min="$2"

    cat <<EOF
Синхронизация Bitrix24 с сайтом ${SITE_NAME} восстановлена.

Последний успешный checkauth: ${last_text}
Возраст последнего успешного запроса: ${age_min} мин.

Пользователь интеграции: ${B24_USER}
Контролируемый URL: ${EXCHANGE_PATH}?type=crm&mode=checkauth
HTTP status: 200

Сервер: ${HOST_NAME}
EOF
}

for cmd in awk sed date base64 gzip hostname; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        log_event "ERROR" "required command not found: $cmd"
        printf 'ERROR: required command not found: %s\n' "$cmd" >&2
        exit 2
    fi
done

previous_state="$(read_state)"
last_line=""

if last_line="$(find_last_success)"; then
    last_epoch="$(line_to_epoch "$last_line" || true)"

    if [ -z "${last_epoch:-}" ]; then
        log_event "ERROR" "cannot parse nginx timestamp from matching line"
        printf 'ERROR: cannot parse timestamp from nginx log\n' >&2
        exit 3
    fi

    now_epoch="$(date '+%s')"
    age_sec=$((now_epoch - last_epoch))
    [ "$age_sec" -lt 0 ] && age_sec=0

    age_min=$((age_sec / 60))
    last_text="$(date -d "@${last_epoch}" '+%Y-%m-%d %H:%M:%S %z')"

    if [ "$age_sec" -le "$ALERT_AFTER" ]; then
        printf 'OK: последний checkauth %s, прошло %s мин.\n' "$last_text" "$age_min"

        if [ "$previous_state" = "ALERT" ]; then
            log_event "RECOVERED" "last checkauth=${last_text}; age=${age_min}m"
            send_email \
                "[OK] Bitrix24: синхронизация восстановлена" \
                "$(recovered_body "$last_text" "$age_min")" || true
        elif [ -z "$previous_state" ]; then
            log_event "STARTED" "monitoring active; last checkauth=${last_text}; age=${age_min}m"
        fi

        write_state "OK"
        exit 0
    fi

    printf 'ALERT: последний checkauth %s, прошло %s мин.\n' "$last_text" "$age_min"

    if [ "$previous_state" != "ALERT" ]; then
        log_event "ALERT" "last checkauth=${last_text}; age=${age_min}m"
        send_email \
            "[ALERT] Bitrix24: синхронизация остановлена" \
            "$(alert_body "$last_text" "${age_min} мин.")" || true
    fi

    write_state "ALERT"
    exit 1
fi

printf 'ALERT: успешный checkauth Bitrix24 не найден в доступных nginx-логах.\n'

if [ "$previous_state" != "ALERT" ]; then
    log_event "ALERT" "successful checkauth not found in current or newest rotated nginx log"
    send_email \
        "[ALERT] Bitrix24: успешный checkauth не найден" \
        "$(alert_body "не найден" "неизвестно")" || true
fi

write_state "ALERT"
exit 1
