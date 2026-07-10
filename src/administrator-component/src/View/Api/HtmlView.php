<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\View\Api;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TraccarModel;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $app = Factory::getApplication();

        $deviceId =
            $app->input->getInt('deviceId', 0);

        $traccar = new TraccarModel();

        $positions = $traccar->getPositions();

        $result = [];

        foreach ($positions as $position)
        {
            if (
                (int)$position['deviceId']
                === $deviceId
            )
            {
                $result = $position;
                break;
            }
        }

        header('Content-Type: application/json');

        echo json_encode($result);

        $app->close();
    }
}
