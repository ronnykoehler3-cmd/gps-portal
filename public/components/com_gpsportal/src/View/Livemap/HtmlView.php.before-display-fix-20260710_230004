<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Livemap;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\TraccarModel;
use TKKundendienst\Component\Gpsportal\Site\Model\GeofencesModel;

class HtmlView extends BaseHtmlView
{
    public $devices = [];
    public $positions = [];
    public $vehicleMeta = [];
    public $traccarUserId = null;
    public $geofences = [];

    public $showGeofences = true;
    public $rememberMapPosition = true;
    public $popupGeofenceEvents = true;
    public $showVehicleNames = true;
    
    public function display($tpl = null)
    {
        $model = new TraccarModel();

        $this->traccarUserId =
            $model->getCurrentTraccarUserId();

        $this->devices =
            $model->getDevices();

        $this->positions =
            $model->getPositions();

        $this->vehicleMeta =
            $model->getVehicleMeta();

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $user = Factory::getApplication()
            ->getIdentity();

        $query = $db->getQuery(true)

            ->select('*')

            ->from(
                '#__gpsportal_user_settings'
            )

            ->where(
                'user_id=' . (int) $user->id
            );

        $db->setQuery($query);

        $settings = $db->loadObject();

        if ($settings)
        {
            $this->showGeofences =
                (bool) $settings->show_geofences;

            $this->rememberMapPosition =
                (bool) $settings->remember_map_position;

            $this->popupGeofenceEvents =
                (bool) $settings->popup_geofence_events;
	    $this->showVehicleNames =
	        (bool) $settings->show_vehicle_names;
        }

        $geofenceModel = new GeofencesModel();

        $this->geofences =
            $geofenceModel->getGeofences();

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }
}
