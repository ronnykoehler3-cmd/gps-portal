<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TraccarModel;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TodoModel;

class HtmlView extends BaseHtmlView
{
    public $deviceCount = 0;
    public $onlineCount = 0;
    public $offlineCount = 0;
    public $todoProgress = 0;

    public $devices = [];

    public function display($tpl = null)
    {
        $traccar = new TraccarModel();

        $devices = $traccar->getDevices();

        if (is_array($devices))
        {
            $this->devices = $devices;
            $this->deviceCount = count($devices);

            foreach ($devices as $device)
            {
                if (($device['status'] ?? '') === 'online')
                {
                    $this->onlineCount++;
                }
                else
                {
                    $this->offlineCount++;
                }
            }
        }

        $todoModel = new TodoModel();

        $todos = $todoModel->getTodos();

        $total = count($todos);
        $done  = 0;

        foreach ($todos as $todo)
        {
            if (!empty($todo['status']))
            {
                $done++;
            }
        }

        if ($total > 0)
        {
            $this->todoProgress =
                round(($done / $total) * 100);
        }

        parent::display($tpl);
    }
}
