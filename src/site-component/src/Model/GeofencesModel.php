<?php

namespace TKKundendienst\Component\Gpsportal\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class GeofencesModel extends BaseDatabaseModel
{
    public function getGeofences()
    {
        $user = Factory::getApplication()->getIdentity();

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)

            ->select('*')

            ->from('#__gpsportal_geofences')

            ->where(
                'user_id = ' . (int) $user->id
            )

            ->order('name ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    public function getGeofence(int $id)
    {
        $user = Factory::getApplication()->getIdentity();

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)

            ->select('*')

            ->from('#__gpsportal_geofences')

            ->where(
                'id = ' . (int) $id
            )

            ->where(
                'user_id = ' . (int) $user->id
            );

        $db->setQuery($query);

        return $db->loadObject();
    }

    public function saveGeofence(array $data)
    {
        $user = Factory::getApplication()->getIdentity();

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $name = trim(
            $data['name'] ?? ''
        );

        if ($name === '')
        {
            return false;
        }

        $row = (object) [

            'user_id'
                => (int) $user->id,

            'name'
                => $name,

            'latitude'
                => (float) (
                    $data['latitude']
                    ?? 0
                ),

            'longitude'
                => (float) (
                    $data['longitude']
                    ?? 0
                ),

            'radius'
                => max(
                    1,
                    (int) (
                        $data['radius']
                        ?? 100
                    )
                )
        ];

        return $db->insertObject(
            '#__gpsportal_geofences',
            $row
        );
    }

    public function deleteGeofence(int $id)
    {
        $user = Factory::getApplication()->getIdentity();

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)

            ->delete(
                '#__gpsportal_geofences'
            )

            ->where(
                'id = ' . (int) $id
            )

            ->where(
                'user_id = '
                . (int) $user->id
            );

        $db->setQuery($query);

        $db->execute();

        return true;
    }
}
