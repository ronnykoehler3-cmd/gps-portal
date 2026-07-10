<?php

namespace TKKundendienst\Component\Gpsportal\Site\Helper;

defined('_JEXEC') or die;

class GpsHelper
{
    public static function formatDateTime(
        ?string $utcDateTime
    ): string
    {
        if (empty($utcDateTime))
        {
            return '-';
        }

        $date = new \DateTime(
            $utcDateTime,
            new \DateTimeZone('UTC')
        );

        $date->setTimezone(
            new \DateTimeZone('Europe/Berlin')
        );

        return $date->format(
            'd.m.Y H:i:s'
        );
    }

    public static function formatDate(
        ?string $utcDateTime
    ): string
    {
        if (empty($utcDateTime))
        {
            return '-';
        }

        $date = new \DateTime(
            $utcDateTime,
            new \DateTimeZone('UTC')
        );

        $date->setTimezone(
            new \DateTimeZone('Europe/Berlin')
        );

        return $date->format(
            'd.m.Y'
        );
    }
}
