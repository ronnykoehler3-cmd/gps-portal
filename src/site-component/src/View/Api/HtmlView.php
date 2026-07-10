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

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)

            ->select([
                'e.*',
                'g.name AS geofence_name',
                'd.name AS vehicle_name'
            ])

            ->from('#__gpsportal_geofence_events e')

            ->join(
                'LEFT',
                '#__gpsportal_geofences g
                ON g.id = e.geofence_id'
            )

            ->join(
                'LEFT',
                '#__gpsportal_devices d
                ON d.traccar_device_id = e.device_id'
            )

            ->order('e.id DESC');

        $db->setQuery($query, 0, 20);

        $events = $db->loadObjectList();

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode([
            'devices'        => $model->getDevices(),
            'positions'      => $model->getPositions(),
            'vehicleMeta'    => $model->getVehicleMeta(),
            'geofenceEvents' => $events
        ]);

        exit;
    }
}
