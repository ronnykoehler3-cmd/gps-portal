<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\View\Logbook;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TraccarModel;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TripGroupModel;

class HtmlView extends BaseHtmlView
{
    public $devices = [];
    public $trips = [];
    public $groups = [];
    public $summary = [];
    public $deviceId = 0;
    public $from = '';
    public $to = '';

    public $message = '';

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
                }
            }
        }

        $selectedTrips =
            $app->input->post->get(
                'selectedTrips',
                [],
                'array'
            );

        $groupModel =
            new TripGroupModel();

        if (!empty($selectedTrips))
        {
            $groupId =
                $groupModel->createTripGroup(
                    $this->deviceId,
                    $selectedTrips,
                    $this->trips
                );

            $this->message =
                'Sammelfahrt #'
                . $groupId
                . ' erstellt ('
                . count($selectedTrips)
                . ' Fahrten)';
        }
$this->summary =
    $groupModel->getMileageSummary();

        $this->groups =
            $groupModel->getGroups();

        foreach ($this->groups as &$group)
        {
            $group['items'] =
                $groupModel->getGroupItems(
                    $group['id']
                );

            $group['distance'] =
                $groupModel->getGroupDistance(
                    $group['id']
                );

            $group['duration'] =
                $groupModel->getGroupDuration(
                    $group['id']
                );
        }

        parent::display($tpl);
    }
}
