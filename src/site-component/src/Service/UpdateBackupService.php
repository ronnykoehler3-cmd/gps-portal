<?php

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use RuntimeException;
use Throwable;
use ZipArchive;

class UpdateBackupService
{
    private object $database;

    public function __construct()
    {
        $this->database = Factory::getContainer()
            ->get('DatabaseDriver');
    }

    public function createBackup(): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'Die PHP-ZIP-Erweiterung ist nicht verfuegbar.'
            );
        }

        $timestamp = date('Y-m-d_H-i-s');

        $storageRoot =
            JPATH_SITE
            . '/storage/updates';

        $backupDirectory =
            $storageRoot
            . '/backups';

        $temporaryDirectory =
            $storageRoot
            . '/temporary/backup-'
            . $timestamp;

        $archiveFile =
            $backupDirectory
            . '/gps-portal-backup-'
            . $timestamp
            . '.zip';

        $this->createDirectory(
            $backupDirectory
        );

        $this->createDirectory(
            $temporaryDirectory
        );

        try {
            $contentDirectory =
                $temporaryDirectory
                . '/content';

            $this->createDirectory(
                $contentDirectory
            );

            $copiedPaths = [];

            $sources = [
                JPATH_SITE
                    . '/components/com_gpsportal'
                    => 'components/com_gpsportal',

                JPATH_SITE
                    . '/templates/tkgpsportal'
                    => 'templates/tkgpsportal',
            ];

            foreach ($sources as $source => $relativeTarget) {
                if (!is_dir($source)) {
                    continue;
                }

                $target =
                    $contentDirectory
                    . '/'
                    . $relativeTarget;

                $this->copyDirectory(
                    $source,
                    $target
                );

                $copiedPaths[] =
                    $relativeTarget;
            }

            $databaseFile =
                $temporaryDirectory
                . '/gpsportal-database.sql';

            $tableCount =
                $this->createDatabaseDump(
                    $databaseFile
                );

            $versionInfo =
                $this->readVersionInformation();

            $backupManifest = [
                'project' => 'GPS-Portal',

                'created_at' =>
                    date(DATE_ATOM),

                'version' =>
                    (string) (
                        $versionInfo['version']
                        ?? 'unknown'
                    ),

                'build' =>
                    (string) (
                        $versionInfo['build']
                        ?? 'unknown'
                    ),

                'channel' =>
                    (string) (
                        $versionInfo['channel']
                        ?? 'unknown'
                    ),

                'php_version' =>
                    PHP_VERSION,

                'joomla_version' =>
                    defined('JVERSION')
                        ? JVERSION
                        : 'unknown',

                'database_tables' =>
                    $tableCount,

                'copied_paths' =>
                    $copiedPaths,
            ];

            $manifestJson = json_encode(
                $backupManifest,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );

            file_put_contents(
                $temporaryDirectory
                . '/backup-manifest.json',
                $manifestJson
            );

            $this->createZipArchive(
                $temporaryDirectory,
                $archiveFile
            );

            if (!is_file($archiveFile)) {
                throw new RuntimeException(
                    'Die Backupdatei wurde nicht erzeugt.'
                );
            }

            $archiveSize =
                filesize($archiveFile);

            $archiveHash =
                hash_file(
                    'sha256',
                    $archiveFile
                );

            if (
                $archiveSize === false
                || !is_string($archiveHash)
            ) {
                throw new RuntimeException(
                    'Das Backup konnte nicht vollstaendig geprueft werden.'
                );
            }

            return [
                'success' => true,

                'filename' =>
                    basename($archiveFile),

                'path' =>
                    $archiveFile,

                'size_bytes' =>
                    (int) $archiveSize,

                'sha256' =>
                    strtolower($archiveHash),

                'table_count' =>
                    $tableCount,

                'created_at' =>
                    date('Y-m-d H:i:s'),
            ];
        } catch (Throwable $exception) {
            if (is_file($archiveFile)) {
                @unlink($archiveFile);
            }

            throw $exception;
        } finally {
            $this->deleteDirectory(
                $temporaryDirectory
            );
        }
    }

    private function readVersionInformation(): array
    {
        $versionFile =
            JPATH_SITE
            . '/components/com_gpsportal/version.php';

        if (!is_file($versionFile)) {
            return [];
        }

        $versionInfo =
            require $versionFile;

        return is_array($versionInfo)
            ? $versionInfo
            : [];
    }

    private function createDatabaseDump(
        string $targetFile
    ): int {
        $prefix =
            (string) $this->database
                ->getPrefix();

        $pattern =
            $prefix
            . 'gpsportal_%';

        $query =
            'SHOW TABLES LIKE '
            . $this->database->quote(
                $pattern
            );

        $this->database->setQuery(
            $query
        );

        $tables =
            $this->database->loadColumn();

        if (!is_array($tables)) {
            $tables = [];
        }

        $handle = fopen(
            $targetFile,
            'wb'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'Die SQL-Sicherungsdatei konnte nicht erstellt werden.'
            );
        }

        try {
            fwrite(
                $handle,
                "-- GPS-Portal Datenbanksicherung\n"
            );

            fwrite(
                $handle,
                '-- Erstellt: '
                . date(DATE_ATOM)
                . "\n\n"
            );

            fwrite(
                $handle,
                "SET FOREIGN_KEY_CHECKS=0;\n\n"
            );

            foreach ($tables as $table) {
                $tableName =
                    (string) $table;

                $quotedTable =
                    $this->database
                        ->quoteName(
                            $tableName
                        );

                $this->database->setQuery(
                    'SHOW CREATE TABLE '
                    . $quotedTable
                );

                $createResult =
                    $this->database
                        ->loadAssoc();

                $createStatement = '';

                if (is_array($createResult)) {
                    foreach (
                        $createResult
                        as $column => $value
                    ) {
                        if (
                            stripos(
                                (string) $column,
                                'create table'
                            ) !== false
                        ) {
                            $createStatement =
                                (string) $value;

                            break;
                        }
                    }
                }

                fwrite(
                    $handle,
                    '-- Tabelle: '
                    . $tableName
                    . "\n"
                );

                fwrite(
                    $handle,
                    'DROP TABLE IF EXISTS '
                    . $quotedTable
                    . ";\n"
                );

                if ($createStatement !== '') {
                    fwrite(
                        $handle,
                        $createStatement
                        . ";\n\n"
                    );
                }

                $this->database->setQuery(
                    'SELECT * FROM '
                    . $quotedTable
                );

                $rows =
                    $this->database
                        ->loadAssocList();

                if (!is_array($rows)) {
                    $rows = [];
                }

                foreach ($rows as $row) {
                    $columns = [];
                    $values = [];

                    foreach (
                        $row
                        as $column => $value
                    ) {
                        $columns[] =
                            $this->database
                                ->quoteName(
                                    (string) $column
                                );

                        $values[] =
                            $value === null
                                ? 'NULL'
                                : $this->database
                                    ->quote(
                                        (string) $value
                                    );
                    }

                    fwrite(
                        $handle,
                        'INSERT INTO '
                        . $quotedTable
                        . ' ('
                        . implode(
                            ', ',
                            $columns
                        )
                        . ') VALUES ('
                        . implode(
                            ', ',
                            $values
                        )
                        . ");\n"
                    );
                }

                fwrite(
                    $handle,
                    "\n"
                );
            }

            fwrite(
                $handle,
                "SET FOREIGN_KEY_CHECKS=1;\n"
            );
        } finally {
            fclose($handle);
        }

        return count($tables);
    }

    private function copyDirectory(
        string $source,
        string $target
    ): void {
        $this->createDirectory(
            $target
        );

        $iterator =
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $source,
                    \FilesystemIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::SELF_FIRST
            );

        foreach ($iterator as $item) {
            $relativePath =
                substr(
                    $item->getPathname(),
                    strlen($source) + 1
                );

            if (
                $relativePath === false
                || $relativePath === ''
            ) {
                continue;
            }

            $destination =
                $target
                . DIRECTORY_SEPARATOR
                . $relativePath;

            if ($item->isDir()) {
                $this->createDirectory(
                    $destination
                );

                continue;
            }

            $this->createDirectory(
                dirname($destination)
            );

            if (
                !copy(
                    $item->getPathname(),
                    $destination
                )
            ) {
                throw new RuntimeException(
                    'Datei konnte nicht gesichert werden: '
                    . $item->getPathname()
                );
            }
        }
    }

    private function createZipArchive(
        string $sourceDirectory,
        string $archiveFile
    ): void {
        $zip = new ZipArchive();

        $openResult = $zip->open(
            $archiveFile,
            ZipArchive::CREATE
            | ZipArchive::OVERWRITE
        );

        if ($openResult !== true) {
            throw new RuntimeException(
                'Das Backup-ZIP konnte nicht erstellt werden.'
            );
        }

        $normalizedSource =
            rtrim(
                str_replace(
                    '\\',
                    '/',
                    $sourceDirectory
                ),
                '/'
            );

        $iterator =
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $sourceDirectory,
                    \FilesystemIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::SELF_FIRST
            );

        foreach ($iterator as $item) {
            $absolutePath =
                str_replace(
                    '\\',
                    '/',
                    $item->getPathname()
                );

            $relativePath =
                ltrim(
                    substr(
                        $absolutePath,
                        strlen($normalizedSource)
                    ),
                    '/'
                );

            if ($relativePath === '') {
                continue;
            }

            if ($item->isDir()) {
                $zip->addEmptyDir(
                    $relativePath
                );
            } else {
                if (
                    !$zip->addFile(
                        $item->getPathname(),
                        $relativePath
                    )
                ) {
                    $zip->close();

                    throw new RuntimeException(
                        'Eine Datei konnte nicht in das Backup-ZIP aufgenommen werden.'
                    );
                }
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException(
                'Das Backup-ZIP konnte nicht abgeschlossen werden.'
            );
        }
    }

    private function createDirectory(
        string $directory
    ): void {
        if (is_dir($directory)) {
            return;
        }

        if (
            !mkdir(
                $directory,
                0775,
                true
            )
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Ordner konnte nicht erstellt werden: '
                . $directory
            );
        }
    }

    private function deleteDirectory(
        string $directory
    ): void {
        if (!is_dir($directory)) {
            return;
        }

        $iterator =
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $directory,
                    \FilesystemIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir(
                    $item->getPathname()
                );
            } else {
                @unlink(
                    $item->getPathname()
                );
            }
        }

        @rmdir($directory);
    }
}