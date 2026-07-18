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

    public function checkRemoteManifest(
        string $manifestUrl,
        string $downloadDirectory
    ): array {
        try {
            if (filter_var($manifestUrl, FILTER_VALIDATE_URL) === false) {
                throw new \RuntimeException('Die Adresse des Updateservers ist ungültig.');
            }

            if (!is_dir($downloadDirectory) && !mkdir($downloadDirectory, 0750, true) && !is_dir($downloadDirectory)) {
                throw new \RuntimeException('Der lokale Updateordner konnte nicht erstellt werden.');
            }

            if (!is_writable($downloadDirectory)) {
                throw new \RuntimeException('Der lokale Updateordner ist nicht beschreibbar.');
            }

            $remoteJson = $this->downloadText($manifestUrl);
            $remoteJson = $this->removeUtf8Bom($remoteJson);

            try {
                $remoteManifest = json_decode($remoteJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new \RuntimeException('Der Updateserver liefert ungültiges JSON.', 0, $exception);
            }

            if (!is_array($remoteManifest)) {
                throw new \RuntimeException('Das Server-Manifest besitzt ein ungültiges Format.');
            }

            $version = trim((string) ($remoteManifest['version'] ?? $remoteManifest['latest'] ?? ''));
            $packageReference = trim((string) ($remoteManifest['package'] ?? $remoteManifest['download'] ?? ''));
            $checksumReference = trim((string) ($remoteManifest['checksum'] ?? ''));
            $sha256 = strtolower(trim((string) ($remoteManifest['sha256'] ?? '')));

            if ($version === '') {
                throw new \RuntimeException('Im Server-Manifest fehlt die Versionsnummer.');
            }

            if ($packageReference === '') {
                throw new \RuntimeException('Im Server-Manifest fehlt das Updatepaket.');
            }

            $packageUrl = $this->resolveUrl($manifestUrl, $packageReference);

            if ($sha256 === '' && $checksumReference !== '') {
                $checksumUrl = $this->resolveUrl($manifestUrl, $checksumReference);
                $checksumText = trim($this->removeUtf8Bom($this->downloadText($checksumUrl)));
                if (preg_match('/\b([a-fA-F0-9]{64})\b/', $checksumText, $match) === 1) {
                    $sha256 = strtolower($match[1]);
                }
            }

            if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
                throw new \RuntimeException('Der Updateserver liefert keine gültige SHA-256-Prüfsumme.');
            }

            $packageName = basename((string) parse_url($packageUrl, PHP_URL_PATH));
            if ($packageName === '' || strtolower(pathinfo($packageName, PATHINFO_EXTENSION)) !== 'zip') {
                throw new \RuntimeException('Der Dateiname des Updatepakets ist ungültig.');
            }

            $temporaryPackage = $downloadDirectory . DIRECTORY_SEPARATOR . $packageName . '.download';
            $packageFile = $downloadDirectory . DIRECTORY_SEPARATOR . $packageName;
            $this->downloadFile($packageUrl, $temporaryPackage);

            $actualSha256 = strtolower((string) hash_file('sha256', $temporaryPackage));
            if (!hash_equals($sha256, $actualSha256)) {
                @unlink($temporaryPackage);
                throw new \RuntimeException('Die SHA-256-Prüfsumme des heruntergeladenen Pakets stimmt nicht.');
            }

            if (is_file($packageFile) && !unlink($packageFile)) {
                @unlink($temporaryPackage);
                throw new \RuntimeException('Ein altes Updatepaket konnte nicht ersetzt werden.');
            }

            if (!rename($temporaryPackage, $packageFile)) {
                @unlink($temporaryPackage);
                throw new \RuntimeException('Das Updatepaket konnte nicht gespeichert werden.');
            }

            $changelog = $remoteManifest['changelog'] ?? [];
            if (!is_array($changelog)) {
                $description = trim((string) ($remoteManifest['description'] ?? ''));
                $changelog = $description !== '' ? [$description] : [];
            }

            $localManifest = [
                'project' => (string) ($this->installedVersion['project'] ?? 'GPS-Portal'),
                'version' => $version,
                'channel' => trim((string) ($remoteManifest['channel'] ?? 'stable')),
                'released_at' => trim((string) ($remoteManifest['released_at'] ?? $remoteManifest['releaseDate'] ?? '')),
                'package' => $packageName,
                'sha256' => $sha256,
                'changelog' => array_values($changelog),
            ];

            $localManifestFile = $downloadDirectory . DIRECTORY_SEPARATOR . 'manifest.json';
            $encodedManifest = json_encode(
                $localManifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            if (file_put_contents($localManifestFile, $encodedManifest . PHP_EOL, LOCK_EX) === false) {
                throw new \RuntimeException('Das lokale Update-Manifest konnte nicht gespeichert werden.');
            }

            return $this->checkLocalManifest($localManifestFile);
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'update_available' => false,
                'installed_version' => (string) ($this->installedVersion['version'] ?? '0.0.0'),
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
                'error' => $exception->getMessage(),
                'checked_at' => date('Y-m-d H:i:s'),
            ];
        }
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

        $json = $this->removeUtf8Bom($json);

        try {
            $manifest = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            $result['error'] =
                'Das Update-Manifest enthält ungültiges JSON.';

            return $result;
        }

        if (!is_array($manifest)) {
            $result['error'] =
                'Das Update-Manifest besitzt ein ungültiges Format.';

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
                'Das Manifest gehört nicht zu diesem Projekt.';

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
                'Im Manifest fehlt eine gültige SHA-256-Prüfsumme.';

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
                'Der Paketpfad ist ungültig oder verlässt den Updateordner.';

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
                'Die Paketprüfsumme konnte nicht berechnet werden.';

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
                'Die SHA-256-Prüfsumme des Updatepakets stimmt nicht.';

            return $result;
        }

        if (!class_exists(ZipArchive::class)) {
            $result['error'] =
                'Die PHP-ZIP-Erweiterung ist nicht verfügbar.';

            return $result;
        }

        $zip = new ZipArchive();

        $zipResult = $zip->open(
            $realPackageFile
        );

        if ($zipResult !== true) {
            $result['error'] =
                'Das Updatepaket ist keine gültige ZIP-Datei.';

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

    private function removeUtf8Bom(string $content): string
    {
        return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }

    private function resolveUrl(string $baseUrl, string $reference): string
    {
        if (filter_var($reference, FILTER_VALIDATE_URL) !== false) {
            return $reference;
        }

        $parts = parse_url($baseUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \RuntimeException('Die Basisadresse des Updateservers ist ungültig.');
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $directory = rtrim(str_replace('\\', '/', dirname((string) ($parts['path'] ?? '/'))), '/');
        $path = str_starts_with($reference, '/') ? $reference : $directory . '/' . ltrim($reference, '/');

        return $parts['scheme'] . '://' . $parts['host'] . $port . $path;
    }

    private function downloadText(string $url): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'gps-update-');
        if ($temporaryFile === false) {
            throw new \RuntimeException('Eine temporäre Datei konnte nicht erstellt werden.');
        }

        try {
            $this->downloadFile($url, $temporaryFile);
            $content = file_get_contents($temporaryFile);
            if ($content === false) {
                throw new \RuntimeException('Die heruntergeladene Datei konnte nicht gelesen werden.');
            }
            return $content;
        } finally {
            @unlink($temporaryFile);
        }
    }

    private function downloadFile(string $url, string $targetFile): void
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Die PHP-cURL-Erweiterung ist nicht verfügbar.');
        }

        $handle = fopen($targetFile, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Die lokale Zieldatei konnte nicht geöffnet werden.');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            fclose($handle);
            throw new \RuntimeException('Der HTTP-Download konnte nicht vorbereitet werden.');
        }

        curl_setopt_array($curl, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_FAILONERROR => true,
            CURLOPT_USERAGENT => 'TK-Kundendienst GPS-Portal Updater/1.0',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);

        $success = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        fclose($handle);

        if ($success !== true || $statusCode < 200 || $statusCode >= 300) {
            @unlink($targetFile);
            throw new \RuntimeException(
                'Der Updateserver konnte nicht abgerufen werden (HTTP ' . $statusCode . ')' . ($error !== '' ? ': ' . $error : '.')
            );
        }
    }
}
