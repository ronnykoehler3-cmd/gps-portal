<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$app = Factory::getApplication();

$controller = $app->bootComponent('com_gpsportal')
    ->getMVCFactory()
    ->createController('Display', 'Administrator');

$controller->execute(
    $app->input->getCmd('task')
);

$controller->redirect();
