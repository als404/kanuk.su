#!/bin/bash
#
# backup.sh — резервное копирование сайта Bitrix + MySQL на FTP
# Версия 2.1-stream
#
# Основная идея:
#   1) рабочий кэш сайта НЕ очищается;
#   2) кэши НЕ включаются в архив;
#   3) скрытые файлы корня сайта (.htaccess и т.п.) сохраняются;
#   4) большой итоговый архив НЕ создаётся локально —
#      tar.gz передаётся потоком прямо на FTP;
#   5) на FTP хранятся только последние 10 успешных архивов;
#   6) старые архивы удаляются только после успешной загрузки нового;
#   7) ошибки отправляются на email через msmtp.
#
# Совместим с существующим:
#   /home/script/inc/backup.conf
#

set -Eeuo pipefail
umask 077

VERSION="2.1-stream"

# -----------------------------------------------------------------------------
# НАСТРОЙКИ
# -----------------------------------------------------------------------------

CONFIG="/home/script/inc/backup.conf"

LOG_FILE="/home/script/logs/backup_v2.log"
LOCK_FILE="/var/lock/backup_v2.lock"

# Для потокового backup локально нужен только сжатый дамп MySQL
# и небольшой рабочий каталог.
MIN_FREE_MB=1024

# Для локальных режимов --all-noftp / --site нужен больший запас.
LOCAL_ARCHIVE_MIN_FREE_GB=5

# На FTP всегда оставляем последние 10 УСПЕШНЫХ архивов.
FTP_KEEP_COUNT=10

# Проверять локально созданные gzip/tar.gz архивы.
VERIFY_LOCAL_ARCHIVES=1

# Уведомлять об успешном backup:
# 0 = только ошибки
# 1 = ошибки + успешный полный backup
MAIL_ON_SUCCESS=0

EMAIL_TO="adm+site@kanuk.su"
EMAIL_FROM="info@discount-tools.ru"

BACKUP_PREFIX="kanuk"

HOST="$(hostname -f 2>/dev/null || hostname)"
TIMESTAMP="$(date '+%Y-%m-%d_%H%M%S')"

# Совместимость со старым backup.conf, где использовалась опечатка TIMESHTAMP.
TIMESHTAMP="$TIMESTAMP"

START_EPOCH="$(date '+%s')"
MODE=""
CURRENT_STAGE="инициализация"

WORK_DIR=""
MY_CNF=""
NETRC_FILE=""
REMOTE_PARTIAL_NAME=""

# Эти каталоги не нужны для восстановления и не должны раздувать backup.
TAR_EXCLUDES=(
    "--exclude=./bitrix/cache"
    "--exclude=./bitrix/managed_cache"
    "--exclude=./bitrix/stack_cache"
    "--exclude=./bitrix/html_pages"
    "--exclude=./bitrix/backup"
    "--exclude=./bitrix/updates"
    "--exclude=./upload/tmp"
    "--exclude=./upload/resize_cache"
)

# -----------------------------------------------------------------------------
# ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
# -----------------------------------------------------------------------------

usage() {
    cat <<'EOF'
Использование:

  /home/script/backup.sh --all
      Полный backup: MySQL + файлы сайта -> tar.gz потоком прямо на FTP.
      После успешной загрузки оставить на FTP только последние 10 архивов.

  /home/script/backup.sh --check
      Проверить конфигурацию, команды, MySQL, FTP, диск и msmtp.
      Ничего не архивирует и ничего не удаляет.

  /home/script/backup.sh --sql
      Создать только локальный сжатый дамп MySQL.

  /home/script/backup.sh --site
      Создать только локальный tar.gz сайта без кэшей.
      Требует дополнительного свободного места на локальном диске.

  /home/script/backup.sh --all-noftp
      Создать полный tar.gz локально.
      Требует дополнительного свободного места на локальном диске.

  /home/script/backup.sh --clear-ftp
      Оставить на FTP только последние 10 архивов.
      Также удалить зависшие *.part от прошлых неудачных загрузок.

  /home/script/backup.sh --test-email
      Отправить тестовое письмо через msmtp.

  /home/script/backup.sh -h | --help
      Показать справку.
EOF
}

log() {
    mkdir -p "$(dirname "$LOG_FILE")"
    printf '%s [%s] %s\n' \
        "$(date '+%Y-%m-%d %H:%M:%S')" \
        "$CURRENT_STAGE" \
        "$*" >> "$LOG_FILE"
}

say() {
    printf '%s\n' "$*"
    log "$*"
}

human_bytes() {
    local bytes="${1:-0}"

    awk -v b="$bytes" '
    function human(x) {
        split("B KB MB GB TB PB", u, " ")
        i=1
        while (x >= 1024 && i < 6) {
            x /= 1024
            i++
        }
        if (i == 1)
            return sprintf("%.0f %s", x, u[i])
        return sprintf("%.2f %s", x, u[i])
    }
    BEGIN { print human(b) }'
}

duration_text() {
    local seconds="$1"
    printf '%02d:%02d:%02d' \
        $((seconds / 3600)) \
        $(((seconds % 3600) / 60)) \
        $((seconds % 60))
}

need_cmd() {
    local cmd="$1"

    if ! command -v "$cmd" >/dev/null 2>&1; then
        log "ERROR: не найдена команда: $cmd"
        return 1
    fi
}

file_size() {
    stat -c '%s' "$1"
}

get_usage_percent() {
    df -P "$1" | awk 'NR==2 {gsub("%","",$5); print $5}'
}

get_available_bytes() {
    df -PB1 "$1" | awk 'NR==2 {print $4}'
}

ftp_base_url() {
    local dir="${FTP_DIR:-}"

    if [ -n "$dir" ]; then
        dir="${dir#/}"
        dir="${dir%/}"
        printf 'ftp://%s/%s/' "$FTP_IP" "$dir"
    else
        printf 'ftp://%s/' "$FTP_IP"
    fi
}

escape_option() {
    local value="$1"
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    printf '%s' "$value"
}

send_email() {
    local subject="$1"
    local body="$2"
    local subject_b64
    local rc

    if [ ! -x /usr/sbin/sendmail ]; then
        log "ERROR: sendmail не найден"
        return 1
    fi

    subject_b64="$(printf '%s' "$subject" | base64 | tr -d '\n')"

    set +e
    {
        echo "From: ${EMAIL_FROM}"
        echo "To: ${EMAIL_TO}"
        echo "Subject: =?UTF-8?B?${subject_b64}?="
        echo "MIME-Version: 1.0"
        echo "Content-Type: text/plain; charset=UTF-8"
        echo "Content-Transfer-Encoding: base64"
        echo
        printf '%s' "$body" | base64
    } | /usr/sbin/sendmail -f "$EMAIL_FROM" -t -i
    rc=$?
    set -e

    if [ "$rc" -ne 0 ]; then
        log "ERROR: sendmail завершился с кодом $rc"
        return "$rc"
    fi

    return 0
}

create_mysql_config() {
    MY_CNF="$WORK_DIR/mysql-backup.cnf"

    {
        echo "[client]"
        printf 'host="%s"\n' "$(escape_option "$DB_HOST")"
        printf 'user="%s"\n' "$(escape_option "$DB_USER")"
        printf 'password="%s"\n' "$(escape_option "$DB_PASS")"
    } > "$MY_CNF"

    chmod 600 "$MY_CNF"
}

create_netrc() {
    NETRC_FILE="$WORK_DIR/ftp.netrc"

    {
        printf 'machine %s\n' "$FTP_IP"
        printf 'login %s\n' "$FTP_USER"
        printf 'password %s\n' "$FTP_PASS"
    } > "$NETRC_FILE"

    chmod 600 "$NETRC_FILE"
}

ftp_list() {
    local base
    base="$(ftp_base_url)"

    curl \
        --silent \
        --show-error \
        --fail \
        --connect-timeout 20 \
        --netrc-file "$NETRC_FILE" \
        --list-only \
        "$base"
}

cleanup_local_secrets() {
    set +e

    [ -n "${MY_CNF:-}" ] && rm -f -- "$MY_CNF"
    [ -n "${NETRC_FILE:-}" ] && rm -f -- "$NETRC_FILE"

    set -e
}

cleanup_workdir() {
    set +e

    cleanup_local_secrets

    if [ -n "${WORK_DIR:-}" ] && [ -d "${WORK_DIR:-}" ]; then
        rm -rf -- "$WORK_DIR"
    fi

    set -e
}

cleanup_partial_remote() {
    set +e

    if [ -n "${REMOTE_PARTIAL_NAME:-}" ] \
       && [ -n "${WORK_DIR:-}" ] \
       && [ -d "${WORK_DIR:-}" ]; then

        if [ -z "${NETRC_FILE:-}" ] || [ ! -f "${NETRC_FILE:-}" ]; then
            create_netrc 2>/dev/null
        fi

        if [ -n "${NETRC_FILE:-}" ] && [ -f "$NETRC_FILE" ]; then
            curl \
                --silent \
                --show-error \
                --connect-timeout 10 \
                --netrc-file "$NETRC_FILE" \
                --quote "DELE $REMOTE_PARTIAL_NAME" \
                "$(ftp_base_url)" \
                -o /dev/null >/dev/null 2>&1
        fi
    fi

    set -e
}

failure_email() {
    local rc="$1"
    local line="$2"
    local elapsed

    elapsed=$(( $(date '+%s') - START_EPOCH ))

    send_email \
        "[CRITICAL] ${HOST}: ошибка резервного копирования" \
"Резервное копирование завершилось ошибкой.

Сервер:
${HOST}

Режим:
${MODE:-не определён}

Этап:
${CURRENT_STAGE}

Код ошибки:
${rc}

Строка:
${line}

Время:
$(date '+%Y-%m-%d %H:%M:%S %z')

Длительность:
$(duration_text "$elapsed")

Старые успешные архивы на FTP при ошибке не удаляются.

Лог:
${LOG_FILE}"
}

on_error() {
    local rc="$1"
    local line="$2"

    trap - ERR
    set +e

    log "ERROR: аварийное завершение, code=$rc, line=$line"

    failure_email "$rc" "$line" || \
        log "ERROR: не удалось отправить email об ошибке"

    cleanup_partial_remote
    cleanup_workdir

    exit "$rc"
}

trap 'on_error $? $LINENO' ERR
trap cleanup_local_secrets EXIT

# -----------------------------------------------------------------------------
# КОНФИГУРАЦИЯ
# -----------------------------------------------------------------------------

require_var() {
    local name="$1"

    if [ -z "${!name:-}" ]; then
        log "ERROR: в $CONFIG не задан параметр $name"
        return 1
    fi
}

load_config() {
    CURRENT_STAGE="конфигурация"

    if [ ! -f "$CONFIG" ]; then
        log "ERROR: не найден $CONFIG"
        return 1
    fi

    # backup.conf использует TIMESHTAMP при формировании старого TAR_NAME.
    # Переменная объявлена заранее, поэтому set -u не мешает загрузке.
    # shellcheck source=/dev/null
    source "$CONFIG"

    require_var DIR_SITE
    require_var DIR_BACKUP

    require_var DB_HOST
    require_var DB_USER
    require_var DB_PASS
    require_var DB_NAME

    require_var FTP_IP
    require_var FTP_USER
    require_var FTP_PASS

    FTP_DIR="${FTP_DIR:-}"

    if [ ! -d "$DIR_SITE" ]; then
        log "ERROR: не найден каталог сайта: $DIR_SITE"
        return 1
    fi

    mkdir -p "$DIR_BACKUP"
    mkdir -p "$(dirname "$LOG_FILE")"

    local conf_mode
    conf_mode="$(stat -c '%a' "$CONFIG" 2>/dev/null || echo unknown)"

    log "CONFIG: $CONFIG, mode=$conf_mode"

    if [[ "$conf_mode" =~ ^[0-9]+$ ]] && [ "$conf_mode" -gt 600 ]; then
        log "WARNING: в backup.conf находятся пароли; рекомендуется chmod 600 $CONFIG"
    fi
}

create_workdir() {
    CURRENT_STAGE="рабочий каталог"

    WORK_DIR="$DIR_BACKUP/.backup_v2_work/${TIMESTAMP}-$$"
    mkdir -p "$WORK_DIR/__backup"

    log "WORKDIR: $WORK_DIR"
}

# -----------------------------------------------------------------------------
# ПРОВЕРКИ ДИСКА
# -----------------------------------------------------------------------------

check_streaming_disk() {
    local path="$1"
    local usage
    local avail
    local min_bytes

    usage="$(get_usage_percent "$path")"
    avail="$(get_available_bytes "$path")"
    min_bytes=$((MIN_FREE_MB * 1024 * 1024))

    log "DISK: used=${usage}%, free=$(human_bytes "$avail"), streaming minimum=${MIN_FREE_MB} MB"

    if [ "$avail" -lt "$min_bytes" ]; then
        log "ERROR: для временного дампа БД осталось меньше ${MIN_FREE_MB} MB"
        return 1
    fi
}

check_local_archive_disk() {
    local path="$1"
    local avail
    local min_bytes

    avail="$(get_available_bytes "$path")"
    min_bytes=$((LOCAL_ARCHIVE_MIN_FREE_GB * 1024 * 1024 * 1024))

    log "DISK LOCAL: free=$(human_bytes "$avail"), minimum=${LOCAL_ARCHIVE_MIN_FREE_GB} GiB"

    if [ "$avail" -lt "$min_bytes" ]; then
        log "ERROR: локальный архив не создаётся: свободно меньше ${LOCAL_ARCHIVE_MIN_FREE_GB} GiB"
        return 1
    fi
}

# -----------------------------------------------------------------------------
# MYSQL
# -----------------------------------------------------------------------------

mysql_database_bytes() {
    local result

    create_mysql_config

    result="$(
        mysql \
            --defaults-extra-file="$MY_CNF" \
            --batch \
            --skip-column-names \
            -e "SELECT COALESCE(SUM(data_length + index_length),0)
                FROM information_schema.tables
                WHERE table_schema='${DB_NAME//\'/\'\'}';"
    )"

    rm -f "$MY_CNF"
    MY_CNF=""

    printf '%s' "${result:-0}"
}

create_db_dump() {
    CURRENT_STAGE="дамп MySQL"

    local dump_file="$WORK_DIR/__backup/${DB_NAME}.sql.gz"
    local dump_size

    create_mysql_config

    log "DB: создаю сжатый дамп $DB_NAME"

    # nice/ionice снижают влияние backup на живой сервер.
    if command -v ionice >/dev/null 2>&1; then
        ionice -c2 -n7 nice -n 10 \
            mysqldump \
                --defaults-extra-file="$MY_CNF" \
                --single-transaction \
                --quick \
                --triggers \
                --hex-blob \
                "$DB_NAME" \
            | gzip -1 > "$dump_file"
    else
        nice -n 10 \
            mysqldump \
                --defaults-extra-file="$MY_CNF" \
                --single-transaction \
                --quick \
                --triggers \
                --hex-blob \
                "$DB_NAME" \
            | gzip -1 > "$dump_file"
    fi

    rm -f "$MY_CNF"
    MY_CNF=""

    gzip -t "$dump_file"

    dump_size="$(file_size "$dump_file")"

    if [ "$dump_size" -lt 1024 ]; then
        log "ERROR: дамп БД подозрительно мал: $(human_bytes "$dump_size")"
        return 1
    fi

    log "DB OK: size=$(human_bytes "$dump_size")"
}

create_manifest() {
    CURRENT_STAGE="manifest"

    local manifest="$WORK_DIR/__backup/backup-info.txt"

    {
        echo "Backup script version: $VERSION"
        echo "Created: $(date '+%Y-%m-%d %H:%M:%S %z')"
        echo "Host: $HOST"
        echo "Site path: $DIR_SITE"
        echo "Database: $DB_NAME"
        echo "Archive format: tar.gz"
        echo
        echo "Excluded:"
        printf '%s\n' "${TAR_EXCLUDES[@]#--exclude=}"
    } > "$manifest"

    chmod 600 "$manifest"
}

# -----------------------------------------------------------------------------
# FTP
# -----------------------------------------------------------------------------

ftp_count_backups() {
    local listing="$1"

    {
        printf '%s\n' "$listing" \
            | tr -d '\r' \
            | grep -E "^${BACKUP_PREFIX}_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}\.tar(\.gz)?$" \
            || true
    } | wc -l
}

ftp_cleanup_part_files() {
    local listing="$1"
    local base
    local name

    base="$(ftp_base_url)"

    while IFS= read -r name; do
        name="${name//$'\r'/}"

        [ -z "$name" ] && continue

        if [[ "$name" =~ ^${BACKUP_PREFIX}_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}\.tar\.gz\.part$ ]]; then
            log "FTP DELETE stale part: $name"

            curl \
                --silent \
                --show-error \
                --fail \
                --connect-timeout 20 \
                --netrc-file "$NETRC_FILE" \
                --quote "DELE $name" \
                "$base" \
                -o /dev/null
        fi
    done <<< "$listing"
}

ftp_keep_last_backups() {
    CURRENT_STAGE="ротация FTP"

    local base
    local listing
    local total
    local delete_count
    local i
    local name
    local -a backups=()

    base="$(ftp_base_url)"

    listing="$(ftp_list)"

    # Незавершённые *.part не являются backup и удаляются.
    ftp_cleanup_part_files "$listing"

    listing="$(ftp_list)"

    mapfile -t backups < <(
        {
            printf '%s\n' "$listing" \
                | tr -d '\r' \
                | grep -E "^${BACKUP_PREFIX}_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}\.tar(\.gz)?$" \
                || true
        } | sort
    )

    total="${#backups[@]}"

    log "FTP RETENTION: найдено=$total, оставить=$FTP_KEEP_COUNT"

    if [ "$total" -le "$FTP_KEEP_COUNT" ]; then
        log "FTP RETENTION OK: удаление не требуется"
        return 0
    fi

    delete_count=$((total - FTP_KEEP_COUNT))

    for ((i=0; i<delete_count; i++)); do
        name="${backups[$i]}"

        log "FTP DELETE old backup: $name"

        curl \
            --silent \
            --show-error \
            --fail \
            --connect-timeout 20 \
            --netrc-file "$NETRC_FILE" \
            --quote "DELE $name" \
            "$base" \
            -o /dev/null
    done

    listing="$(ftp_list)"

    total="$(ftp_count_backups "$listing")"

    if [ "$total" -gt "$FTP_KEEP_COUNT" ]; then
        log "ERROR: после ротации осталось $total архивов"
        return 1
    fi

    log "FTP RETENTION OK: осталось=$total"
}

stream_full_backup_to_ftp() {
    CURRENT_STAGE="потоковый backup на FTP"

    local base
    local final_name
    local partial_name
    local upload_url
    local size_upload
    local listing
    local headers
    local remote_size=""

    base="$(ftp_base_url)"

    final_name="${BACKUP_PREFIX}_${TIMESTAMP}.tar.gz"
    partial_name="${final_name}.part"
    upload_url="${base}${partial_name}"

    REMOTE_PARTIAL_NAME="$partial_name"

    log "STREAM: начинаю tar.gz -> FTP: $final_name"

    # Большой tar.gz НЕ создаётся локально.
    # -C "$DIR_SITE" . включает скрытые файлы, в том числе .htaccess.
    #
    # curl возвращает количество реально переданных байт через size_upload.
    if command -v ionice >/dev/null 2>&1; then
        size_upload="$(
            ionice -c2 -n7 nice -n 10 \
                tar -czf - \
                    "${TAR_EXCLUDES[@]}" \
                    -C "$DIR_SITE" . \
                    -C "$WORK_DIR" __backup \
            | curl \
                --silent \
                --show-error \
                --fail \
                --connect-timeout 20 \
                --netrc-file "$NETRC_FILE" \
                -T - \
                -o /dev/null \
                -w '%{size_upload}' \
                "$upload_url"
        )"
    else
        size_upload="$(
            nice -n 10 \
                tar -czf - \
                    "${TAR_EXCLUDES[@]}" \
                    -C "$DIR_SITE" . \
                    -C "$WORK_DIR" __backup \
            | curl \
                --silent \
                --show-error \
                --fail \
                --connect-timeout 20 \
                --netrc-file "$NETRC_FILE" \
                -T - \
                -o /dev/null \
                -w '%{size_upload}' \
                "$upload_url"
        )"
    fi

    if ! [[ "$size_upload" =~ ^[0-9]+([.][0-9]+)?$ ]]; then
        log "ERROR: curl не вернул корректный size_upload: $size_upload"
        return 1
    fi

    # Проверяем, что временный файл действительно появился.
    listing="$(ftp_list)"

    if ! printf '%s\n' "$listing" | tr -d '\r' | grep -Fxq "$partial_name"; then
        log "ERROR: после upload временный файл не найден на FTP: $partial_name"
        return 1
    fi

    # Если сервер отдаёт Content-Length для FTP SIZE — сверяем.
    set +e
    headers="$(
        curl \
            --silent \
            --show-error \
            --netrc-file "$NETRC_FILE" \
            --connect-timeout 20 \
            -I "$upload_url" 2>/dev/null
    )"
    set -e

    remote_size="$(
        printf '%s\n' "$headers" \
            | awk 'BEGIN{IGNORECASE=1} /^Content-Length:/ {gsub("\r","",$2); print $2; exit}'
    )"

    if [[ "$remote_size" =~ ^[0-9]+$ ]]; then
        # size_upload curl может быть с .000; приводим к целому.
        local upload_int="${size_upload%%.*}"

        if [ "$upload_int" -ne "$remote_size" ]; then
            log "ERROR: размер upload не совпал: sent=$upload_int, remote=$remote_size"
            return 1
        fi

        log "FTP SIZE OK: $(human_bytes "$remote_size")"
    else
        log "FTP SIZE: сервер не вернул размер, проверено наличие файла"
    fi

    CURRENT_STAGE="финализация FTP"

    # Только полностью загруженный .part переименовывается в финальный backup.
    curl \
        --silent \
        --show-error \
        --fail \
        --connect-timeout 20 \
        --netrc-file "$NETRC_FILE" \
        --quote "RNFR $partial_name" \
        --quote "RNTO $final_name" \
        "$base" \
        -o /dev/null

    REMOTE_PARTIAL_NAME=""

    listing="$(ftp_list)"

    if ! printf '%s\n' "$listing" | tr -d '\r' | grep -Fxq "$final_name"; then
        log "ERROR: после rename финальный файл не найден: $final_name"
        return 1
    fi

    log "FTP UPLOAD OK: $final_name, sent=$(human_bytes "${size_upload%%.*}")"

    printf '%s' "$final_name"
}

# -----------------------------------------------------------------------------
# ЛОКАЛЬНЫЕ РЕЖИМЫ
# -----------------------------------------------------------------------------

create_local_site_archive() {
    CURRENT_STAGE="локальный архив сайта"

    local out="$DIR_BACKUP/${TIMESTAMP}-public_html.tar.gz"

    check_local_archive_disk "$DIR_BACKUP"

    tar -czf "$out" \
        "${TAR_EXCLUDES[@]}" \
        -C "$DIR_SITE" .

    if [ "$VERIFY_LOCAL_ARCHIVES" -eq 1 ]; then
        tar -tzf "$out" >/dev/null
    fi

    log "LOCAL SITE OK: $out, size=$(human_bytes "$(file_size "$out")")"
    printf '%s' "$out"
}

create_local_full_archive() {
    CURRENT_STAGE="локальный полный архив"

    local out="$DIR_BACKUP/${BACKUP_PREFIX}_${TIMESTAMP}.tar.gz"

    check_local_archive_disk "$DIR_BACKUP"

    tar -czf "$out" \
        "${TAR_EXCLUDES[@]}" \
        -C "$DIR_SITE" . \
        -C "$WORK_DIR" __backup

    if [ "$VERIFY_LOCAL_ARCHIVES" -eq 1 ]; then
        tar -tzf "$out" >/dev/null
    fi

    log "LOCAL FULL OK: $out, size=$(human_bytes "$(file_size "$out")")"
    printf '%s' "$out"
}

# -----------------------------------------------------------------------------
# CHECK
# -----------------------------------------------------------------------------

run_check() {
    CURRENT_STAGE="CHECK"

    local warnings=0
    local usage
    local avail
    local db_bytes
    local listing
    local backup_count
    local conf_mode

    echo "=== backup.sh $VERSION: проверка ==="

    # Команды
    for cmd in tar gzip df awk stat date base64 flock curl mysqldump mysql; do
        if command -v "$cmd" >/dev/null 2>&1; then
            echo "[OK]      команда: $cmd"
        else
            echo "[ERROR]   нет команды: $cmd"
            warnings=$((warnings + 1))
        fi
    done

    # Конфиг
    conf_mode="$(stat -c '%a' "$CONFIG" 2>/dev/null || echo unknown)"
    echo "[OK]      config: $CONFIG (mode=$conf_mode)"

    if [[ "$conf_mode" =~ ^[0-9]+$ ]] && [ "$conf_mode" -gt 600 ]; then
        echo "[WARNING] backup.conf содержит пароли; лучше: chmod 600 $CONFIG"
        warnings=$((warnings + 1))
    fi

    # Сайт
    echo "[OK]      сайт: $DIR_SITE"

    # Диск — для stream это предупреждение по %, а не запрет.
    usage="$(get_usage_percent "$DIR_BACKUP")"
    avail="$(get_available_bytes "$DIR_BACKUP")"

    echo "[INFO]    диск: занято=${usage}%, свободно=$(human_bytes "$avail")"
    echo "[INFO]    основной --all создаёт локально только дамп БД; большой tar.gz идёт сразу на FTP"

    if [ "$avail" -lt $((MIN_FREE_MB * 1024 * 1024)) ]; then
        echo "[ERROR]   свободно меньше ${MIN_FREE_MB} MB — даже потоковый backup запускать нельзя"
        warnings=$((warnings + 1))
    elif [ "$usage" -ge 90 ]; then
        echo "[WARNING] диск заполнен >=90%; должен сработать отдельный hdd_size.sh"
        warnings=$((warnings + 1))
    elif [ "$usage" -ge 85 ]; then
        echo "[WARNING] диск заполнен на ${usage}%, но для потокового backup это не блокирующее условие"
        warnings=$((warnings + 1))
    else
        echo "[OK]      свободного места достаточно для потокового режима"
    fi

    # Рабочий каталог нужен для безопасных временных credential-файлов.
    create_workdir

    # MySQL
    if command -v mysql >/dev/null 2>&1 && command -v mysqldump >/dev/null 2>&1; then
        set +e
        db_bytes="$(mysql_database_bytes 2>/dev/null)"
        local mysql_rc=$?
        set -e

        if [ "$mysql_rc" -eq 0 ] && [[ "$db_bytes" =~ ^[0-9]+$ ]]; then
            echo "[OK]      MySQL: подключение работает, размер таблиц ≈ $(human_bytes "$db_bytes")"
        else
            echo "[ERROR]   MySQL: не удалось подключиться или получить размер БД"
            warnings=$((warnings + 1))
        fi
    fi

    # FTP
    create_netrc

    set +e
    listing="$(ftp_list 2>/dev/null)"
    local ftp_rc=$?
    set -e

    if [ "$ftp_rc" -eq 0 ]; then
        backup_count="$(ftp_count_backups "$listing")"
        echo "[OK]      FTP: соединение работает"
        echo "[INFO]    FTP: найдено backup-файлов=${backup_count}, политика хранения=${FTP_KEEP_COUNT}"
    else
        echo "[ERROR]   FTP: соединение/листинг не работает"
        warnings=$((warnings + 1))
    fi

    # Postfix / sendmail
    if [ -x /usr/sbin/sendmail ] && systemctl is-active --quiet postfix; then
        echo "[OK]      Postfix: active"
        echo "[OK]      sendmail: /usr/sbin/sendmail"
    else
        echo "[ERROR]   Postfix или sendmail недоступны"
        warnings=$((warnings + 1))
    fi

    cleanup_workdir
    WORK_DIR=""

    if [ "$warnings" -eq 0 ]; then
        echo "=== CHECK OK: ошибок и предупреждений нет ==="
        return 0
    fi

    echo "=== CHECK завершён: замечаний=${warnings} ==="
    echo "Предупреждения по заполнению диска сами по себе не блокируют потоковый --all."
    return 0
}

# -----------------------------------------------------------------------------
# УСПЕШНОЕ ЗАВЕРШЕНИЕ
# -----------------------------------------------------------------------------

success_finish() {
    local result="$1"
    local elapsed

    elapsed=$(( $(date '+%s') - START_EPOCH ))
    CURRENT_STAGE="завершение"

    log "SUCCESS: $result, duration=$(duration_text "$elapsed")"

    if [ "$MAIL_ON_SUCCESS" -eq 1 ]; then
        send_email \
            "[OK] ${HOST}: резервное копирование завершено" \
"Резервное копирование успешно завершено.

Сервер:
${HOST}

Режим:
${MODE}

Результат:
${result}

Длительность:
$(duration_text "$elapsed")

Время:
$(date '+%Y-%m-%d %H:%M:%S %z')

Лог:
${LOG_FILE}" || log "WARNING: success-email не отправлен"
    fi
}

# -----------------------------------------------------------------------------
# РЕЖИМЫ
# -----------------------------------------------------------------------------

mode_all() {
    CURRENT_STAGE="предварительная проверка"

    for cmd in tar gzip df awk stat date base64 flock curl mysqldump; do
        need_cmd "$cmd"
    done

    check_streaming_disk "$DIR_BACKUP"

    create_workdir
    create_netrc

    # FTP проверяется ДО mysqldump и архивации.
    CURRENT_STAGE="предварительная проверка FTP"
    local listing
    listing="$(ftp_list)"
    log "FTP PRECHECK OK: backup-файлов=$(ftp_count_backups "$listing")"

    create_db_dump

    # После дампа снова убеждаемся, что системный диск не стал критически тесным.
    check_streaming_disk "$DIR_BACKUP"

    create_manifest

    local final_name
    final_name="$(stream_full_backup_to_ftp)"

    # Только после подтверждённого успешного нового backup.
    ftp_keep_last_backups

    success_finish "FTP archive=$final_name; retention=last ${FTP_KEEP_COUNT}"

    cleanup_workdir
    WORK_DIR=""
}

mode_sql() {
    for cmd in gzip df awk stat date base64 flock mysqldump; do
        need_cmd "$cmd"
    done

    check_streaming_disk "$DIR_BACKUP"

    create_workdir
    create_db_dump

    local source_file="$WORK_DIR/__backup/${DB_NAME}.sql.gz"
    local target="$DIR_BACKUP/${TIMESTAMP}-${DB_NAME}.sql.gz"

    mv "$source_file" "$target"

    log "LOCAL SQL OK: $target, size=$(human_bytes "$(file_size "$target")")"

    success_finish "SQL=$target"

    cleanup_workdir
    WORK_DIR=""
}

mode_site() {
    for cmd in tar gzip df awk stat date base64 flock; do
        need_cmd "$cmd"
    done

    local out
    out="$(create_local_site_archive)"

    success_finish "site archive=$out"
}

mode_all_noftp() {
    for cmd in tar gzip df awk stat date base64 flock mysqldump; do
        need_cmd "$cmd"
    done

    check_local_archive_disk "$DIR_BACKUP"

    create_workdir
    create_db_dump
    create_manifest

    local out
    out="$(create_local_full_archive)"

    success_finish "local archive=$out"

    cleanup_workdir
    WORK_DIR=""
}

mode_clear_ftp() {
    for cmd in curl date base64 flock; do
        need_cmd "$cmd"
    done

    create_workdir
    create_netrc

    CURRENT_STAGE="проверка FTP"
    ftp_list >/dev/null

    ftp_keep_last_backups

    success_finish "FTP retention=last ${FTP_KEEP_COUNT}"

    cleanup_workdir
    WORK_DIR=""
}

mode_test_email() {
    CURRENT_STAGE="тест email"

    send_email \
        "[TEST] ${HOST}: backup.sh $VERSION" \
"Тестовое письмо резервного копирования.

Сервер:
${HOST}

Версия:
${VERSION}

Если письмо получено, уведомления backup.sh через Postfix работают.

Время:
$(date '+%Y-%m-%d %H:%M:%S %z')"

    echo "OK: тестовое письмо отправлено на $EMAIL_TO"
}

# -----------------------------------------------------------------------------
# MAIN
# -----------------------------------------------------------------------------

if [ "$#" -ne 1 ]; then
    usage
    exit 1
fi

MODE="$1"

case "$MODE" in
    -h|--help)
        usage
        exit 0
        ;;
    --all|--check|--sql|--site|--all-noftp|--clear-ftp|--test-email)
        ;;
    *)
        echo "Неизвестный параметр: $MODE"
        echo
        usage
        exit 1
        ;;
esac

if [ "$MODE" = "--test-email" ]; then
    mode_test_email
    exit 0
fi

need_cmd flock

exec 9>"$LOCK_FILE"

if ! flock -n 9; then
    log "INFO: другой экземпляр backup.sh уже работает; запуск пропущен"
    echo "Другой экземпляр backup.sh уже работает."
    exit 0
fi

load_config

log "START: version=$VERSION, mode=$MODE, host=$HOST"

case "$MODE" in
    --all)
        mode_all
        ;;
    --check)
        run_check
        ;;
    --sql)
        mode_sql
        ;;
    --site)
        mode_site
        ;;
    --all-noftp)
        mode_all_noftp
        ;;
    --clear-ftp)
        mode_clear_ftp
        ;;
esac

exit 0

