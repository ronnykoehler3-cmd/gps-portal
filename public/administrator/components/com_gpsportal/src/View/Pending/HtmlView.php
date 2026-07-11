<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\View\Pending;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Administrator\Model\PendingModel;

class HtmlView extends BaseHtmlView
{
    public $devices = [];

    public function display($tpl = null)
    {
        $model = new PendingModel();

        $this->devices =
            $model->getPendingDevices();

        parent::display($tpl);
    }
}
