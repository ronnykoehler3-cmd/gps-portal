<?php
declare(strict_types=1);

$githubRoot = 'D:/GitHub/gps-portal/public';

$productionRoot =
    'D:/Projekte/GPS-Portal/Vergleich/'
    . 'gps-production-different/files';

$backupRoot =
    'D:/Projekte/GPS-Portal/Vergleich/'
    . 'Abgleich-Backup-'
    . date('Y-m-d_H-i-s');

$resultFile =
    'D:/Projekte/GPS-Portal/Vergleich/'
    . 'Abgleich-Ergebnis.txt';

$files = [
    'components/com_gpsportal/layouts/sidebar.php',
    'components/com_gpsportal/media/css/gpsportal-dark.css',
    'components/com_gpsportal/src/Model/DocumentsModel.php',
    'components/com_gpsportal/tmpl/dashboard/default.php',
    'templates/tkgpsportal/css/portal.css',
    'templates/tkgpsportal/index.php',
];

function normalize(string $path): string
{
    return str_replace('\\', '/', $path);
}

function ensureDirectory(string $directory): void
{
    if (
        !is_dir($directory)
        && !mkdir($directory, 0775, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            'Ordner konnte nicht erstellt werden: '
            . $directory
        );
    }
}

function copyFileSafe(
    string $source,
    string $destination
): void {
    ensureDirectory(dirname($destination));

    if (!copy($source, $destination)) {
        throw new RuntimeException(
            'Datei konnte nicht kopiert werden: '
            . $source
        );
    }
}

ensureDirectory($backupRoot);

$results = [];

foreach ($files as $relativePath) {
    $githubFile =
        normalize($githubRoot . '/' . $relativePath);

    $productionFile =
        normalize($productionRoot . '/' . $relativePath);

    if (!is_file($githubFile)) {
        $results[] =
            'FEHLT IN GITHUB: '
            . $relativePath;

        continue;
    }

    if (!is_file($productionFile)) {
        $results[] =
            'FEHLT IM PRODUKTIONSPAKET: '
            . $relativePath;

        continue;
    }

    $githubBackup =
        normalize(
            $backupRoot
            . '/github/'
            . $relativePath
        );

    $productionBackup =
        normalize(
            $backupRoot
            . '/produktiv/'
            . $relativePath
        );

    copyFileSafe(
        $githubFile,
        $githubBackup
    );

    copyFileSafe(
        $productionFile,
        $productionBackup
    );

    $githubTime =
        filemtime($githubFile);

    $productionTime =
        filemtime($productionFile);

    if (
        $githubTime === false
        || $productionTime === false
    ) {
        $results[] =
            'DATUM NICHT LESBAR: '
            . $relativePath;

        continue;
    }

    $githubHash =
        hash_file('sha256', $githubFile);

    $productionHash =
        hash_file('sha256', $productionFile);

    if ($githubHash === $productionHash) {
        $results[] =
            'BEREITS IDENTISCH: '
            . $relativePath;

        continue;
    }

    if ($productionTime > $githubTime) {
        copyFileSafe(
            $productionFile,
            $githubFile
        );

        touch(
            $githubFile,
            $productionTime
        );

        $results[] =
            'PRODUKTIV NACH GITHUB: '
            . $relativePath
            . ' | Produktiv: '
            . date('Y-m-d H:i:s', $productionTime)
            . ' | GitHub: '
            . date('Y-m-d H:i:s', $githubTime);
    } elseif ($githubTime > $productionTime) {
        $results[] =
            'GITHUB BLEIBT: '
            . $relativePath
            . ' | GitHub: '
            . date('Y-m-d H:i:s', $githubTime)
            . ' | Produktiv: '
            . date('Y-m-d H:i:s', $productionTime);
    } else {
        $results[] =
            'GLEICHES DATUM – GITHUB BLEIBT: '
            . $relativePath
            . ' | Datum: '
            . date('Y-m-d H:i:s', $githubTime);
    }
}

$output = implode(
    PHP_EOL,
    [
        'GPS-Portal Dateiabgleich',
        '========================',
        '',
        'Backup:',
        $backupRoot,
        '',
        ...$results,
        '',
    ]
);

file_put_contents(
    $resultFile,
    $output
);

echo $output;
