<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\View\Vehicle;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TraccarModel;

class HtmlView extends BaseHtmlView
{
    public $devices = [];
    public $positions = [];

    public $position = [];
    public $deviceName = '';
    public $deviceId = 0;

    public function display($tpl = null)
    {
        $traccar = new TraccarModel();

        $devices = $traccar->getDevices();
        $positions = $traccar->getPositions();

        $this->devices = $devices;
        $this->positions = $positions;

        $selectedDeviceId =
            Factory::getApplication()
                ->input
                ->getInt('deviceId', 0);

        if ($selectedDeviceId === 0 && !empty($devices))
        {
            $selectedDeviceId =
                (int) $devices[0]['id'];
        }

        $this->deviceId = $selectedDeviceId;

        foreach ($devices as $device)
        {
            if ((int)$device['id'] === $selectedDeviceId)
            {
                $this->deviceName =
                    $device['name'];

                break;
            }
        }

        foreach ($positions as $position)
        {
            if (
                (int)$position['deviceId']
                === $selectedDeviceId
            )
            {
                $this->position =
                    $position;

                break;
            }
        }

        parent::display($tpl);
    }
}
