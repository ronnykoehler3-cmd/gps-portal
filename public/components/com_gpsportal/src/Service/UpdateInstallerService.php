<?php

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

use RuntimeException;
use Throwable;
use ZipArchive;

class UpdateInstallerService
{
    private UpdateBackupService $backupService;

    public function __construct()
    {
        $this->backupService =
            new UpdateBackupService();
    }

    public function installFromLocalManifest(
        string $manifestFile,
        array $installedVersion
    ): array {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'Die PHP-ZIP-Erweiterung ist nicht verfügbar.'
            );
        }

        $updateService =
            new UpdateService(
                $installedVersion
            );

        $checkResult =
            $updateService->checkLocalManifest(
                $manifestFile
            );

        if (empty($checkResult['success'])) {
            throw new RuntimeException(
                (string) (
                    $checkResult['error']
                    ?? 'Das Updatepaket konnte nicht geprüft werden.'
                )
            );
        }

        if (empty($checkResult['package_valid'])) {
            throw new RuntimeException(
                'Das Updatepaket ist nicht vollständig gültig.'
            );
        }

        if (empty($checkResult['update_available'])) {
            throw new RuntimeException(
                'Es ist kein neueres Update verfügbar.'
            );
        }

        $packageFile =
            (string) (
                $checkResult['package_file']
                ?? ''
            );

        if (
            $packageFile === ''
            || !is_file($packageFile)
        ) {
            throw new RuntimeException(
                'Das lokale Updatepaket wurde nicht gefunden.'
            );
        }

        /*
         * Vor jeder Installation zwingend ein Backup.
         */
        $backupResult =
            $this->backupService
                ->createBackup();

        if (empty($backupResult['success'])) {
            throw new RuntimeException(
                'Das Sicherheitsbackup konnte nicht erstellt werden.'
            );
        }

        $timestamp =
            date('Y-m-d_H-i-s');

        $temporaryRoot =
            JPATH_SITE
            . '/storage/updates/temporary/install-'
            . $timestamp;

        $extractDirectory =
            $temporaryRoot
            . '/extracted';

        $this->createDirectory(
            $extractDirectory
        );

        $copiedFiles = [];
        $detectedMigrations = [];

        try {
            $this->extractPackage(
                $packageFile,
                $extractDirectory
            );

            $packageInformation =
                $this->readPackageInformation(
                    $extractDirectory
                    . '/package.json'
                );

            $expectedProject =
                trim(
                    (string) (
                        $installedVersion['project']
                        ?? 'GPS-Portal'
                    )
                );

            $packageProject =
                trim(
                    (string) (
                        $packageInformation['project']
                        ?? ''
                    )
                );

            if (
                $packageProject === ''
                || strcasecmp(
                    $packageProject,
                    $expectedProject
                ) !== 0
            ) {
                throw new RuntimeException(
                    'Das Updatepaket gehört nicht zum GPS-Portal.'
                );
            }

            $packageVersion =
                trim(
                    (string) (
                        $packageInformation['version']
                        ?? ''
                    )
                );

            if (
                $packageVersion === ''
                || $packageVersion !==
                    (string) (
                        $checkResult['available_version']
                        ?? ''
                    )
            ) {
                throw new RuntimeException(
                    'Die Version in package.json stimmt nicht mit dem Manifest überein.'
                );
            }

            $filesDirectory =
                $extractDirectory
                . '/files';

            if (!is_dir($filesDirectory)) {
                throw new RuntimeException(
                    'Im Updatepaket fehlt der Ordner files.'
                );
            }

            /*
             * Der Inhalt von files/ wird relativ zum
             * Komponentenordner installiert.
             *
             * files/version.php
             * wird damit zu
             * components/com_gpsportal/version.php
             */
            $componentDirectory =
                JPATH_SITE
                . '/components/com_gpsportal';

            if (!is_dir($componentDirectory)) {
                throw new RuntimeException(
                    'Der GPS-Portal-Komponentenordner wurde nicht gefunden.'
                );
            }

            $copiedFiles =
                $this->copyUpdateFiles(
                    $filesDirectory,
                    $componentDirectory
                );

            $migrationDirectory =
                $extractDirectory
                . '/migrations';

            if (is_dir($migrationDirectory)) {
                $migrationFiles = glob(
                    $migrationDirectory
                    . '/*.sql'
                );

                if (is_array($migrationFiles)) {
                    foreach ($migrationFiles as $migrationFile) {
                        if (is_file($migrationFile)) {
                            $detectedMigrations[] =
                                basename($migrationFile);
                        }
                    }
                }
            }

            $installedVersionFile =
                $componentDirectory
                . '/version.php';

            if (!is_file($installedVersionFile)) {
                throw new RuntimeException(
                    'Die neue Versionsdatei wurde nicht installiert.'
                );
            }

            /*
             * Dateistatus und OPcache leeren, damit die neu installierte
             * version.php sofort und nicht aus dem Cache gelesen wird.
             */
            clearstatcache(
                true,
                $installedVersionFile
            );

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate(
                    $installedVersionFile,
                    true
                );
            }

            $newVersionInformation =
                require $installedVersionFile;

            if (
                !is_array($newVersionInformation)
                || (
                    (string) (
                        $newVersionInformation['version']
                        ?? ''
                    )
                ) !== $packageVersion
            ) {
                throw new RuntimeException(
                    'Die installierte Versionsdatei enthält nicht die erwartete Version.'
                );
            }

            return [
                'success' => true,

                'installed_version' =>
                    $packageVersion,

                'installed_build' =>
                    (string) (
                        $newVersionInformation['build']
                        ?? (
                            $packageInformation['build']
                            ?? ''
                        )
                    ),

                'backup_filename' =>
                    (string) (
                        $backupResult['filename']
                        ?? ''
                    ),

                'backup_sha256' =>
                    (string) (
                        $backupResult['sha256']
                        ?? ''
                    ),

                'copied_files' =>
                    $copiedFiles,

                'copied_file_count' =>
                    count($copiedFiles),

                'detected_migrations' =>
                    $detectedMigrations,

                'migration_count' =>
                    count($detectedMigrations),

                'installed_at' =>
                    date('Y-m-d H:i:s'),

                'note' =>
                    empty($detectedMigrations)
                        ? 'Es waren keine Datenbankmigrationen enthalten.'
                        : 'Enthaltene Datenbankmigrationen wurden im ersten Testlauf bewusst noch nicht automatisch ausgeführt.',
            ];
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Updateinstallation abgebrochen: '
                . $exception->getMessage(),
                0,
                $exception
            );
        } finally {
            $this->deleteDirectory(
                $temporaryRoot
            );
        }
    }

    private function extractPackage(
        string $packageFile,
        string $targetDirectory
    ): void {
        $zip = new ZipArchive();

        $openResult =
            $zip->open(
                $packageFile
            );

        if ($openResult !== true) {
            throw new RuntimeException(
                'Das Update-ZIP konnte nicht geöffnet werden.'
            );
        }

        try {
            /*
             * Schutz gegen unsichere Pfade im ZIP.
             */
            for (
                $index = 0;
                $index < $zip->numFiles;
                $index++
            ) {
                $entryName =
                    (string) $zip->getNameIndex(
                        $index
                    );

                $normalizedEntry =
                    str_replace(
                        '\\',
                        '/',
                        $entryName
                    );

                if (
                    $normalizedEntry === ''
                    || str_starts_with(
                        $normalizedEntry,
                        '/'
                    )
                    || preg_match(
                        '/^[A-Za-z]:\//',
                        $normalizedEntry
                    ) === 1
                    || str_contains(
                        $normalizedEntry,
                        '../'
                    )
                ) {
                    throw new RuntimeException(
                        'Das Updatepaket enthält einen unsicheren Dateipfad: '
                        . $entryName
                    );
                }
            }

            if (
                !$zip->extractTo(
                    $targetDirectory
                )
            ) {
                throw new RuntimeException(
                    'Das Updatepaket konnte nicht entpackt werden.'
                );
            }
        } finally {
            $zip->close();
        }
    }

    private function readPackageInformation(
        string $packageJsonFile
    ): array {
        if (!is_file($packageJsonFile)) {
            throw new RuntimeException(
                'Im Updatepaket fehlt package.json.'
            );
        }

        $json =
            file_get_contents(
                $packageJsonFile
            );

        if ($json === false) {
            throw new RuntimeException(
                'package.json konnte nicht gelesen werden.'
            );
        }

        try {
            $packageInformation =
                json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                'package.json enthält ungültiges JSON.',
                0,
                $exception
            );
        }

        if (!is_array($packageInformation)) {
            throw new RuntimeException(
                'package.json besitzt ein ungültiges Format.'
            );
        }

        return $packageInformation;
    }

    private function copyUpdateFiles(
        string $sourceDirectory,
        string $targetDirectory
    ): array {
        $copiedFiles = [];

        $sourceDirectory =
            rtrim(
                $sourceDirectory,
                '/\\'
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
            $relativePath =
                substr(
                    $item->getPathname(),
                    strlen($sourceDirectory) + 1
                );

            if (
                $relativePath === false
                || $relativePath === ''
            ) {
                continue;
            }

            $normalizedRelativePath =
                str_replace(
                    '\\',
                    '/',
                    $relativePath
                );

            if (
                str_starts_with(
                    $normalizedRelativePath,
                    '/'
                )
                || str_contains(
                    $normalizedRelativePath,
                    '../'
                )
            ) {
                throw new RuntimeException(
                    'Ungültiger Installationspfad im Updatepaket.'
                );
            }

            $destination =
                $targetDirectory
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $normalizedRelativePath
                );

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
                    'Updatedatei konnte nicht installiert werden: '
                    . $normalizedRelativePath
                );
            }

            $copiedFiles[] =
                $normalizedRelativePath;
        }

        if (empty($copiedFiles)) {
            throw new RuntimeException(
                'Das Updatepaket enthält keine installierbaren Dateien.'
            );
        }

        return $copiedFiles;
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