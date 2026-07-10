<?php
defined('_JEXEC') or die;
use TKKundendienst\Component\Gpsportal\Site\Helper\GpsHelper;
?>

<h2>Geozonen-Ereignisse</h2>

<table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr>
            <th style="text-align:left;padding:10px;border-bottom:1px solid #444;">
                Datum
            </th>

            <th style="text-align:left;padding:10px;border-bottom:1px solid #444;">
                Fahrzeug
            </th>

            <th style="text-align:left;padding:10px;border-bottom:1px solid #444;">
                Geozone
            </th>

            <th style="text-align:left;padding:10px;border-bottom:1px solid #444;">
                Ereignis
            </th>
        </tr>
    </thead>

    <tbody>

<?php foreach ($this->events as $event) : ?>

<tr>

    <td style="padding:10px;border-bottom:1px solid #222;">
        <?= GpsHelper::formatDateTime($event->event_time); ?>
    </td>

    <td style="padding:10px;border-bottom:1px solid #222;">
        <?= htmlspecialchars($event->vehicle_name); ?>
    </td>

    <td style="padding:10px;border-bottom:1px solid #222;">
        <?= htmlspecialchars($event->geofence_name); ?>
    </td>

    <td style="padding:10px;border-bottom:1px solid #222;">

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
