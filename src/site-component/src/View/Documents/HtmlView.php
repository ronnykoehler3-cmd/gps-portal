<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Documents;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\DocumentsModel;

class HtmlView extends BaseHtmlView
{
    public $vehicles = [];
    public $documents = [];

    public function display($tpl = null)
    {
        $model = new DocumentsModel();

        $this->vehicles = $model->getVehicles();

        if (!empty($_GET['vehicle_id']))
        {
            $this->documents = $model->getDocuments(
                (int) $_GET['vehicle_id']
            );
        }

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }
}
