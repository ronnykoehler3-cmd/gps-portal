<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use TKKundendienst\Component\Gpsportal\Site\Model\DemovehiclesModel;
use TKKundendienst\Component\Gpsportal\Site\Service\AdministratorService;

final class DemovehiclesController extends BaseController
{
    private function executeAction(callable $action, string $success): void
    {
        Session::checkToken('post') or jexit('Ungültiges Sicherheitstoken.');
        $app = Factory::getApplication();
        $status = 'success';
        $message = $success;

        try {
            (new AdministratorService())->assertAdministrator();
            $action($app, new DemovehiclesModel());
            $app->enqueueMessage($success);
        } catch (\Throwable $error) {
            $status = 'error';
            $message = $error->getMessage();
            $app->enqueueMessage($error->getMessage(), 'error');
        }

        $app->redirect(
            'index.php?option=com_gpsportal&view=demovehicles'
            . '&gps_status=' . rawurlencode($status)
            . '&gps_message=' . rawurlencode($message)
        );
    }

    public function saveCustomer(): void
    {
        $this->executeAction(
            static function ($app, DemovehiclesModel $model): void {
                $model->saveCustomer(
                    $app->input->post->getString('customer_name'),
                    $app->input->post->getString('customer_number')
                );
            },
            'Der Kunde wurde angelegt.'
        );
    }

    public function assignUser(): void
    {
        $this->executeAction(
            static function ($app, DemovehiclesModel $model): void {
                $model->assignUser(
                    $app->input->post->getInt('customer_id'),
                    $app->input->post->getInt('user_id')
                );
            },
            'Der Benutzer wurde dem Kunden zugeordnet.'
        );
    }

    public function saveVehicle(): void
    {
        $this->executeAction(
            static function ($app, DemovehiclesModel $model): void {
                $model->saveDemoVehicle([
                    'customer_id' => $app->input->post->getInt('customer_id'),
                    'name' => $app->input->post->getString('name'),
                    'unique_id' => $app->input->post->getString('unique_id'),
                    'license_plate' => $app->input->post->getString('license_plate'),
                    'region' => $app->input->post->getString('region'),
                    'start_address' => $app->input->post->getString('start_address'),
                    'destinations' => $app->input->post->getString('destinations'),
                    'minimum_speed_kmh' => $app->input->post->getInt('minimum_speed_kmh'),
                    'maximum_speed_kmh' => $app->input->post->getInt('maximum_speed_kmh'),
                    'active' => $app->input->post->getInt('active'),
                ]);
            },
            'Das Dummyfahrzeug wurde gespeichert und zur Synchronisation vorgemerkt.'
        );
    }
}
