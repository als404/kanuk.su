#!/usr/bin/env bash
#
# hdd_size.sh - контроль заполнения диска discount-tools.ru
#
# Подтверждённая исходная логика:
#   - контролировать /dev/vda1;
#   - порог 90%;
#   - при достижении порога очистить:
#       /home/bitrix/ext_www/public_html/bitrix/cache/*
#
# Текущая Git-версия дополнительно:
#   - использует df -P;
#   - защищена flock от параллельного запуска;
#   - хранит state OK/ALERT;
#   - может уведомлять через msmtp;
#   - поддерживает --check и --dry-run.
#

set -Eeuo pipefail
umask 077

VERSION="2.0-git"

CONFIG_FILE="${CONFIG_FILE:-/etc/hdd_size.conf}"

# ---------------------------------------------------------------------------
# Значения текущего сервера
# ---------------------------------------------------------------------------

DISK_TARGET="/dev/vda1"
THRESHOLD=90

CACHE_DIR="/home/bitrix/ext_www/public_html/bitrix/cache"

STATE_FILE="/var/tmp/hdd_size.state"
LOCK_FILE="/var/lock/hdd_size.lock"

MAIL_ENABLED=1
MSMTP="/usr/bin/msmtp"
MSMTP_CONFIG="/home/bitrix/.msmtprc"
MSMTP_ACCOUNT="discount-tools.ru"
EMAIL_FROM="info@discount-tools.ru"
EMAIL_TO="adm+site@kanuk.su"

# Внешний конфиг позволяет переносить скрипт без изменения файла из Git.
if [ -r "$CONFIG_FILE" ]; then
    # shellcheck disable=SC1090
    . "$CONFIG_FILE"
fi

HOST_NAME="$(hostname -f 2>/dev/null || hostname)"
MODE="${1:-run}"

# ---------------------------------------------------------------------------
# Вспомогательные функции
# ---------------------------------------------------------------------------

die() {
    printf '%s ERROR: %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >&2
    exit 1
}

need_cmd() {
    command -v "$1" >/dev/null 2>&1 || die "не найдена команда: $1"
}

get_usage_percent() {
    local value

    value="$(
        df -P "$DISK_TARGET" 2>/dev/null \
        | awk 'NR==2 {gsub("%","",$5); print $5}'
    )"

    [[ "$value" =~ ^[0-9]+$ ]] || return 1
    printf '%s\n' "$value"
}

get_free_human() {
    df -Ph "$DISK_TARGET" 2>/dev/null | awk 'NR==2 {print $4}'
}

get_state() {
    if [ -r "$STATE_FILE" ]; then
        head -n 1 "$STATE_FILE" 2>/dev/null || true
    fi
}

set_state() {
    printf '%s\n' "$1" > "$STATE_FILE"
}

send_email() {
    local subject="$1"
    local body="$2"
    local subject_b64
    local rc

    [ "$MAIL_ENABLED" -eq 1 ] || return 0

    if [ ! -x "$MSMTP" ]; then
        printf '%s ERROR: msmtp недоступен: %s\n' \
            "$(date '+%Y-%m-%d %H:%M:%S')" "$MSMTP" >&2
        return 1
    fi

    if [ ! -r "$MSMTP_CONFIG" ]; then
        printf '%s ERROR: конфиг msmtp недоступен: %s\n' \
            "$(date '+%Y-%m-%d %H:%M:%S')" "$MSMTP_CONFIG" >&2
        return 1
    fi

    subject_b64="$(printf '%s' "$subject" | base64 | tr -d '\n')"

    set +e
    {
        printf 'From: %s\n' "$EMAIL_FROM"
        printf 'To: %s\n' "$EMAIL_TO"
        printf 'Subject: =?UTF-8?B?%s?=\n' "$subject_b64"
        printf 'MIME-Version: 1.0\n'
        printf 'Content-Type: text/plain; charset=UTF-8\n'
        printf 'Content-Transfer-Encoding: base64\n'
        printf '\n'
        printf '%s' "$body" | base64
    } | "$MSMTP" \
        -C "$MSMTP_CONFIG" \
        -a "$MSMTP_ACCOUNT" \
        -t
    rc=$?
    set -e

    return "$rc"
}

cleanup_cache() {
    # Защита от ошибочного/пустого пути.
    [ -n "$CACHE_DIR" ] || die "CACHE_DIR пуст"
    [ "$CACHE_DIR" != "/" ] || die "CACHE_DIR не может быть /"
    [ -d "$CACHE_DIR" ] || die "каталог кэша не найден: $CACHE_DIR"

    if [ "$MODE" = "--dry-run" ]; then
        printf '%s DRY-RUN: была бы выполнена очистка %s/*\n' \
            "$(date '+%Y-%m-%d %H:%M:%S')" "$CACHE_DIR"
        return 0
    fi

    # Удаляем только содержимое каталога, сам каталог сохраняем.
    find "$CACHE_DIR" -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +
}

# ---------------------------------------------------------------------------
# Проверки
# ---------------------------------------------------------------------------

for cmd in df awk find flock date base64 hostname; do
    need_cmd "$cmd"
done

[ -d "$CACHE_DIR" ] || die "каталог кэша не существует: $CACHE_DIR"

usage="$(get_usage_percent)" || die "не удалось определить заполнение $DISK_TARGET"
free="$(get_free_human || printf '?')"

if [ "$MODE" = "--check" ]; then
    printf 'hdd_size.sh %s\n' "$VERSION"
    printf 'DISK_TARGET=%s\n' "$DISK_TARGET"
    printf 'USAGE=%s%%\n' "$usage"
    printf 'FREE=%s\n' "$free"
    printf 'THRESHOLD=%s%%\n' "$THRESHOLD"
    printf 'CACHE_DIR=%s\n' "$CACHE_DIR"
    printf 'STATE=%s\n' "$(get_state)"
    printf 'MAIL_ENABLED=%s\n' "$MAIL_ENABLED"
    printf 'MSMTP=%s\n' "$MSMTP"
    printf 'MSMTP_CONFIG=%s\n' "$MSMTP_CONFIG"
    exit 0
fi

if [ "$MODE" != "run" ] && [ "$MODE" != "--dry-run" ]; then
    cat <<EOF
Использование:
  $0
  $0 --check
  $0 --dry-run
EOF
    exit 2
fi

# Один экземпляр скрипта одновременно.
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    # Cron может запускаться каждую минуту; второй экземпляр просто завершаем.
    exit 0
fi

previous_state="$(get_state)"

if [ "$usage" -lt "$THRESHOLD" ]; then
    if [ "$previous_state" = "ALERT" ]; then
        printf '%s RECOVERED: disk=%s used=%s%% free=%s threshold=%s%%\n' \
            "$(date '+%Y-%m-%d %H:%M:%S')" \
            "$DISK_TARGET" "$usage" "$free" "$THRESHOLD"

        send_email \
            "[OK] discount-tools.ru: заполнение диска нормализовалось" \
            "Сервер: ${HOST_NAME}
Диск: ${DISK_TARGET}
Текущее заполнение: ${usage}%
Свободно: ${free}
Порог очистки: ${THRESHOLD}%

Состояние диска вернулось ниже порога." || true
    fi

    set_state "OK"
    exit 0
fi

before="$usage"
before_free="$free"

cleanup_cache

after="$(get_usage_percent)" || die "не удалось повторно определить заполнение $DISK_TARGET"
after_free="$(get_free_human || printf '?')"

printf '%s CLEANUP: disk=%s before=%s%% free_before=%s after=%s%% free_after=%s cache=%s%s\n' \
    "$(date '+%Y-%m-%d %H:%M:%S')" \
    "$DISK_TARGET" "$before" "$before_free" "$after" "$after_free" "$CACHE_DIR" \
    "$([ "$MODE" = "--dry-run" ] && printf ' DRY-RUN' || true)"

# Не отправляем одинаковое письмо каждую минуту, пока проблема сохраняется.
if [ "$previous_state" != "ALERT" ]; then
    send_email \
        "[ALERT] discount-tools.ru: заполнение диска ${before}%" \
        "Сервер: ${HOST_NAME}
Диск: ${DISK_TARGET}
Порог: ${THRESHOLD}%

До очистки:
  занято: ${before}%
  свободно: ${before_free}

После очистки Bitrix cache:
  занято: ${after}%
  свободно: ${after_free}

Очищен каталог:
${CACHE_DIR}

Режим: ${MODE}" || true
fi

set_state "ALERT"
exit 0
