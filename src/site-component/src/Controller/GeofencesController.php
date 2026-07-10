<?php

namespace TKKundendienst\Component\Gpsportal\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use TKKundendienst\Component\Gpsportal\Site\Model\GeofencesModel;

class GeofencesController extends BaseController
{
    public function save()
    {
        $app = Factory::getApplication();

        $model = new GeofencesModel();

        $model->saveGeofence([
            'name'      => $_POST['name'] ?? '',
            'latitude'  => $_POST['latitude'] ?? 0,
            'longitude' => $_POST['longitude'] ?? 0,
            'radius'    => $_POST['radius'] ?? 100
        ]);

        $app->enqueueMessage(
            'Geozone gespeichert'
        );

        $app->redirect(
            'index.php?option=com_gpsportal&view=geofences'
        );
    }

    public function delete()
    {
        $app = Factory::getApplication();

        $model = new GeofencesModel();

        $model->deleteGeofence(
            (int) ($_GET['id'] ?? 0)
        );

        $app->enqueueMessage(
            'Geozone gelöscht'
        );

        $app->redirect(
            'index.php?option=com_gpsportal&view=geofences'
        );
    }
}
