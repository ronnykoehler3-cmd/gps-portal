<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Geofenceevents;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\TraccarModel;

class HtmlView extends BaseHtmlView
{
    public array $events = [];

    public function display($tpl = null)
    {
        $traccarModel = new TraccarModel();
        $devices = $traccarModel->getDevices();

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

        if (empty($allowedDeviceIds)) {
            $this->events = [];
        } else {
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
                    $db->quoteName('e.event_time')
                    . ' DESC'
                );

            $db->setQuery($query);

            $events = $db->loadObjectList();

            foreach ($events as $event) {
                $deviceId = (int) (
                    $event->device_id ?? 0
                );

                $event->vehicle_name =
                    $deviceNames[$deviceId]
                    ?? ('Fahrzeug ' . $deviceId);
            }

            $this->events = $events;
        }

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }
}
