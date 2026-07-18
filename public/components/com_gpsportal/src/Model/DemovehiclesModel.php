<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use TKKundendienst\Component\Gpsportal\Site\Service\AdministratorService;
use TKKundendienst\Component\Gpsportal\Site\Service\GeocodingService;
use TKKundendienst\Component\Gpsportal\Site\Service\SimulatorSyncService;
use TKKundendienst\Component\Gpsportal\Site\Service\SimulatorStatusService;

final class DemovehiclesModel extends BaseDatabaseModel
{
    public function getCustomers(): array
    {
        (new AdministratorService())->assertAdministrator();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('c.*')
            ->from($db->quoteName('#__gpsportal_customers', 'c'))
            ->order($db->quoteName('c.name') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getScheduleSettings(): object
    {
        (new AdministratorService())->assertAdministrator();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__gpsportal_demo_settings'))
            ->where($db->quoteName('id') . ' = 1');
        $db->setQuery($query);

        return $db->loadObject() ?: (object) [
            'working_weekdays' => '0,1,2,3,4,5',
            'workday_start' => '06:30:00',
            'workday_end' => '18:30:00',
            'minimum_stop_minutes' => 15,
            'maximum_stop_minutes' => 240,
            'long_stop_probability' => 0.220,
        ];
    }

    public function saveScheduleSettings(array $data): void
    {
        (new AdministratorService())->assertAdministrator();
        $schedule = $this->validateSchedule($data);
        $db = Factory::getContainer()->get('DatabaseDriver');
        $row = (object) array_merge(['id' => 1], $schedule, [
            'modified_by' => (int) Factory::getApplication()->getIdentity()->id,
            'modified' => date('Y-m-d H:i:s'),
        ]);

        try {
            $db->insertObject('#__gpsportal_demo_settings', $row);
        } catch (\Throwable $error) {
            if (stripos($error->getMessage(), 'Duplicate') === false) {
                throw $error;
            }
            $db->updateObject('#__gpsportal_demo_settings', $row, 'id');
        }

        if (!empty($data['apply_to_all'])) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__gpsportal_demo_vehicles'))
                ->set($db->quoteName('working_weekdays') . ' = ' . $db->quote($schedule['working_weekdays']))
                ->set($db->quoteName('workday_start') . ' = ' . $db->quote($schedule['workday_start']))
                ->set($db->quoteName('workday_end') . ' = ' . $db->quote($schedule['workday_end']))
                ->set($db->quoteName('minimum_stop_minutes') . ' = ' . (int) $schedule['minimum_stop_minutes'])
                ->set($db->quoteName('maximum_stop_minutes') . ' = ' . (int) $schedule['maximum_stop_minutes'])
                ->set($db->quoteName('long_stop_probability') . ' = ' . (float) $schedule['long_stop_probability']);
            $db->setQuery($query)->execute();

            foreach ($this->getDemoVehicles() as $vehicle) {
                $this->enqueueVehicle($vehicle);
            }
        }
    }

    public function getUsers(): array
    {
        (new AdministratorService())->assertAdministrator();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['u.id', 'u.name', 'u.username', 'u.email'])
            ->from($db->quoteName('#__users', 'u'))
            ->where($db->quoteName('u.block') . ' = 0')
            ->order($db->quoteName('u.name') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getDemoVehicles(): array
    {
        (new AdministratorService())->assertAdministrator();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([
                'dv.*', 'd.name', 'd.tracker_unique_id', 'd.license_plate',
                'du.user_id', 'du.fixed_assignment',
                'u.name AS assigned_user_name', 'u.username AS assigned_username',
            ])
            ->from($db->quoteName('#__gpsportal_demo_vehicles', 'dv'))
            ->join('INNER', $db->quoteName('#__gpsportal_devices', 'd')
                . ' ON d.id = dv.device_id')
            ->join('LEFT', $db->quoteName('#__gpsportal_demo_vehicle_users', 'du')
                . ' ON du.device_id = d.id')
            ->join('LEFT', $db->quoteName('#__users', 'u')
                . ' ON u.id = du.user_id')
            ->order($db->quoteName('d.name') . ' ASC');
        $db->setQuery($query);

        return (new SimulatorStatusService())->enrich($db->loadObjectList() ?: []);
    }

    public function getDemoVehicle(int $deviceId): ?object
    {
        foreach ($this->getDemoVehicles() as $vehicle) {
            if ((int) $vehicle->device_id === $deviceId) {
                return $vehicle;
            }
        }

        return null;
    }

    public function saveCustomer(string $name, string $number): void
    {
        (new AdministratorService())->assertAdministrator();
        $name = trim($name);

        if ($name === '') {
            throw new \RuntimeException('Der Kundenname fehlt.');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $row = (object) [
            'name' => $name,
            'customer_number' => trim($number) ?: null,
            'published' => 1,
            'created' => date('Y-m-d H:i:s'),
        ];
        $db->insertObject('#__gpsportal_customers', $row);
    }

    public function assignUser(int $customerId, int $userId): void
    {
        (new AdministratorService())->assertAdministrator();

        if ($customerId <= 0 || $userId <= 0) {
            throw new \RuntimeException('Kunde oder Benutzer ist ungültig.');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__gpsportal_customer_users'))
            ->where($db->quoteName('user_id') . ' = ' . $userId);
        $db->setQuery($query)->execute();
        $customerUser = (object) [
            'customer_id' => $customerId,
            'user_id' => $userId,
            'created' => date('Y-m-d H:i:s'),
        ];
        $db->insertObject('#__gpsportal_customer_users', $customerUser);

        $query = $db->getQuery(true)
            ->select($db->quoteName('device_id'))
            ->from($db->quoteName('#__gpsportal_customer_devices'))
            ->where($db->quoteName('customer_id') . ' = ' . $customerId);
        $db->setQuery($query);

        foreach (array_map('intval', $db->loadColumn() ?: []) as $deviceId) {
            try {
                $userDevice = (object) [
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                    'created' => date('Y-m-d H:i:s'),
                ];
                $db->insertObject('#__gpsportal_user_devices', $userDevice);
            } catch (\Throwable $error) {
                if (stripos($error->getMessage(), 'Duplicate') === false) {
                    throw $error;
                }
            }
        }
    }

    public function saveDemoVehicle(array $data): void
    {
        (new AdministratorService())->assertAdministrator();
        $userId = (int) ($data['user_id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $uniqueId = trim((string) ($data['unique_id'] ?? ''));
        $destinations = array_values(array_filter(array_map(
            'trim',
            preg_split('/\R/u', (string) ($data['destinations'] ?? '')) ?: []
        )));

        if ($name === '' || $uniqueId === '') {
            throw new \RuntimeException('Fahrzeugname und Unique ID sind erforderlich.');
        }

        if ($destinations === []) {
            throw new \RuntimeException('Mindestens ein Zielort ist erforderlich.');
        }

        $minimumSpeed = max(1, min(200, (int) ($data['minimum_speed_kmh'] ?? 20)));
        $maximumSpeed = max($minimumSpeed, min(200, (int) ($data['maximum_speed_kmh'] ?? 100)));
        $schedule = $this->validateSchedule($data);
        $geocoder = new GeocodingService();
        $start = $geocoder->resolve((string) ($data['start_address'] ?? ''));
        $resolvedDestinations = [];

        foreach ($destinations as $address) {
            $resolvedDestinations[] = $geocoder->resolve($address);
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__gpsportal_devices'))
            ->where($db->quoteName('tracker_unique_id') . ' = ' . $db->quote($uniqueId));
        $db->setQuery($query);
        $deviceId = (int) $db->loadResult();

        if ($deviceId <= 0) {
            if ($this->isLocalTest()) {
                $temporaryTraccarId = -1 * max(
                    1,
                    (int) sprintf('%u', crc32($uniqueId))
                );
                $pendingDevice = (object) [
                    'traccar_device_id' => $temporaryTraccarId,
                    'tracker_unique_id' => $uniqueId,
                    'name' => $name,
                    'license_plate' => trim((string) ($data['license_plate'] ?? '')),
                    'vehicle_type' => 'Dummyfahrzeug',
                    'published' => 1,
                    'created' => date('Y-m-d H:i:s'),
                ];
                $db->insertObject('#__gpsportal_devices', $pendingDevice);
            } else {
                $vehicleModel = new VehiclesModel();
                $vehicleModel->saveVehicle([
                    'name' => $name,
                    'tracker_unique_id' => $uniqueId,
                    'license_plate' => (string) ($data['license_plate'] ?? ''),
                    'vehicle_type' => 'Dummyfahrzeug',
                ]);
            }

            $db->setQuery($query);
            $deviceId = (int) $db->loadResult();
        } else {
            $device = (object) [
                'id' => $deviceId,
                'name' => $name,
                'license_plate' => trim((string) ($data['license_plate'] ?? '')),
                'vehicle_type' => 'Dummyfahrzeug',
            ];
            $db->updateObject('#__gpsportal_devices', $device, 'id');
        }

        if ($deviceId <= 0) {
            throw new \RuntimeException('Das Fahrzeug konnte nicht angelegt werden.');
        }

        $row = (object) [
            'device_id' => $deviceId,
            'region' => trim((string) ($data['region'] ?? '')) ?: $start['address'],
            'start_address' => $start['address'],
            'start_latitude' => $start['latitude'],
            'start_longitude' => $start['longitude'],
            'destinations_json' => json_encode($resolvedDestinations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'minimum_speed_kmh' => $minimumSpeed,
            'maximum_speed_kmh' => $maximumSpeed,
            'working_weekdays' => $schedule['working_weekdays'],
            'workday_start' => $schedule['workday_start'],
            'workday_end' => $schedule['workday_end'],
            'minimum_stop_minutes' => $schedule['minimum_stop_minutes'],
            'maximum_stop_minutes' => $schedule['maximum_stop_minutes'],
            'long_stop_probability' => $schedule['long_stop_probability'],
            'active' => !empty($data['active']) ? 1 : 0,
            'sync_status' => 'pending',
            'created_by' => (int) Factory::getApplication()->getIdentity()->id,
            'created' => date('Y-m-d H:i:s'),
        ];

        try {
            $db->insertObject('#__gpsportal_demo_vehicles', $row);
        } catch (\Throwable $error) {
            if (stripos($error->getMessage(), 'Duplicate') === false) {
                throw $error;
            }

            $row->modified = date('Y-m-d H:i:s');
            unset($row->created);
            $db->updateObject('#__gpsportal_demo_vehicles', $row, 'device_id');
        }

        if ($userId > 0) {
            $this->assignVehicle($deviceId, $userId, !empty($data['fixed_assignment']));
        }

        $queued = (new SimulatorSyncService())->enqueueUpsert([
            'name' => $name,
            'unique_id' => $uniqueId,
            'region' => $row->region,
            'home' => [
                (float) $row->start_longitude,
                (float) $row->start_latitude,
            ],
            'waypoints' => array_merge(
                [[(float) $row->start_longitude, (float) $row->start_latitude]],
                array_map(
                    static fn (array $destination): array => [
                        (float) $destination['longitude'],
                        (float) $destination['latitude'],
                    ],
                    $resolvedDestinations
                )
            ),
            'minimum_speed_kmh' => $minimumSpeed,
            'maximum_speed_kmh' => $maximumSpeed,
            'working_weekdays' => array_map('intval', explode(',', $schedule['working_weekdays'])),
            'workday_start' => substr($schedule['workday_start'], 0, 5),
            'workday_end' => substr($schedule['workday_end'], 0, 5),
            'minimum_stop_minutes' => $schedule['minimum_stop_minutes'],
            'maximum_stop_minutes' => $schedule['maximum_stop_minutes'],
            'long_stop_probability' => $schedule['long_stop_probability'],
            'active' => (bool) $row->active,
        ]);

        if (!$queued) {
            Factory::getApplication()->enqueueMessage(
                'Lokal gespeichert. Die Simulator-Synchronisation wird erst auf dem GPS-Server ausgeführt.',
                'warning'
            );
        }
    }

    public function assignVehicle(int $deviceId, int $userId, bool $fixed): void
    {
        (new AdministratorService())->assertAdministrator();

        if ($deviceId <= 0 || $userId <= 0) {
            throw new \RuntimeException('Fahrzeug oder Benutzer ist ungültig.');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__gpsportal_demo_vehicle_users'))
            ->where($db->quoteName('device_id') . ' = ' . $deviceId);
        $db->setQuery($query)->execute();
        $assignment = (object) [
            'device_id' => $deviceId,
            'user_id' => $userId,
            'fixed_assignment' => $fixed ? 1 : 0,
            'created' => date('Y-m-d H:i:s'),
        ];
        $db->insertObject('#__gpsportal_demo_vehicle_users', $assignment);

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__gpsportal_user_devices'))
            ->where($db->quoteName('device_id') . ' = ' . $deviceId);
        $db->setQuery($query)->execute();
        $userDevice = (object) [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'created' => date('Y-m-d H:i:s'),
        ];
        $db->insertObject('#__gpsportal_user_devices', $userDevice);

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__gpsportal_user_hidden_devices'))
            ->where($db->quoteName('device_id') . ' = ' . $deviceId);
        $db->setQuery($query)->execute();
    }

    public function assignInitialVehicles(int $userId, int $quantity = 4): int
    {
        (new AdministratorService())->assertAdministrator();
        $quantity = max(1, min(20, $quantity));
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__gpsportal_demo_vehicle_users'))
            ->where($db->quoteName('user_id') . ' = ' . $userId);
        $db->setQuery($query);
        $missing = max(0, $quantity - (int) $db->loadResult());

        if ($missing === 0) {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select('dv.device_id')
            ->from($db->quoteName('#__gpsportal_demo_vehicles', 'dv'))
            ->join('LEFT', $db->quoteName('#__gpsportal_demo_vehicle_users', 'du')
                . ' ON du.device_id = dv.device_id')
            ->where($db->quoteName('du.id') . ' IS NULL')
            ->where($db->quoteName('dv.active') . ' = 1')
            ->order($db->quoteName('dv.id') . ' ASC');
        $db->setQuery($query, 0, $missing);
        $freeDeviceIds = array_map('intval', $db->loadColumn() ?: []);

        foreach ($freeDeviceIds as $deviceId) {
            $this->assignVehicle($deviceId, $userId, false);
        }

        if (count($freeDeviceIds) < $missing) {
            $needed = $missing - count($freeDeviceIds);
            $query = $db->getQuery(true)
                ->select(['dv.*', 'd.name', 'd.license_plate'])
                ->from($db->quoteName('#__gpsportal_demo_vehicles', 'dv'))
                ->join('INNER', $db->quoteName('#__gpsportal_devices', 'd')
                    . ' ON d.id = dv.device_id')
                ->where($db->quoteName('dv.active') . ' = 1')
                ->order($db->quoteName('dv.id') . ' ASC');
            $db->setQuery($query);
            $templates = $db->loadObjectList() ?: [];

            if ($templates === []) {
                throw new \RuntimeException(
                    'Es ist keine Demofahrzeug-Routenvorlage vorhanden. Bitte einmalig ein Demofahrzeug anlegen.'
                );
            }

            for ($index = 0; $index < $needed; $index++) {
                $this->cloneTemplateForUser(
                    $templates[$index % count($templates)],
                    $userId,
                    count($freeDeviceIds) + $index + 1
                );
            }
        }

        return $missing;
    }

    private function cloneTemplateForUser(object $template, int $userId, int $sequence): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('id') . ' = ' . $userId);
        $db->setQuery($query);
        $userName = trim((string) $db->loadResult()) ?: 'Benutzer ' . $userId;
        $uniqueId = sprintf('DEMO-U%d-%s-%02d', $userId, date('YmdHis'), $sequence);
        $name = sprintf('Demo %s %02d', $userName, $sequence);

        if ($this->isLocalTest()) {
            $device = (object) [
                'traccar_device_id' => -1 * max(1, (int) sprintf('%u', crc32($uniqueId))),
                'tracker_unique_id' => $uniqueId,
                'name' => $name,
                'license_plate' => 'DEMO-' . $userId . '-' . $sequence,
                'vehicle_type' => 'Dummyfahrzeug',
                'published' => 1,
                'created' => date('Y-m-d H:i:s'),
            ];
            $db->insertObject('#__gpsportal_devices', $device);
        } else {
            (new VehiclesModel())->saveVehicle([
                'name' => $name,
                'tracker_unique_id' => $uniqueId,
                'license_plate' => 'DEMO-' . $userId . '-' . $sequence,
                'vehicle_type' => 'Dummyfahrzeug',
            ]);
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__gpsportal_devices'))
            ->where($db->quoteName('tracker_unique_id') . ' = ' . $db->quote($uniqueId));
        $db->setQuery($query);
        $deviceId = (int) $db->loadResult();

        if ($deviceId <= 0) {
            throw new \RuntimeException('Ein zusätzliches Demofahrzeug konnte nicht erzeugt werden.');
        }

        $row = (object) [
            'device_id' => $deviceId,
            'region' => $template->region,
            'start_address' => $template->start_address,
            'start_latitude' => $template->start_latitude,
            'start_longitude' => $template->start_longitude,
            'destinations_json' => $template->destinations_json,
            'minimum_speed_kmh' => $template->minimum_speed_kmh,
            'maximum_speed_kmh' => $template->maximum_speed_kmh,
            'working_weekdays' => $template->working_weekdays ?? '0,1,2,3,4,5',
            'workday_start' => $template->workday_start ?? '06:30:00',
            'workday_end' => $template->workday_end ?? '18:30:00',
            'minimum_stop_minutes' => $template->minimum_stop_minutes ?? 15,
            'maximum_stop_minutes' => $template->maximum_stop_minutes ?? 240,
            'long_stop_probability' => $template->long_stop_probability ?? 0.220,
            'active' => 1,
            'sync_status' => 'pending',
            'created_by' => (int) Factory::getApplication()->getIdentity()->id,
            'created' => date('Y-m-d H:i:s'),
        ];
        $db->insertObject('#__gpsportal_demo_vehicles', $row);
        $this->assignVehicle($deviceId, $userId, false);
        $destinations = json_decode((string) $template->destinations_json, true) ?: [];
        (new SimulatorSyncService())->enqueueUpsert([
            'name' => $name,
            'unique_id' => $uniqueId,
            'region' => $template->region,
            'home' => [(float) $template->start_longitude, (float) $template->start_latitude],
            'waypoints' => array_merge(
                [[(float) $template->start_longitude, (float) $template->start_latitude]],
                array_map(static fn (array $destination): array => [
                    (float) ($destination['longitude'] ?? 0),
                    (float) ($destination['latitude'] ?? 0),
                ], $destinations)
            ),
            'minimum_speed_kmh' => (int) $template->minimum_speed_kmh,
            'maximum_speed_kmh' => (int) $template->maximum_speed_kmh,
            'working_weekdays' => array_map('intval', explode(',', (string) ($template->working_weekdays ?? '0,1,2,3,4,5'))),
            'workday_start' => substr((string) ($template->workday_start ?? '06:30:00'), 0, 5),
            'workday_end' => substr((string) ($template->workday_end ?? '18:30:00'), 0, 5),
            'minimum_stop_minutes' => (int) ($template->minimum_stop_minutes ?? 15),
            'maximum_stop_minutes' => (int) ($template->maximum_stop_minutes ?? 240),
            'long_stop_probability' => (float) ($template->long_stop_probability ?? 0.220),
            'active' => true,
        ]);
    }

    public function unassignVehicle(int $deviceId): void
    {
        (new AdministratorService())->assertAdministrator();
        $db = Factory::getContainer()->get('DatabaseDriver');

        foreach (['#__gpsportal_demo_vehicle_users', '#__gpsportal_user_devices', '#__gpsportal_user_hidden_devices'] as $table) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName($table))
                ->where($db->quoteName('device_id') . ' = ' . $deviceId);
            $db->setQuery($query)->execute();
        }
    }

    public function deleteDemoVehicle(int $deviceId): void
    {
        (new AdministratorService())->assertAdministrator();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('tracker_unique_id'))
            ->from($db->quoteName('#__gpsportal_devices'))
            ->where($db->quoteName('id') . ' = ' . $deviceId);
        $db->setQuery($query);
        $uniqueId = (string) $db->loadResult();

        if ($uniqueId === '') {
            throw new \RuntimeException('Das Demofahrzeug wurde nicht gefunden.');
        }

        (new SimulatorSyncService())->enqueueDelete($uniqueId);
        $this->unassignVehicle($deviceId);
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__gpsportal_demo_vehicles'))
            ->where($db->quoteName('device_id') . ' = ' . $deviceId);
        $db->setQuery($query)->execute();
        if ($this->isLocalTest()) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__gpsportal_devices'))
                ->where($db->quoteName('id') . ' = ' . $deviceId);
            $db->setQuery($query)->execute();
        } else {
            (new VehiclesModel())->deleteVehicle($deviceId);
        }
    }

    private function synchroniseCustomerUsers(int $customerId, int $deviceId): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('user_id'))
            ->from($db->quoteName('#__gpsportal_customer_users'))
            ->where($db->quoteName('customer_id') . ' = ' . $customerId);
        $db->setQuery($query);

        foreach (array_map('intval', $db->loadColumn() ?: []) as $userId) {
            try {
                $userDevice = (object) [
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                    'created' => date('Y-m-d H:i:s'),
                ];
                $db->insertObject('#__gpsportal_user_devices', $userDevice);
            } catch (\Throwable $error) {
                if (stripos($error->getMessage(), 'Duplicate') === false) {
                    throw $error;
                }
            }
        }
    }

    private function validateSchedule(array $data): array
    {
        $weekdays = array_values(array_unique(array_filter(
            array_map('intval', (array) ($data['working_weekdays'] ?? [])),
            static fn (int $day): bool => $day >= 0 && $day <= 6
        )));

        if ($weekdays === []) {
            throw new \RuntimeException('Bitte mindestens einen Fahrtag auswählen.');
        }

        sort($weekdays);
        $start = trim((string) ($data['workday_start'] ?? '06:30'));
        $end = trim((string) ($data['workday_end'] ?? '18:30'));

        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start)
            || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)
            || $start >= $end) {
            throw new \RuntimeException('Start- und Endzeit sind ungültig. Die Endzeit muss nach der Startzeit liegen.');
        }

        $minimumStop = max(1, min(1440, (int) ($data['minimum_stop_minutes'] ?? 15)));
        $maximumStop = max($minimumStop, min(1440, (int) ($data['maximum_stop_minutes'] ?? 240)));
        $probability = max(0.0, min(1.0, (float) ($data['long_stop_probability'] ?? 0.220)));

        return [
            'working_weekdays' => implode(',', $weekdays),
            'workday_start' => $start . ':00',
            'workday_end' => $end . ':00',
            'minimum_stop_minutes' => $minimumStop,
            'maximum_stop_minutes' => $maximumStop,
            'long_stop_probability' => $probability,
        ];
    }

    private function enqueueVehicle(object $vehicle): void
    {
        $destinations = json_decode((string) $vehicle->destinations_json, true) ?: [];
        (new SimulatorSyncService())->enqueueUpsert([
            'name' => (string) $vehicle->name,
            'unique_id' => (string) $vehicle->tracker_unique_id,
            'region' => (string) $vehicle->region,
            'home' => [(float) $vehicle->start_longitude, (float) $vehicle->start_latitude],
            'waypoints' => array_merge(
                [[(float) $vehicle->start_longitude, (float) $vehicle->start_latitude]],
                array_map(static fn (array $destination): array => [
                    (float) ($destination['longitude'] ?? 0),
                    (float) ($destination['latitude'] ?? 0),
                ], $destinations)
            ),
            'minimum_speed_kmh' => (int) $vehicle->minimum_speed_kmh,
            'maximum_speed_kmh' => (int) $vehicle->maximum_speed_kmh,
            'working_weekdays' => array_map('intval', explode(',', (string) $vehicle->working_weekdays)),
            'workday_start' => substr((string) $vehicle->workday_start, 0, 5),
            'workday_end' => substr((string) $vehicle->workday_end, 0, 5),
            'minimum_stop_minutes' => (int) $vehicle->minimum_stop_minutes,
            'maximum_stop_minutes' => (int) $vehicle->maximum_stop_minutes,
            'long_stop_probability' => (float) $vehicle->long_stop_probability,
            'active' => (bool) $vehicle->active,
        ]);
    }

    private function isLocalTest(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        return PHP_OS_FAMILY === 'Windows'
            || str_starts_with($host, 'localhost')
            || str_starts_with($host, '127.0.0.1')
            || str_starts_with($host, '[::1]');
    }
}
