<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\View\Settings;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Administrator\Model\SettingsModel;

class HtmlView extends BaseHtmlView
{
    public $traccarUrl = '';
    public $traccarUser = '';
    public $traccarPassword = '';

    public function display($tpl = null)
    {
        $model = new SettingsModel();

        $this->traccarUrl =
            $model->getSetting('traccar_url');

        $this->traccarUser =
            $model->getSetting('traccar_user');

        $this->traccarPassword =
            $model->getSetting('traccar_password');

        parent::display($tpl);
    }
}
