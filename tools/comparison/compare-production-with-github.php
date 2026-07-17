<?php

$githubRoot = 'D:/GitHub/gps-portal/public';

$productionManifest =
    'D:/Projekte/GPS-Portal/Vergleich/'
    . 'gps-production-comparison/production-manifest.tsv';

$outputRoot =
    'D:/Projekte/GPS-Portal/Vergleich/Ergebnis';

$excludedPaths = [
    'configuration.php',
];

$excludedPrefixes = [
    'cache/',
    'administrator/cache/',
    'tmp/',
    'logs/',
    'log/',
    'storage/',
    'images/',
    'media/cache/',
    'administrator/logs/',
];

$excludedExtensions = [
    'log',
    'tmp',
    'bak',
    'old',
    'zip',
    'tar',
    'gz',
];

function failCompare(string $message): void
{
    fwrite(STDERR, 'FEHLER: ' . $message . PHP_EOL);
    exit(1);
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function isExcluded(
    string $relativePath,
    array $excludedPaths,
    array $excludedPrefixes,
    array $excludedExtensions
): bool {
    $relativePath = normalizePath($relativePath);

    if (in_array($relativePath, $excludedPaths, true)) {
        return true;
    }

    foreach ($excludedPrefixes as $prefix) {
        if (str_starts_with(
            strtolower($relativePath),
            strtolower($prefix)
        )) {
            return true;
        }
    }

    $extension = strtolower(
        pathinfo($relativePath, PATHINFO_EXTENSION)
    );

    return in_array(
        $extension,
        $excludedExtensions,
        true
    );
}

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
            rmdir($path);
        } else {
            unlink($path);
        }
    }

    rmdir($directory);
}

function writeCsv(
    string $filename,
    array $rows
): void {
    $handle = fopen($filename, 'wb');

    if ($handle === false) {
        failCompare(
            'CSV-Datei konnte nicht erstellt werden: '
            . $filename
        );
    }

    fwrite($handle, "\xEF\xBB\xBF");

    fputcsv(
        $handle,
        [
            'Status',
            'Pfad',
            'ProduktivGroesse',
            'GitHubGroesse',
            'ProduktivSHA256',
            'GitHubSHA256',
        ],
        ';'
    );

    foreach ($rows as $row) {
        fputcsv(
            $handle,
            [
                $row['status'],
                $row['path'],
                $row['production_size'],
                $row['github_size'],
                $row['production_hash'],
                $row['github_hash'],
            ],
            ';'
        );
    }

    fclose($handle);
}

if (!is_dir($githubRoot)) {
    failCompare(
        'GitHub-Ordner fehlt: '
        . $githubRoot
    );
}

if (!is_file($productionManifest)) {
    failCompare(
        'Produktionsmanifest fehlt: '
        . $productionManifest
    );
}

removeDirectory($outputRoot);

if (
    !mkdir($outputRoot, 0775, true)
    && !is_dir($outputRoot)
) {
    failCompare(
        'Ergebnisordner konnte nicht erstellt werden: '
        . $outputRoot
    );
}

echo PHP_EOL;
echo 'GitHub-Dateien werden geprüft ...' . PHP_EOL;

$githubIndex = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $githubRoot,
        FilesystemIterator::SKIP_DOTS
    ),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$githubRootNormalized = rtrim(
    normalizePath($githubRoot),
    '/'
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $fullPath = normalizePath(
        $fileInfo->getPathname()
    );

    $relativePath = ltrim(
        substr(
            $fullPath,
            strlen($githubRootNormalized)
        ),
        '/'
    );

    if (
        isExcluded(
            $relativePath,
            $excludedPaths,
            $excludedPrefixes,
            $excludedExtensions
        )
    ) {
        continue;
    }

    $hash = hash_file(
        'sha256',
        $fileInfo->getPathname()
    );

    if ($hash === false) {
        failCompare(
            'Hash konnte nicht erstellt werden: '
            . $relativePath
        );
    }

    $githubIndex[$relativePath] = [
        'size' => $fileInfo->getSize(),
        'hash' => strtolower($hash),
    ];
}

echo 'Produktionsmanifest wird geladen ...' . PHP_EOL;

$productionIndex = [];

$handle = fopen(
    $productionManifest,
    'rb'
);

if ($handle === false) {
    failCompare(
        'Produktionsmanifest konnte nicht geöffnet werden.'
    );
}

while (($line = fgets($handle)) !== false) {
    $line = rtrim(
        $line,
        "\r\n"
    );

    if ($line === '') {
        continue;
    }

    $columns = explode(
        "\t",
        $line,
        3
    );

    if (count($columns) !== 3) {
        continue;
    }

    $relativePath = normalizePath(
        trim($columns[0])
    );

    $productionIndex[$relativePath] = [
        'size' => (int) $columns[1],
        'hash' => strtolower(
            trim($columns[2])
        ),
    ];
}

fclose($handle);

$allPaths = array_unique(
    array_merge(
        array_keys($githubIndex),
        array_keys($productionIndex)
    )
);

sort(
    $allPaths,
    SORT_STRING
);

$comparison = [];

foreach ($allPaths as $path) {
    $githubFile =
        $githubIndex[$path]
        ?? null;

    $productionFile =
        $productionIndex[$path]
        ?? null;

    if ($productionFile === null) {
        $status = 'Nur GitHub';
    } elseif ($githubFile === null) {
        $status = 'Nur Produktiv';
    } elseif (
        hash_equals(
            $productionFile['hash'],
            $githubFile['hash']
        )
    ) {
        $status = 'Identisch';
    } else {
        $status = 'Unterschiedlich';
    }

    $comparison[] = [
        'status' => $status,
        'path' => $path,
        'production_size' =>
            $productionFile['size']
            ?? '',
        'github_size' =>
            $githubFile['size']
            ?? '',
        'production_hash' =>
            $productionFile['hash']
            ?? '',
        'github_hash' =>
            $githubFile['hash']
            ?? '',
    ];
}

$identical = array_values(
    array_filter(
        $comparison,
        fn(array $row): bool =>
            $row['status'] === 'Identisch'
    )
);

$different = array_values(
    array_filter(
        $comparison,
        fn(array $row): bool =>
            $row['status'] === 'Unterschiedlich'
    )
);

$onlyProduction = array_values(
    array_filter(
        $comparison,
        fn(array $row): bool =>
            $row['status'] === 'Nur Produktiv'
    )
);

$onlyGitHub = array_values(
    array_filter(
        $comparison,
        fn(array $row): bool =>
            $row['status'] === 'Nur GitHub'
    )
);

writeCsv(
    $outputRoot . '/production-vs-github.csv',
    $comparison
);

writeCsv(
    $outputRoot . '/identisch.csv',
    $identical
);

writeCsv(
    $outputRoot . '/unterschiedlich.csv',
    $different
);

writeCsv(
    $outputRoot . '/nur-produktiv.csv',
    $onlyProduction
);

writeCsv(
    $outputRoot . '/nur-github.csv',
    $onlyGitHub
);

$summary = implode(
    PHP_EOL,
    [
        'GPS-Portal Vergleich Produktiv gegen GitHub',
        '===========================================',
        '',
        'Produktivdateien:    '
            . count($productionIndex),
        'GitHub-Dateien:      '
            . count($githubIndex),
        '',
        'Identisch:           '
            . count($identical),
        'Unterschiedlich:     '
            . count($different),
        'Nur Produktiv:       '
            . count($onlyProduction),
        'Nur GitHub:          '
            . count($onlyGitHub),
        '',
        'Ergebnisordner:',
        $outputRoot,
        '',
    ]
);

if (
    file_put_contents(
        $outputRoot . '/zusammenfassung.txt',
        $summary
    ) === false
) {
    failCompare(
        'Zusammenfassung konnte nicht geschrieben werden.'
    );
}

echo PHP_EOL;
echo '============================================='
    . PHP_EOL;

echo 'Vergleich abgeschlossen'
    . PHP_EOL;

echo '============================================='
    . PHP_EOL;

echo 'Identisch:       '
    . count($identical)
    . PHP_EOL;

echo 'Unterschiedlich: '
    . count($different)
    . PHP_EOL;

echo 'Nur Produktiv:   '
    . count($onlyProduction)
    . PHP_EOL;

echo 'Nur GitHub:      '
    . count($onlyGitHub)
    . PHP_EOL;

echo PHP_EOL;
echo 'Ergebnis:'
    . PHP_EOL;

echo $outputRoot
    . PHP_EOL;
