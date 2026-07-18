<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use TKKundendienst\Component\Gpsportal\Site\Service\AdministratorService;
use TKKundendienst\Component\Gpsportal\Site\Service\VehicleVisibilityService;

final class VehiclesController extends BaseController
{
    public function hide(): void
    {
        Session::checkToken('post') or jexit('Ungültiges Sicherheitstoken.');
        $app = Factory::getApplication();

        try {
            (new VehicleVisibilityService())->hideForCurrentUser(
                $app->input->post->getInt('device_id')
            );
            $app->enqueueMessage('Das Fahrzeug wurde aus deiner Ansicht entfernt.');
        } catch (\Throwable $error) {
            $app->enqueueMessage($error->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_gpsportal&view=vehicles');
    }

    public function restore(): void
    {
        Session::checkToken('post') or jexit('Ungültiges Sicherheitstoken.');
        $app = Factory::getApplication();

        try {
            (new VehicleVisibilityService())->showForCurrentUser(
                $app->input->post->getInt('device_id')
            );
            $app->enqueueMessage('Das Fahrzeug wird wieder angezeigt.');
        } catch (\Throwable $error) {
            $app->enqueueMessage($error->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_gpsportal&view=vehicles');
    }

    public function delete(): void
    {
        Session::checkToken('post') or jexit('Ungültiges Sicherheitstoken.');
        (new AdministratorService())->assertAdministrator();

        throw new \RuntimeException(
            'Das endgültige Löschen wird erst nach erfolgreicher Simulator-Synchronisation freigeschaltet.'
        );
    }
}
