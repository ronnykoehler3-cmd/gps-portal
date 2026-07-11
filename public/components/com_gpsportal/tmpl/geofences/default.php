<?php

defined('_JEXEC') or die;

?>

<link rel="stylesheet"
href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>

.geofence-card{
    background:#111827;
    border:1px solid #374151;
    border-radius:18px;
    padding:15px;
    color:#fff;
    box-shadow:0 10px 25px rgba(0,0,0,.35);
}

.geofence-title{
    font-size:24px;
    font-weight:700;
    margin-bottom:15px;
}

.geofence-grid{
    display:grid;
    grid-template-columns:350px 1fr;
    gap:20px;
}

.geofence-box{
    background:#1f2937;
    border:1px solid #374151;
    border-radius:12px;
    padding:15px;
}

.geofence-box label{
    display:block;
    margin-bottom:5px;
    color:#cbd5e1;
}

.geofence-box input{
    width:100%;
    box-sizing:border-box;
    background:#111827;
    color:#fff;
    border:1px solid #374151;
    border-radius:8px;
    padding:8px;
    margin-bottom:12px;
}

.save-btn{
    width:100%;
    background:#f97316;
    color:#fff;
    border:none;
    border-radius:8px;
    padding:10px;
    cursor:pointer;
}

#map{
    height:600px;
    border-radius:12px;
}

.zone-table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

.zone-table th{
    background:#1f2937;
    border:1px solid #374151;
    padding:10px;
    text-align:left;
}

.zone-table td{
    border:1px solid #374151;
    padding:10px;
}

.delete-btn{
    background:#dc2626;
    color:#fff;
    padding:6px 10px;
    border-radius:6px;
    text-decoration:none;
}

@media(max-width:1200px){

    .geofence-grid{
        grid-template-columns:1fr;
    }
}

</style>

<div class="geofence-card">

<div class="geofence-title">
Geozonen
</div>

<div class="geofence-grid">

<div class="geofence-box">

<form method="post">

<input
    type="hidden"
    name="option"
    value="com_gpsportal"
>

<input
    type="hidden"
    name="task"
    value="geofences.save"
>

<label>Name</label>

<input
    type="text"
    name="name"
    required
>

<label>Radius (Meter)</label>

<input
    type="number"
    name="radius"
    value="100"
>

<label>Latitude</label>

<input
    type="text"
    id="latitude"
    name="latitude"
    required
>

<label>Longitude</label>

<input
    type="text"
    id="longitude"
    name="longitude"
    required
>

<button
    type="submit"
    class="save-btn"
>
Geozone speichern
</button>

</form>

</div>

<div class="geofence-box">

<div id="map"></div>

</div>

</div>

<table class="zone-table">

<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Radius</th>
    <th>Aktion</th>
</tr>

<?php foreach ($this->geofences as $zone): ?>

<tr>

<td>
<?php echo $zone->id; ?>
</td>

<td>
<?php echo htmlspecialchars($zone->name); ?>
</td>

<td>
<?php echo (int)$zone->radius; ?> m
</td>

<td>

<a
class="delete-btn"
href="index.php?option=com_gpsportal&task=geofences.delete&id=<?php echo $zone->id; ?>"
onclick="return confirm('Geozone wirklich löschen?');"
>
Löschen
</a>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

<script>

var map = L.map('map').setView(
    [51.1657,10.4515],
    6
);

/* GPS-PORTAL-BASEMAP-SWITCHER-START */

const gpsPortalMapTilerKey = "3sqnkHzyCcWTYRCiB3Yw";

const gpsPortalBaseMaps = {
    'MapTiler Streets Dark': L.tileLayer(
        'https://api.maptiler.com/maps/streets-v4-dark/256/{z}/{x}/{y}.png?key='
            + encodeURIComponent(gpsPortalMapTilerKey),
        {
            attribution:
                '&copy; MapTiler &copy; OpenStreetMap contributors',
            maxZoom: 22,
            tileSize: 256
        }
    ),

    'MapTiler Streets': L.tileLayer(
        'https://api.maptiler.com/maps/streets-v4/256/{z}/{x}/{y}.png?key='
            + encodeURIComponent(gpsPortalMapTilerKey),
        {
            attribution:
                '&copy; MapTiler &copy; OpenStreetMap contributors',
            maxZoom: 22,
            tileSize: 256
        }
    ),

    'MapTiler Satellit': L.tileLayer(
        'https://api.maptiler.com/maps/satellite/256/{z}/{x}/{y}.jpg?key='
            + encodeURIComponent(gpsPortalMapTilerKey),
        {
            attribution:
                '&copy; MapTiler',
            maxZoom: 20,
            tileSize: 256
        }
    ),

    'MapTiler Hybrid': L.tileLayer(
        'https://api.maptiler.com/maps/hybrid/256/{z}/{x}/{y}.jpg?key='
            + encodeURIComponent(gpsPortalMapTilerKey),
        {
            attribution:
                '&copy; MapTiler &copy; OpenStreetMap contributors',
            maxZoom: 20,
            tileSize: 256
        }
    ),

    'OpenStreetMap': L.tileLayer(
        'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution:
                '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }
    ),

    'OpenTopoMap': L.tileLayer(
        'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
        {
            attribution:
                'Kartendaten &copy; OpenStreetMap-Mitwirkende, Darstellung &copy; OpenTopoMap',
            maxZoom: 17
        }
    ),

    'CARTO Dark Matter': L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        {
            attribution:
                '&copy; OpenStreetMap contributors &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 20
        }
    ),

    'CARTO Positron': L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
        {
            attribution:
                '&copy; OpenStreetMap contributors &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 20
        }
    )
};

const gpsPortalDefaultMapName = 'MapTiler Streets Dark';
const gpsPortalStorageKey = 'gpsportal.selectedBasemap';

let gpsPortalSelectedMapName = localStorage.getItem(
    gpsPortalStorageKey
);

if (
    !gpsPortalSelectedMapName
    || !gpsPortalBaseMaps[gpsPortalSelectedMapName]
) {
    gpsPortalSelectedMapName = gpsPortalDefaultMapName;
}

gpsPortalBaseMaps[gpsPortalSelectedMapName].addTo(
    map
);

L.control.layers(
    gpsPortalBaseMaps,
    null,
    {
        position: 'topright',
        collapsed: true,
        sortLayers: false
    }
).addTo(
    map
);

map.on(
    'baselayerchange',
    function (event) {
        if (event && event.name) {
            localStorage.setItem(
                gpsPortalStorageKey,
                event.name
            );
        }
    }
);

/* GPS-PORTAL-BASEMAP-SWITCHER-END */

var marker = null;

map.on('click', function(e){

    document.getElementById('latitude').value =
        e.latlng.lat.toFixed(6);

    document.getElementById('longitude').value =
        e.latlng.lng.toFixed(6);

    if(marker){
        map.removeLayer(marker);
    }

    marker = L.marker(e.latlng)
        .addTo(map);
});

<?php foreach ($this->geofences as $zone): ?>

L.circle(
    [
        <?php echo $zone->latitude; ?>,
        <?php echo $zone->longitude; ?>
    ],
    {
        radius:
            <?php echo (int)$zone->radius; ?>
    }
)
.addTo(map)
.bindPopup(
    <?php echo json_encode($zone->name); ?>
);

<?php endforeach; ?>

</script>
