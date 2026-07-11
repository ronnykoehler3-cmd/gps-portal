#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

MAINTENANCE_SCRIPT="/usr/local/sbin/tk-nightly-maintenance"
BACKUP_ROOT="/var/backups/tk-nightly"

echo "=== Installiere nächtliche GPS-Server-Wartung ==="

apt-get update
apt-get install -y \
    tar \
    gzip \
    postgresql-client \
    util-linux \
    coreutils

install -d -m 0700 "$BACKUP_ROOT"
install -d -m 0755 /usr/local/sbin

cat > "$MAINTENANCE_SCRIPT" <<'SCRIPT'
#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

readonly BACKUP_ROOT="/var/backups/tk-nightly"
readonly LOCK_FILE="/run/lock/tk-nightly-maintenance.lock"
readonly TRACCAR_CONFIG="/opt/traccar/conf/traccar.xml"
readonly RETENTION_DAYS="7"

# Dienste, die nach einem Neustart laufen sollen.
# Nicht installierte Dienste werden automatisch übersprungen.
SERVICES=(
    "nftables.service"
    "fail2ban.service"
    "postgresql.service"
    "redis-server.service"
    "php8.3-fpm.service"
    "nginx.service"
    "traccar.service"
)

log() {
    printf '[%s] %s\n' "$(date '+%F %T')" "$*"
    logger -t tk-nightly-maintenance -- "$*"
}

die() {
    log "FEHLER: $*"
    exit 1
}

require_root() {
    [[ "$EUID" -eq 0 ]] || die "Dieses Skript muss als root laufen."
}

unit_exists() {
    systemctl list-unit-files "$1" --no-legend 2>/dev/null |
        grep -q "^${1}[[:space:]]"
}

check_free_space() {
    local available_kb

    available_kb="$(
        df --output=avail "$BACKUP_ROOT" |
            tail -1 |
            tr -d '[:space:]'
    )"

    [[ "$available_kb" =~ ^[0-9]+$ ]] ||
        die "Freier Speicher konnte nicht ermittelt werden."

    # Mindestens 5 GiB freier Speicher erforderlich.
    if (( available_kb < 5242880 )); then
        die "Weniger als 5 GiB freier Speicher im Backup-Dateisystem."
    fi

    log "Freier Backup-Speicher: $((available_kb / 1024)) MiB"
}

detect_traccar_database() {
    local database_url=""
    local database_name=""

    [[ -r "$TRACCAR_CONFIG" ]] || return 1

    database_url="$(
        sed -n \
            's#.*<entry[[:space:]]\+key="database.url">\([^<]*\)</entry>.*#\1#p' \
            "$TRACCAR_CONFIG" |
            head -1
    )"

    [[ "$database_url" == jdbc:postgresql:* ]] || return 1

    database_name="${database_url##*/}"
    database_name="${database_name%%\?*}"

    [[ -n "$database_name" ]] || return 1

    printf '%s\n' "$database_name"
}

create_postgresql_backup() {
    local destination="$1"
    local database_name=""

    if ! command -v pg_dump >/dev/null 2>&1; then
        die "pg_dump ist nicht installiert."
    fi

    if ! id postgres >/dev/null 2>&1; then
        die "PostgreSQL-Benutzer 'postgres' fehlt."
    fi

    database_name="$(detect_traccar_database)" ||
        die "Traccar-PostgreSQL-Datenbank konnte nicht aus $TRACCAR_CONFIG ermittelt werden."

    log "Sichere PostgreSQL-Rollen und globale Einstellungen ..."

    runuser -u postgres -- \
        pg_dumpall --globals-only \
        > "${destination}/postgresql-globals.sql"

    log "Sichere Traccar-Datenbank: ${database_name}"

    runuser -u postgres -- \
        pg_dump \
        --format=custom \
        --compress=9 \
        --file="${destination}/traccar-${database_name}.dump" \
        "$database_name"

    [[ -s "${destination}/traccar-${database_name}.dump" ]] ||
        die "Der Traccar-Datenbankdump ist leer."

    printf '%s\n' "$database_name" \
        > "${destination}/traccar-database-name.txt"
}

create_file_backup() {
    local destination="$1"
    local sources=()
    local source

    # Nur vorhandene Quellen werden aufgenommen.
    for source in \
        /etc \
        /var/www \
        /opt/traccar/conf \
        /root/install-gps-firewall.sh \
        /root/install-nightly-maintenance.sh \
        /var/lib/tk-gps-firewall
    do
        if [[ -e "$source" ]]; then
            sources+=("$source")
        fi
    done

    (( ${#sources[@]} > 0 )) ||
        die "Keine Sicherungsquellen gefunden."

    log "Sichere System-, Web- und Traccar-Konfigurationen ..."

    tar \
        --create \
        --gzip \
        --file="${destination}/system-files.tar.gz" \
        --acls \
        --xattrs \
        --numeric-owner \
        --warning=no-file-changed \
        "${sources[@]}"

    [[ -s "${destination}/system-files.tar.gz" ]] ||
        die "Das Datei-Backup ist leer."
}

create_system_information() {
    local destination="$1"

    {
        echo "Backup-Zeit: $(date --iso-8601=seconds)"
        echo "Hostname: $(hostname --fqdn 2>/dev/null || hostname)"
        echo "Kernel: $(uname -a)"
        echo
        echo "=== Betriebssystem ==="
        cat /etc/os-release 2>/dev/null || true
        echo
        echo "=== Dateisysteme ==="
        df -hT
        echo
        echo "=== Dienste ==="

        for service in "${SERVICES[@]}"; do
            if unit_exists "$service"; then
                printf '%-30s %s\n' \
                    "$service" \
                    "$(systemctl is-active "$service" 2>/dev/null || true)"
            else
                printf '%-30s %s\n' "$service" "nicht installiert"
            fi
        done

        echo
        echo "=== Offene Ports ==="
        ss -tulpen

        echo
        echo "=== nftables ==="
        nft list ruleset 2>/dev/null || true

        echo
        echo "=== Pakete ==="
        dpkg-query -W \
            -f='${binary:Package}\t${Version}\n' 2>/dev/null || true
    } > "${destination}/system-information.txt"
}

create_checksums() {
    local destination="$1"

    log "Erzeuge SHA-256-Prüfsummen ..."

    (
        cd "$destination"

        find . \
            -maxdepth 1 \
            -type f \
            ! -name 'SHA256SUMS' \
            -printf '%f\0' |
            sort -z |
            xargs -0 sha256sum \
            > SHA256SUMS
    )

    (
        cd "$destination"
        sha256sum --check SHA256SUMS
    )
}

cleanup_old_backups() {
    log "Lösche Backups, die älter als ${RETENTION_DAYS} Tage sind ..."

    find "$BACKUP_ROOT" \
        -mindepth 1 \
        -maxdepth 1 \
        -type d \
        -name '20??-??-??_??-??-??' \
        -mtime "+${RETENTION_DAYS}" \
        -print \
        -exec rm -rf -- {} +
}

backup() {
    local timestamp
    local temporary_directory
    local final_directory

    timestamp="$(date '+%Y-%m-%d_%H-%M-%S')"
    temporary_directory="${BACKUP_ROOT}/.${timestamp}.incomplete"
    final_directory="${BACKUP_ROOT}/${timestamp}"

    check_free_space

    rm -rf "$temporary_directory"
    install -d -m 0700 "$temporary_directory"

    log "Backup gestartet: $timestamp"

    create_system_information "$temporary_directory"
    create_postgresql_backup "$temporary_directory"
    create_file_backup "$temporary_directory"
    create_checksums "$temporary_directory"

    printf '%s\n' \
        "Backup erfolgreich abgeschlossen: $(date --iso-8601=seconds)" \
        > "${temporary_directory}/BACKUP_OK"

    mv "$temporary_directory" "$final_directory"

    cleanup_old_backups

    log "Backup erfolgreich: $final_directory"
}

service_check() {
    local service
    local failures=0

    log "Warte 45 Sekunden auf den vollständigen Systemstart ..."
    sleep 45

    log "Prüfe wichtige Dienste nach dem Neustart ..."

    for service in "${SERVICES[@]}"; do
        if ! unit_exists "$service"; then
            log "ÜBERSPRUNGEN: $service ist nicht installiert."
            continue
        fi

        if systemctl is-active --quiet "$service"; then
            log "OK: $service läuft."
            continue
        fi

        log "WARNUNG: $service läuft nicht. Starte den Dienst ..."

        systemctl restart "$service" || true
        sleep 5

        if systemctl is-active --quiet "$service"; then
            log "ERFOLG: $service wurde gestartet."
        else
            log "FEHLER: $service konnte nicht gestartet werden."
            journalctl \
                -u "$service" \
                -n 30 \
                --no-pager \
                2>&1 |
                logger -t tk-nightly-maintenance

            failures=$((failures + 1))
        fi
    done

    if (( failures > 0 )); then
        die "${failures} Dienst(e) konnten nicht gestartet werden."
    fi

    log "Alle vorhandenen wichtigen Dienste laufen."
}

status() {
    local service
    local latest_backup=""

    echo
    echo "=== Wartungstimer ==="
    systemctl status tk-nightly-reboot.timer --no-pager || true

    echo
    echo "=== Prüfung nach Systemstart ==="
    systemctl status tk-postboot-service-check.service --no-pager || true

    echo
    echo "=== Dienste ==="

    for service in "${SERVICES[@]}"; do
        if unit_exists "$service"; then
            printf '%-30s %s\n' \
                "$service" \
                "$(systemctl is-active "$service" 2>/dev/null || true)"
        else
            printf '%-30s %s\n' "$service" "nicht installiert"
        fi
    done

    latest_backup="$(
        find "$BACKUP_ROOT" \
            -mindepth 1 \
            -maxdepth 1 \
            -type d \
            -name '20??-??-??_??-??-??' \
            -printf '%f\n' |
            sort |
            tail -1
    )"

    echo
    echo "=== Letztes Backup ==="

    if [[ -n "$latest_backup" ]]; then
        echo "$BACKUP_ROOT/$latest_backup"
        du -sh "$BACKUP_ROOT/$latest_backup"
    else
        echo "Noch kein abgeschlossenes Backup vorhanden."
    fi

    echo
    echo "=== Freier Speicher ==="
    df -h "$BACKUP_ROOT"
}

main() {
    require_root
    create_lock_directory="$(dirname "$LOCK_FILE")"
    install -d -m 0755 "$create_lock_directory"

    exec 9>"$LOCK_FILE"

    if ! flock -n 9; then
        die "Eine Wartung läuft bereits."
    fi

    case "${1:-}" in
        backup)
            backup
            ;;

        check-services)
            service_check
            ;;

        status)
            status
            ;;

        *)
            echo "Verwendung:"
            echo "  $0 backup"
            echo "  $0 check-services"
            echo "  $0 status"
            exit 1
            ;;
    esac
}

main "$@"
SCRIPT

chmod 0750 "$MAINTENANCE_SCRIPT"

cat > /etc/systemd/system/tk-nightly-reboot.service <<'EOF'
[Unit]
Description=TK nächtliches Backup mit anschließendem Neustart
Wants=network-online.target
After=network-online.target postgresql.service traccar.service
ConditionACPower=true

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/tk-nightly-maintenance backup
ExecStartPost=/usr/bin/systemctl reboot
TimeoutStartSec=3h
Nice=10
IOSchedulingClass=best-effort
IOSchedulingPriority=7

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/tk-nightly-reboot.timer <<'EOF'
[Unit]
Description=Tägliches GPS-Server-Backup und Neustart um 02:30 Uhr

[Timer]
OnCalendar=*-*-* 02:30:00
Persistent=true
AccuracySec=1min
Unit=tk-nightly-reboot.service

[Install]
WantedBy=timers.target
EOF

cat > /etc/systemd/system/tk-postboot-service-check.service <<'EOF'
[Unit]
Description=TK Prüfung wichtiger Dienste nach dem Systemstart
Wants=network-online.target
After=network-online.target nftables.service fail2ban.service postgresql.service redis-server.service php8.3-fpm.service nginx.service traccar.service

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/tk-nightly-maintenance check-services
TimeoutStartSec=10min
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/logrotate.d/tk-nightly-maintenance <<'EOF'
/var/log/tk-nightly-maintenance.log {
    weekly
    rotate 8
    compress
    delaycompress
    missingok
    notifempty
    create 0640 root adm
}
EOF

systemctl daemon-reload

echo
echo "=== Syntaxprüfung ==="

bash -n "$MAINTENANCE_SCRIPT"

echo "Syntax ist korrekt."

echo
echo "=== Installierte Dateien ==="

ls -lh \
    "$MAINTENANCE_SCRIPT" \
    /etc/systemd/system/tk-nightly-reboot.service \
    /etc/systemd/system/tk-nightly-reboot.timer \
    /etc/systemd/system/tk-postboot-service-check.service

echo
echo "Installation abgeschlossen."
echo
echo "Der Timer ist NOCH NICHT aktiviert."
echo "Zuerst muss ein manuelles Testbackup erfolgreich durchlaufen."
