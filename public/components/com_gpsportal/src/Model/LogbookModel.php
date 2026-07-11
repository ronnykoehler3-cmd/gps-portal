<?php

namespace TKKundendienst\Component\Gpsportal\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class LogbookModel extends BaseDatabaseModel
{
    public function saveTripData(array $data): bool
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__gpsportal_logbook')
            ->where(
                'trip_key = ' .
                $db->quote($data['trip_key'])
            );

        $db->setQuery($query);

        $existingId = (int) $db->loadResult();

        if ($existingId)
        {
            $entry = (object) [

                'id' =>
                    $existingId,

                'trip_type' =>
                    $data['trip_type'],

                'trip_reason' =>
                    $data['trip_reason']
            ];

            $db->updateObject(
                '#__gpsportal_logbook',
                $entry,
                'id'
            );

            return true;
        }

        $entry = (object) [

            'user_id' =>
                (int) $data['user_id'],

            'vehicle_id' =>
                (int) $data['vehicle_id'],

            'trip_key' =>
                $data['trip_key'],

            'trip_start' =>
                $data['trip_start'],

            'trip_end' =>
                $data['trip_end'],

            'start_km' =>
                (float) $data['start_km'],

            'end_km' =>
                (float) $data['end_km'],

            'distance_km' =>
                (float) $data['distance_km'],

            'duration_minutes' =>
                (int) $data['duration_minutes'],

            'trip_type' =>
                $data['trip_type'],

            'trip_reason' =>
                $data['trip_reason'],

            'created' =>
                date('Y-m-d H:i:s')
        ];

        $db->insertObject(
            '#__gpsportal_logbook',
            $entry
        );

        return true;
    }

    public function getTripData(string $tripKey)
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)

            ->select('*')

            ->from('#__gpsportal_logbook')

            ->where(
                'trip_key = ' .
                $db->quote($tripKey)
            );

        $db->setQuery($query);

        return $db->loadObject();
    }
}
