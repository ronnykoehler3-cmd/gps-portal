<?php

namespace TKKundendienst\Component\Gpsportal\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use TKKundendienst\Component\Gpsportal\Site\Service\AdministratorService;

class VehiclesModel extends BaseDatabaseModel
{
    private function getTraccarSettings(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('setting_key'),
                $db->quoteName('setting_value'),
            ])
            ->from($db->quoteName('#__gpsportal_settings'));

        $db->setQuery($query);

        return $db->loadAssocList('setting_key', 'setting_value') ?: [];
    }

    private function requestTraccar(
        string $method,
        string $endpoint,
        ?array $payload = null
    ): array {
        if (!function_exists('curl_init')) {
            return [];
        }

        $settings = $this->getTraccarSettings();
        $baseUrl = rtrim((string) ($settings['traccar_url'] ?? ''), '/');

        if ($baseUrl === '') {
            return [];
        }

        $curl = curl_init();

        if ($curl === false) {
            return [];
        }

        $options = [
            CURLOPT_URL => $baseUrl . '/' . ltrim($endpoint, '/'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD =>
                (string) ($settings['traccar_user'] ?? '')
                . ':'
                . (string) ($settings['traccar_password'] ?? ''),
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode(
                $payload ?? [],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if (
            !is_string($response)
            || $response === ''
            || $httpStatus < 200
            || $httpStatus >= 300
        ) {
            return [];
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function findTraccarDeviceByUniqueId(string $uniqueId): int
    {
        foreach ($this->requestTraccar('GET', '/api/devices') as $device) {
            if (
                isset($device['uniqueId'])
                && trim((string) $device['uniqueId']) === trim($uniqueId)
            ) {
                return (int) ($device['id'] ?? 0);
            }
        }

        return 0;
    }

    private function createTraccarDevice(
        string $name,
        string $uniqueId
    ): int {
        $device = $this->requestTraccar(
            'POST',
            '/api/devices',
            [
                'name' => trim($name),
                'uniqueId' => trim($uniqueId),
            ]
        );

        return (int) ($device['id'] ?? 0);
    }

    private function getCurrentTotalDistanceMeters(
        int $traccarDeviceId
    ): float {
        if ($traccarDeviceId <= 0) {
            return 0.0;
        }

        $positions = $this->requestTraccar(
            'GET',
            '/api/positions?deviceId=' . $traccarDeviceId
        );

        if ($positions === []) {
            return 0.0;
        }

        $latest = end($positions);

        if (!is_array($latest)) {
            return 0.0;
        }

        return max(
            0.0,
            (float) ($latest['attributes']['totalDistance'] ?? 0)
        );
    }

    public function getVehicles(): array
    {
        $user = Factory::getApplication()->getIdentity();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('d.*')
            ->from($db->quoteName('#__gpsportal_devices', 'd'))
            ->join(
                'INNER',
                $db->quoteName('#__gpsportal_user_devices', 'ud')
                . ' ON '
                . $db->quoteName('ud.device_id')
                . ' = '
                . $db->quoteName('d.id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__gpsportal_user_hidden_devices', 'uhd')
                . ' ON ' . $db->quoteName('uhd.device_id')
                . ' = ' . $db->quoteName('d.id')
                . ' AND ' . $db->quoteName('uhd.user_id')
                . ' = ' . (int) $user->id
            )
            ->where(
                $db->quoteName('ud.user_id')
                . ' = '
                . (int) $user->id
            )
            ->where($db->quoteName('uhd.id') . ' IS NULL')
            ->order($db->quoteName('d.name') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getVehicle(int $id): ?object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__gpsportal_devices'))
            ->where($db->quoteName('id') . ' = ' . $id);

        $db->setQuery($query);

        $vehicle = $db->loadObject();

        return is_object($vehicle) ? $vehicle : null;
    }

    public function saveVehicle(array $data): bool
    {
        $user = Factory::getApplication()->getIdentity();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $uniqueId = trim((string) ($data['tracker_unique_id'] ?? ''));

        if ($uniqueId === '') {
            throw new \RuntimeException('Die Tracker Unique ID fehlt.');
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__gpsportal_devices'))
            ->where(
                $db->quoteName('tracker_unique_id')
                . ' = '
                . $db->quote($uniqueId)
            );

        $db->setQuery($query);
        $existingVehicle = $db->loadObject();

        if ($existingVehicle) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__gpsportal_user_devices'))
                ->where(
                    $db->quoteName('user_id')
                    . ' = '
                    . (int) $user->id
                )
                ->where(
                    $db->quoteName('device_id')
                    . ' = '
                    . (int) $existingVehicle->id
                );

            $db->setQuery($query);

            if ((int) $db->loadResult() === 0) {
                $link = (object) [
                    'user_id' => (int) $user->id,
                    'device_id' => (int) $existingVehicle->id,
                    'created' => date('Y-m-d H:i:s'),
                ];

                $db->insertObject('#__gpsportal_user_devices', $link);
            }

            Factory::getApplication()->enqueueMessage(
                'Fahrzeug wird gemeinsam genutzt.'
            );

            return true;
        }

        $traccarId = $this->findTraccarDeviceByUniqueId($uniqueId);

        if ($traccarId <= 0) {
            $traccarId = $this->createTraccarDevice(
                (string) ($data['name'] ?? ''),
                $uniqueId
            );
        }

        if ($traccarId <= 0) {
            throw new \RuntimeException(
                'Traccar-Gerät konnte nicht gefunden oder angelegt werden.'
            );
        }

        $vehicle = (object) [
            'traccar_device_id' => $traccarId,
            'tracker_unique_id' => $uniqueId,
            'name' => trim((string) ($data['name'] ?? '')),
            'license_plate' => trim((string) ($data['license_plate'] ?? '')),
            'vehicle_type' => trim((string) ($data['vehicle_type'] ?? '')),
            'manufacturer' => trim((string) ($data['manufacturer'] ?? '')),
            'model' => trim((string) ($data['model'] ?? '')),
            'driver' => trim((string) ($data['driver'] ?? '')),
            'cost_center' => trim((string) ($data['cost_center'] ?? '')),
            'initial_odometer_km' => max(
                0,
                round((float) ($data['initial_odometer_km'] ?? 0), 1)
            ),
            'odometer_base_m' =>
                $this->getCurrentTotalDistanceMeters($traccarId),
            'color' => trim((string) ($data['color'] ?? '')),
            'marker_icon' => trim((string) ($data['marker_icon'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'created' => date('Y-m-d H:i:s'),
        ];

        $db->insertObject('#__gpsportal_devices', $vehicle, 'id');

        $link = (object) [
            'user_id' => (int) $user->id,
            'device_id' => (int) $vehicle->id,
            'created' => date('Y-m-d H:i:s'),
        ];

        $db->insertObject('#__gpsportal_user_devices', $link);

        return true;
    }

    public function updateVehicle(int $id, array $data): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $existingVehicle = $this->getVehicle($id);

        if (!$existingVehicle) {
            throw new \RuntimeException('Fahrzeug wurde nicht gefunden.');
        }

        $newInitialOdometer = max(
            0,
            round((float) ($data['initial_odometer_km'] ?? 0), 1)
        );

        $oldInitialOdometer = round(
            (float) ($existingVehicle->initial_odometer_km ?? 0),
            1
        );

        $odometerBaseM = (float) (
            $existingVehicle->odometer_base_m ?? 0
        );

        if (
            abs($newInitialOdometer - $oldInitialOdometer) >= 0.05
            || $odometerBaseM <= 0
        ) {
            $newBase = $this->getCurrentTotalDistanceMeters(
                (int) ($existingVehicle->traccar_device_id ?? 0)
            );

            if ($newBase > 0) {
                $odometerBaseM = $newBase;
            }
        }

        $vehicle = (object) [
            'id' => $id,
            'tracker_unique_id' =>
                trim((string) ($data['tracker_unique_id'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'license_plate' =>
                trim((string) ($data['license_plate'] ?? '')),
            'vehicle_type' =>
                trim((string) ($data['vehicle_type'] ?? '')),
            'manufacturer' =>
                trim((string) ($data['manufacturer'] ?? '')),
            'model' => trim((string) ($data['model'] ?? '')),
            'driver' => trim((string) ($data['driver'] ?? '')),
            'cost_center' =>
                trim((string) ($data['cost_center'] ?? '')),
            'initial_odometer_km' => $newInitialOdometer,
            'odometer_base_m' => $odometerBaseM,
            'color' => trim((string) ($data['color'] ?? '')),
            'marker_icon' =>
                trim((string) ($data['marker_icon'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'modified' => date('Y-m-d H:i:s'),
        ];

        $db->updateObject('#__gpsportal_devices', $vehicle, 'id');

        return true;
    }

    public function deleteVehicle(int $id): bool
    {
        (new AdministratorService())->assertAdministrator();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__gpsportal_user_devices'))
            ->where($db->quoteName('device_id') . ' = ' . $id);

        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__gpsportal_devices'))
            ->where($db->quoteName('id') . ' = ' . $id);

        $db->setQuery($query);
        $db->execute();

        return true;
    }
}
