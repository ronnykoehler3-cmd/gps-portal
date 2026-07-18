<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

final class VehicleVisibilityService
{
    public function hideForCurrentUser(int $deviceId): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || (int) $user->id <= 0 || $deviceId <= 0) {
            throw new \RuntimeException('Ungültige Fahrzeugauswahl.');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__gpsportal_user_devices'))
            ->where($db->quoteName('user_id') . ' = ' . (int) $user->id)
            ->where($db->quoteName('device_id') . ' = ' . $deviceId);
        $db->setQuery($query);

        if ((int) $db->loadResult() === 0) {
            throw new \RuntimeException('Das Fahrzeug ist diesem Benutzer nicht zugeordnet.', 403);
        }

        $row = (object) [
            'user_id' => (int) $user->id,
            'device_id' => $deviceId,
            'created' => date('Y-m-d H:i:s'),
        ];

        try {
            $db->insertObject('#__gpsportal_user_hidden_devices', $row);
        } catch (\Throwable $error) {
            if (stripos($error->getMessage(), 'Duplicate') === false) {
                throw $error;
            }
        }
    }

    public function showForCurrentUser(int $deviceId): void
    {
        $user = Factory::getApplication()->getIdentity();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__gpsportal_user_hidden_devices'))
            ->where($db->quoteName('user_id') . ' = ' . (int) $user->id)
            ->where($db->quoteName('device_id') . ' = ' . $deviceId);
        $db->setQuery($query)->execute();
    }
}
