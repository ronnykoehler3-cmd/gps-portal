<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class SystemModel extends BaseDatabaseModel
{
    public function getSettings()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__gpsportal_config');

        $db->setQuery($query);

        $rows = $db->loadAssocList();

        $settings = [];

        foreach ($rows as $row)
        {
            $settings[$row['setting_key']] =
                $row['setting_value'];
        }

        return $settings;
    }

    public function saveSetting($key, $value)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->update('#__gpsportal_config')
            ->set('setting_value=' . $db->quote($value))
            ->where('setting_key=' . $db->quote($key));

        $db->setQuery($query);
        $db->execute();
    }
}
