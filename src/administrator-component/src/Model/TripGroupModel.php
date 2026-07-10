<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class TripGroupModel
{
    public function createTripGroup(
        $deviceId,
        array $selectedTrips,
        array $allTrips = []
    )
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $name =
            'Sammelfahrt '
            . date('d.m.Y H:i');

        $query =
            $db->getQuery(true)
                ->insert('eusdi_gpsportal_trip_groups')
                ->columns([
                    'device_id',
                    'name'
                ])
                ->values(
                    (int)$deviceId
                    . ','
                    . $db->quote($name)
                );

        $db->setQuery($query);
        $db->execute();

        $groupId =
            $db->insertid();

        foreach ($selectedTrips as $tripKey)
        {
            foreach ($allTrips as $trip)
            {
                $currentKey =
                    ($trip['startPositionId'] ?? 0)
                    . '_'
                    . ($trip['endPositionId'] ?? 0);

                if ($currentKey !== $tripKey)
                {
                    continue;
                }

                $query =
                    $db->getQuery(true)
                        ->insert(
                            'eusdi_gpsportal_trip_group_items'
                        )
                        ->columns([
                            'group_id',
                            'start_position_id',
                            'end_position_id',
                            'start_address',
                            'end_address',
                            'distance',
                            'duration',
                            'start_odometer',
                            'end_odometer',
			    'trip_date',                            
			    'trip_type',
                            'note'
                        ])
                        ->values(
                            (int)$groupId
                            . ','
                            . (int)($trip['startPositionId'] ?? 0)
                            . ','
                            . (int)($trip['endPositionId'] ?? 0)
                            . ','
                            . $db->quote($trip['startAddress'] ?? '')
                            . ','
                            . $db->quote($trip['endAddress'] ?? '')
                            . ','
                            . (float)($trip['distance'] ?? 0)
                            . ','
                            . (int)($trip['duration'] ?? 0)
                            . ','
                            . (int)($trip['startOdometer'] ?? 0)
                            . ','
			    . (int)($trip['endOdometer'] ?? 0)
			    . ','
			    . $db->quote(
			        date(
       				    'Y-m-d H:i:s',
        			    strtotime(
            				$trip['startTime'] ?? 'now'
     				    )
   				 )
			      )
			    . ','
			    . $db->quote('Geschäftlich')
			    . ','
			    . $db->quote('')
                        );

                $db->setQuery($query);
                $db->execute();

                break;
            }
        }

        return $groupId;
    }

    public function deleteGroup($groupId)
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query =
            $db->getQuery(true)
                ->delete(
                    'eusdi_gpsportal_trip_group_items'
                )
                ->where(
                    'group_id=' . (int)$groupId
                );

        $db->setQuery($query);
        $db->execute();

        $query =
            $db->getQuery(true)
                ->delete(
                    'eusdi_gpsportal_trip_groups'
                )
                ->where(
                    'id=' . (int)$groupId
                );

        $db->setQuery($query);
        $db->execute();
    }

    public function getGroups()
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from('eusdi_gpsportal_trip_groups')
            ->order('id DESC');

        $db->setQuery($query);

        return $db->loadAssocList();
    }

    public function getGroupItems($groupId)
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from('eusdi_gpsportal_trip_group_items')
            ->where('group_id=' . (int)$groupId);

        $db->setQuery($query);

        return $db->loadAssocList();
    }

    public function getGroupDistance($groupId)
    {
        $distance = 0;

        foreach ($this->getGroupItems($groupId) as $item)
        {
            $distance +=
                (float)($item['distance'] ?? 0);
        }

        return round(
            $distance / 1000,
            2
        );
    }

    public function getGroupDuration($groupId)
    {
        $duration = 0;

        foreach ($this->getGroupItems($groupId) as $item)
        {
            $duration +=
                (int)($item['duration'] ?? 0);
        }

        return round(
            $duration / 60000
        );
    }
    public function saveTripItem(
        $id,
        $tripType,
        $note,
        $customer,
        $orderNumber
    )
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->update('eusdi_gpsportal_trip_group_items')
            ->set(
                'trip_type=' .
                $db->quote($tripType)
            )
            ->set(
                'note=' .
                $db->quote($note)
            )
            ->set(
                'customer=' .
                $db->quote($customer)
            )
            ->set(
                'order_number=' .
                $db->quote($orderNumber)
            )
            ->where(
                'id=' . (int)$id
            );

        $db->setQuery($query);
        $db->execute();

        return true;
    }
    public function getMileageSummary()
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'trip_type',
                'SUM(distance) AS total_distance'
            ])
            ->from(
                'eusdi_gpsportal_trip_group_items'
            )
            ->group('trip_type');

        $db->setQuery($query);

        $rows =
            $db->loadAssocList();

        $summary = [
            'Geschäftlich' => 0,
            'Privat' => 0,
            'Arbeitsweg' => 0
        ];

        foreach ($rows as $row)
        {
            $type =
                $row['trip_type']
                ?: 'Geschäftlich';

            $summary[$type] =
                round(
                    ($row['total_distance'] ?? 0)
                    / 1000,
                    2
                );
        }

        return $summary;
    }
    public function getGroup($groupId)
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from('eusdi_gpsportal_trip_groups')
            ->where(
                'id=' . (int)$groupId
            );

        $db->setQuery($query);

        return $db->loadAssoc();
    }
}
