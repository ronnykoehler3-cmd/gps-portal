<?php

declare(strict_types=1);

/*
 * GPS Portal – lokale Karten auf CARTO Dark Matter umstellen
 *
 * Dieses Skript verändert ausschließlich:
 * D:\Web\gps-portal
 *
 * Vor jeder Änderung wird eine Sicherung unter:
 * D:\Projekte\GPS-Portal\DarkMap-Backup\<Zeitstempel>
 * angelegt.
 */

$projectRoot = 'D:\\Web\\gps-portal';

$backupRoot = sprintf(
    'D:\\Projekte\\GPS-Portal\\DarkMap-Backup\\%s',
    date('Y-m-d_H-i-s')
);

$files = [
    'administrator\\components\\com_gpsportal\\tmpl\\vehicle\\default.php',
    'components\\com_gpsportal\\tmpl\\dashboard\\default.php',
    'components\\com_gpsportal\\tmpl\\geofences\\default.php',
    'components\\com_gpsportal\\tmpl\\history\\default.php',
    'components\\com_gpsportal\\tmpl\\livemap\\default.php',
];

$oldTileUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

$newTileUrl =
    'https://{s}.basemaps.cartocdn.com/'
    . 'dark_all/{z}/{x}/{y}{r}.png';

$newAttribution =
    '&copy; OpenStreetMap contributors '
    . '&copy; CARTO';

$changedFiles = [];
$unchangedFiles = [];
$missingFiles = [];
$failedFiles = [];

echo PHP_EOL;
echo "============================================================" . PHP_EOL;
echo " GPS Portal – Dark-Map-Umstellung" . PHP_EOL;
echo "============================================================" . PHP_EOL;
echo "Lokales Projekt:" . PHP_EOL;
echo $projectRoot . PHP_EOL;
echo PHP_EOL;
echo "Sicherungen:" . PHP_EOL;
echo $backupRoot . PHP_EOL;
echo "============================================================" . PHP_EOL;
echo PHP_EOL;

foreach ($files as $relativePath) {
    $sourceFile = $projectRoot
        . DIRECTORY_SEPARATOR
        . str_replace(
            '\\',
            DIRECTORY_SEPARATOR,
            $relativePath
        );

    if (!is_file($sourceFile)) {
        $missingFiles[] = $relativePath;

        echo "[FEHLT]      {$relativePath}" . PHP_EOL;

        continue;
    }

    $originalContent = file_get_contents($sourceFile);

    if ($originalContent === false) {
        $failedFiles[] = $relativePath;

        echo "[FEHLER]     {$relativePath}" . PHP_EOL;

        continue;
    }

    $newContent = str_replace(
        $oldTileUrl,
        $newTileUrl,
        $originalContent,
        $tileReplacementCount
    );

    /*
     * Häufig verwendete OpenStreetMap-Quellenangaben ersetzen.
     * Die Ersetzung erfolgt nur in Dateien, in denen auch die
     * Kartenadresse erfolgreich geändert wurde.
     */
    if ($tileReplacementCount > 0) {
        $attributionPatterns = [
            "/attribution\\s*:\\s*['\"]"
                . "[^'\"]*OpenStreetMap[^'\"]*"
                . "['\"]/iu",

            "/attribution\\s*=>\\s*['\"]"
                . "[^'\"]*OpenStreetMap[^'\"]*"
                . "['\"]/iu",
        ];

        $attributionReplacement =
            "attribution: '"
            . $newAttribution
            . "'";

        $newContent = preg_replace(
            $attributionPatterns,
            $attributionReplacement,
            $newContent
        ) ?? $newContent;
    }

    if ($tileReplacementCount === 0) {
        $unchangedFiles[] = $relativePath;

        echo "[UNVERÄNDERT] {$relativePath}" . PHP_EOL;

        continue;
    }

    $backupFile = $backupRoot
        . DIRECTORY_SEPARATOR
        . str_replace(
            '\\',
            DIRECTORY_SEPARATOR,
            $relativePath
        );

    $backupDirectory = dirname($backupFile);

    if (
        !is_dir($backupDirectory)
        && !mkdir($backupDirectory, 0775, true)
        && !is_dir($backupDirectory)
    ) {
        $failedFiles[] = $relativePath;

        echo "[FEHLER] Sicherungsordner nicht erstellbar: "
            . $backupDirectory
            . PHP_EOL;

        continue;
    }

    if (!copy($sourceFile, $backupFile)) {
        $failedFiles[] = $relativePath;

        echo "[FEHLER] Sicherung fehlgeschlagen: "
            . $relativePath
            . PHP_EOL;

        continue;
    }

    if (file_put_contents($sourceFile, $newContent) === false) {
        $failedFiles[] = $relativePath;

        echo "[FEHLER] Datei konnte nicht geschrieben werden: "
            . $relativePath
            . PHP_EOL;

        continue;
    }

    $changedFiles[] = $relativePath;

    echo "[GEÄNDERT]   {$relativePath}" . PHP_EOL;
}

echo PHP_EOL;
echo "============================================================" . PHP_EOL;
echo " ERGEBNIS" . PHP_EOL;
echo "============================================================" . PHP_EOL;
echo "Geändert:      " . count($changedFiles) . PHP_EOL;
echo "Unverändert:   " . count($unchangedFiles) . PHP_EOL;
echo "Nicht gefunden:" . count($missingFiles) . PHP_EOL;
echo "Fehler:        " . count($failedFiles) . PHP_EOL;
echo PHP_EOL;

if ($changedFiles !== []) {
    echo "Geänderte Dateien:" . PHP_EOL;

    foreach ($changedFiles as $file) {
        echo "  - {$file}" . PHP_EOL;
    }

    echo PHP_EOL;
    echo "Sicherung der Originaldateien:" . PHP_EOL;
    echo $backupRoot . PHP_EOL;
}

if ($unchangedFiles !== []) {
    echo PHP_EOL;
    echo "Dateien ohne gefundene OpenStreetMap-Kartenadresse:" . PHP_EOL;

    foreach ($unchangedFiles as $file) {
        echo "  - {$file}" . PHP_EOL;
    }
}

if ($missingFiles !== []) {
    echo PHP_EOL;
    echo "Nicht vorhandene Dateien:" . PHP_EOL;

    foreach ($missingFiles as $file) {
        echo "  - {$file}" . PHP_EOL;
    }
}

if ($failedFiles !== []) {
    echo PHP_EOL;
    echo "Fehlerhafte Dateien:" . PHP_EOL;

    foreach ($failedFiles as $file) {
        echo "  - {$file}" . PHP_EOL;
    }
}

echo PHP_EOL;
echo "============================================================" . PHP_EOL;

exit($failedFiles === [] ? 0 : 1);