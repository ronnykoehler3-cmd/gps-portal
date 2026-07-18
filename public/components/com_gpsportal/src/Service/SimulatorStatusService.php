<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

final class SimulatorStatusService
{
    private const STATE_FILE = '/opt/tk-gps-simulator/state.json';

    public function enrich(array $vehicles): array
    {
        $states = $this->readStates();

        foreach ($vehicles as $vehicle) {
            $uniqueId = (string) ($vehicle->tracker_unique_id ?? '');
            $state = is_array($states[$uniqueId] ?? null) ? $states[$uniqueId] : [];
            [$label, $class] = $this->determineStatus($vehicle, $state);
            $vehicle->display_status = $label;
            $vehicle->display_status_class = $class;
            $vehicle->next_departure = $this->nextDeparture($vehicle);
            $vehicle->last_simulator_update = (string) ($state['updated'] ?? '');
        }

        return $vehicles;
    }

    private function readStates(): array
    {
        if (!is_readable(self::STATE_FILE)) {
            return [];
        }

        $content = file_get_contents(self::STATE_FILE);
        $decoded = is_string($content) ? json_decode($content, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function determineStatus(object $vehicle, array $state): array
    {
        if (!(bool) ($vehicle->active ?? false)) {
            return ['Deaktiviert', 'inactive'];
        }

        if (!$this->isInsideSchedule($vehicle, new \DateTimeImmutable('now'))) {
            return ['Außerhalb der Fahrzeit', 'outside'];
        }

        if (($state['status'] ?? '') === 'driving') {
            return ['Fährt', 'driving'];
        }

        if (($state['status'] ?? '') === 'parked') {
            return ['Pause', 'paused'];
        }

        return ['Synchronisiert – Status wird geladen', 'synchronised'];
    }

    private function isInsideSchedule(object $vehicle, \DateTimeImmutable $time): bool
    {
        $weekdays = array_map('intval', explode(',', (string) ($vehicle->working_weekdays ?? '0,1,2,3,4,5')));
        $weekday = (int) $time->format('N') - 1;
        $current = $time->format('H:i:s');

        return in_array($weekday, $weekdays, true)
            && $current >= (string) ($vehicle->workday_start ?? '06:30:00')
            && $current < (string) ($vehicle->workday_end ?? '18:30:00');
    }

    private function nextDeparture(object $vehicle): string
    {
        $now = new \DateTimeImmutable('now');

        if ($this->isInsideSchedule($vehicle, $now)) {
            return 'Nach Ende der aktuellen Pause';
        }

        $weekdays = array_map('intval', explode(',', (string) ($vehicle->working_weekdays ?? '0,1,2,3,4,5')));
        $start = substr((string) ($vehicle->workday_start ?? '06:30:00'), 0, 5);

        for ($days = 0; $days <= 7; $days++) {
            $candidate = $now->modify('+' . $days . ' day')->setTime(
                (int) substr($start, 0, 2),
                (int) substr($start, 3, 2)
            );
            $weekday = (int) $candidate->format('N') - 1;

            if (in_array($weekday, $weekdays, true) && $candidate > $now) {
                return $candidate->format('d.m.Y H:i') . ' Uhr';
            }
        }

        return 'Keine Fahrzeit eingestellt';
    }
}
