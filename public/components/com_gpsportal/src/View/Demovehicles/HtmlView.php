<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\View\Demovehicles;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\DemovehiclesModel;
use TKKundendienst\Component\Gpsportal\Site\Service\AdministratorService;

final class HtmlView extends BaseHtmlView
{
    public array $customers = [];
    public array $users = [];
    public array $vehicles = [];
    public ?object $editVehicle = null;

    public function display($tpl = null): void
    {
        (new AdministratorService())->assertAdministrator();
        $model = new DemovehiclesModel();
        $this->customers = $model->getCustomers();
        $this->users = $model->getUsers();
        $this->vehicles = $model->getDemoVehicles();
        $this->editVehicle = $model->getDemoVehicle(
            (int) Factory::getApplication()->input->getInt('edit', 0)
        );

        ob_start();
        parent::display($tpl);
        $content = ob_get_clean();
        require JPATH_SITE . '/components/com_gpsportal/layouts/app.php';
    }
}
