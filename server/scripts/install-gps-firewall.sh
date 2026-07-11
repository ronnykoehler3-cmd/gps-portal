#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

WORK_DIR="/var/lib/tk-gps-firewall"
GEO_DIR="${WORK_DIR}/geo"
BACKUP_DIR="${WORK_DIR}/backups"

NFT_CONFIG="/etc/nftables.conf"
NEW_CONFIG="${WORK_DIR}/nftables.conf.new"
PENDING_FILE="${WORK_DIR}/pending-backup"
SSH_IP_FILE="${WORK_DIR}/last-ssh-ip"

IPDENY_URL="https://www.ipdeny.com/ipblocks/data/aggregated"

TRACCAR_FIRST_PORT="5001"
TRACCAR_LAST_PORT="5251"

EU_COUNTRIES=(
    at be bg cy cz de dk ee es fi fr gr hr hu ie it
    lt lu lv mt nl pl pt ro se si sk
)

SSH_WHITELIST=(
    "10.78.0.0/16"
    "194.93.11.0/24"
    "185.155.193.0/24"
    "80.151.246.56/32"
)

log() {
    printf '[%s] %s\n' "$(date '+%F %T')" "$*"
}

die() {
    log "FEHLER: $*"
    exit 1
}

require_root() {
    [[ "${EUID}" -eq 0 ]] || die "Bitte als root ausführen."
}

create_directories() {
    install -d -m 0700 "$WORK_DIR"
    install -d -m 0700 "$GEO_DIR"
    install -d -m 0700 "$BACKUP_DIR"
}

detect_ssh_ip() {
    local ip=""

    if [[ -n "${SSH_CONNECTION:-}" ]]; then
        ip="${SSH_CONNECTION%% *}"
    elif [[ -n "${SSH_CLIENT:-}" ]]; then
        ip="${SSH_CLIENT%% *}"
    fi

    if [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        printf '%s\n' "$ip"
    fi
}

save_current_ssh_ip() {
    local ip=""

    ip="$(detect_ssh_ip || true)"

    if [[ -n "$ip" ]]; then
        printf '%s/32\n' "$ip" > "$SSH_IP_FILE"
        chmod 0600 "$SSH_IP_FILE"
        log "Aktuelle SSH-IP gespeichert: ${ip}/32"
    else
        log "Hinweis: Aktuelle SSH-IP konnte nicht erkannt werden."
    fi
}

install_packages() {
    log "Installiere benötigte Pakete ..."

    export DEBIAN_FRONTEND=noninteractive

    apt-get update
    apt-get install -y nftables curl ca-certificates python3

    systemctl enable nftables.service >/dev/null 2>&1 || true
}

validate_zone_file() {
    local file="$1"

    python3 - "$file" <<'PY'
import ipaddress
import pathlib
import sys

path = pathlib.Path(sys.argv[1])

if not path.is_file():
    raise SystemExit(f"Datei fehlt: {path}")

count = 0

for number, raw in enumerate(path.read_text().splitlines(), 1):
    line = raw.strip()

    if not line or line.startswith("#"):
        continue

    try:
        network = ipaddress.ip_network(line, strict=False)
    except ValueError as error:
        raise SystemExit(
            f"Ungültiger Eintrag in {path}, Zeile {number}: {line}: {error}"
        )

    if network.version != 4:
        raise SystemExit(
            f"Kein IPv4-Netz in {path}, Zeile {number}: {line}"
        )

    count += 1

if count == 0:
    raise SystemExit(f"Keine IPv4-Netze in {path}")

print(count)
PY
}

download_geo_lists() {
    local country
    local destination
    local temporary
    local count

    log "Lade EU-Geo-IP-Listen ..."

    rm -rf "${GEO_DIR:?}/"*
    install -d -m 0700 "$GEO_DIR"

    for country in "${EU_COUNTRIES[@]}"; do
        destination="${GEO_DIR}/${country}-aggregated.zone"
        temporary="${destination}.tmp"

        log "Lade ${country^^} ..."

        curl \
            --fail \
            --silent \
            --show-error \
            --location \
            --connect-timeout 20 \
            --max-time 180 \
            --retry 3 \
            --retry-delay 2 \
            --output "$temporary" \
            "${IPDENY_URL}/${country}-aggregated.zone"

        count="$(validate_zone_file "$temporary")"

        mv "$temporary" "$destination"
        chmod 0600 "$destination"

        log "${country^^}: ${count} IPv4-Netze geprüft."
    done

    log "Alle Geo-IP-Listen wurden erfolgreich geladen."
}

write_geo_elements() {
    local country
    local file
    local line
    local first=1

    for country in "${EU_COUNTRIES[@]}"; do
        file="${GEO_DIR}/${country}-aggregated.zone"

        [[ -s "$file" ]] || die "Geo-IP-Datei fehlt: $file"

        while IFS= read -r line; do
            line="${line%%#*}"
            line="$(printf '%s' "$line" | tr -d '[:space:]')"

            [[ -z "$line" ]] && continue

            if (( first == 0 )); then
                printf ',\n'
            fi

            printf '            %s' "$line"
            first=0
        done < "$file"
    done

    printf '\n'
}

write_ssh_whitelist() {
    local entry
    local first=1

    for entry in "${SSH_WHITELIST[@]}"; do
        [[ -z "$entry" ]] && continue

        if (( first == 0 )); then
            printf ',\n'
        fi

        printf '            %s' "$entry"
        first=0
    done

    if [[ -s "$SSH_IP_FILE" ]]; then
        entry="$(cat "$SSH_IP_FILE")"

        if [[ -n "$entry" ]]; then
            if (( first == 0 )); then
                printf ',\n'
            fi

            printf '            %s' "$entry"
        fi
    fi

    printf '\n'
}

generate_config() {
    log "Erzeuge nftables-Konfiguration ..."

    {
        cat <<EOF
#!/usr/sbin/nft -f

# TK Kundendienst – GPS-/Traccar-Firewall
# Erzeugt: $(date --iso-8601=seconds)

flush ruleset

table inet tk_gps_firewall {

    set ssh_eu_ipv4 {
        type ipv4_addr
        flags interval
        auto-merge

        elements = {
EOF

        write_geo_elements

        cat <<'EOF'
        }
    }

    set ssh_whitelist_ipv4 {
        type ipv4_addr
        flags interval
        auto-merge

        elements = {
EOF

        write_ssh_whitelist

        cat <<EOF
        }
    }

    chain input {
        type filter hook input priority filter
        policy drop

        iifname "lo" accept

        ct state established,related accept
        ct state invalid drop

        ip protocol icmp accept
        meta l4proto ipv6-icmp accept

        udp sport 547 udp dport 546 accept

        # SSH aus manueller Whitelist
        ip saddr @ssh_whitelist_ipv4 tcp dport 22 ct state new accept

        # SSH aus EU-IPv4-Netzen
        ip saddr @ssh_eu_ipv4 tcp dport 22 ct state new accept

        # Webseiten weltweit
        tcp dport { 80, 443 } ct state new accept

        # Traccar weltweit – TCP
        tcp dport ${TRACCAR_FIRST_PORT}-${TRACCAR_LAST_PORT} ct state new accept

        # Traccar weltweit – UDP
        udp dport ${TRACCAR_FIRST_PORT}-${TRACCAR_LAST_PORT} accept

        limit rate 5/second burst 20 packets log \
            prefix "NFT-DROP: " flags all counter
    }

    chain forward {
        type filter hook forward priority filter
        policy drop
    }

    chain output {
        type filter hook output priority filter
        policy accept
    }
}
EOF
    } > "$NEW_CONFIG"

    chmod 0600 "$NEW_CONFIG"

    nft --check --file "$NEW_CONFIG"

    log "Konfiguration wurde erfolgreich geprüft."
}

backup_firewall() {
    local timestamp
    local destination

    timestamp="$(date '+%Y-%m-%d_%H-%M-%S')"
    destination="${BACKUP_DIR}/${timestamp}"

    install -d -m 0700 "$destination"

    nft list ruleset > "${destination}/active-ruleset.nft"

    if [[ -f "$NFT_CONFIG" ]]; then
        cp -a "$NFT_CONFIG" "${destination}/nftables.conf"
    fi

    printf '%s\n' "$destination" > "$PENDING_FILE"
    chmod 0600 "$PENDING_FILE"

    log "Firewall-Sicherung: $destination"
}

create_rollback_script() {
    cat > "${WORK_DIR}/rollback.sh" <<'ROLLBACK'
#!/usr/bin/env bash

set -Eeuo pipefail

WORK_DIR="/var/lib/tk-gps-firewall"
PENDING_FILE="${WORK_DIR}/pending-backup"

[[ -s "$PENDING_FILE" ]] || exit 0

BACKUP="$(cat "$PENDING_FILE")"

[[ -d "$BACKUP" ]] || exit 1

if [[ -f "${BACKUP}/nftables.conf" ]]; then
    cp -a "${BACKUP}/nftables.conf" /etc/nftables.conf
elif [[ -f "${BACKUP}/active-ruleset.nft" ]]; then
    cp -a "${BACKUP}/active-ruleset.nft" /etc/nftables.conf
fi

if [[ -f "${BACKUP}/active-ruleset.nft" ]]; then
    nft --file "${BACKUP}/active-ruleset.nft"
fi

rm -f "$PENDING_FILE"

logger -t tk-gps-firewall \
    "Vorherige Firewall wurde automatisch wiederhergestellt."
ROLLBACK

    chmod 0700 "${WORK_DIR}/rollback.sh"
}

create_rollback_units() {
    cat > /etc/systemd/system/tk-gps-firewall-rollback.service <<EOF
[Unit]
Description=TK GPS Firewall Rücksetzung

[Service]
Type=oneshot
ExecStart=${WORK_DIR}/rollback.sh
EOF

    cat > /etc/systemd/system/tk-gps-firewall-rollback.timer <<'EOF'
[Unit]
Description=TK GPS Firewall automatische Rücksetzung

[Timer]
OnActiveSec=5min
AccuracySec=1s
Unit=tk-gps-firewall-rollback.service

[Install]
WantedBy=timers.target
EOF

    systemctl daemon-reload
}

schedule_rollback() {
    systemctl stop tk-gps-firewall-rollback.timer >/dev/null 2>&1 || true
    systemctl stop tk-gps-firewall-rollback.service >/dev/null 2>&1 || true
    systemctl reset-failed tk-gps-firewall-rollback.service >/dev/null 2>&1 || true

    systemctl start tk-gps-firewall-rollback.timer

    log "Automatische Rücksetzung in fünf Minuten aktiviert."
}

install_geo_timer() {
    cat > /etc/systemd/system/tk-gps-geo-update.service <<EOF
[Unit]
Description=TK GPS Geo-IP-Aktualisierung
Wants=network-online.target
After=network-online.target

[Service]
Type=oneshot
ExecStart=/root/install-gps-firewall.sh update-geo
EOF

    cat > /etc/systemd/system/tk-gps-geo-update.timer <<'EOF'
[Unit]
Description=Wöchentliche TK GPS Geo-IP-Aktualisierung

[Timer]
OnCalendar=Sun *-*-* 04:15:00
Persistent=true
RandomizedDelaySec=15min

[Install]
WantedBy=timers.target
EOF

    systemctl daemon-reload
    systemctl enable --now tk-gps-geo-update.timer

    log "Wöchentliche Geo-IP-Aktualisierung aktiviert."
}

prepare() {
    install_packages
    create_directories
    save_current_ssh_ip
    download_geo_lists
    generate_config
    create_rollback_script
    create_rollback_units

    echo
    log "Vorbereitung abgeschlossen."
    log "Die aktive Firewall wurde noch nicht verändert."
    log "Nächster Befehl:"
    log "/root/install-gps-firewall.sh apply"
}

apply() {
    [[ -s "$NEW_CONFIG" ]] ||
        die "Konfiguration fehlt. Zuerst prepare ausführen."

    nft --check --file "$NEW_CONFIG"

    backup_firewall
    schedule_rollback

    cp "$NEW_CONFIG" "$NFT_CONFIG"
    chmod 0600 "$NFT_CONFIG"

    nft --file "$NFT_CONFIG"

    systemctl enable nftables.service
    systemctl restart nftables.service

    systemctl is-active --quiet nftables.service ||
        die "nftables konnte nicht gestartet werden."

    echo
    log "Firewall ist vorläufig aktiv."
    log "Aktuelle SSH-Sitzung geöffnet lassen."
    log "Jetzt eine zweite SSH-Verbindung testen."
    log "Anschließend innerhalb von fünf Minuten ausführen:"
    log "/root/install-gps-firewall.sh confirm"
}

confirm() {
    [[ -s "$PENDING_FILE" ]] ||
        die "Keine ausstehende Änderung vorhanden."

    systemctl stop tk-gps-firewall-rollback.timer >/dev/null 2>&1 || true
    systemctl stop tk-gps-firewall-rollback.service >/dev/null 2>&1 || true
    systemctl reset-failed tk-gps-firewall-rollback.service >/dev/null 2>&1 || true

    rm -f "$PENDING_FILE"

    nft --check --file "$NFT_CONFIG"
    systemctl restart nftables.service

    install_geo_timer

    log "Firewall dauerhaft bestätigt."
}

rollback() {
    [[ -s "$PENDING_FILE" ]] ||
        die "Keine Sicherung zum Zurücksetzen vorhanden."

    systemctl stop tk-gps-firewall-rollback.timer >/dev/null 2>&1 || true
    systemctl stop tk-gps-firewall-rollback.service >/dev/null 2>&1 || true

    "${WORK_DIR}/rollback.sh"

    log "Vorherige Firewall wiederhergestellt."
}

update_geo() {
    local backup="${WORK_DIR}/nftables.conf.before-update"

    [[ -f "$NFT_CONFIG" ]] ||
        die "$NFT_CONFIG fehlt."

    cp -a "$NFT_CONFIG" "$backup"

    download_geo_lists
    generate_config

    cp "$NEW_CONFIG" "$NFT_CONFIG"

    if ! nft --file "$NFT_CONFIG"; then
        cp -a "$backup" "$NFT_CONFIG"
        nft --file "$NFT_CONFIG"
        die "Geo-IP-Aktualisierung fehlgeschlagen und wurde zurückgesetzt."
    fi

    systemctl restart nftables.service
    rm -f "$backup"

    log "Geo-IP-Listen erfolgreich aktualisiert."
}

status() {
    echo
    echo "=== NFTABLES ==="
    systemctl is-active nftables.service || true

    echo
    echo "=== AKTIVE TABELLEN ==="
    nft list tables

    echo
    echo "=== WICHTIGE REGELN ==="
    nft list ruleset |
        grep -E 'policy|dport 22|dport \{ 80, 443 \}|5001-5251' || true

    echo
    echo "=== TRACCAR ==="
    systemctl is-active traccar.service || true

    echo
    echo "=== RÜCKSETZUNG ==="
    if [[ -s "$PENDING_FILE" ]]; then
        echo "Ausstehend: $(cat "$PENDING_FILE")"
        systemctl status tk-gps-firewall-rollback.timer --no-pager || true
    else
        echo "Keine Rücksetzung ausstehend."
    fi

    echo
    echo "=== GEO-IP-TIMER ==="
    systemctl status tk-gps-geo-update.timer --no-pager || true
}

help_text() {
    cat <<EOF
Verwendung:

  $0 prepare
  $0 apply
  $0 confirm
  $0 rollback
  $0 update-geo
  $0 status
EOF
}

main() {
    require_root
    create_directories

    case "${1:-}" in
        prepare)
            prepare
            ;;
        apply)
            apply
            ;;
        confirm)
            confirm
            ;;
        rollback)
            rollback
            ;;
        update-geo)
            update_geo
            ;;
        status)
            status
            ;;
        *)
            help_text
            exit 1
            ;;
    esac
}

main "$@"
