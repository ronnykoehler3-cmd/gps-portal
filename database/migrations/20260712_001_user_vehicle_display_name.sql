-- GPS-Portal
-- Migration: Kundenbezogene Fahrzeugnamen
-- Datum: 2026-07-12
--
-- Diese Migration erweitert die Zuordnung zwischen einem
-- Joomla-Benutzer und einem Fahrzeug um einen eigenen Anzeigenamen.
--
-- Beispiel:
-- Benutzer 357 sieht Fahrzeug 10 als "Ford-Ka".
-- Benutzer 360 sieht dasselbe Fahrzeug als "Leiche".
--
-- Der globale Name in gpsportal_devices bleibt als Standardname erhalten.

START TRANSACTION;

-- Sicherung der Fahrzeugstammdaten
CREATE TABLE IF NOT EXISTS
    eusdi_gpsportal_devices_backup_20260712
LIKE
    eusdi_gpsportal_devices;

INSERT IGNORE INTO
    eusdi_gpsportal_devices_backup_20260712
SELECT
    *
FROM
    eusdi_gpsportal_devices;

-- Sicherung der Benutzer-Fahrzeug-Zuordnungen
CREATE TABLE IF NOT EXISTS
    eusdi_gpsportal_user_devices_backup_20260712
LIKE
    eusdi_gpsportal_user_devices;

INSERT IGNORE INTO
    eusdi_gpsportal_user_devices_backup_20260712
SELECT
    *
FROM
    eusdi_gpsportal_user_devices;

-- Kundenbezogener Anzeigename
ALTER TABLE
    eusdi_gpsportal_user_devices
ADD COLUMN IF NOT EXISTS
    display_name VARCHAR(190) NULL
AFTER
    device_id;

-- Änderungszeitpunkt
ALTER TABLE
    eusdi_gpsportal_user_devices
ADD COLUMN IF NOT EXISTS
    modified DATETIME NULL
AFTER
    created;

-- Bestehende Zuordnungen übernehmen zunächst den bisherigen
-- globalen Fahrzeugnamen. Dadurch ändert sich nach der Migration
-- noch keine bestehende Anzeige.
UPDATE
    eusdi_gpsportal_user_devices AS ud
INNER JOIN
    eusdi_gpsportal_devices AS d
        ON d.id = ud.device_id
SET
    ud.display_name = d.name,
    ud.modified = NOW()
WHERE
    ud.display_name IS NULL
    OR TRIM(ud.display_name) = '';

COMMIT;

-- Kontrollausgabe
SELECT
    ud.id,
    ud.user_id,
    ud.device_id,
    ud.display_name,
    d.name AS global_name,
    ud.created,
    ud.modified
FROM
    eusdi_gpsportal_user_devices AS ud
INNER JOIN
    eusdi_gpsportal_devices AS d
        ON d.id = ud.device_id
ORDER BY
    ud.user_id,
    ud.device_id;
