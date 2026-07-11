<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class SettingsModel extends BaseDatabaseModel
{
    public function getSetting($key)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('setting_value')
            ->from('#__gpsportal_settings')
            ->where('setting_key=' . $db->quote($key));

        $db->setQuery($query);

        return $db->loadResult();
    }

    public function saveSetting($key, $value)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->update('#__gpsportal_settings')
            ->set('setting_value=' . $db->quote($value))
            ->where('setting_key=' . $db->quote($key));

        $db->setQuery($query);
        $db->execute();
    }
}
