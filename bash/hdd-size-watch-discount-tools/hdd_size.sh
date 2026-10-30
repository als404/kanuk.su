#!/usr/bin/env bash
# hdd_size.sh 2.1-postfix — контроль диска и очистка кеша Bitrix
set -Eeuo pipefail
umask 077
VERSION="2.1-postfix"
CONFIG_FILE="${CONFIG_FILE:-/etc/hdd_size.conf}"
# Все изменяемые настройки задаются в /etc/hdd_size.conf
DISK_TARGET="/dev/vda1"
THRESHOLD=90
CACHE_DIR="/var/www/discount-tools/public/bitrix/cache"
STATE_FILE="/var/tmp/hdd_size.state"
LOCK_FILE="/var/lock/hdd_size.lock"
MAIL_ENABLED=1
SENDMAIL="/usr/sbin/sendmail"
EMAIL_FROM="info@discount-tools.ru"
EMAIL_TO="adm+site@kanuk.su"
HOST_LABEL="discount-tools.ru"
# Игнорируем только гонку удаления кеша, если после попытки диск уже ниже порога.
# Ошибки удаления всегда фиксируются в журнале.

[[ -r "$CONFIG_FILE" ]] || { echo "CONFIG NOT READABLE: $CONFIG_FILE" >&2; exit 1; }
# Конфиг должен принадлежать администратору, не быть редактируемым посторонними.
# shellcheck disable=SC1090
source "$CONFIG_FILE"
MODE="${1:-run}"
log() { printf '%s %s\n' "$(date '+%F %T')" "$*"; }
die() { log "ERROR: $*" >&2; exit 1; }
for cmd in df awk find flock date base64 hostname mktemp rm readlink; do
    command -v "$cmd" >/dev/null 2>&1 || die "не найдена команда $cmd"
done
[[ "$THRESHOLD" =~ ^[0-9]+$ ]] && ((THRESHOLD >= 1 && THRESHOLD <= 100)) || die "THRESHOLD должен быть 1..100"
[[ "$MAIL_ENABLED" == 0 || "$MAIL_ENABLED" == 1 ]] || die "MAIL_ENABLED должен быть 0 или 1"
[[ "$CACHE_DIR" == /* && -d "$CACHE_DIR" && ! -L "$CACHE_DIR" ]] || die "небезопасный CACHE_DIR: $CACHE_DIR"
CACHE_CANON="$(readlink -f -- "$CACHE_DIR")"
[[ "$CACHE_CANON" == */bitrix/cache && "$CACHE_CANON" != /bitrix/cache ]] || die "разрешена очистка только каталога .../bitrix/cache"
[[ "$STATE_FILE" == /* && "$LOCK_FILE" == /* && "$STATE_FILE" != "$LOCK_FILE" ]] || die "ошибочные пути STATE_FILE/LOCK_FILE"
[[ "$EMAIL_FROM" != *$'\n'* && "$EMAIL_TO" != *$'\n'* ]] || die "недопустимые символы в email"

usage_percent() {
    local result
    result="$(df -P -- "$DISK_TARGET" | awk 'NR==2 {gsub(/%/,"",$5); print $5}')" || return 1
    [[ "$result" =~ ^[0-9]+$ ]] || return 1
    printf '%s\n' "$result"
}
free_space() { df -Ph -- "$DISK_TARGET" | awk 'NR==2 {print $4}'; }
read_state() { if [[ -r "$STATE_FILE" ]]; then head -n 1 -- "$STATE_FILE" 2>/dev/null || true; fi; }
write_state() { printf '%s\n' "$1" > "$STATE_FILE"; }
send_email() {
    local subject="$1" body="$2" b64
    [[ "$MAIL_ENABLED" == 1 ]] || return 0
    [[ -x "$SENDMAIL" ]] || { log "WARN: sendmail недоступен: $SENDMAIL" >&2; return 1; }
    b64="$(printf '%s' "$subject" | base64 | tr -d '\n')"
    {
        printf 'From: %s\n' "$EMAIL_FROM"
        printf 'To: %s\n' "$EMAIL_TO"
        printf 'Subject: =?UTF-8?B?%s?=\n' "$b64"
        printf 'MIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: base64\n\n'
        printf '%s' "$body" | base64
    } | "$SENDMAIL" -f "$EMAIL_FROM" -t -i
}

case "$MODE" in
    --check)
        printf 'hdd_size.sh %s\n' "$VERSION"
        printf 'DISK_TARGET=%s\nUSAGE=%s%%\nFREE=%s\nTHRESHOLD=%s%%\n' "$DISK_TARGET" "$(usage_percent)" "$(free_space)" "$THRESHOLD"
        printf 'CACHE_DIR=%s\nSTATE=%s\nMAIL_ENABLED=%s\nSENDMAIL=%s\nEMAIL_FROM=%s\nEMAIL_TO=%s\n' "$CACHE_CANON" "$(read_state)" "$MAIL_ENABLED" "$SENDMAIL" "$EMAIL_FROM" "$EMAIL_TO"
        exit 0;;
    --dry-run)
        usage="$(usage_percent)" || die "не удалось прочитать заполнение"
        if ((usage >= THRESHOLD)); then
            log "DRY-RUN: при ${usage}% был бы очищен $CACHE_CANON"
        else
            log "DRY-RUN: ${usage}% ниже порога ${THRESHOLD}%, очистка не требуется"
        fi
        exit 0;;
    run) ;;
    *) printf 'Использование: %s [--check|--dry-run]\n' "$0"; exit 2;;
esac

exec 9>"$LOCK_FILE"
flock -n 9 || exit 0
usage="$(usage_percent)" || die "не удалось прочитать заполнение"
free="$(free_space)"
previous="$(read_state)"
if ((usage < THRESHOLD)); then
    if [[ "$previous" == ALERT ]]; then
        log "RECOVERED: диск $DISK_TARGET заполнен на ${usage}%, свободно $free"
        send_email "[OK] $HOST_LABEL: место на диске восстановлено" "Сервер: $HOST_LABEL
Диск: $DISK_TARGET
Занято: ${usage}%
Свободно: $free
Порог: ${THRESHOLD}%" || log "WARN: уведомление OK не отправлено" >&2
    fi
    write_state OK
    exit 0
fi

log "START: занято ${usage}%, свободно $free; очистка $CACHE_CANON"
# Активный Bitrix может создавать файлы одновременно с rm. Фиксируем ошибки,
# а результат определяем по повторному df, не по одному сообщению rm.
errfile="$(mktemp /tmp/hdd_size_cleanup.XXXXXXXX)" || die "не удалось создать временный файл"
trap 'rm -f -- "$errfile"' EXIT
cleanup_rc=0
find "$CACHE_CANON" -mindepth 1 -maxdepth 1 -exec rm -rf -- {} + 2>"$errfile" || cleanup_rc=$?
if ((cleanup_rc != 0)); then
    log "WARN: удаление кеша вернуло код $cleanup_rc; последние сообщения:" >&2
    tail -n 15 -- "$errfile" >&2 || true
fi
after="$(usage_percent)" || die "не удалось проверить диск после очистки"
after_free="$(free_space)"
log "RESULT: before=${usage}% after=${after}% free_after=$after_free delete_rc=$cleanup_rc"
if ((after < THRESHOLD)); then
    write_state OK
    if [[ "$previous" == ALERT ]]; then
        send_email "[OK] $HOST_LABEL: очистка кеша завершена" "Было: ${usage}%
Стало: ${after}%
Свободно: $after_free
Каталог: $CACHE_CANON
Ошибки удаления: $cleanup_rc" || log "WARN: уведомление OK не отправлено" >&2
    elif [[ "$previous" != OK ]]; then
        send_email "[INFO] $HOST_LABEL: освобождено место" "Было: ${usage}%
Стало: ${after}%
Свободно: $after_free
Каталог: $CACHE_CANON
Ошибки удаления: $cleanup_rc" || log "WARN: уведомление INFO не отправлено" >&2
    fi
    exit 0
fi

write_state ALERT
if [[ "$previous" != ALERT ]]; then
    send_email "[ALERT] $HOST_LABEL: диск заполнен на ${after}%" "Диск: $DISK_TARGET
До очистки: ${usage}%
После очистки: ${after}%
Свободно: $after_free
Каталог: $CACHE_CANON
Код удаления: $cleanup_rc" || log "WARN: уведомление ALERT не отправлено" >&2
fi
log "ERROR: место после очистки по-прежнему ниже порога свободного пространства (${after}% занято)" >&2
exit 1
