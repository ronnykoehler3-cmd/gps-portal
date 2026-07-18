<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Api;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\TraccarModel;
use TKKundendienst\Component\Gpsportal\Site\Service\TripService;
use TKKundendienst\Component\Gpsportal\Site\Service\UserSettingsService;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        if (!$user || (int) $user->id <= 0) {
            http_response_code(401);

            echo json_encode(
                [
                    'error' =>
                        'Für diese API ist eine Anmeldung erforderlich.',
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

            exit;
        }

        $model = new TraccarModel();

        $model->checkGeofenceEvents();

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'e.*',
                'g.name AS geofence_name',
                'd.name AS vehicle_name',
            ])
            ->from(
                $db->quoteName(
                    '#__gpsportal_geofence_events',
                    'e'
                )
            )
            ->join(
                'INNER',
                $db->quoteName(
                    '#__gpsportal_devices',
                    'd'
                )
                . ' ON '
                . $db->quoteName('d.traccar_device_id')
                . ' = '
                . $db->quoteName('e.device_id')
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
            ->join(
                'INNER',
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
                $db->quoteName('ud.user_id')
                . ' = '
                . (int) $user->id
            )
            ->where(
                $db->quoteName('g.user_id')
                . ' = '
                . (int) $user->id
            )
            ->order(
                $db->quoteName('e.id')
                . ' DESC'
            );

        $db->setQuery($query, 0, 20);

        $events = $db->loadObjectList() ?: [];

        $devices = $model->getDevices();
        $vehicleMeta = $model->getVehicleMeta();

        foreach ($devices as &$device) {
            $deviceId = (int) ($device['id'] ?? 0);
            $meta = $vehicleMeta[$deviceId] ?? [];

            $device['license_plate'] =
                (string) ($meta['license_plate'] ?? '');
        }

        unset($device);

        $trail = null;
        $trailDeviceId = (int) $app->input->getInt('trailDevice');

        if ($trailDeviceId > 0) {
            $trailPositions = array_slice(
                $model->getLatestTripPositions($trailDeviceId),
                -4000
            );
            $tripEndStopMinutes = (new UserSettingsService())
                ->getTripStopMinutes((int) $user->id);
            $analysis = (new TripService())->analyse(
                $trailPositions,
                $tripEndStopMinutes
            );
            $trips = $analysis['trips'];

            if (!empty($trips)) {
                $latestTrip = $trips[count($trips) - 1];
                $trail = [
                    'trip' => $latestTrip,
                    'stops' => array_values(
                        array_filter(
                            $analysis['stops'],
                            static fn (array $stop): bool =>
                                $stop['timestamp'] >= $latestTrip['startTimestamp']
                        )
                    ),
                ];
            }
        }

        echo json_encode(
            [
                'devices' =>
                    $devices,

                'positions' =>
                    $model->getPositions(),

                'vehicleMeta' =>
                    $vehicleMeta,

                'geofenceEvents' =>
                    $events,

                'trail' =>
                    $trail,
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        exit;
    }
}
