<?php

$path = 'D:/GitHub/gps-portal/public/components/com_gpsportal/src/Model/DocumentsModel.php';

$content = file_get_contents($path);

if ($content === false) {
    fwrite(STDERR, "FEHLER: DocumentsModel.php konnte nicht gelesen werden.\n");
    exit(1);
}

/*
 * Einheitliche Speicherpfad-Methode.
 *
 * Lokal:
 * D:\Web\gps-portal\storage\vehicles
 *
 * Produktion:
 * /var/www/gps/storage/vehicles
 */
$storageMethod = <<<'PHP'
    private function getStoragePath(): string
    {
        $projectRoot = JPATH_ROOT;

        /*
         * Auf dem Produktionsserver liegt Joomla unter:
         * /var/www/gps/public
         *
         * Der gesch?tzte Speicher liegt daneben unter:
         * /var/www/gps/storage
         */
        if (
            strtolower(
                basename(
                    str_replace('\\', '/', JPATH_ROOT)
                )
            ) === 'public'
        ) {
            $projectRoot = dirname(JPATH_ROOT);
        }

        $storagePath = $projectRoot
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'vehicles';

        if (
            !is_dir($storagePath)
            && !mkdir($storagePath, 0770, true)
            && !is_dir($storagePath)
        ) {
            $this->lastError =
                'Der Dokumentenordner konnte nicht angelegt werden.';

            return '';
        }

        return $storagePath
            . DIRECTORY_SEPARATOR;
    }

PHP;

/*
 * Methode nur einf?gen, wenn sie noch nicht existiert.
 */
if (
    strpos(
        $content,
        'private function getStoragePath()'
    ) === false
) {
    $marker = '    public function getVehicles(): array';

    if (strpos($content, $marker) === false) {
        fwrite(
            STDERR,
            "FEHLER: Die Methode getVehicles() wurde nicht gefunden.\n"
        );
        exit(1);
    }

    $content = str_replace(
        $marker,
        $storageMethod . $marker,
        $content,
        $insertCount
    );

    if ($insertCount !== 1) {
        fwrite(
            STDERR,
            "FEHLER: Speicherpfad-Methode wurde nicht eindeutig eingef?gt.\n"
        );
        exit(1);
    }
}

/*
 * Upload-Pfad ersetzen.
 */
$uploadPattern =
    '~\$storagePath\s*=\s*'
    . '\'/var/www/gps/storage/vehicles/\''
    . '\s*;~';

$uploadReplacement = <<<'PHP'
$storagePath = $this->getStoragePath();

    if (
        $storagePath === ''
        || !is_dir($storagePath)
        || !is_writable($storagePath)
    ) {
        $this->lastError =
            'Der Dokumentenordner ist nicht beschreibbar.';

        return false;
    }
PHP;

$content = preg_replace(
    $uploadPattern,
    $uploadReplacement,
    $content,
    1,
    $uploadCount
);

if ($content === null) {
    fwrite(STDERR, "FEHLER: Upload-Ersetzung ist fehlgeschlagen.\n");
    exit(1);
}

if ($uploadCount !== 1) {
    fwrite(
        STDERR,
        "FEHLER: Upload-Pfad wurde nicht genau einmal gefunden. Treffer: "
        . $uploadCount
        . "\n"
    );
    exit(1);
}

/*
 * Download- und L?schpfad ersetzen.
 * Die Formatierung und Einr?ckung d?rfen unterschiedlich sein.
 */
$filePattern =
    '~\'/var/www/gps/storage/vehicles/\''
    . '\s*\.\s*'
    . '\$document->filename~';

$fileReplacement =
    '$this->getStoragePath()'
    . "\n            . basename((string) \$document->filename)";

$content = preg_replace(
    $filePattern,
    $fileReplacement,
    $content,
    -1,
    $fileCount
);

if ($content === null) {
    fwrite(STDERR, "FEHLER: Datei-Pfad-Ersetzung ist fehlgeschlagen.\n");
    exit(1);
}

if ($fileCount !== 2) {
    fwrite(
        STDERR,
        "FEHLER: Download- und L?schpfad wurden nicht genau zweimal gefunden. Treffer: "
        . $fileCount
        . "\n"
    );
    exit(1);
}

/*
 * Kontrollieren, dass kein alter Serverpfad mehr vorhanden ist.
 */
if (
    strpos(
        $content,
        '/var/www/gps/storage/vehicles/'
    ) !== false
) {
    fwrite(
        STDERR,
        "FEHLER: Es ist noch mindestens ein alter Linux-Pfad vorhanden.\n"
    );
    exit(1);
}

if (file_put_contents($path, $content) === false) {
    fwrite(
        STDERR,
        "FEHLER: DocumentsModel.php konnte nicht gespeichert werden.\n"
    );
    exit(1);
}

echo "OK: Speicherpfad-Methode wurde eingerichtet.\n";
echo "OK: Upload-Pfad wurde ersetzt.\n";
echo "OK: Download- und L?schpfad wurden ersetzt.\n";
