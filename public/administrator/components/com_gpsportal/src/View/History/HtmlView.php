<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\View\History;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TraccarModel;

class HtmlView extends BaseHtmlView
{
    public $devices = [];
    public $trips = [];

    public $tripCount = 0;
    public $totalMinutes = 0;
    public $totalDistance = 0;
    public $longestTrip = 0;

    public $deviceId = 0;
    public $from = '';
    public $to = '';

    public function display($tpl = null)
    {
        $traccar = new TraccarModel();

        $this->devices =
            $traccar->getDevices();

        $app = Factory::getApplication();

        $this->deviceId =
            $app->input->getInt(
                'deviceId',
                0
            );

        $this->from =
            $app->input->getString(
                'from',
                date('Y-m-d')
            );

        $this->to =
            $app->input->getString(
                'to',
                date('Y-m-d')
            );

        if ($this->deviceId > 0)
        {
            $from =
                $this->from
                . 'T00:00:00Z';

            $to =
                $this->to
                . 'T23:59:59Z';

            $trips =
                $traccar->getTrips(
                    $this->deviceId,
                    $from,
                    $to
                );

            if (is_array($trips))
            {
                foreach ($trips as $trip)
                {
                    if (
                        ($trip['distance'] ?? 0)
                        < 100
                    )
                    {
                        continue;
                    }

                    $this->trips[] =
                        $trip;

                    $this->tripCount++;

                    $duration =
                        round(
                            ($trip['duration'] ?? 0)
                            / 60000
                        );

                    $this->totalMinutes +=
                        $duration;

                    $this->totalDistance +=
                        ($trip['distance'] ?? 0);

                    if (
                        $duration >
                        $this->longestTrip
                    )
                    {
                        $this->longestTrip =
                            $duration;
                    }
                }
            }
        }

        $this->totalDistance =
            round(
                $this->totalDistance
                / 1000,
                2
            );

parent::display($tpl);
}
}
