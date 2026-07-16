<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$user = Factory::getApplication()->getIdentity();

$isSuperUserGroup = in_array(
    8,
    array_map(
        'intval',
        $user->getAuthorisedGroups()
    ),
    true
);

$isAdministrator =
    $user
    && $user->id
    && (
        $isSuperUserGroup
        || $user->authorise(
            'core.admin'
        )
        || $user->authorise(
            'core.manage',
            'com_gpsportal'
        )
    );
?>

<aside class="gps-sidebar">

    <div class="gps-logo">
        <img
            src="/images/gpsportal/logo.png"
            alt="GPS Portal"
        >
    </div>

    <nav>

        <a href="index.php?option=com_gpsportal&view=dashboard">
            Dashboard
        </a>

        <a href="index.php?option=com_gpsportal&view=livemap">
            Live-Karte
        </a>

        <a href="index.php?option=com_gpsportal&view=vehicles">
            Fahrzeuge
        </a>

        <a href="index.php?option=com_gpsportal&view=history">
            Historie
        </a>

        <a href="index.php?option=com_gpsportal&view=logbook">
            Fahrtenbuch
        </a>

        <a href="index.php?option=com_gpsportal&view=reports">
            Berichte
        </a>

        <a href="index.php?option=com_gpsportal&view=geofences">
            Geozonen
        </a>
        <a href="index.php?option=com_gpsportal&view=geofenceevents">
            Geozonen-Ereignisse
        </a>
        <a href="index.php?option=com_gpsportal&view=documents">
            Dokumente
        </a>

        <a href="index.php?option=com_gpsportal&view=settings">
            Einstellungen
        </a>

        <?php if ($isAdministrator): ?>
            <a href="index.php?option=com_gpsportal&view=updates">
                Updates
            </a>
        <?php endif; ?>

    </nav>

    <div class="sidebar-user">

        <div class="sidebar-user-name">
            <?php echo htmlspecialchars($user->name); ?>
        </div>

        <div class="sidebar-user-role">
            Kunde
        </div>

        <form
            action="<?php echo Route::_('index.php?option=com_users&task=user.logout'); ?>"
            method="post"
        >

            <button
                type="submit"
                class="logout-btn sidebar-logout"
            >
                Logout
            </button>

            <?php echo HTMLHelper::_('form.token'); ?>

        </form>

    </div>

</aside>
