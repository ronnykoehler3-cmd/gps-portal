<?php

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

/**
 * Wertet Traccar-Positionen einheitlich für Historie und Livekarte aus.
 */
class TripService
{
    public const TRIP_END_STOP_MINUTES = 45;

    private const MINIMUM_STOP_MINUTES = 1;

    private const TRIP_COLORS = [
        '#3b82f6',
        '#f97316',
        '#a855f7',
        '#14b8a6',
        '#eab308',
        '#ec4899',
        '#22c55e',
        '#ef4444',
    ];

    public function analyse(
        array $positions,
        int $tripEndStopMinutes = self::TRIP_END_STOP_MINUTES
    ): array
    {
        $tripEndStopMinutes = max(5, min(180, $tripEndStopMinutes));
        $positions = $this->preparePositions($positions);
        $trips = [];
        $stops = [];
        $tripStartIndex = null;
        $stopStartIndex = null;
        $tripClosed = false;

        foreach ($positions as $index => $position) {
            $moving = $this->isMoving($position);

            if ($moving) {
                if ($stopStartIndex !== null) {
                    $this->addStop(
                        $stops,
                        $positions,
                        $stopStartIndex,
                        max($stopStartIndex, $index - 1),
                        $tripStartIndex,
                        $tripEndStopMinutes
                    );

                    $stopStartIndex = null;
                }

                if ($tripStartIndex === null || $tripClosed) {
                    $tripStartIndex = $index;
                    $tripClosed = false;
                }

                continue;
            }

            if ($tripStartIndex !== null && $stopStartIndex === null) {
                $stopStartIndex = $index;
            }

            if (
                $tripStartIndex === null
                || $stopStartIndex === null
                || $tripClosed
            ) {
                continue;
            }

            $stopMinutes = $this->minutesBetween(
                $positions[$stopStartIndex]['timestamp'],
                $position['timestamp']
            );

            if ($stopMinutes < $tripEndStopMinutes) {
                continue;
            }

            $this->addTrip(
                $trips,
                $positions,
                $tripStartIndex,
                max($tripStartIndex, $stopStartIndex - 1)
            );

            $tripClosed = true;
        }

        if ($tripStartIndex !== null) {
            $lastIndex = count($positions) - 1;

            if ($stopStartIndex !== null) {
                $this->addStop(
                    $stops,
                    $positions,
                    $stopStartIndex,
                    $lastIndex,
                    $tripStartIndex,
                    $tripEndStopMinutes
                );
            }

            if (!$tripClosed) {
                $this->addTrip(
                    $trips,
                    $positions,
                    $tripStartIndex,
                    $stopStartIndex !== null
                        ? max($tripStartIndex, $stopStartIndex - 1)
                        : $lastIndex
                );
            }
        }

        foreach ($stops as &$stop) {
            $stop['tripIndex'] = $this->findTripIndex(
                $trips,
                (int) $stop['timestamp'],
                $tripEndStopMinutes
            );
        }
        unset($stop);

        return [
            'positions' => $positions,
            'trips' => $trips,
            'stops' => $stops,
            'distanceKm' => round(array_sum(array_column($trips, 'distanceKm')), 1),
            'tripEndStopMinutes' => $tripEndStopMinutes,
        ];
    }

    private function preparePositions(array $positions): array
    {
        $prepared = [];

        foreach ($positions as $position) {
            $latitude = (float) ($position['latitude'] ?? 0);
            $longitude = (float) ($position['longitude'] ?? 0);
            $time = (string) (
                $position['fixTime']
                ?? $position['deviceTime']
                ?? $position['serverTime']
                ?? ''
            );
            $timestamp = strtotime($time);

            if (
                $timestamp === false
                || $latitude < -90
                || $latitude > 90
                || $longitude < -180
                || $longitude > 180
                || ($latitude === 0.0 && $longitude === 0.0)
            ) {
                continue;
            }

            $position['latitude'] = $latitude;
            $position['longitude'] = $longitude;
            $position['timestamp'] = $timestamp;
            $position['speedKmh'] = round(
                max(0, (float) ($position['speed'] ?? 0)) * 1.852,
                1
            );
            $position['course'] = $this->normaliseCourse(
                (float) ($position['course'] ?? 0)
            );
            $prepared[] = $position;
        }

        usort(
            $prepared,
            static fn (array $left, array $right): int =>
                $left['timestamp'] <=> $right['timestamp']
        );

        return $prepared;
    }

    private function isMoving(array $position): bool
    {
        $attributes = is_array($position['attributes'] ?? null)
            ? $position['attributes']
            : [];

        if (array_key_exists('motion', $attributes)) {
            return (bool) $attributes['motion'];
        }

        return (float) ($position['speedKmh'] ?? 0) >= 1.0;
    }

    private function addTrip(
        array &$trips,
        array $positions,
        int $startIndex,
        int $endIndex
    ): void {
        if ($endIndex <= $startIndex || !isset($positions[$endIndex])) {
            return;
        }

        $tripPositions = array_slice(
            $positions,
            $startIndex,
            $endIndex - $startIndex + 1
        );
        $distance = 0.0;

        for ($index = 1; $index < count($tripPositions); $index++) {
            $distance += $this->distanceKm(
                $tripPositions[$index - 1],
                $tripPositions[$index]
            );
        }

        $tripNumber = count($trips) + 1;
        $start = $tripPositions[0];
        $end = $tripPositions[count($tripPositions) - 1];

        $trips[] = [
            'number' => $tripNumber,
            'color' => self::TRIP_COLORS[($tripNumber - 1) % count(self::TRIP_COLORS)],
            'startTime' => $start['fixTime'] ?? date('c', $start['timestamp']),
            'endTime' => $end['fixTime'] ?? date('c', $end['timestamp']),
            'startTimestamp' => $start['timestamp'],
            'endTimestamp' => $end['timestamp'],
            'durationMinutes' => max(
                0,
                (int) round(($end['timestamp'] - $start['timestamp']) / 60)
            ),
            'distanceKm' => round($distance, 1),
            'start' => $start,
            'end' => $end,
            'positions' => $tripPositions,
        ];
    }

    private function addStop(
        array &$stops,
        array $positions,
        int $startIndex,
        int $endIndex,
        int $tripStartIndex,
        int $tripEndStopMinutes
    ): void {
        if (!isset($positions[$startIndex], $positions[$endIndex])) {
            return;
        }

        $duration = $this->minutesBetween(
            $positions[$startIndex]['timestamp'],
            $positions[$endIndex]['timestamp']
        );

        if ($duration < self::MINIMUM_STOP_MINUTES) {
            return;
        }

        $position = $positions[$startIndex];

        $stops[] = [
            'startTime' => $position['fixTime'] ?? date('c', $position['timestamp']),
            'endTime' => $positions[$endIndex]['fixTime']
                ?? date('c', $positions[$endIndex]['timestamp']),
            'timestamp' => $position['timestamp'],
            'durationMinutes' => $duration,
            'latitude' => $position['latitude'],
            'longitude' => $position['longitude'],
            'endsTrip' => $duration >= $tripEndStopMinutes,
            'tripStartTimestamp' => $positions[$tripStartIndex]['timestamp'] ?? 0,
        ];
    }

    private function findTripIndex(
        array $trips,
        int $timestamp,
        int $tripEndStopMinutes
    ): ?int
    {
        foreach ($trips as $index => $trip) {
            if (
                $timestamp >= (int) $trip['startTimestamp']
                && $timestamp <= (
                    (int) $trip['endTimestamp']
                    + ($tripEndStopMinutes * 60)
                )
            ) {
                return $index;
            }
        }

        return null;
    }

    private function minutesBetween(int $start, int $end): int
    {
        return max(0, (int) floor(($end - $start) / 60));
    }

    private function normaliseCourse(float $course): float
    {
        $course = fmod($course, 360.0);

        return $course < 0 ? $course + 360.0 : $course;
    }

    private function distanceKm(array $from, array $to): float
    {
        $earthRadius = 6371.0;
        $lat1 = deg2rad((float) $from['latitude']);
        $lat2 = deg2rad((float) $to['latitude']);
        $deltaLat = $lat2 - $lat1;
        $deltaLon = deg2rad(
            (float) $to['longitude'] - (float) $from['longitude']
        );
        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }
}
