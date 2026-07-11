<?php

namespace TKKundendienst\Component\Gpsportal\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class TraccarModel
{
    /**
     * Führt eine Anfrage an die Traccar-API aus.
     */
    private function request(string $endpoint): array
    {
        if (!function_exists('curl_init')) {
            $this->writeDebugLog(
                'traccar_error.log',
                'Die PHP-cURL-Erweiterung ist nicht verfügbar.'
            );

            return [];
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('setting_key'),
                $db->quoteName('setting_value')
            ])
            ->from($db->quoteName('#__gpsportal_settings'));

        $db->setQuery($query);

        $settings = $db->loadAssocList(
            'setting_key',
            'setting_value'
        );

        $traccarUrl = trim(
            (string) ($settings['traccar_url'] ?? '')
        );

        $traccarUser = (string) (
            $settings['traccar_user'] ?? ''
        );

        $traccarPassword = (string) (
            $settings['traccar_password'] ?? ''
        );

        if ($traccarUrl === '') {
            $this->writeDebugLog(
                'traccar_error.log',
                'Die Einstellung traccar_url ist leer.'
            );

            return [];
        }

        $url = rtrim($traccarUrl, '/')
            . '/'
            . ltrim($endpoint, '/');

        $curl = curl_init();

        if ($curl === false) {
            $this->writeDebugLog(
                'traccar_error.log',
                'curl_init() konnte nicht initialisiert werden.'
            );

            return [];
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $traccarUser
                . ':'
                . $traccarPassword,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,

            /*
             * Aktueller Bestand:
             * Die Zertifikatsprüfung ist deaktiviert.
             *
             * Für den späteren Produktivbetrieb sollte dies auf true
             * umgestellt werden, sobald ein gültiges Zertifikat genutzt wird.
             */
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response = curl_exec($curl);

        $curlError = curl_error($curl);
        $httpStatus = (int) curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close($curl);

        if ($response === false) {
            $this->writeDebugLog(
                'traccar_error.log',
                [
                    'endpoint' => $endpoint,
                    'url'      => $url,
                    'error'    => $curlError
                ]
            );

            return [];
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $this->writeDebugLog(
                'traccar_error.log',
                [
                    'endpoint'   => $endpoint,
                    'url'        => $url,
                    'httpStatus' => $httpStatus,
                    'response'   => $response
                ]
            );

            return [];
        }

        $decoded = json_decode(
            (string) $response,
            true
        );

        if (!is_array($decoded)) {
            $this->writeDebugLog(
                'traccar_error.log',
                [
                    'endpoint' => $endpoint,
                    'error'    => json_last_error_msg(),
                    'response' => $response
                ]
            );

            return [];
        }

        return $decoded;
    }

    /**
     * Schreibt Debugdateien plattformunabhängig.
     *
     * Windows:
     * D:\Web\gps-portal\logs\gpsportal
     *
     * Linux:
     * /var/www/gps/public/logs/gpsportal
     */
    private function writeDebugLog(
        string $filename,
        mixed $content
    ): void {
        /*
         * Debugdateien werden nur geschrieben, wenn Joomla-Debug
         * aktiviert ist. Dadurch entstehen im Produktivbetrieb keine
         * unnötigen Dateien mit möglicherweise sensiblen API-Daten.
         */
        if (!defined('JDEBUG') || JDEBUG !== true) {
            return;
        }

        $logDirectory = JPATH_ROOT
            . DIRECTORY_SEPARATOR
            . 'logs'
            . DIRECTORY_SEPARATOR
            . 'gpsportal';

        if (
            !is_dir($logDirectory)
            && !@mkdir($logDirectory, 0775, true)
            && !is_dir($logDirectory)
        ) {
            return;
        }

        $safeFilename = basename($filename);

        $logFile = $logDirectory
            . DIRECTORY_SEPARATOR
            . $safeFilename;

        $output = sprintf(
            "[%s]\n%s\n\n",
            date('Y-m-d H:i:s'),
            is_string($content)
                ? $content
                : print_r($content, true)
        );

        /*
         * Fehler beim Debug-Logging dürfen niemals die Anwendung
         * oder die Livemap beeinträchtigen.
         */
        @file_put_contents(
            $logFile,
            $output,
            LOCK_EX
        );
    }

    private function isSuperUser(): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return in_array(
            8,
            $user->groups ?? [],
            true
        );
    }

    private function getAllowedTraccarDeviceIds(): array
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->id) {
            return [];
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(
                $db->quoteName(
                    'd.traccar_device_id'
                )
            )
            ->from(
                $db->quoteName(
                    '#__gpsportal_devices',
                    'd'
                )
            )
            ->join(
                'INNER',
                $db->quoteName(
                    '#__gpsportal_user_devices',
                    'ud'
                )
                . ' ON '
                . $db->quoteName('ud.device_id')
                . ' = '
                . $db->quoteName('d.id')
            )
            ->where(
                $db->quoteName('ud.user_id')
                . ' = '
                . (int) $user->id
            );

        $db->setQuery($query);

        $result = $db->loadColumn();

        return array_map(
            'intval',
            $result ?: []
        );
    }

    public function getDevices(): array
    {
        $devices = $this->request(
            '/api/devices'
        );

        $this->writeDebugLog(
            'traccar_devices.log',
            $devices
        );

        if ($this->isSuperUser()) {
            return $devices;
        }

        $allowedIds =
            $this->getAllowedTraccarDeviceIds();

        if (empty($allowedIds)) {
            return [];
        }

        return array_values(
            array_filter(
                $devices,
                static function (
                    array $device
                ) use (
                    $allowedIds
                ): bool {
                    return in_array(
                        (int) ($device['id'] ?? 0),
                        $allowedIds,
                        true
                    );
                }
            )
        );
    }

    public function getPositions(): array
    {
        $positions = $this->request(
            '/api/positions'
        );

        $this->writeDebugLog(
            'traccar_positions.log',
            $positions
        );

        if ($this->isSuperUser()) {
            return $positions;
        }

        $allowedIds =
            $this->getAllowedTraccarDeviceIds();

        if (empty($allowedIds)) {
            return [];
        }

        return array_values(
            array_filter(
                $positions,
                static function (
                    array $position
                ) use (
                    $allowedIds
                ): bool {
                    return in_array(
                        (int) (
                            $position['deviceId'] ?? 0
                        ),
                        $allowedIds,
                        true
                    );
                }
            )
        );
    }

    public function getVehicleMeta(): array
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->id) {
            return [];
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName(
                    'd.traccar_device_id'
                ),
                $db->quoteName(
                    'd.name'
                ),
                $db->quoteName(
                    'd.vehicle_type'
                ),
                $db->quoteName(
                    'd.marker_icon'
                ),
                $db->quoteName(
                    'd.color'
                ),
                $db->quoteName(
                    'd.license_plate'
                ),
                $db->quoteName(
                    'd.driver'
                )
            ])
            ->from(
                $db->quoteName(
                    '#__gpsportal_devices',
                    'd'
                )
            );

        /*
         * Normale Benutzer erhalten ausschließlich die ihnen
         * zugeordneten Portalfahrzeuge. Dadurch kann jeder Kunde
         * für dieselbe Traccar-ID einen eigenen Fahrzeugnamen,
         * ein eigenes Kennzeichen und eigene Metadaten verwenden.
         */
        if (!$this->isSuperUser()) {
            $query
                ->join(
                    'INNER',
                    $db->quoteName(
                        '#__gpsportal_user_devices',
                        'ud'
                    )
                    . ' ON '
                    . $db->quoteName('ud.device_id')
                    . ' = '
                    . $db->quoteName('d.id')
                )
                ->where(
                    $db->quoteName('ud.user_id')
                    . ' = '
                    . (int) $user->id
                );
        }

        $query
            ->where(
                $db->quoteName('d.published')
                . ' = 1'
            )
            ->order(
                $db->quoteName('d.modified')
                . ' DESC'
            )
            ->order(
                $db->quoteName('d.id')
                . ' DESC'
            );

        $db->setQuery($query);

        $rows = $db->loadAssocList();

        $result = [];

        foreach ($rows ?: [] as $row) {
            $deviceId = (int) (
                $row['traccar_device_id'] ?? 0
            );

            if ($deviceId <= 0) {
                continue;
            }

            /*
             * Bei mehreren Portal-Datensätzen für dieselbe
             * Traccar-ID bleibt durch die Sortierung der zuletzt
             * geänderte Datensatz maßgeblich.
             */
            if (isset($result[$deviceId])) {
                continue;
            }

            $result[$deviceId] = $row;
        }

        return $result;
    }


    public function getHistory(
        int $deviceId,
        string $from,
        string $to
    ): array {
        if ($deviceId <= 0) {
            return [];
        }

        $positions = $this->request(
            '/api/positions?'
            . http_build_query([
                'deviceId' => $deviceId,
                'from'     => $from,
                'to'       => $to
            ])
        );

        $this->writeDebugLog(
            'traccar_history.log',
            [
                'deviceId' => $deviceId,
                'from'     => $from,
                'to'       => $to,
                'positions' => $positions
            ]
        );

        if ($this->isSuperUser()) {
            return $positions;
        }

        $allowedIds =
            $this->getAllowedTraccarDeviceIds();

        if (
            !in_array(
                $deviceId,
                $allowedIds,
                true
            )
        ) {
            return [];
        }

        return $positions;
    }

    public function getAddress(
        float $latitude,
        float $longitude
    ): string {
        if (
            $latitude < -90
            || $latitude > 90
            || $longitude < -180
            || $longitude > 180
            || ($latitude === 0.0 && $longitude === 0.0)
        ) {
            return '';
        }

        $latitude = round($latitude, 6);
        $longitude = round($longitude, 6);

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('address'))
            ->from($db->quoteName('#__gpsportal_geocode_cache'))
            ->where(
                $db->quoteName('latitude')
                . ' = '
                . $db->quote($latitude)
            )
            ->where(
                $db->quoteName('longitude')
                . ' = '
                . $db->quote($longitude)
            );

        $db->setQuery($query);

        $cachedAddress = $db->loadResult();

        if (
            is_string($cachedAddress)
            && trim($cachedAddress) !== ''
        ) {
            return trim($cachedAddress);
        }

        static $nominatimAvailable = null;

        if ($nominatimAvailable === null) {
            $socket = @fsockopen(
                '127.0.0.1',
                8088,
                $errorCode,
                $errorMessage,
                0.25
            );

            $nominatimAvailable = is_resource($socket);

            if (is_resource($socket)) {
                fclose($socket);
            }
        }

        if ($nominatimAvailable !== true) {
            return '';
        }

        if (!function_exists('curl_init')) {
            return '';
        }

        $url = 'http://127.0.0.1:8088/reverse?'
            . http_build_query([
                'format' => 'jsonv2',
                'lat' => $latitude,
                'lon' => $longitude,
                'addressdetails' => 1,
            ]);

        $curl = curl_init();

        if ($curl === false) {
            return '';
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: GPSPortal/1.0',
            ],
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            CURLOPT_TIMEOUT_MS => 2500,
        ]);

        $json = curl_exec($curl);
        $httpStatus = (int) curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close($curl);

        if (
            !is_string($json)
            || $json === ''
            || $httpStatus < 200
            || $httpStatus >= 300
        ) {
            return '';
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            return '';
        }

        $address = trim(
            (string) ($data['display_name'] ?? '')
        );

        if ($address === '') {
            return '';
        }

        $structured = is_array($data['address'] ?? null)
            ? $data['address']
            : [];

        $road = trim((string) (
            $structured['road']
            ?? $structured['pedestrian']
            ?? $structured['residential']
            ?? ''
        ));

        $houseNumber = trim(
            (string) ($structured['house_number'] ?? '')
        );

        $city = trim((string) (
            $structured['city']
            ?? $structured['town']
            ?? $structured['village']
            ?? $structured['municipality']
            ?? ''
        ));

        if ($road !== '') {
            $address = trim($road . ' ' . $houseNumber);

            if ($city !== '') {
                $address .= ', ' . $city;
            }
        }

        try {
            $entry = (object) [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'address' => $address,
            ];

            $db->insertObject(
                '#__gpsportal_geocode_cache',
                $entry
            );
        } catch (\Throwable $exception) {
            // Parallel angelegter Cache-Eintrag ist unkritisch.
        }

        return $address;
    }
    public function getCurrentTraccarUserId(): ?int
    {
        $user = Factory::getApplication()
            ->getIdentity();

        if (!$user || !$user->id) {
            return null;
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(
                $db->quoteName(
                    'traccar_user_id'
                )
            )
            ->from(
                $db->quoteName(
                    '#__gpsportal_traccar_users'
                )
            )
            ->where(
                $db->quoteName(
                    'joomla_user_id'
                )
                . ' = '
                . (int) $user->id
            );

        $db->setQuery($query);

        $result = $db->loadResult();

        if ($result === null) {
            return null;
        }

        return (int) $result;
    }

    public function checkGeofenceEvents(): void
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $positions = $this->request(
            '/api/positions'
        );

        if (empty($positions)) {
            return;
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from(
                $db->quoteName(
                    '#__gpsportal_geofences'
                )
            );

        $db->setQuery($query);

        $zones = $db->loadObjectList();

        foreach ($positions as $position) {
            $deviceId = (int) (
                $position['deviceId'] ?? 0
            );

            $latitude = (float) (
                $position['latitude'] ?? 0
            );

            $longitude = (float) (
                $position['longitude'] ?? 0
            );

            if ($deviceId <= 0) {
                continue;
            }

            foreach ($zones as $zone) {
                $distance =
                    $this->calculateDistance(
                        $latitude,
                        $longitude,
                        (float) $zone->latitude,
                        (float) $zone->longitude
                    );

                $insideNow =
                    $distance <= (int) $zone->radius
                        ? 1
                        : 0;

                $query = $db->getQuery(true)
                    ->select('*')
                    ->from(
                        $db->quoteName(
                            '#__gpsportal_geofence_status'
                        )
                    )
                    ->where(
                        $db->quoteName('device_id')
                        . ' = '
                        . $deviceId
                    )
                    ->where(
                        $db->quoteName('geofence_id')
                        . ' = '
                        . (int) $zone->id
                    );

                $db->setQuery($query);

                $status = $db->loadObject();

                if (!$status) {
                    $row = (object) [
                        'device_id'   => $deviceId,
                        'geofence_id' => (int) $zone->id,
                        'inside_zone' => $insideNow,
                        'last_update' => date(
                            'Y-m-d H:i:s'
                        )
                    ];

                    $db->insertObject(
                        '#__gpsportal_geofence_status',
                        $row
                    );

                    continue;
                }

                $insideBefore =
                    (int) $status->inside_zone;

                if ($insideBefore === $insideNow) {
                    continue;
                }

                $event = (object) [
                    'device_id'   => $deviceId,
                    'geofence_id' => (int) $zone->id,
                    'event_type'  =>
                        $insideNow
                            ? 'enter'
                            : 'exit',
                    'event_time'  => date(
                        'Y-m-d H:i:s'
                    )
                ];

                $db->insertObject(
                    '#__gpsportal_geofence_events',
                    $event
                );

                $update = $db->getQuery(true)
                    ->update(
                        $db->quoteName(
                            '#__gpsportal_geofence_status'
                        )
                    )
                    ->set(
                        $db->quoteName('inside_zone')
                        . ' = '
                        . $insideNow
                    )
                    ->set(
                        $db->quoteName('last_update')
                        . ' = '
                        . $db->quote(
                            date('Y-m-d H:i:s')
                        )
                    )
                    ->where(
                        $db->quoteName('device_id')
                        . ' = '
                        . $deviceId
                    )
                    ->where(
                        $db->quoteName('geofence_id')
                        . ' = '
                        . (int) $zone->id
                    );

                $db->setQuery($update);
                $db->execute();
            }
        }
    }

    private function calculateDistance(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371000;

        $latitudeDifference = deg2rad(
            $latitude2 - $latitude1
        );

        $longitudeDifference = deg2rad(
            $longitude2 - $longitude1
        );

        $a =
            sin($latitudeDifference / 2)
            * sin($latitudeDifference / 2)
            +
            cos(deg2rad($latitude1))
            *
            cos(deg2rad($latitude2))
            *
            sin($longitudeDifference / 2)
            *
            sin($longitudeDifference / 2);

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }
}