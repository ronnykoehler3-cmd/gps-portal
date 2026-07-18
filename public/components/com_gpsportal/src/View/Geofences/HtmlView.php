<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Geofences;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use TKKundendienst\Component\Gpsportal\Site\Model\GeofencesModel;

class HtmlView extends BaseHtmlView
{
    public $geofences = [];
    public $editGeofence = null;

    public function display($tpl = null)
    {
        $model = new GeofencesModel();

        $this->geofences =
            $model->getGeofences();
        $this->editGeofence = $model->getGeofence(
            Factory::getApplication()->input->getInt('edit', 0)
        );

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }
}
