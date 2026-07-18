<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Settings;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $settings = null;

    public function display($tpl = null)
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $user = Factory::getApplication()
            ->getIdentity();

        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__gpsportal_user_settings')
            ->where('user_id=' . (int) $user->id);

        $db->setQuery($query);

        $this->settings = $db->loadObject();

        if (!$this->settings)
        {
            $this->settings = (object) [

                'show_vehicle_names'    => 1,
                'vehicle_display_mode'  => 'name',
                'show_geofences'        => 1,
                'remember_map_position' => 1,
                'refresh_interval'      => 5,
                'trip_stop_minutes'     => 45,

                'popup_geofence_events' => 1,
                'popup_offline_events'  => 0,

                'email_geofence_events' => 0,
                'email_offline_events'  => 0
            ];
        }

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }
}
