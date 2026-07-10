<?php
defined('_JEXEC') or die;
use TKKundendienst\Component\Gpsportal\Site\Model\TraccarModel;

$traccarModel = new TraccarModel();
?>

<style>

.logbook-card{
    background:#111827;
    border:1px solid #374151;
    border-radius:18px;
    padding:15px;
    color:#fff;
    box-shadow:0 10px 25px rgba(0,0,0,.35);
}

.logbook-title{
    font-size:24px;
    font-weight:700;
    margin-bottom:15px;
}

.top-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:10px;
}

.tile{
    background:#111827;
    border:1px solid #374151;
    border-radius:12px;
    padding:10px;
}

.tile label{
    display:block;
    font-size:12px;
    color:#cbd5e1;
    margin-bottom:4px;
}

.tile input,
.tile select{
    width:100%;
    background:#1f2937;
    color:#fff;
    border:1px solid #374151;
    border-radius:8px;
    padding:6px;
    height:34px;
}

.route-btn{
    width:100%;
    height:34px;
    margin-top:18px;
    background:#f97316;
    color:#fff;
    border:none;
    border-radius:8px;
    cursor:pointer;
}

.stats-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:10px;
    margin-top:10px;
}

.stat-value{
    display:block;
    margin-top:5px;
    color:#60a5fa;
    font-size:18px;
    font-weight:700;
}

.logbook-table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}

.logbook-table th{
    background:#1f2937;
    border:1px solid #374151;
    padding:10px;
    text-align:left;
}

.logbook-table td{
    border:1px solid #374151;
    padding:10px;
}

.logbook-table input,
.logbook-table select{
    width:100%;
    background:#111827;
    color:#fff;
    border:1px solid #374151;
    border-radius:6px;
    padding:6px;
}

.signature-box{
    margin-top:20px;
    background:#1f2937;
    border:1px solid #374151;
    border-radius:12px;
    padding:15px;
}
@media(max-width:1200px){

    .top-grid,
    .stats-grid{
        grid-template-columns:1fr;
    }
}

</style>

<div class="logbook-card">

<div class="logbook-title">
Fahrtenbuch
</div>

<form method="get">

<input type="hidden" name="option" value="com_gpsportal">
<input type="hidden" name="view" value="logbook">

<div class="top-grid">

<div class="tile">
<label>Fahrzeug</label>

<select name="vehicle">

<?php foreach ($this->vehicles as $vehicle): ?>

<option
value="<?php echo (int)$vehicle->traccar_device_id; ?>"
<?php echo ((int)$this->vehicleId === (int)$vehicle->traccar_device_id) ? 'selected' : ''; ?>
>

<?php echo htmlspecialchars($vehicle->name); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="tile">
<label>Von</label>

<input
type="datetime-local"
name="from"
value="<?php echo htmlspecialchars($this->from); ?>"
>

</div>

<div class="tile">
<label>Bis</label>

<input
type="datetime-local"
name="to"
value="<?php echo htmlspecialchars($this->to); ?>"
>

</div>

<div class="tile">
<label>&nbsp;</label>

<button
class="route-btn"
type="submit"
>
Fahrtenbuch anzeigen
</button>
</div>

</div>
</form>
<?php if (!empty($this->history)): ?>
<div style="
margin-top:10px;
background:#1f2937;
padding:10px;
border-radius:10px;
">

Gefundene Fahrten:

<strong>
<?php echo count($this->trips); ?>
</strong>

</div>
<div style="
background:#166534;
padding:10px;
border-radius:10px;
margin-top:10px;
">

Historie geladen:

<strong>
<?php echo count($this->history); ?>
</strong>

Positionen

</div>

<?php endif; ?>
<div class="stats-grid">

<div class="tile">
Fahrten
<span class="stat-value">
<?php echo count($this->trips); ?>
</span>
</div>

<div class="tile">
Kilometer
<span class="stat-value">

<?php
$totalKm = 0;

foreach ($this->trips as $trip)
{
    $totalKm += $trip['distance'];
}

echo round($totalKm,1);
?>

km

</span>
</div>

<div class="tile">
Fahrzeit
<span class="stat-value">

<?php

$totalMinutes = 0;

foreach ($this->trips as $trip)
{
    $totalMinutes += (int)($trip['duration'] ?? 0);
}

echo floor($totalMinutes / 60) . ' h '
    . ($totalMinutes % 60) . ' min';

?>

</span>
</div>
<div class="tile">
Zeitraum
<span class="stat-value">-</span>
</div>

</div>
<form method="post">

<input type="hidden" name="option" value="com_gpsportal">
<input type="hidden" name="task" value="logbook.save">

<input
type="hidden"
name="vehicle"
value="<?php echo (int)$this->vehicleId; ?>"
>
<div style="overflow-x:auto;margin-top:15px;"><table class="logbook-table">
<thead>

<tr>
<th>Datum</th>
<th>Startzeit</th>
<th>Startadresse</th>
<th>Fahrtart</th>
<th>Endzeit</th>
<th>Zieladresse</th>
<th>Start-KM</th>
<th>End-KM</th>
<th>KM</th>
<th>Dauer</th>
<th>Fahrtgrund</th>
</tr>

</thead>

<tbody>

<?php foreach ($this->trips as $trip): ?>
<?php
$tripKey = $trip['trip_key'];
?>
<tr>

<td>
<?php echo date('d.m.Y', strtotime($trip['start'])); ?>
</td>

<td>
<?php echo date('H:i', strtotime($trip['start'])); ?>
</td>

<td>
<?php
echo htmlspecialchars(
    $traccarModel->getAddress(
        (float)$trip['start_lat'],
        (float)$trip['start_lon']
    )
);
?>
</td>

<td>

<select name="trip_type[<?php echo $tripKey; ?>]">

<option
value="Geschäftlich"
<?php echo (($trip['trip_type'] ?? '') === 'Geschäftlich') ? 'selected' : ''; ?>
>
Geschäftlich
</option>

<option
value="Privat"
<?php echo (($trip['trip_type'] ?? '') === 'Privat') ? 'selected' : ''; ?>
>
Privat
</option>

<option
value="Arbeitsweg"
<?php echo (($trip['trip_type'] ?? '') === 'Arbeitsweg') ? 'selected' : ''; ?>
>
Arbeitsweg
</option>

</select>
</td>

<td>
<?php echo date('H:i', strtotime($trip['end'])); ?>
</td>

<td>
<?php
echo htmlspecialchars(
    $traccarModel->getAddress(
        (float)$trip['end_lat'],
        (float)$trip['end_lon']
    )
);
?>
</td>
<td>
<?php echo $trip['start_km']; ?>
</td>

<td>
<?php echo $trip['end_km']; ?>
</td>

<td>
<?php echo $trip['distance']; ?>
</td>
<td>

<?php

$minutes = (int)($trip['duration'] ?? 0);

echo floor($minutes / 60)
    . ' h '
    . ($minutes % 60)
    . ' min';

?>

</td>

<td>

<input
type="text"
name="trip_reason[<?php echo $tripKey; ?>]"
value="<?php echo htmlspecialchars($trip['trip_reason'] ?? ''); ?>"
placeholder="Fahrtgrund"
>
</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>
</div>
<div class="signature-box">

<h3>Bestätigung</h3>
<p>
Ort:
<input
type="text"
name="signature_place"
value="<?php echo htmlspecialchars($this->trips[0]['signature_place'] ?? ''); ?>"
style="width:200px;"
>
</p>

<p>
Datum:
<input
type="date"
name="signature_date"
value="<?php echo htmlspecialchars($this->trips[0]['signature_date'] ?? date('Y-m-d')); ?>"
>
</p>

<p>
Fahrer:
<input
type="text"
name="signature_driver"
value="<?php echo htmlspecialchars($this->trips[0]['signature_driver'] ?? ''); ?>"
style="width:200px;"
>
</p>

</div>

<?php foreach ($this->trips as $trip): ?>

<input
type="hidden"
name="trip_key[]"
value="<?php echo $trip['trip_key']; ?>"
>

<input
type="hidden"
name="trip_start[<?php echo $trip['trip_key']; ?>]"
value="<?php echo $trip['start']; ?>"
>

<input
type="hidden"
name="trip_end[<?php echo $trip['trip_key']; ?>]"
value="<?php echo $trip['end']; ?>"
>
<input
type="hidden"
name="start_lat[<?php echo $trip['trip_key']; ?>]"
value="<?php echo $trip['start_lat']; ?>"
>

<input
type="hidden"
name="start_lon[<?php echo $trip['trip_key']; ?>]"
value="<?php echo $trip['start_lon']; ?>"
>

<input
type="hidden"
name="end_lat[<?php echo $trip['trip_key']; ?>]"
value="<?php echo $trip['end_lat']; ?>"
>

<input
type="hidden"
name="end_lon[<?php echo $trip['trip_key']; ?>]"
value="<?php echo $trip['end_lon']; ?>"
>
<input
type="hidden"
name="start_km[<?php echo $trip['trip_key']; ?>]"
value="<?php echo $trip['start_km']; ?>"
>

<input
type="hidden"
name="end_km[<?php echo $trip['trip_key']; ?>]"
value="<?php echo $trip['end_km']; ?>"
>

<input
type="hidden"
name="distance_km[<?php echo $trip['trip_key']; ?>]"
value="<?php echo $trip['distance']; ?>"
>

<input
type="hidden"
name="duration_minutes[<?php echo $trip['trip_key']; ?>]"
value="<?php echo $trip['duration']; ?>"
>

<?php endforeach; ?>
<div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">

<button
type="submit"
class="route-btn"
style="max-width:250px;"
>
Fahrtenbuch speichern
</button>

<a
id="pdfButtonBottom"
href="#"
target="_blank"
class="route-btn"
style="
max-width:250px;
background:#dc2626;
pointer-events:none;
opacity:.6;
text-decoration:none;
display:inline-block;
text-align:center;
"
>
PDF erstellen
</a>
<script>
let placeField =
    document.querySelector(
        '[name="signature_place"]'
    );

let driverField =
    document.querySelector(
        '[name="signature_driver"]'
    );

if (
    placeField &&
    driverField &&
    placeField.value.trim() !== '' &&
    driverField.value.trim() !== ''
)
{
    let btn =
        document.getElementById(
            'pdfButtonBottom'
        );

    if (btn)
    {
        btn.style.background =
            '#16a34a';

        btn.style.pointerEvents =
            'auto';

        btn.style.opacity =
            '1';
    }
}
{
    let btn =
        document.getElementById(
            'pdfButtonBottom'
        );

    if (btn)
    {
        btn.style.background =
            '#16a34a';

        btn.style.pointerEvents =
            'auto';

        btn.style.opacity =
            '1';
    }
}

document
.getElementById('pdfButtonBottom')
.addEventListener(
    'click',
    function(e)
{
    e.preventDefault();

    let place =
        document.querySelector(
            '[name="signature_place"]'
        ).value;

    let date =
        document.querySelector(
            '[name="signature_date"]'
        ).value;

    let driver =
        document.querySelector(
            '[name="signature_driver"]'
        ).value;

    let url =
        '/component/gpsportal'
        + '?task=logbook.pdf'
        + '&vehicle=<?php echo (int)$this->vehicleId; ?>'
        + '&signature_place='
        + encodeURIComponent(place)
        + '&signature_date='
        + encodeURIComponent(date)
        + '&signature_driver='
        + encodeURIComponent(driver);

    window.open(
        url,
        '_blank'
    );
});

</script>
</div>
</form>
</div>

