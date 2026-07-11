<?php

namespace TKKundendienst\Plugin\User\Gpsportal\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

final class Gpsportal extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onUserAfterSave' => 'onUserAfterSave',
        ];
    }

    public function onUserAfterSave($event): void
    {
        file_put_contents(
            '/tmp/gpsportal_plugin.log',
            date('Y-m-d H:i:s') . " USER SAVED\n",
            FILE_APPEND
        );
    }
}
