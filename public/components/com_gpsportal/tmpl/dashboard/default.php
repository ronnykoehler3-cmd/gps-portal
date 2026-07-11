<?php
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use TKKundendienst\Component\Gpsportal\Site\Helper\GpsHelper;

$user = Factory::getApplication()->getIdentity();
$totalDevices = count($this->devices);
$onlineDevices = 0;
$batteryTotal = 0;
$batteryCount = 0;

$positionsByDevice = [];

foreach ($this->positions as $position)
{
    $positionsByDevice[$position['deviceId']] = $position;
}

foreach ($this->devices as $device)
{
    if (
        isset($device['status']) &&
        $device['status'] === 'online'
    ) {
        $onlineDevices++;
    }
$position =
    $positionsByDevice[$device['id']]
    ?? [];

$battery =
    $position['attributes']['batteryLevel']
    ?? null;

if (
    $battery !== null
    && is_numeric($battery)
)
{
    $batteryTotal += (float) $battery;
    $batteryCount++;
}
}
$averageBattery = 0;

if ($batteryCount > 0)
{
    $averageBattery =
        round(
            $batteryTotal / $batteryCount
        );
}
?>

<link rel="stylesheet"
href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<div class="dashboard-header">

    <div>
        <h1>GPS Portal Dashboard</h1>
        <p>
            Willkommen
            <strong><?php echo htmlspecialchars($user->name); ?></strong>
        </p>
    </div>

    <div>

        <form
            action="<?php echo Route::_('index.php?option=com_users&task=user.logout'); ?>"
            method="post"
        >

            <button
                type="submit"
                class="logout-btn"
            >
                Logout
            </button>

            <?php echo HTMLHelper::_('form.token'); ?>

        </form>

    </div>

</div>
<div class="cards">

    <div class="card">
        <h3>Fahrzeuge gesamt</h3>
        <h1><?php echo $totalDevices; ?></h1>
    </div>

    <div class="card">
        <h3>Fahrzeuge online</h3>
        <h1><?php echo $onlineDevices; ?></h1>
    </div>

<div class="card">
    <h3>Offline</h3>
    <h1><?php echo $totalDevices - $onlineDevices; ?></h1>
</div>
<div class="card">
    <h3>Ø Batterie</h3>
    <h1><?php echo $averageBattery; ?>%</h1>
</div>
</div>

<div class="vehicle-table">

    <h2>Fahrzeuge</h2>

    <table>

        <thead>
            <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Batterie</th>
                <th>Geschwindigkeit</th>
                <th>Letzte Meldung</th>
            </tr>
        </thead>

        <tbody>

        <?php foreach ($this->devices as $device): ?>

            <?php

            $position =
                $positionsByDevice[$device['id']]
                ?? [];

            $battery =
                $position['attributes']['batteryLevel']
                ?? '-';

            $speed =
                round(
                    (($position['speed'] ?? 0) * 1.852),
                    1
                );


$lastUpdate =
    GpsHelper::formatDateTime(
        $device['lastUpdate'] ?? null
    );
            ?>

            <tr>

                <td>
                    <?php
echo htmlspecialchars(
    trim(
        (string) (
            $device['vehicle_name']
            ?? $device['name']
            ?? ''
        )
    ),
    ENT_QUOTES,
    'UTF-8'
);
?>
                </td>

                <td>
                    <?php echo htmlspecialchars($device['status']); ?>
                </td>

                <td>
                    <?php echo $battery; ?> %
                </td>

                <td>
                    <?php echo $speed; ?> km/h
                </td>

                <td>
                    <?php echo $lastUpdate; ?>
                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>
<div class="vehicle-table">

    <h2>Letzte Geozonen-Ereignisse</h2>

    <table>

        <thead>
            <tr>
                <th>Zeit</th>
                <th>Fahrzeug</th>
                <th>Geozone</th>
                <th>Ereignis</th>
            </tr>
        </thead>

        <tbody>

        <?php foreach ($this->geofenceEvents as $event) : ?>

            <tr>

                <td>
		    <?= GpsHelper::formatDateTime($event->event_time); ?>
                </td>

                <td>
                    <?= htmlspecialchars($event->vehicle_name); ?>
                </td>

                <td>
                    <?= htmlspecialchars($event->geofence_name); ?>
                </td>

                <td>
                    <?php if ($event->event_type === 'enter') : ?>
                        🟢 Einfahrt
                    <?php else : ?>
                        🔴 Ausfahrt
                    <?php endif; ?>
                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>
<script>
