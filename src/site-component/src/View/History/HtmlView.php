<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\History;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\VehiclesModel;
use TKKundendienst\Component\Gpsportal\Site\Model\TraccarModel;

class HtmlView extends BaseHtmlView
{
    public $vehicles = [];
    public $history = [];
    public $distanceKm = 0;
    public $vehicleId = 0;
    public $from = '';
    public $to = '';

    public function display($tpl = null)
    {
        $vehiclesModel = new VehiclesModel();
        $traccarModel  = new TraccarModel();

        $this->vehicles =
            $vehiclesModel->getVehicles();

        $app = Factory::getApplication();

        $this->vehicleId =
            (int) $app->input->getInt('vehicle');

        $this->from =
            $app->input->getString('from');

        $this->to =
            $app->input->getString('to');

        if (
            $this->vehicleId > 0
            && !empty($this->from)
            && !empty($this->to)
        )
        {
            $this->history =
                $traccarModel->getHistory(
                    $this->vehicleId,
                    date('c', strtotime($this->from)),
                    date('c', strtotime($this->to))
                );
$this->distanceKm = 0;

for ($i = 1; $i < count($this->history); $i++)
{
$lat1 = (float)$this->history[$i - 1]['latitude'];
$lon1 = (float)$this->history[$i - 1]['longitude'];

$lat2 = (float)$this->history[$i]['latitude'];
$lon2 = (float)$this->history[$i]['longitude'];
    $earthRadius = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a =
        sin($dLat / 2) * sin($dLat / 2)
        +
        cos(deg2rad($lat1))
        * cos(deg2rad($lat2))
        *
        sin($dLon / 2)
        * sin($dLon / 2);

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    $this->distanceKm +=
        $earthRadius * $c;
}

$this->distanceKm =
    round($this->distanceKm, 1);
        }

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }
}
