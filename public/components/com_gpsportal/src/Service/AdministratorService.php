<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserHelper;

final class AdministratorService
{
    public function isAdministrator(): bool
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || (int) $user->id <= 0) {
            return false;
        }

        $groups = array_unique(array_merge(
            array_map('intval', $user->getAuthorisedGroups()),
            array_map('intval', UserHelper::getUserGroups((int) $user->id))
        ));

        if (in_array(8, $groups, true)
            || in_array(7, $groups, true)
            || $user->authorise('core.admin')
            || $user->authorise('core.login.admin')
            || $user->authorise('core.manage', 'com_gpsportal')) {
            return true;
        }

        /*
         * Manche ältere Portalinstallationen liefern im Frontend einen
         * unvollständigen ACL-Cache. Deshalb werden die tatsächlich in
         * Joomla gespeicherten Gruppen zusätzlich direkt geprüft.
         */
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__user_usergroup_map', 'm'))
            ->join('INNER', $db->quoteName('#__usergroups', 'g')
                . ' ON g.id = m.group_id')
            ->where($db->quoteName('m.user_id') . ' = ' . (int) $user->id)
            ->where('(' . implode(' OR ', [
                'LOWER(' . $db->quoteName('g.title') . ') = ' . $db->quote('super users'),
                'LOWER(' . $db->quoteName('g.title') . ') = ' . $db->quote('super benutzer'),
                'LOWER(' . $db->quoteName('g.title') . ') = ' . $db->quote('administrator'),
                $db->quoteName('g.id') . ' IN (7, 8)',
            ]) . ')');
        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    public function assertAdministrator(): void
    {
        if (!$this->isAdministrator()) {
            throw new \RuntimeException(
                'Diese Funktion steht ausschließlich Administratoren zur Verfügung.',
                403
            );
        }
    }
}
