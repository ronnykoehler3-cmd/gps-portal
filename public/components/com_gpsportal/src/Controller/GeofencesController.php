<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use TKKundendienst\Component\Gpsportal\Site\Model\GeofencesModel;

final class GeofencesController extends BaseController
{
    public function save(): void
    {
        Session::checkToken('post') or jexit('Ungültiges Sicherheitstoken.');
        $app = Factory::getApplication();

        try {
            $input = $app->input;
            (new GeofencesModel())->saveGeofence([
                'id' => $input->post->getInt('id'),
                'name' => $input->post->getString('name'),
                'zone_type' => $input->post->getCmd('zone_type'),
                'address' => $input->post->getString('address'),
                'country_code' => $input->post->getCmd('country_code'),
                'country_name' => $input->post->getString('country_name'),
                'radius' => $input->post->getInt('radius'),
                'status_color' => $input->post->getCmd('status_color'),
                'warning_buffer_km' => $input->post->getInt('warning_buffer_km'),
            ]);
            $app->enqueueMessage(
                'Die Geozone wurde gespeichert und in der Datenbank überprüft.'
            );
        } catch (\Throwable $error) {
            $app->enqueueMessage($error->getMessage(), 'error');
        }

        $app->redirect(
            'index.php?option=com_gpsportal&view=geofences&saved=' . time()
        );
    }

    public function delete(): void
    {
        Session::checkToken('post') or jexit('Ungültiges Sicherheitstoken.');
        $app = Factory::getApplication();

        try {
            (new GeofencesModel())->deleteGeofence($app->input->post->getInt('id'));
            $app->enqueueMessage('Die Geozone wurde gelöscht.');
        } catch (\Throwable $error) {
            $app->enqueueMessage($error->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_gpsportal&view=geofences');
    }
}
