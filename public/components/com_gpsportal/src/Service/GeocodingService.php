<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

final class GeocodingService
{
    private const SEARCH_URL = 'https://nominatim.openstreetmap.org/search';
    private const MINIMUM_REQUEST_INTERVAL_SECONDS = 1.1;
    private static float $lastRequestTime = 0.0;

    public function resolve(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            throw new \RuntimeException('Eine Adresse fehlt.');
        }

        $url = self::SEARCH_URL . '?' . http_build_query([
            'q' => $address,
            'format' => 'jsonv2',
            'limit' => 1,
        ]);

        $lastError = '';

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->waitForRateLimit();
            $curl = curl_init($url);

            if ($curl === false) {
                throw new \RuntimeException('Die Adresssuche konnte nicht gestartet werden.');
            }

            $curlOptions = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Accept-Language: de',
                    'User-Agent: TK-Kundendienst-GPS-Portal/1.2.4 (mail@tk-kundendienst.de)',
                ],
            ];

            $windowsCaFile = 'E:/Programme/PHP/certs/cacert.pem';

            if (PHP_OS_FAMILY === 'Windows' && is_file($windowsCaFile)) {
                $curlOptions[CURLOPT_CAINFO] = $windowsCaFile;
            }

            curl_setopt_array($curl, $curlOptions);

            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            self::$lastRequestTime = microtime(true);

            if (is_string($response) && $status === 200) {
                $results = json_decode($response, true);
                $result = is_array($results) ? ($results[0] ?? null) : null;

                if (!is_array($result) || !isset($result['lat'], $result['lon'])) {
                    throw new \RuntimeException('Adresse wurde nicht gefunden: ' . $address);
                }

                return [
                    'address' => (string) ($result['display_name'] ?? $address),
                    'latitude' => (float) $result['lat'],
                    'longitude' => (float) $result['lon'],
                ];
            }

            $lastError = $curlError !== ''
                ? $curlError
                : 'HTTP-Status ' . $status;

            if (!in_array($status, [429, 503], true)) {
                break;
            }

            sleep($attempt + 1);
        }

        throw new \RuntimeException(
            'Adressdienst fehlgeschlagen für „' . $address . '“: ' . $lastError
        );
    }

    public function resolveCountry(string $countryCode, string $countryName): array
    {
        $countryCode = strtolower(trim($countryCode));
        $countryName = trim($countryName);

        if (!preg_match('/^[a-z]{2}$/', $countryCode) || $countryName === '') {
            throw new \RuntimeException('Das ausgewählte Land ist ungültig.');
        }

        $url = self::SEARCH_URL . '?' . http_build_query([
            'country' => $countryName,
            'countrycodes' => $countryCode,
            'format' => 'jsonv2',
            'polygon_geojson' => 1,
            'limit' => 1,
        ]);

        $this->waitForRateLimit();
        $curl = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: de',
                'User-Agent: TK-Kundendienst-GPS-Portal/1.2.6 (mail@tk-kundendienst.de)',
            ],
        ];
        $windowsCaFile = 'E:/Programme/PHP/certs/cacert.pem';

        if (PHP_OS_FAMILY === 'Windows' && is_file($windowsCaFile)) {
            $options[CURLOPT_CAINFO] = $windowsCaFile;
        }

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        self::$lastRequestTime = microtime(true);

        if (!is_string($response) || $status !== 200) {
            throw new \RuntimeException('Landesgrenze konnte nicht geladen werden: ' . ($error ?: 'HTTP-Status ' . $status));
        }

        $results = json_decode($response, true);
        $result = is_array($results) ? ($results[0] ?? null) : null;
        $geometry = is_array($result) ? ($result['geojson'] ?? null) : null;

        if (!is_array($result) || !is_array($geometry) || !isset($result['lat'], $result['lon'])) {
            throw new \RuntimeException('Für dieses Land wurde keine Landesfläche gefunden.');
        }

        return [
            'address' => (string) ($result['display_name'] ?? $countryName),
            'latitude' => (float) $result['lat'],
            'longitude' => (float) $result['lon'],
            'geometry' => $geometry,
        ];
    }

    private function waitForRateLimit(): void
    {
        $elapsed = microtime(true) - self::$lastRequestTime;
        $remaining = self::MINIMUM_REQUEST_INTERVAL_SECONDS - $elapsed;

        if ($remaining > 0) {
            usleep((int) ceil($remaining * 1000000));
        }
    }
}
