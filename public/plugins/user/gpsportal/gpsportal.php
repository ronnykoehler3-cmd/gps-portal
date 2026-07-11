<?php

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

class PlgUserGpsportal extends CMSPlugin
{
    public function onUserAfterSave(
        $user,
        $isNew,
        $success,
        $msg
    )
    {
        file_put_contents(
            '/tmp/gpsportal_plugin.log',
            date('Y-m-d H:i:s')
            . " USER SAVED\n",
            FILE_APPEND
        );

        return true;
    }
}
