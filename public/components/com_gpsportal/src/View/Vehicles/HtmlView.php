<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Vehicles;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\VehiclesModel;

class HtmlView extends BaseHtmlView
{
    public $vehicles = [];

    public function display($tpl = null)
    {

$user = \Joomla\CMS\Factory::getApplication()->getIdentity();


        $model = new VehiclesModel();

        $this->vehicles = $model->getVehicles();

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }
}
