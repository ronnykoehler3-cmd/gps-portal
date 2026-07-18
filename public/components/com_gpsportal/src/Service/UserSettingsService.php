<?php

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class UserSettingsService
{
    public const DEFAULT_TRIP_STOP_MINUTES = 45;
    public const MINIMUM_TRIP_STOP_MINUTES = 5;
    public const MAXIMUM_TRIP_STOP_MINUTES = 180;

    public function getTripStopMinutes(?int $userId = null): int
    {
        $userId = $userId ?: (int) Factory::getApplication()
            ->getIdentity()
            ->id;

        if ($userId <= 0) {
            return self::DEFAULT_TRIP_STOP_MINUTES;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__gpsportal_user_settings'))
            ->where($db->quoteName('user_id') . ' = ' . $userId);
        $db->setQuery($query);
        $settings = $db->loadObject();

        return $this->normaliseTripStopMinutes(
            $settings->trip_stop_minutes
                ?? self::DEFAULT_TRIP_STOP_MINUTES
        );
    }

    public function normaliseTripStopMinutes(mixed $value): int
    {
        return max(
            self::MINIMUM_TRIP_STOP_MINUTES,
            min(self::MAXIMUM_TRIP_STOP_MINUTES, (int) $value)
        );
    }

    /**
     * Übergangsschutz für lokale Bestandsinstallationen. Das reguläre
     * Produktivupdate legt dieselbe Spalte über die SQL-Migration an.
     */
    public function ensureTripStopMinutesColumn(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $tableName = $db->replacePrefix('#__gpsportal_user_settings');
        $columns = $db->getTableColumns($tableName, false);

        if (isset($columns['trip_stop_minutes'])) {
            return;
        }

        $db->setQuery(
            'ALTER TABLE '
            . $db->quoteName($tableName)
            . ' ADD COLUMN '
            . $db->quoteName('trip_stop_minutes')
            . ' SMALLINT UNSIGNED NOT NULL DEFAULT '
            . self::DEFAULT_TRIP_STOP_MINUTES
        );
        $db->execute();
    }
}
