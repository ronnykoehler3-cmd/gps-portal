<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\History;

defined('_JEXEC') or die;

use DateTimeImmutable;
use DateTimeZone;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use TKKundendienst\Component\Gpsportal\Site\Model\TraccarModel;
use TKKundendienst\Component\Gpsportal\Site\Model\VehiclesModel;
use TKKundendienst\Component\Gpsportal\Site\Service\TripService;
use TKKundendienst\Component\Gpsportal\Site\Service\UserSettingsService;

class HtmlView extends BaseHtmlView
{
    public array $vehicles = [];
    public array $history = [];
    public array $trips = [];
    public array $stops = [];
    public array $availableDays = [];
    public float $distanceKm = 0.0;
    public int $vehicleId = 0;
    public string $selectionMode = 'day';
    public string $selectedDate = '';
    public string $fromDate = '';
    public string $toDate = '';
    public string $calendarMonth = '';
    public string $displayTimezone = 'Europe/Berlin';
    public int $tripEndStopMinutes = UserSettingsService::DEFAULT_TRIP_STOP_MINUTES;

    public function display($tpl = null)
    {
        $vehiclesModel = new VehiclesModel();
        $traccarModel = new TraccarModel();
        $tripService = new TripService();
        $userSettingsService = new UserSettingsService();
        $app = Factory::getApplication();
        $configuredTimezone = (string) $app->get('offset', 'Europe/Berlin');
        $userTimezone = (string) (
            $app->getIdentity()->getParam('timezone', $configuredTimezone)
            ?: $configuredTimezone
        );

        try {
            $timezone = new DateTimeZone($userTimezone);
        } catch (\Throwable $exception) {
            $timezone = new DateTimeZone('Europe/Berlin');
        }

        $this->displayTimezone = $timezone->getName();
        $today = new DateTimeImmutable('today', $timezone);
        $this->tripEndStopMinutes = $userSettingsService->getTripStopMinutes();

        $this->vehicles = $vehiclesModel->getVehicles();
        $this->vehicleId = (int) $app->input->getInt('vehicle');

        if ($this->vehicleId <= 0 && !empty($this->vehicles)) {
            $this->vehicleId = (int) ($this->vehicles[0]->traccar_device_id ?? 0);
        }

        $requestedMode = $app->input->getCmd('selection_mode', 'day');
        $this->selectionMode = $requestedMode === 'range' ? 'range' : 'day';
        $requestedDate = $app->input->getString('date');
        $requestedFrom = $app->input->getString('from');
        $requestedTo = $app->input->getString('to');

        $this->selectedDate = $this->validDate(
            $requestedDate,
            $today->format('Y-m-d')
        );
        $this->fromDate = $this->validDate(
            $requestedFrom,
            $this->selectedDate
        );
        $this->toDate = $this->validDate(
            $requestedTo,
            $this->fromDate
        );
        $this->calendarMonth = $this->validMonth(
            $app->input->getString('calendar_month'),
            substr($this->selectedDate, 0, 7)
        );

        if ($this->vehicleId > 0) {
            $monthStart = new DateTimeImmutable(
                $this->calendarMonth . '-01 00:00:00',
                $timezone
            );
            $monthEnd = $monthStart->modify('first day of next month');
            $monthTrips = $traccarModel->getTrips(
                $this->vehicleId,
                $monthStart->format(DATE_ATOM),
                $monthEnd->format(DATE_ATOM)
            );
            $this->availableDays = $this->collectAvailableDays(
                $monthTrips,
                $timezone
            );

            /*
             * Beim ersten Öffnen wird der jüngste Fahrtag des Monats
             * verwendet. So zeigt die Seite sofort echte Fahrdaten und
             * nicht einen möglicherweise leeren heutigen Kalendertag.
             */
            if (
                $requestedDate === null
                && $requestedFrom === null
                && $requestedTo === null
                && !empty($this->availableDays)
            ) {
                sort($this->availableDays);
                $latestAvailableDay = $this->availableDays[
                    count($this->availableDays) - 1
                ];
                $this->selectedDate = $latestAvailableDay;
                $this->fromDate = $latestAvailableDay;
                $this->toDate = $latestAvailableDay;
                $this->selectionMode = 'day';
            }

            [$selectionStart, $selectionEnd] = $this->getSelectionPeriod($timezone);
            $this->history = $traccarModel->getHistory(
                $this->vehicleId,
                $selectionStart->format(DATE_ATOM),
                $selectionEnd->format(DATE_ATOM)
            );

            $analysis = $tripService->analyse(
                $this->history,
                $this->tripEndStopMinutes
            );
            $this->history = $analysis['positions'];
            $this->trips = $analysis['trips'];
            $this->stops = $analysis['stops'];
            $this->distanceKm = (float) $analysis['distanceKm'];

            foreach ($this->trips as &$trip) {
                $trip['startAddress'] = $traccarModel->getAddress(
                    (float) $trip['start']['latitude'],
                    (float) $trip['start']['longitude']
                );
                $trip['endAddress'] = $traccarModel->getAddress(
                    (float) $trip['end']['latitude'],
                    (float) $trip['end']['longitude']
                );
            }
            unset($trip);
        }

        ob_start();
        parent::display($tpl);
        $content = ob_get_clean();

        require JPATH_SITE . '/components/com_gpsportal/layouts/app.php';
    }

    private function getSelectionPeriod(DateTimeZone $timezone): array
    {
        if ($this->selectionMode === 'range') {
            $from = new DateTimeImmutable($this->fromDate . ' 00:00:00', $timezone);
            $to = new DateTimeImmutable($this->toDate . ' 00:00:00', $timezone);

            if ($to < $from) {
                [$from, $to] = [$to, $from];
                $this->fromDate = $from->format('Y-m-d');
                $this->toDate = $to->format('Y-m-d');
            }

            return [$from, $to->modify('+1 day')];
        }

        $from = new DateTimeImmutable($this->selectedDate . ' 00:00:00', $timezone);

        return [$from, $from->modify('+1 day')];
    }

    private function collectAvailableDays(
        array $trips,
        DateTimeZone $timezone
    ): array
    {
        $days = [];

        foreach ($trips as $trip) {
            $timestamp = strtotime((string) (
                $trip['startTime']
                ?? $trip['start_time']
                ?? ''
            ));

            if ($timestamp !== false) {
                $date = (new DateTimeImmutable('@' . $timestamp))
                    ->setTimezone($timezone)
                    ->format('Y-m-d');
                $days[$date] = true;
            }
        }

        return array_keys($days);
    }

    private function validDate(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value
            ? $value
            : $fallback;
    }

    private function validMonth(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1
            ? $value
            : $fallback;
    }
}
