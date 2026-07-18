<?php

namespace TKKundendienst\Component\Gpsportal\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use TKKundendienst\Component\Gpsportal\Site\Service\UserSettingsService;

class SettingsController extends BaseController
{
    public function save()
    {
        $app = Factory::getApplication();

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $user = $app->getIdentity();
        $userSettingsService = new UserSettingsService();
        $userSettingsService->ensureTripStopMinutesColumn();
        $tripStopMinutes = $userSettingsService->normaliseTripStopMinutes(
            $app->input->getInt(
                'trip_stop_minutes',
                UserSettingsService::DEFAULT_TRIP_STOP_MINUTES
            )
        );

        $showVehicleNames =
            $app->input->getInt(
                'show_vehicle_names',
                0
            );

        $vehicleDisplayMode = $app->input->getCmd('vehicle_display_mode', 'name');
        if (!in_array($vehicleDisplayMode, ['name', 'license_plate', 'name_and_plate'], true)) {
            $vehicleDisplayMode = 'name';
        }

        $showGeofences =
            $app->input->getInt(
                'show_geofences',
                0
            );

        $rememberMapPosition =
            $app->input->getInt(
                'remember_map_position',
                0
            );

        $refreshInterval =
            $app->input->getInt(
                'refresh_interval',
                5
            );

        $popupGeofenceEvents =
            $app->input->getInt(
                'popup_geofence_events',
                0
            );

        $popupOfflineEvents =
            $app->input->getInt(
                'popup_offline_events',
                0
            );

        $emailGeofenceEvents =
            $app->input->getInt(
                'email_geofence_events',
                0
            );

        $emailOfflineEvents =
            $app->input->getInt(
                'email_offline_events',
                0
            );

        $query = $db->getQuery(true)

            ->select('id')

            ->from(
                '#__gpsportal_user_settings'
            )

            ->where(
                'user_id=' . (int) $user->id
            );

        $db->setQuery($query);

        $existingId =
            (int) $db->loadResult();

        if ($existingId)
        {
            $query = $db->getQuery(true)

                ->update(
                    '#__gpsportal_user_settings'
                )

                ->set(
                    'show_vehicle_names='
                    . (int) $showVehicleNames
                )

                ->set(
                    'vehicle_display_mode='
                    . $db->quote($vehicleDisplayMode)
                )

                ->set(
                    'trip_stop_minutes='
                    . (int) $tripStopMinutes
                )

                ->set(
                    'show_geofences='
                    . (int) $showGeofences
                )

                ->set(
                    'remember_map_position='
                    . (int) $rememberMapPosition
                )

                ->set(
                    'refresh_interval='
                    . (int) $refreshInterval
                )

                ->set(
                    'popup_geofence_events='
                    . (int) $popupGeofenceEvents
                )

                ->set(
                    'popup_offline_events='
                    . (int) $popupOfflineEvents
                )

                ->set(
                    'email_geofence_events='
                    . (int) $emailGeofenceEvents
                )

                ->set(
                    'email_offline_events='
                    . (int) $emailOfflineEvents
                )

                ->where(
                    'user_id=' . (int) $user->id
                );

            $db->setQuery($query);
            $db->execute();
        }
        else
        {
            $row = (object) [

                'user_id'
                    => (int) $user->id,

                'show_vehicle_names'
                    => (int) $showVehicleNames,

                'vehicle_display_mode'
                    => $vehicleDisplayMode,

                'trip_stop_minutes'
                    => (int) $tripStopMinutes,

                'show_geofences'
                    => (int) $showGeofences,

                'remember_map_position'
                    => (int) $rememberMapPosition,

                'refresh_interval'
                    => (int) $refreshInterval,

                'popup_geofence_events'
                    => (int) $popupGeofenceEvents,

                'popup_offline_events'
                    => (int) $popupOfflineEvents,

                'email_geofence_events'
                    => (int) $emailGeofenceEvents,

                'email_offline_events'
                    => (int) $emailOfflineEvents
            ];

            $db->insertObject(
                '#__gpsportal_user_settings',
                $row
            );
        }

        $app->enqueueMessage(
            'Einstellungen gespeichert'
        );

        $app->redirect(
            'index.php?option=com_gpsportal&view=settings'
        );
    }
}
