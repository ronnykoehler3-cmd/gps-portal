<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class PendingModel extends BaseDatabaseModel
{
    public function getPendingDevices()
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)

            ->select('*')

            ->from(
                '#__gpsportal_pending_devices'
            )

            ->order(
                'first_seen DESC'
            );

        $db->setQuery($query);

        return $db->loadAssocList();
    }
}
