<?php

declare(strict_types=1);

/*
 * GPS-Portal UTF-8-Pr?fung
 *
 * Gepr?ft werden ausschlie?lich unsere eigenen Projektdateien.
 * Joomla-Core, Vendor, Cache, Backups und Update-ZIPs werden ignoriert.
 */

$scanRoots = [
    'D:/GitHub/gps-portal/src/site-component',
    'D:/GitHub/gps-portal/src/template',
    'D:/GitHub/gps-portal/public/components/com_gpsportal',
    'D:/GitHub/gps-portal/public/templates/tkgpsportal',

    'D:/Web/gps-portal/components/com_gpsportal',
    'D:/Web/gps-portal/templates/tkgpsportal',
    'D:/Web/gps-portal/router.php',
];

$allowedExtensions = [
    'php',
    'phtml',
    'html',
    'htm',
    'css',
    'js',
    'json',
    'xml',
    'sql',
    'md',
    'txt',
    'ini',
    'yml',
    'yaml',
];

$excludedDirectoryNames = [
    '.git',
    '.idea',
    '.vscode',
    'node_modules',
    'vendor',
    'cache',
    'logs',
    'tmp',
    'temporary',
    'backups',
    'packages',
    'storage',
];

$issues = [];
$checkedFiles = 0;

function normalizePath(string $path): string
{
    return str_replace(
        '\\',
        '/',
        $path
    );
}

function isValidUtf8(string $content): bool
{
    if (function_exists('mb_check_encoding')) {
        return mb_check_encoding(
            $content,
            'UTF-8'
        );
    }

    return preg_match(
        '//u',
        $content
    ) === 1;
}

function getLineNumber(
    string $content,
    int $offset
): int {
    if ($offset <= 0) {
        return 1;
    }

    return substr_count(
        substr(
            $content,
            0,
            $offset
        ),
        "\n"
    ) + 1;
}

function getLineText(
    string $content,
    int $lineNumber
): string {
    $lines = preg_split(
        '/\R/',
        $content
    );

    if (!is_array($lines)) {
        return '';
    }

    return trim(
        (string) (
            $lines[$lineNumber - 1]
            ?? ''
        )
    );
}

function addIssue(
    array &$issues,
    string $file,
    int $line,
    string $type,
    string $details,
    string $text = ''
): void {
    $issues[] = [
        'file' => normalizePath($file),
        'line' => $line,
        'type' => $type,
        'details' => $details,
        'text' => $text,
    ];
}

function scanFile(
    string $file,
    array &$issues,
    int &$checkedFiles,
    array $allowedExtensions
): void {
    if (!is_file($file)) {
        return;
    }

    $extension = strtolower(
        pathinfo(
            $file,
            PATHINFO_EXTENSION
        )
    );

    if (
        !in_array(
            $extension,
            $allowedExtensions,
            true
        )
    ) {
        return;
    }

    $content = file_get_contents(
        $file
    );

    if ($content === false) {
        addIssue(
            $issues,
            $file,
            0,
            'Lesefehler',
            'Datei konnte nicht gelesen werden.'
        );

        return;
    }

    $checkedFiles++;

    if (
        str_starts_with(
            $content,
            "\xEF\xBB\xBF"
        )
    ) {
        addIssue(
            $issues,
            $file,
            1,
            'UTF-8-BOM',
            'Datei beginnt mit einem UTF-8-BOM.'
        );
    }

    if (!isValidUtf8($content)) {
        addIssue(
            $issues,
            $file,
            0,
            'Kodierung',
            'Datei ist kein g?ltiges UTF-8.'
        );

        return;
    }

    /*
     * Echtes Unicode-Ersatzzeichen.
     */
    $replacementOffset = strpos(
        $content,
        "\u{FFFD}"
    );

    if ($replacementOffset !== false) {
        $line = getLineNumber(
            $content,
            $replacementOffset
        );

        addIssue(
            $issues,
            $file,
            $line,
            'Ersatzzeichen',
            'Unicode-Ersatzzeichen gefunden.',
            getLineText(
                $content,
                $line
            )
        );
    }

    /*
     * Typische UTF-8-Fehlinterpretationen.
     * Die Zeichen werden ?ber Hexwerte definiert,
     * damit der Scanner selbst ASCII-sicher gespeichert bleibt.
     */
    $mojibakePatterns = [
        "\xC3\x83\xC2\xA4",
        "\xC3\x83\xC2\xB6",
        "\xC3\x83\xC2\xBC",
        "\xC3\x83\xC2\x84",
        "\xC3\x83\xC2\x96",
        "\xC3\x83\xC2\x9C",
        "\xC3\x83\xC2\x9F",
        "\xC3\x82\xC2\xA0",
        "\xC3\xA2\xE2\x82\xAC\xE2\x80\x93",
        "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D",
    ];

    foreach ($mojibakePatterns as $pattern) {
        $offset = strpos(
            $content,
            $pattern
        );

        if ($offset === false) {
            continue;
        }

        $line = getLineNumber(
            $content,
            $offset
        );

        addIssue(
            $issues,
            $file,
            $line,
            'Fehlkodierung',
            'Typische UTF-8-Fehlkodierung gefunden.',
            getLineText(
                $content,
                $line
            )
        );
    }

    /*
     * Besch?digte deutsche W?rter mit Fragezeichen.
     *
     * Ausgeschlossen werden:
     * - <?php
     * - <?xml
     * - PHP-Operator ??
     * - URLs wie index.php?option=
     * - tern?re Operatoren
     */
    $lines = preg_split(
        '/\R/',
        $content
    );

    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $index => $lineText) {
        $lineNumber = $index + 1;

        $cleanLine = str_replace(
            [
                '<?php',
                '<?xml',
                '??',
                '?:',
                '?option=',
                '?Itemid=',
                '?task=',
                '?view=',
                '?format=',
                '?id=',
                '?controller=',
            ],
            '',
            $lineText
        );

        $matchCount = preg_match_all(
            '/(?<![A-Za-z0-9_.\/-])'
            . '[A-Za-z???????]{2,}'
            . '\?+'
            . '[A-Za-z???????]{1,}'
            . '(?![A-Za-z0-9_.\/-])/u',
            $cleanLine,
            $matches
        );

        if (
            !is_int($matchCount)
            || $matchCount < 1
        ) {
            continue;
        }

        foreach ($matches[0] as $match) {
            addIssue(
                $issues,
                $file,
                $lineNumber,
                'Verd?chtiges Wort',
                'M?glicherweise besch?digtes Wort: '
                . $match,
                trim($lineText)
            );
        }
    }
}

foreach ($scanRoots as $scanRoot) {
    $scanRoot = normalizePath(
        $scanRoot
    );

    if (is_file($scanRoot)) {
        scanFile(
            $scanRoot,
            $issues,
            $checkedFiles,
            $allowedExtensions
        );

        continue;
    }

    if (!is_dir($scanRoot)) {
        continue;
    }

    $directoryIterator =
        new RecursiveDirectoryIterator(
            $scanRoot,
            FilesystemIterator::SKIP_DOTS
        );

    $filterIterator =
        new RecursiveCallbackFilterIterator(
            $directoryIterator,
            static function (
                SplFileInfo $item
            ) use (
                $excludedDirectoryNames
            ): bool {
                if (!$item->isDir()) {
                    return true;
                }

                return !in_array(
                    $item->getFilename(),
                    $excludedDirectoryNames,
                    true
                );
            }
        );

    $iterator =
        new RecursiveIteratorIterator(
            $filterIterator
        );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        scanFile(
            $fileInfo->getPathname(),
            $issues,
            $checkedFiles,
            $allowedExtensions
        );
    }
}

usort(
    $issues,
    static function (
        array $left,
        array $right
    ): int {
        $fileComparison = strcmp(
            $left['file'],
            $right['file']
        );

        if ($fileComparison !== 0) {
            return $fileComparison;
        }

        return $left['line']
            <=> $right['line'];
    }
);

echo PHP_EOL;
echo "GPS-Portal UTF-8-Pr?fung" . PHP_EOL;
echo "========================" . PHP_EOL;
echo PHP_EOL;
echo "Gepr?fte Dateien: "
    . $checkedFiles
    . PHP_EOL;
echo "Gefundene Probleme: "
    . count($issues)
    . PHP_EOL;
echo PHP_EOL;

if ($issues === []) {
    echo "[OK] Keine UTF-8- oder Umlautprobleme gefunden."
        . PHP_EOL;

    exit(0);
}

foreach ($issues as $index => $issue) {
    echo '['
        . ($index + 1)
        . '] '
        . $issue['type']
        . PHP_EOL;

    echo 'Datei: '
        . $issue['file']
        . PHP_EOL;

    if ($issue['line'] > 0) {
        echo 'Zeile: '
            . $issue['line']
            . PHP_EOL;
    }

    echo 'Hinweis: '
        . $issue['details']
        . PHP_EOL;

    if ($issue['text'] !== '') {
        echo 'Text: '
            . $issue['text']
            . PHP_EOL;
    }

    echo PHP_EOL;
}

exit(1);
