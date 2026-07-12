<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Api;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\TraccarModel;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $model = new TraccarModel();

        $model->checkGeofenceEvents();

        $devices = $model->getDevices();
        $positions = $model->getPositions();

        $deviceNames = [];
        $allowedDeviceIds = [];

        foreach ($devices as $device) {
            $deviceId = (int) ($device['id'] ?? 0);

            if ($deviceId <= 0) {
                continue;
            }

            $allowedDeviceIds[] = $deviceId;

            $deviceNames[$deviceId] = trim(
                (string) (
                    $device['displayName']
                    ?? $device['name']
                    ?? ('Fahrzeug ' . $deviceId)
                )
            );
        }

        $events = [];

        if (!empty($allowedDeviceIds)) {
            $db = Factory::getContainer()
                ->get('DatabaseDriver');

            $query = $db->getQuery(true)
                ->select([
                    'e.*',
                    'g.name AS geofence_name'
                ])
                ->from(
                    $db->quoteName(
                        '#__gpsportal_geofence_events',
                        'e'
                    )
                )
                ->join(
                    'LEFT',
                    $db->quoteName(
                        '#__gpsportal_geofences',
                        'g'
                    )
                    . ' ON '
                    . $db->quoteName('g.id')
                    . ' = '
                    . $db->quoteName('e.geofence_id')
                )
                ->where(
                    $db->quoteName('g.user_id')
                    . ' = '
                    . (int) Factory::getApplication()
                        ->getIdentity()
                        ->id
                )
                ->where(
                    $db->quoteName('e.device_id')
                    . ' IN ('
                    . implode(
                        ',',
                        array_map(
                            'intval',
                            $allowedDeviceIds
                        )
                    )
                    . ')'
                )
                ->order(
                    $db->quoteName('e.id')
                    . ' DESC'
                );

            $db->setQuery($query, 0, 20);

            $events = $db->loadObjectList();

            foreach ($events as $event) {
                $deviceId = (int) (
                    $event->device_id ?? 0
                );

                $event->vehicle_name =
                    $deviceNames[$deviceId]
                    ?? ('Fahrzeug ' . $deviceId);
            }
        }

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode(
            [
                'devices'        => $devices,
                'positions'      => $positions,
                'vehicleMeta'    => $model->getVehicleMeta(),
                'geofenceEvents' => $events
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        exit;
    }
}
