<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Livemap;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\TraccarModel;
use TKKundendienst\Component\Gpsportal\Site\Model\GeofencesModel;
use TKKundendienst\Component\Gpsportal\Site\Service\TripService;
use TKKundendienst\Component\Gpsportal\Site\Service\UserSettingsService;

class HtmlView extends BaseHtmlView
{
    public $devices = [];
    public $positions = [];
    public $vehicleMeta = [];
    public $traccarUserId = null;
    public $geofences = [];
    public $trails = [];
    public $tripEndStopMinutes = UserSettingsService::DEFAULT_TRIP_STOP_MINUTES;

    public $showGeofences = true;
    public $rememberMapPosition = true;
    public $popupGeofenceEvents = true;
    public $showVehicleNames = true;
    public $vehicleDisplayMode = 'name';

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

        /*
         * Beim Öffnen der Livekarte wird die aktuelle beziehungsweise
         * zuletzt abgeschlossene Tour serverseitig geladen. Die Spur ist
         * dadurch auch nach Abmeldung, Browserwechsel oder neuem Login da.
         */
        $tripService = new TripService();
        $this->tripEndStopMinutes = (new UserSettingsService())
            ->getTripStopMinutes();
        $initialDeviceId = (int) Factory::getApplication()
            ->input
            ->getInt('trail_device');

        if ($initialDeviceId <= 0 && !empty($this->positions)) {
            $initialDeviceId = (int) ($this->positions[0]['deviceId'] ?? 0);
        }

        if ($initialDeviceId > 0) {
            $trailPositions = $model->getLatestTripPositions(
                $initialDeviceId
            );

            /* Höchstens 4.000 Punkte gleichzeitig im Seitenaufbau. */
            $trailPositions = array_slice($trailPositions, -4000);
            $analysis = $tripService->analyse(
                $trailPositions,
                $this->tripEndStopMinutes
            );
            $trips = $analysis['trips'];

            if (!empty($trips)) {
                $lastTrip = $trips[count($trips) - 1];
                $this->trails[$initialDeviceId] = [
                    'trip' => $lastTrip,
                    'stops' => array_values(
                        array_filter(
                            $analysis['stops'],
                            static fn (array $stop): bool =>
                                $stop['timestamp'] >= $lastTrip['startTimestamp']
                        )
                    ),
                ];
            }
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $user = Factory::getApplication()
            ->getIdentity();

        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__gpsportal_user_settings')
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

            $this->showVehicleNames = true;

            $vehicleDisplayMode = (string) (
                $settings->vehicle_display_mode ?? 'name'
            );

            if (
                in_array(
                    $vehicleDisplayMode,
                    [
                        'name',
                        'license_plate',
                        'name_and_plate'
                    ],
                    true
                )
            ) {
                $this->vehicleDisplayMode =
                    $vehicleDisplayMode;
            }
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
