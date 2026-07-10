<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class TodoModel extends BaseDatabaseModel
{
    public function getTodos()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__gpsportal_todo')
            ->order('status DESC, priority ASC, id ASC');

        $db->setQuery($query);

        return $db->loadAssocList();
    }

    public function toggle($id)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('status')
            ->from('#__gpsportal_todo')
            ->where('id=' . (int)$id);

        $db->setQuery($query);

        $status = (int)$db->loadResult();

        $newStatus = $status ? 0 : 1;

        $query = $db->getQuery(true)
            ->update('#__gpsportal_todo')
            ->set('status=' . $newStatus)
            ->where('id=' . (int)$id);

        $db->setQuery($query);
        $db->execute();
    }
}
