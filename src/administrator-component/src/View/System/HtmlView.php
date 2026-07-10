<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\View\System;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Administrator\Model\SystemModel;

class HtmlView extends BaseHtmlView
{
    public $settings = [];

    public function display($tpl = null)
    {
        $model = new SystemModel();

        $this->settings =
            $model->getSettings();

        parent::display($tpl);
    }
}
