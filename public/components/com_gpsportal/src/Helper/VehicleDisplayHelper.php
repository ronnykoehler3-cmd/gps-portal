<?php
namespace TKKundendienst\Component\Gpsportal\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

final class VehicleDisplayHelper
{
    public static function getMode(?int $userId = null): string
    {
        static $cache = [];
        $userId ??= (int) Factory::getApplication()->getIdentity()->id;

        if (isset($cache[$userId])) {
            return $cache[$userId];
        }

        if ($userId <= 0) {
            return 'name';
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('vehicle_display_mode'))
            ->from($db->quoteName('#__gpsportal_user_settings'))
            ->where($db->quoteName('user_id') . ' = ' . $userId);

        $db->setQuery($query);
        $mode = (string) $db->loadResult();

        if (!in_array($mode, ['name', 'license_plate', 'name_and_plate'], true)) {
            $mode = 'name';
        }

        return $cache[$userId] = $mode;
    }

    public static function format(string $name, ?string $licensePlate, ?string $mode = null): string
    {
        $name = trim($name);
        $licensePlate = trim((string) $licensePlate);
        $mode ??= self::getMode();

        if ($mode === 'license_plate' && $licensePlate !== '') {
            return $licensePlate;
        }

        if ($mode === 'name_and_plate' && $licensePlate !== '') {
            return $name !== '' ? $name . ' (' . $licensePlate . ')' : $licensePlate;
        }

        return $name !== '' ? $name : $licensePlate;
    }

    public static function formatObject(object $vehicle): string
    {
        return self::format(
            (string) ($vehicle->name ?? $vehicle->vehicle_name ?? ''),
            (string) ($vehicle->license_plate ?? '')
        );
    }
}
