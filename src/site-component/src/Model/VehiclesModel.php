<?php

namespace TKKundendienst\Component\Gpsportal\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class VehiclesModel extends BaseDatabaseModel
{
    private function getCurrentUserId(): int
    {
        $user = Factory::getApplication()
            ->getIdentity();

        return (int) ($user->id ?? 0);
    }

    private function isSuperUser(): bool
    {
        $user = Factory::getApplication()
            ->getIdentity();

        return $user->authorise('core.admin');
    }

    private function getTraccarSettings(): array
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('setting_key'),
                $db->quoteName('setting_value')
            ])
            ->from(
                $db->quoteName(
                    '#__gpsportal_settings'
                )
            );

        $db->setQuery($query);

        return $db->loadAssocList(
            'setting_key',
            'setting_value'
        );
    }

    private function findTraccarDeviceByUniqueId(
        string $uniqueId
    ): int {
        $settings = $this->getTraccarSettings();

        $traccarUrl = trim(
            (string) (
                $settings['traccar_url'] ?? ''
            )
        );

        $traccarUser = (string) (
            $settings['traccar_user'] ?? ''
        );

        $traccarPassword = (string) (
            $settings['traccar_password'] ?? ''
        );

        if ($traccarUrl === '') {
            return 0;
        }

        $curl = curl_init();

        if ($curl === false) {
            return 0;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL =>
                rtrim($traccarUrl, '/')
                . '/api/devices',

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_USERPWD =>
                $traccarUser
                . ':'
                . $traccarPassword,

            CURLOPT_HTTPAUTH =>
                CURLAUTH_BASIC,

            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],

            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,

            /*
             * Bestehender Entwicklungsstand.
             * Vor dem produktiven Release muss die Prüfung
             * wieder aktiviert werden.
             */
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response = curl_exec($curl);

        $httpStatus = (int) curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close($curl);

        if (
            !is_string($response)
            || $httpStatus < 200
            || $httpStatus >= 300
        ) {
            return 0;
        }

        $devices = json_decode(
            $response,
            true
        );

        if (!is_array($devices)) {
            return 0;
        }

        foreach ($devices as $device) {
            if (
                isset($device['uniqueId'])
                && trim(
                    (string) $device['uniqueId']
                ) === trim($uniqueId)
            ) {
                return (int) (
                    $device['id'] ?? 0
                );
            }
        }

        return 0;
    }

    private function createTraccarDevice(
        string $name,
        string $uniqueId
    ): int {
        $settings = $this->getTraccarSettings();

        $traccarUrl = trim(
            (string) (
                $settings['traccar_url'] ?? ''
            )
        );

        $traccarUser = (string) (
            $settings['traccar_user'] ?? ''
        );

        $traccarPassword = (string) (
            $settings['traccar_password'] ?? ''
        );

        if ($traccarUrl === '') {
            return 0;
        }

        $payload = json_encode([
            'name'     => $name,
            'uniqueId' => $uniqueId
        ]);

        if (!is_string($payload)) {
            return 0;
        }

        $curl = curl_init();

        if ($curl === false) {
            return 0;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL =>
                rtrim($traccarUrl, '/')
                . '/api/devices',

            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,

            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json'
            ],

            CURLOPT_USERPWD =>
                $traccarUser
                . ':'
                . $traccarPassword,

            CURLOPT_HTTPAUTH =>
                CURLAUTH_BASIC,

            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,

            /*
             * Bestehender Entwicklungsstand.
             * Vor dem produktiven Release muss die Prüfung
             * wieder aktiviert werden.
             */
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response = curl_exec($curl);

        $httpStatus = (int) curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close($curl);

        if (
            !is_string($response)
            || $httpStatus < 200
            || $httpStatus >= 300
        ) {
            return 0;
        }

        $device = json_decode(
            $response,
            true
        );

        if (!is_array($device)) {
            return 0;
        }

        return (int) (
            $device['id'] ?? 0
        );
    }

    private function userOwnsVehicle(
        int $vehicleId,
        int $userId
    ): bool {
        if ($vehicleId <= 0 || $userId <= 0) {
            return false;
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from(
                $db->quoteName(
                    '#__gpsportal_user_devices'
                )
            )
            ->where(
                $db->quoteName('user_id')
                . ' = '
                . $userId
            )
            ->where(
                $db->quoteName('device_id')
                . ' = '
                . $vehicleId
            );

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    private function getVehicleAssignmentCount(
        int $vehicleId
    ): int {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from(
                $db->quoteName(
                    '#__gpsportal_user_devices'
                )
            )
            ->where(
                $db->quoteName('device_id')
                . ' = '
                . $vehicleId
            );

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    public function getVehicles(): array
    {
        $userId = $this->getCurrentUserId();

        if ($userId <= 0) {
            return [];
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'd.*',
                $db->quoteName(
                    'ud.display_name',
                    'customer_display_name'
                )
            ])
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
                . $userId
            )
            ->order(
                $db->quoteName(
                    'ud.display_name'
                )
                . ' ASC'
            );

        $db->setQuery($query);

        $vehicles = $db->loadObjectList();

        foreach ($vehicles as $vehicle) {
            $globalName = trim(
                (string) (
                    $vehicle->name ?? ''
                )
            );

            $customerName = trim(
                (string) (
                    $vehicle->customer_display_name
                    ?? ''
                )
            );

            $vehicle->global_name =
                $globalName;

            $vehicle->name =
                $customerName !== ''
                    ? $customerName
                    : $globalName;
        }

        return $vehicles;
    }

    public function getVehicle(int $id): ?object
    {
        $userId = $this->getCurrentUserId();

        if (
            $id <= 0
            || $userId <= 0
        ) {
            return null;
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'd.*',
                $db->quoteName(
                    'ud.display_name',
                    'customer_display_name'
                )
            ])
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
                $db->quoteName('d.id')
                . ' = '
                . $id
            )
            ->where(
                $db->quoteName('ud.user_id')
                . ' = '
                . $userId
            );

        $db->setQuery($query);

        $vehicle = $db->loadObject();

        if (!$vehicle) {
            return null;
        }

        $globalName = trim(
            (string) (
                $vehicle->name ?? ''
            )
        );

        $customerName = trim(
            (string) (
                $vehicle->customer_display_name
                ?? ''
            )
        );

        $vehicle->global_name =
            $globalName;

        $vehicle->name =
            $customerName !== ''
                ? $customerName
                : $globalName;

        return $vehicle;
    }

    public function saveVehicle(array $data): bool
    {
        $userId = $this->getCurrentUserId();

        if ($userId <= 0) {
            throw new \RuntimeException(
                'Sie sind nicht angemeldet.'
            );
        }

        $displayName = trim(
            (string) (
                $data['name'] ?? ''
            )
        );

        $uniqueId = trim(
            (string) (
                $data['tracker_unique_id']
                ?? ''
            )
        );

        if ($displayName === '') {
            throw new \RuntimeException(
                'Bitte geben Sie einen Fahrzeugnamen ein.'
            );
        }

        if ($uniqueId === '') {
            throw new \RuntimeException(
                'Bitte geben Sie die Tracker Unique ID ein.'
            );
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from(
                $db->quoteName(
                    '#__gpsportal_devices'
                )
            )
            ->where(
                $db->quoteName(
                    'tracker_unique_id'
                )
                . ' = '
                . $db->quote($uniqueId)
            );

        $db->setQuery($query);

        $existingVehicle = $db->loadObject();

        if ($existingVehicle) {
            $vehicleId = (int) $existingVehicle->id;

            if (
                $this->userOwnsVehicle(
                    $vehicleId,
                    $userId
                )
            ) {
                $query = $db->getQuery(true)
                    ->update(
                        $db->quoteName(
                            '#__gpsportal_user_devices'
                        )
                    )
                    ->set(
                        $db->quoteName(
                            'display_name'
                        )
                        . ' = '
                        . $db->quote(
                            $displayName
                        )
                    )
                    ->set(
                        $db->quoteName(
                            'modified'
                        )
                        . ' = '
                        . $db->quote(
                            date('Y-m-d H:i:s')
                        )
                    )
                    ->where(
                        $db->quoteName('user_id')
                        . ' = '
                        . $userId
                    )
                    ->where(
                        $db->quoteName('device_id')
                        . ' = '
                        . $vehicleId
                    );

                $db->setQuery($query);
                $db->execute();

                return true;
            }

            $link = (object) [
                'user_id'      => $userId,
                'device_id'    => $vehicleId,
                'display_name' => $displayName,
                'created'      => date(
                    'Y-m-d H:i:s'
                ),
                'modified'     => date(
                    'Y-m-d H:i:s'
                )
            ];

            $db->insertObject(
                '#__gpsportal_user_devices',
                $link
            );

            return true;
        }

        $traccarId =
            $this->findTraccarDeviceByUniqueId(
                $uniqueId
            );

        if ($traccarId <= 0) {
            $traccarId =
                $this->createTraccarDevice(
                    $displayName,
                    $uniqueId
                );
        }

        if ($traccarId <= 0) {
            throw new \RuntimeException(
                'Das Traccar-Gerät konnte nicht gefunden oder angelegt werden.'
            );
        }

        $now = date('Y-m-d H:i:s');

        $vehicle = (object) [
            'traccar_device_id' => $traccarId,
            'tracker_unique_id' => $uniqueId,
            'name'              => $displayName,
            'license_plate'     => trim(
                (string) (
                    $data['license_plate']
                    ?? ''
                )
            ),
            'vehicle_type'      => trim(
                (string) (
                    $data['vehicle_type']
                    ?? ''
                )
            ),
            'manufacturer'      => trim(
                (string) (
                    $data['manufacturer']
                    ?? ''
                )
            ),
            'model'             => trim(
                (string) (
                    $data['model']
                    ?? ''
                )
            ),
            'driver'            => trim(
                (string) (
                    $data['driver']
                    ?? ''
                )
            ),
            'cost_center'       => trim(
                (string) (
                    $data['cost_center']
                    ?? ''
                )
            ),
            'initial_odometer_km' => (float) (
                $data['initial_odometer_km']
                ?? 0
            ),
            'color'             => trim(
                (string) (
                    $data['color']
                    ?? ''
                )
            ),
            'marker_icon'       => trim(
                (string) (
                    $data['marker_icon']
                    ?? ''
                )
            ),
            'notes'             => trim(
                (string) (
                    $data['notes']
                    ?? ''
                )
            ),
            'published'         => 1,
            'created'           => $now
        ];

        $db->transactionStart();

        try {
            $db->insertObject(
                '#__gpsportal_devices',
                $vehicle,
                'id'
            );

            $link = (object) [
                'user_id'      => $userId,
                'device_id'    => (int) $vehicle->id,
                'display_name' => $displayName,
                'created'      => $now,
                'modified'     => $now
            ];

            $db->insertObject(
                '#__gpsportal_user_devices',
                $link
            );

            $db->transactionCommit();
        } catch (\Throwable $exception) {
            $db->transactionRollback();

            throw $exception;
        }

        return true;
    }

    public function updateVehicle(
        int $id,
        array $data
    ): bool {
        $userId = $this->getCurrentUserId();

        if (
            $id <= 0
            || $userId <= 0
            || !$this->userOwnsVehicle(
                $id,
                $userId
            )
        ) {
            throw new \RuntimeException(
                'Das Fahrzeug wurde nicht gefunden oder gehört nicht zu Ihrem Konto.'
            );
        }

        $displayName = trim(
            (string) (
                $data['name'] ?? ''
            )
        );

        if ($displayName === '') {
            throw new \RuntimeException(
                'Bitte geben Sie einen Fahrzeugnamen ein.'
            );
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $now = date('Y-m-d H:i:s');

        $db->transactionStart();

        try {
            $query = $db->getQuery(true)
                ->update(
                    $db->quoteName(
                        '#__gpsportal_user_devices'
                    )
                )
                ->set(
                    $db->quoteName(
                        'display_name'
                    )
                    . ' = '
                    . $db->quote(
                        $displayName
                    )
                )
                ->set(
                    $db->quoteName(
                        'modified'
                    )
                    . ' = '
                    . $db->quote($now)
                )
                ->where(
                    $db->quoteName('user_id')
                    . ' = '
                    . $userId
                )
                ->where(
                    $db->quoteName('device_id')
                    . ' = '
                    . $id
                );

            $db->setQuery($query);
            $db->execute();

            $assignmentCount =
                $this->getVehicleAssignmentCount(
                    $id
                );

            /*
             * Gemeinsame technische Stammdaten werden nur verändert,
             * wenn das Fahrzeug ausschließlich diesem Benutzer
             * zugeordnet ist oder ein Super User arbeitet.
             */
            if (
                $assignmentCount <= 1
                || $this->isSuperUser()
            ) {
                $vehicle = (object) [
                    'id'                  => $id,
                    'tracker_unique_id'   => trim(
                        (string) (
                            $data['tracker_unique_id']
                            ?? ''
                        )
                    ),
                    'name'                => $displayName,
                    'license_plate'       => trim(
                        (string) (
                            $data['license_plate']
                            ?? ''
                        )
                    ),
                    'vehicle_type'        => trim(
                        (string) (
                            $data['vehicle_type']
                            ?? ''
                        )
                    ),
                    'manufacturer'        => trim(
                        (string) (
                            $data['manufacturer']
                            ?? ''
                        )
                    ),
                    'model'               => trim(
                        (string) (
                            $data['model']
                            ?? ''
                        )
                    ),
                    'driver'              => trim(
                        (string) (
                            $data['driver']
                            ?? ''
                        )
                    ),
                    'cost_center'         => trim(
                        (string) (
                            $data['cost_center']
                            ?? ''
                        )
                    ),
                    'initial_odometer_km' => (float) (
                        $data['initial_odometer_km']
                        ?? 0
                    ),
                    'color'               => trim(
                        (string) (
                            $data['color']
                            ?? ''
                        )
                    ),
                    'marker_icon'         => trim(
                        (string) (
                            $data['marker_icon']
                            ?? ''
                        )
                    ),
                    'notes'               => trim(
                        (string) (
                            $data['notes']
                            ?? ''
                        )
                    ),
                    'modified'            => $now
                ];

                $db->updateObject(
                    '#__gpsportal_devices',
                    $vehicle,
                    'id'
                );
            } else {
                Factory::getApplication()
                    ->enqueueMessage(
                        'Der persönliche Fahrzeugname wurde gespeichert. Die technischen Stammdaten wurden nicht verändert, da das Fahrzeug mehreren Kunden zugeordnet ist.',
                        'warning'
                    );
            }

            $db->transactionCommit();
        } catch (\Throwable $exception) {
            $db->transactionRollback();

            throw $exception;
        }

        return true;
    }

    public function deleteVehicle(int $id): bool
    {
        $userId = $this->getCurrentUserId();

        if (
            $id <= 0
            || $userId <= 0
            || !$this->userOwnsVehicle(
                $id,
                $userId
            )
        ) {
            throw new \RuntimeException(
                'Das Fahrzeug wurde nicht gefunden oder gehört nicht zu Ihrem Konto.'
            );
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $db->transactionStart();

        try {
            $query = $db->getQuery(true)
                ->delete(
                    $db->quoteName(
                        '#__gpsportal_user_devices'
                    )
                )
                ->where(
                    $db->quoteName('user_id')
                    . ' = '
                    . $userId
                )
                ->where(
                    $db->quoteName('device_id')
                    . ' = '
                    . $id
                );

            $db->setQuery($query);
            $db->execute();

            $remainingAssignments =
                $this->getVehicleAssignmentCount(
                    $id
                );

            if ($remainingAssignments === 0) {
                $query = $db->getQuery(true)
                    ->delete(
                        $db->quoteName(
                            '#__gpsportal_devices'
                        )
                    )
                    ->where(
                        $db->quoteName('id')
                        . ' = '
                        . $id
                    );

                $db->setQuery($query);
                $db->execute();
            }

            $db->transactionCommit();
        } catch (\Throwable $exception) {
            $db->transactionRollback();

            throw $exception;
        }

        return true;
    }
}
