<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Logbook;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\VehiclesModel;
use TKKundendienst\Component\Gpsportal\Site\Model\TraccarModel;
use TKKundendienst\Component\Gpsportal\Site\Model\LogbookModel;

class HtmlView extends BaseHtmlView
{
    public array $vehicles = [];
    public array $history = [];
    public array $trips = [];
    public int $vehicleId = 0;
    public string $from = '';
    public string $to = '';

    public function display($tpl = null)
    {
        $vehiclesModel = new VehiclesModel();
        $traccarModel = new TraccarModel();
        $logbookModel = new LogbookModel();

        $this->vehicles = $vehiclesModel->getVehicles();

        $app = Factory::getApplication();

        $this->vehicleId = (int) $app->input->getInt('vehicle');
        $this->from = (string) $app->input->getString('from');
        $this->to = (string) $app->input->getString('to');

        $selectedVehicle = $this->findSelectedVehicle();

        if (
            $this->vehicleId > 0
            && $this->from !== ''
            && $this->to !== ''
        ) {
            $this->history = $traccarModel->getHistory(
                $this->vehicleId,
                date('c', strtotime($this->from)),
                date('c', strtotime($this->to))
            );

            $this->buildTrips(
                $logbookModel,
                $selectedVehicle
            );
        }

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }

    private function findSelectedVehicle(): ?object
    {
        foreach ($this->vehicles as $vehicle) {
            if (
                (int) ($vehicle->traccar_device_id ?? 0)
                === $this->vehicleId
            ) {
                return $vehicle;
            }
        }

        return null;
    }

    private function buildTrips(
        LogbookModel $logbookModel,
        ?object $selectedVehicle
    ): void {
        $tripStart = null;
        $lastMovingPosition = null;
        $stopStart = null;

        foreach ($this->history as $position) {
            $speed = (float) ($position['speed'] ?? 0);

            if ($speed > 5) {
                if ($tripStart === null) {
                    $tripStart = $position;
                }

                $lastMovingPosition = $position;
                $stopStart = null;

                continue;
            }

            if ($tripStart === null) {
                continue;
            }

            if ($stopStart === null) {
                $stopStart = $position;
                continue;
            }

            $stopMinutes = (
                strtotime((string) ($position['fixTime'] ?? ''))
                - strtotime((string) ($stopStart['fixTime'] ?? ''))
            ) / 60;

            if ($stopMinutes < 60) {
                continue;
            }

            if ($lastMovingPosition !== null) {
                $this->appendTrip(
                    $tripStart,
                    $lastMovingPosition,
                    $logbookModel,
                    $selectedVehicle
                );
            }

            $tripStart = null;
            $lastMovingPosition = null;
            $stopStart = null;
        }

        if (
            $tripStart !== null
            && $lastMovingPosition !== null
        ) {
            $this->appendTrip(
                $tripStart,
                $lastMovingPosition,
                $logbookModel,
                $selectedVehicle
            );
        }
    }

    private function appendTrip(
        array $tripStart,
        array $tripEnd,
        LogbookModel $logbookModel,
        ?object $selectedVehicle
    ): void {
        $rawStartM = (float) (
            $tripStart['attributes']['totalDistance'] ?? 0
        );

        $rawEndM = (float) (
            $tripEnd['attributes']['totalDistance'] ?? 0
        );

        $distanceKm = round(
            max(0, $rawEndM - $rawStartM) / 1000,
            1
        );

        if ($distanceKm < 1) {
            return;
        }

        $startTime = new \DateTime(
            (string) $tripStart['fixTime']
        );
        $startTime->setTimezone(
            new \DateTimeZone('Europe/Berlin')
        );

        $endTime = new \DateTime(
            (string) $tripEnd['fixTime']
        );
        $endTime->setTimezone(
            new \DateTimeZone('Europe/Berlin')
        );

        $durationMinutes = (int) round(
            (
                strtotime((string) $tripEnd['fixTime'])
                - strtotime((string) $tripStart['fixTime'])
            ) / 60
        );

        $tripKey = md5(
            $this->vehicleId
            . (string) $tripStart['fixTime']
            . (string) $tripEnd['fixTime']
        );

        $savedTrip = $logbookModel->getTripData($tripKey);

        $startKm = $this->convertToVehicleOdometer(
            $rawStartM,
            $selectedVehicle
        );

        $endKm = $this->convertToVehicleOdometer(
            $rawEndM,
            $selectedVehicle
        );

        $this->trips[] = [
            'trip_key' => $tripKey,
            'trip_type' =>
                $savedTrip->trip_type ?? 'Geschäftlich',
            'trip_reason' =>
                $savedTrip->trip_reason ?? '',
            'signature_place' =>
                $savedTrip->signature_place ?? '',
            'signature_date' =>
                $savedTrip->signature_date ?? date('Y-m-d'),
            'signature_driver' =>
                $savedTrip->signature_driver ?? '',
            'start_lat' =>
                (float) ($tripStart['latitude'] ?? 0),
            'start_lon' =>
                (float) ($tripStart['longitude'] ?? 0),
            'end_lat' =>
                (float) ($tripEnd['latitude'] ?? 0),
            'end_lon' =>
                (float) ($tripEnd['longitude'] ?? 0),
            'start' =>
                $startTime->format('Y-m-d H:i:s'),
            'end' =>
                $endTime->format('Y-m-d H:i:s'),
            'start_km' => $startKm,
            'end_km' => $endKm,
            'distance' => $distanceKm,
            'duration' => $durationMinutes,
        ];
    }

    private function convertToVehicleOdometer(
        float $rawDistanceM,
        ?object $vehicle
    ): float {
        if (!$vehicle) {
            return round($rawDistanceM / 1000, 1);
        }

        $initialOdometerKm = (float) (
            $vehicle->initial_odometer_km ?? 0
        );

        $odometerBaseM = (float) (
            $vehicle->odometer_base_m ?? 0
        );

        if ($odometerBaseM <= 0) {
            return round($rawDistanceM / 1000, 1);
        }

        return round(
            max(
                0,
                $initialOdometerKm
                + (($rawDistanceM - $odometerBaseM) / 1000)
            ),
            1
        );
    }
}
