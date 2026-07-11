<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$app = Factory::getApplication();

$controller = $app->bootComponent('com_gpsportal')
    ->getMVCFactory()
    ->createController('Display', 'Site');

$controller->execute($app->input->get('task'));
$controller->redirect();
