<?php
declare(strict_types=1);

/*
 * GPS-Portal Update-Bootstrap Builder
 *
 * Erstellt ein minimales Installationspaket mit:
 * - Update-Diensten
 * - Update-Ansicht
 * - Update-Template
 * - Sidebar mit Admin-Updatebutton
 *
 * Dashboard, Karten, Fahrzeuge, Modelle und übrige Ansichten
 * werden nicht in das Paket aufgenommen.
 */

$repositoryRoot = __DIR__;
$sourceRoot     = $repositoryRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'site-component';
$buildRoot      = $repositoryRoot . DIRECTORY_SEPARATOR . 'build';

$packageName = 'gps-portal-update-bootstrap-1.0.0';
$packageRoot = $buildRoot . DIRECTORY_SEPARATOR . $packageName;
$zipFile     = $buildRoot . DIRECTORY_SEPARATOR . $packageName . '.zip';

$files = [
    'src/Service/UpdateService.php',
    'src/Service/UpdateBackupService.php',
    'src/Service/UpdateBackupValidator.php',
    'src/Service/UpdateInstallerService.php',
    'src/View/Updates/HtmlView.php',
    'tmpl/updates/default.php',
    'layouts/sidebar.php',
];

function removeDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();

        if ($item->isDir()) {
            if (!rmdir($path)) {
                throw new RuntimeException(
                    'Ordner konnte nicht gelöscht werden: ' . $path
                );
            }

            continue;
        }

        if (!unlink($path)) {
            throw new RuntimeException(
                'Datei konnte nicht gelöscht werden: ' . $path
            );
        }
    }

    if (!rmdir($directory)) {
        throw new RuntimeException(
            'Paketordner konnte nicht gelöscht werden: ' . $directory
        );
    }
}

function ensureDirectory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException(
            'Ordner konnte nicht erstellt werden: ' . $directory
        );
    }
}

function copyPackageFile(
    string $source,
    string $destination
): void {
    if (!is_file($source)) {
        throw new RuntimeException(
            'Benötigte Quelldatei fehlt: ' . $source
        );
    }

    ensureDirectory(dirname($destination));

    if (!copy($source, $destination)) {
        throw new RuntimeException(
            'Datei konnte nicht kopiert werden: ' . $source
        );
    }
}

if (!class_exists(ZipArchive::class)) {
    throw new RuntimeException(
        'Die PHP-Erweiterung ZipArchive ist nicht verfügbar.'
    );
}

if (!is_dir($sourceRoot)) {
    throw new RuntimeException(
        'Der Quellordner fehlt: ' . $sourceRoot
    );
}

ensureDirectory($buildRoot);

removeDirectory($packageRoot);
ensureDirectory($packageRoot);

if (is_file($zipFile) && !unlink($zipFile)) {
    throw new RuntimeException(
        'Die vorhandene ZIP-Datei konnte nicht gelöscht werden: ' . $zipFile
    );
}

$manifestFiles = [];

foreach ($files as $relativeFile) {
    $normalizedFile = str_replace(
        ['/', '\\'],
        DIRECTORY_SEPARATOR,
        $relativeFile
    );

    $sourceFile = $sourceRoot
        . DIRECTORY_SEPARATOR
        . $normalizedFile;

    $packageRelativePath = 'files/components/com_gpsportal/'
        . str_replace('\\', '/', $relativeFile);

    $packageFile = $packageRoot
        . DIRECTORY_SEPARATOR
        . str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $packageRelativePath
        );

    copyPackageFile(
        $sourceFile,
        $packageFile
    );

    $sha256 = hash_file(
        'sha256',
        $packageFile
    );

    if ($sha256 === false) {
        throw new RuntimeException(
            'Prüfsumme konnte nicht erstellt werden: ' . $packageFile
        );
    }

    $manifestFiles[] = [
        'source' => $packageRelativePath,
        'target' => 'components/com_gpsportal/'
            . str_replace('\\', '/', $relativeFile),
        'sha256' => strtolower($sha256),
    ];
}

$manifest = [
    'schema'      => 1,
    'project'     => 'gps-portal',
    'name'        => 'GPS-Portal Update-System Bootstrap',
    'type'        => 'bootstrap',
    'version'     => '1.0.0',
    'created_at'  => date(DATE_ATOM),
    'description' => 'Installiert ausschließlich das getestete GPS-Portal-Updatesystem.',
    'files'       => $manifestFiles,
    'directories' => [
        'storage/updates',
        'storage/updates/backups',
        'storage/updates/packages',
        'storage/updates/temporary',
        'storage/updates/logs',
    ],
    'excluded' => [
        'src/Model',
        'src/View/Dashboard',
        'src/View/Livemap',
        'src/View/Vehicles',
        'tmpl/dashboard',
        'tmpl/livemap',
        'tmpl/vehicles',
        'templates',
        'administrator',
    ],
];

$manifestJson = json_encode(
    $manifest,
    JSON_PRETTY_PRINT
    | JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_THROW_ON_ERROR
);

if (
    file_put_contents(
        $packageRoot . DIRECTORY_SEPARATOR . 'manifest.json',
        $manifestJson . PHP_EOL
    ) === false
) {
    throw new RuntimeException(
        'manifest.json konnte nicht geschrieben werden.'
    );
}

$versionData = [
    'project'      => 'gps-portal',
    'component'    => 'update-system',
    'version'      => '1.0.0',
    'release_date' => date('Y-m-d'),
    'channel'      => 'stable',
];

$versionJson = json_encode(
    $versionData,
    JSON_PRETTY_PRINT
    | JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_THROW_ON_ERROR
);

if (
    file_put_contents(
        $packageRoot . DIRECTORY_SEPARATOR . 'version.json',
        $versionJson . PHP_EOL
    ) === false
) {
    throw new RuntimeException(
        'version.json konnte nicht geschrieben werden.'
    );
}

$installScript = <<<'PHP'
<?php

declare(strict_types=1);

/*
 * GPS-Portal Update-System Bootstrap Installer
 *
 * Aufruf:
 * php install.php /var/www/gps/public
 */

$packageRoot = __DIR__;
$joomlaRoot  = $argv[1] ?? '/var/www/gps/public';

$joomlaRoot = rtrim(
    str_replace('\\', '/', $joomlaRoot),
    '/'
);

$manifestFile = $packageRoot . '/manifest.json';

function fail(string $message): never
{
    fwrite(
        STDERR,
        'FEHLER: ' . $message . PHP_EOL
    );

    exit(1);
}

function ensureDirectory(
    string $directory,
    int $permissions = 0755
): void {
    if (is_dir($directory)) {
        return;
    }

    if (
        !mkdir($directory, $permissions, true)
        && !is_dir($directory)
    ) {
        fail(
            'Ordner konnte nicht erstellt werden: '
            . $directory
        );
    }
}

function copyFileChecked(
    string $source,
    string $destination
): void {
    ensureDirectory(
        dirname($destination)
    );

    if (!copy($source, $destination)) {
        fail(
            'Datei konnte nicht kopiert werden: '
            . $destination
        );
    }

    if (!chmod($destination, 0644)) {
        fwrite(
            STDERR,
            'WARNUNG: Rechte konnten nicht gesetzt werden: '
            . $destination
            . PHP_EOL
        );
    }
}

if (!is_dir($joomlaRoot)) {
    fail(
        'Joomla-Verzeichnis existiert nicht: '
        . $joomlaRoot
    );
}

if (!is_file($manifestFile)) {
    fail('manifest.json fehlt.');
}

$manifestRaw = file_get_contents(
    $manifestFile
);

if ($manifestRaw === false) {
    fail('manifest.json konnte nicht gelesen werden.');
}

try {
    $manifest = json_decode(
        $manifestRaw,
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (Throwable $exception) {
    fail(
        'manifest.json ist ungültig: '
        . $exception->getMessage()
    );
}

if (
    !isset($manifest['files'])
    || !is_array($manifest['files'])
) {
    fail('Das Manifest enthält keine Dateiliste.');
}

/*
 * Zuerst alle Paketdateien vollständig prüfen.
 * Bis zu diesem Punkt wird nichts am Portal verändert.
 */
foreach ($manifest['files'] as $file) {
    if (
        !isset(
            $file['source'],
            $file['target'],
            $file['sha256']
        )
    ) {
        fail('Ein Manifest-Dateieintrag ist unvollständig.');
    }

    $sourceFile = $packageRoot
        . '/'
        . ltrim(
            (string) $file['source'],
            '/'
        );

    if (!is_file($sourceFile)) {
        fail(
            'Paketdatei fehlt: '
            . $file['source']
        );
    }

    $actualHash = hash_file(
        'sha256',
        $sourceFile
    );

    if ($actualHash === false) {
        fail(
            'Prüfsumme konnte nicht berechnet werden: '
            . $file['source']
        );
    }

    $expectedHash = strtolower(
        trim(
            (string) $file['sha256']
        )
    );

    if (
        !hash_equals(
            $expectedHash,
            strtolower($actualHash)
        )
    ) {
        fail(
            'Prüfsumme stimmt nicht: '
            . $file['source']
        );
    }
}

$timestamp  = date('Ymd-His');
$backupRoot = '/root/backups/gps-update-bootstrap-'
    . $timestamp;

ensureDirectory(
    $backupRoot,
    0750
);

$installedFiles = [];
$backedUpFiles  = [];

try {
    foreach ($manifest['files'] as $file) {
        $sourceFile = $packageRoot
            . '/'
            . ltrim(
                (string) $file['source'],
                '/'
            );

        $relativeTarget = ltrim(
            (string) $file['target'],
            '/'
        );

        $targetFile = $joomlaRoot
            . '/'
            . $relativeTarget;

        /*
         * Identische Dateien müssen nicht ersetzt werden.
         */
        if (is_file($targetFile)) {
            $currentHash = hash_file(
                'sha256',
                $targetFile
            );

            $newHash = hash_file(
                'sha256',
                $sourceFile
            );

            if (
                $currentHash !== false
                && $newHash !== false
                && hash_equals(
                    strtolower($currentHash),
                    strtolower($newHash)
                )
            ) {
                echo 'Unverändert: '
                    . $relativeTarget
                    . PHP_EOL;

                continue;
            }

            $backupFile = $backupRoot
                . '/'
                . $relativeTarget;

            ensureDirectory(
                dirname($backupFile),
                0750
            );

            if (!copy($targetFile, $backupFile)) {
                throw new RuntimeException(
                    'Backup fehlgeschlagen: '
                    . $relativeTarget
                );
            }

            $backedUpFiles[] = $relativeTarget;
        }

        copyFileChecked(
            $sourceFile,
            $targetFile
        );

        $installedFiles[] = $relativeTarget;

        echo 'Installiert: '
            . $relativeTarget
            . PHP_EOL;
    }

    foreach (
        $manifest['directories'] ?? []
        as $relativeDirectory
    ) {
        $directory = $joomlaRoot
            . '/'
            . ltrim(
                (string) $relativeDirectory,
                '/'
            );

        ensureDirectory(
            $directory,
            0775
        );

        chmod(
            $directory,
            0775
        );
    }

    $installationRecord = [
        'project'          => 'gps-portal',
        'component'        => 'update-system',
        'version'          => (string) ($manifest['version'] ?? '1.0.0'),
        'installed_at'     => date(DATE_ATOM),
        'backup_directory' => $backupRoot,
        'installed_files'  => $installedFiles,
        'backed_up_files'  => $backedUpFiles,
    ];

    $recordFile = $joomlaRoot
        . '/storage/updates/bootstrap-installation.json';

    ensureDirectory(
        dirname($recordFile),
        0775
    );

    $recordJson = json_encode(
        $installationRecord,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR
    );

    if (
        file_put_contents(
            $recordFile,
            $recordJson . PHP_EOL
        ) === false
    ) {
        throw new RuntimeException(
            'Installationsprotokoll konnte nicht geschrieben werden.'
        );
    }

    chmod(
        $recordFile,
        0664
    );
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Installation fehlgeschlagen: '
        . $exception->getMessage()
        . PHP_EOL
    );

    fwrite(
        STDERR,
        'Automatische Wiederherstellung wird gestartet.'
        . PHP_EOL
    );

    foreach (
        array_reverse($installedFiles)
        as $relativeTarget
    ) {
        $targetFile = $joomlaRoot
            . '/'
            . $relativeTarget;

        $backupFile = $backupRoot
            . '/'
            . $relativeTarget;

        if (is_file($backupFile)) {
            copyFileChecked(
                $backupFile,
                $targetFile
            );

            fwrite(
                STDERR,
                'Wiederhergestellt: '
                . $relativeTarget
                . PHP_EOL
            );

            continue;
        }

        if (is_file($targetFile)) {
            unlink($targetFile);

            fwrite(
                STDERR,
                'Neue Datei entfernt: '
                . $relativeTarget
                . PHP_EOL
            );
        }
    }

    fail('Bootstrap wurde nicht installiert.');
}

echo PHP_EOL;
echo 'GPS-Portal Update-System wurde erfolgreich installiert.'
    . PHP_EOL;

echo 'Backup: '
    . $backupRoot
    . PHP_EOL;

echo 'Installationsprotokoll: '
    . $joomlaRoot
    . '/storage/updates/bootstrap-installation.json'
    . PHP_EOL;
PHP;

if (
    file_put_contents(
        $packageRoot . DIRECTORY_SEPARATOR . 'install.php',
        $installScript . PHP_EOL
    ) === false
) {
    throw new RuntimeException(
        'install.php konnte nicht geschrieben werden.'
    );
}

$readme = <<<'TEXT'
GPS-Portal Update-System Bootstrap 1.0.0
========================================

Dieses Paket installiert ausschließlich das Update-System.

Enthalten:
- UpdateService
- UpdateBackupService
- UpdateBackupValidator
- UpdateInstallerService
- Updateansicht
- Update-Template
- Sidebar mit Updatebutton für Administratoren

Nicht enthalten:
- Dashboard
- Live-Karte
- Fahrzeugverwaltung
- Historie
- Fahrtenbuch
- Geozonen
- Dokumente
- Modelle
- Portal-Template
- Datenbankänderungen

Installation auf dem Server:

php install.php /var/www/gps/public
TEXT;

if (
    file_put_contents(
        $packageRoot . DIRECTORY_SEPARATOR . 'README.txt',
        $readme . PHP_EOL
    ) === false
) {
    throw new RuntimeException(
        'README.txt konnte nicht geschrieben werden.'
    );
}

$zip = new ZipArchive();

$zipResult = $zip->open(
    $zipFile,
    ZipArchive::CREATE
    | ZipArchive::OVERWRITE
);

if ($zipResult !== true) {
    throw new RuntimeException(
        'ZIP-Datei konnte nicht erstellt werden. Fehlercode: '
        . $zipResult
    );
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $packageRoot,
        FilesystemIterator::SKIP_DOTS
    ),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $fullPath = $fileInfo->getPathname();

    $relativePath = substr(
        $fullPath,
        strlen($packageRoot) + 1
    );

    $relativePath = str_replace(
        '\\',
        '/',
        $relativePath
    );

    if (
        !$zip->addFile(
            $fullPath,
            $relativePath
        )
    ) {
        throw new RuntimeException(
            'Datei konnte nicht zum ZIP hinzugefügt werden: '
            . $relativePath
        );
    }
}

if (!$zip->close()) {
    throw new RuntimeException(
        'ZIP-Datei konnte nicht abgeschlossen werden.'
    );
}

echo PHP_EOL;
echo '============================================='
    . PHP_EOL;

echo 'Bootstrap-Paket wurde erfolgreich erstellt.'
    . PHP_EOL;

echo '============================================='
    . PHP_EOL;

echo $zipFile
    . PHP_EOL
    . PHP_EOL;

echo 'Enthaltene Portaldateien:'
    . PHP_EOL;

foreach ($manifestFiles as $file) {
    echo ' - '
        . $file['target']
        . PHP_EOL;
}

echo PHP_EOL;
echo 'Nicht enthalten: Dashboard, Karten, Modelle und Datenbank.'
    . PHP_EOL;
