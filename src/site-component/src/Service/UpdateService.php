<?php

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

use ZipArchive;

class UpdateService
{
    private array $installedVersion;

    public function __construct(
        array $installedVersion
    ) {
        $this->installedVersion =
            $installedVersion;
    }

    public function checkLocalManifest(
        string $manifestFile
    ): array {
        $result = [
            'success' => false,
            'update_available' => false,
            'installed_version' => (string) (
                $this->installedVersion['version']
                ?? '0.0.0'
            ),
            'available_version' => '',
            'channel' => '',
            'released_at' => '',
            'package' => '',
            'package_file' => '',
            'sha256' => '',
            'actual_sha256' => '',
            'package_exists' => false,
            'checksum_valid' => false,
            'zip_valid' => false,
            'package_valid' => false,
            'changelog' => [],
            'error' => '',
            'checked_at' => date(
                'Y-m-d H:i:s'
            ),
        ];

        if (!is_file($manifestFile)) {
            $result['error'] =
                'Das lokale Update-Manifest wurde nicht gefunden.';

            return $result;
        }

        $json = file_get_contents(
            $manifestFile
        );

        if ($json === false) {
            $result['error'] =
                'Das Update-Manifest konnte nicht gelesen werden.';

            return $result;
        }

        try {
            $manifest = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            $result['error'] =
                'Das Update-Manifest enthaelt ungueltiges JSON.';

            return $result;
        }

        if (!is_array($manifest)) {
            $result['error'] =
                'Das Update-Manifest besitzt ein ungueltiges Format.';

            return $result;
        }

        $project = trim(
            (string) (
                $manifest['project']
                ?? ''
            )
        );

        $expectedProject = trim(
            (string) (
                $this->installedVersion['project']
                ?? 'GPS-Portal'
            )
        );

        if (
            $project === ''
            || strcasecmp(
                $project,
                $expectedProject
            ) !== 0
        ) {
            $result['error'] =
                'Das Manifest gehoert nicht zu diesem Projekt.';

            return $result;
        }

        $availableVersion = trim(
            (string) (
                $manifest['version']
                ?? ''
            )
        );

        if ($availableVersion === '') {
            $result['error'] =
                'Im Manifest fehlt die Versionsnummer.';

            return $result;
        }

        $package = trim(
            (string) (
                $manifest['package']
                ?? ''
            )
        );

        $expectedSha256 = strtolower(
            trim(
                (string) (
                    $manifest['sha256']
                    ?? ''
                )
            )
        );

        $result['available_version'] =
            $availableVersion;

        $result['channel'] = trim(
            (string) (
                $manifest['channel']
                ?? ''
            )
        );

        $result['released_at'] = trim(
            (string) (
                $manifest['released_at']
                ?? ''
            )
        );

        $result['package'] = $package;
        $result['sha256'] = $expectedSha256;

        $result['changelog'] =
            is_array(
                $manifest['changelog']
                ?? null
            )
                ? array_values(
                    array_filter(
                        array_map(
                            static function (
                                mixed $entry
                            ): string {
                                return trim(
                                    (string) $entry
                                );
                            },
                            $manifest['changelog']
                        )
                    )
                )
                : [];

        $result['update_available'] =
            version_compare(
                $availableVersion,
                $result['installed_version'],
                '>'
            );

        if ($package === '') {
            $result['error'] =
                'Im Manifest fehlt der Paketpfad.';

            return $result;
        }

        if (
            !preg_match(
                '/^[a-f0-9]{64}$/',
                $expectedSha256
            )
        ) {
            $result['error'] =
                'Im Manifest fehlt eine gueltige SHA-256-Pruefsumme.';

            return $result;
        }

        $manifestDirectory = dirname(
            $manifestFile
        );

        $packageFile =
            $manifestDirectory
            . DIRECTORY_SEPARATOR
            . str_replace(
                [
                    '/',
                    '\\'
                ],
                DIRECTORY_SEPARATOR,
                $package
            );

        $realManifestDirectory = realpath(
            $manifestDirectory
        );

        $realPackageFile = realpath(
            $packageFile
        );

        if (
            $realManifestDirectory === false
            || $realPackageFile === false
            || !str_starts_with(
                $realPackageFile,
                $realManifestDirectory
                . DIRECTORY_SEPARATOR
            )
        ) {
            $result['error'] =
                'Der Paketpfad ist ungueltig oder verlaesst den Updateordner.';

            return $result;
        }

        $result['package_file'] =
            $realPackageFile;

        $result['package_exists'] =
            is_file($realPackageFile);

        if (!$result['package_exists']) {
            $result['error'] =
                'Das Updatepaket wurde nicht gefunden.';

            return $result;
        }

        $actualSha256 = hash_file(
            'sha256',
            $realPackageFile
        );

        if (!is_string($actualSha256)) {
            $result['error'] =
                'Die Paketpruefsumme konnte nicht berechnet werden.';

            return $result;
        }

        $actualSha256 = strtolower(
            $actualSha256
        );

        $result['actual_sha256'] =
            $actualSha256;

        $result['checksum_valid'] =
            hash_equals(
                $expectedSha256,
                $actualSha256
            );

        if (!$result['checksum_valid']) {
            $result['error'] =
                'Die SHA-256-Pruefsumme des Updatepakets stimmt nicht.';

            return $result;
        }

        if (!class_exists(ZipArchive::class)) {
            $result['error'] =
                'Die PHP-ZIP-Erweiterung ist nicht verfuegbar.';

            return $result;
        }

        $zip = new ZipArchive();

        $zipResult = $zip->open(
            $realPackageFile
        );

        if ($zipResult !== true) {
            $result['error'] =
                'Das Updatepaket ist keine gueltige ZIP-Datei.';

            return $result;
        }

        $requiredFiles = [
            'package.json',
            'files/version.php'
        ];

        $zipValid = true;

        foreach ($requiredFiles as $requiredFile) {
            if (
                $zip->locateName(
                    $requiredFile,
                    ZipArchive::FL_NOCASE
                ) === false
            ) {
                $zipValid = false;
                break;
            }
        }

        $zip->close();

        $result['zip_valid'] =
            $zipValid;

        if (!$zipValid) {
            $result['error'] =
                'Im Updatepaket fehlen erforderliche Dateien.';

            return $result;
        }

        $result['package_valid'] = true;
        $result['success'] = true;

        return $result;
    }
}