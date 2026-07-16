<?php

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

use RuntimeException;
use ZipArchive;

class UpdateBackupValidator
{
    public function validate(
        string $filename
    ): array {
        $filename = basename(
            trim($filename)
        );

        if (
            preg_match(
                '/^gps-portal-backup-'
                . '\d{4}-\d{2}-\d{2}_'
                . '\d{2}-\d{2}-\d{2}'
                . '\.zip$/',
                $filename
            ) !== 1
        ) {
            throw new RuntimeException(
                'Der Backup-Dateiname ist ung?ltig.'
            );
        }

        $backupDirectory =
            JPATH_SITE
            . '/storage/updates/backups';

        $backupRoot =
            realpath($backupDirectory);

        $backupFile =
            realpath(
                $backupDirectory
                . DIRECTORY_SEPARATOR
                . $filename
            );

        if (
            $backupRoot === false
            || $backupFile === false
            || !is_file($backupFile)
            || !str_starts_with(
                $backupFile,
                $backupRoot
                . DIRECTORY_SEPARATOR
            )
        ) {
            throw new RuntimeException(
                'Das Backup wurde nicht gefunden.'
            );
        }

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'Die PHP-ZIP-Erweiterung ist nicht verf?gbar.'
            );
        }

        $sha256 = hash_file(
            'sha256',
            $backupFile
        );

        $size = filesize(
            $backupFile
        );

        if (
            !is_string($sha256)
            || $size === false
        ) {
            throw new RuntimeException(
                'Das Backup konnte nicht vollständig gelesen werden.'
            );
        }

        $zip = new ZipArchive();

        $openResult = $zip->open(
            $backupFile
        );

        if ($openResult !== true) {
            throw new RuntimeException(
                'Das Backup ist keine g?ltige ZIP-Datei.'
            );
        }

        try {
            $requiredEntries = [
                'backup-manifest.json',
                'gpsportal-database.sql',
            ];

            $missingEntries = [];

            foreach ($requiredEntries as $entry) {
                if (
                    $zip->locateName(
                        $entry,
                        ZipArchive::FL_NOCASE
                    ) === false
                ) {
                    $missingEntries[] = $entry;
                }
            }

            $componentFilesFound = false;
            $templateFilesFound = false;

            for (
                $index = 0;
                $index < $zip->numFiles;
                $index++
            ) {
                $entryName =
                    (string) $zip->getNameIndex(
                        $index
                    );

                $normalizedName =
                    str_replace(
                        '\\',
                        '/',
                        $entryName
                    );

                if (
                    str_starts_with(
                        $normalizedName,
                        'content/components/com_gpsportal/'
                    )
                ) {
                    $componentFilesFound = true;
                }

                if (
                    str_starts_with(
                        $normalizedName,
                        'content/templates/tkgpsportal/'
                    )
                ) {
                    $templateFilesFound = true;
                }
            }

            if (!$componentFilesFound) {
                $missingEntries[] =
                    'content/components/com_gpsportal/';
            }

            if (!$templateFilesFound) {
                $missingEntries[] =
                    'content/templates/tkgpsportal/';
            }

            $manifestData = [];

            $manifestIndex =
                $zip->locateName(
                    'backup-manifest.json',
                    ZipArchive::FL_NOCASE
                );

            if ($manifestIndex !== false) {
                $manifestJson =
                    $zip->getFromIndex(
                        $manifestIndex
                    );

                if (is_string($manifestJson)) {
                    try {
                        $decodedManifest =
                            json_decode(
                                $manifestJson,
                                true,
                                512,
                                JSON_THROW_ON_ERROR
                            );

                        if (
                            is_array(
                                $decodedManifest
                            )
                        ) {
                            $manifestData =
                                $decodedManifest;
                        }
                    } catch (\JsonException) {
                        $missingEntries[] =
                            'g?ltiges backup-manifest.json';
                    }
                }
            }

            $valid =
                empty($missingEntries);

            return [
                'success' => true,
                'valid' => $valid,
                'filename' => $filename,
                'size_bytes' => (int) $size,
                'sha256' => strtolower($sha256),
                'entries' => $zip->numFiles,
                'missing_entries' => $missingEntries,
                'manifest' => $manifestData,
                'checked_at' => date(
                    'Y-m-d H:i:s'
                ),
            ];
        } finally {
            $zip->close();
        }
    }
}