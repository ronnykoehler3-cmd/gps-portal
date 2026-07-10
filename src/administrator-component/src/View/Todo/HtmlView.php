<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\View\Todo;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TodoModel;

class HtmlView extends BaseHtmlView
{
    public $todos = [];

    public function display($tpl = null)
    {
        $model = new TodoModel();

        $this->todos = $model->getTodos();

        parent::display($tpl);
    }
}
