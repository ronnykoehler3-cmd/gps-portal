<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Geofenceevents;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $events = [];

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user || (int) $user->id <= 0) {
            throw new \RuntimeException(
                'Für diese Ansicht ist eine Anmeldung erforderlich.'
            );
        }

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
                $db->quoteName('e.event_time')
                . ' DESC'
            );

        $db->setQuery($query);

        $this->events = $db->loadObjectList() ?: [];

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }
}
