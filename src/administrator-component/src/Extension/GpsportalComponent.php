<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;

class GpsportalComponent extends MVCComponent
{
    public function boot()
    {
        if (defined('JPATH_ADMINISTRATOR'))
        {
            ToolbarHelper::preferences(
                'com_gpsportal'
            );

            \JHtmlSidebar::addEntry(
                'Dashboard',
                'index.php?option=com_gpsportal&view=dashboard',
                $_GET['view'] ?? 'dashboard' === 'dashboard'
            );

            \JHtmlSidebar::addEntry(
                'Fahrzeuge',
                'index.php?option=com_gpsportal&view=vehicle',
                ($_GET['view'] ?? '') === 'vehicle'
            );

            \JHtmlSidebar::addEntry(
                'Projekt-ToDo',
                'index.php?option=com_gpsportal&view=todo',
                ($_GET['view'] ?? '') === 'todo'
            );

            \JHtmlSidebar::addEntry(
                'Einstellungen',
                'index.php?option=com_gpsportal&view=settings',
                ($_GET['view'] ?? '') === 'settings'
            );
        }
    }
}
