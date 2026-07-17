# GPS-Portal Updateanleitung

---

# Version 1.2.0

## Enthalten

### Dokumentenverwaltung

- Dokumentenvorschau
- Dokumentensuche
- Dokumentfilter
- Responsive Darstellung
- Rechteprüfung
- Sichere Dateiverwaltung
- Automatische Windows/Linux Speicherpfaderkennung
- Ablaufüberwachung

---

## Datenbank

Für dieses Update sind **keine Datenbankänderungen erforderlich**.

---

## Geänderte Dateien

```
components/com_gpsportal/src/Model/DocumentsModel.php

components/com_gpsportal/src/View/Documents/HtmlView.php

components/com_gpsportal/tmpl/documents/default.php
```

---

## Updateablauf

1. Backup erstellen
2. Dateien kopieren
3. SQL-Updates ausführen (falls vorhanden)
4. Cache leeren
5. Anmeldung testen
6. Dokumentenverwaltung testen

---

## Nach dem Update prüfen

- Upload
- Download
- Vorschau
- Löschen
- Rechteprüfung
- Dokumentensuche
- Dokumentfilter

---

## Rollback

Vor jedem Update ist ein vollständiges Backup des Servers anzulegen.

Sollte ein Update fehlschlagen:

- Backup zurückspielen
- Datenbank zurückspielen
- Dienste prüfen
- Logdateien kontrollieren