<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Geofenceevents;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $events = [];

    public function display($tpl = null)
    {
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

            ->order('e.event_time DESC');

        $db->setQuery($query);

        $this->events = $db->loadObjectList();

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }
}
