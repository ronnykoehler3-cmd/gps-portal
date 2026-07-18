<?php
defined('_JEXEC') or die;

$positionsByDevice = [];

foreach ($this->positions as $position)
{
    $positionsByDevice[$position['deviceId']] = $position;
}

$totalDevices = count($this->devices);

$onlineDevices = 0;

foreach ($this->devices as $device)
{
    if (
        isset($device['status']) &&
        $device['status'] === 'online'
    ) {
        $onlineDevices++;
    }
}
?>

<link rel="stylesheet"
href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>

.livemap-top{
    display:flex;
    gap:20px;
    margin-bottom:20px;
}

.livemap-stat{
    background:#111827;
    color:#ffffff;
    min-width:130px;
    padding:12px 15px;
    border-radius:18px;
    border:1px solid #374151;
    box-shadow:0 10px 25px rgba(0,0,0,.35);
}

.livemap-stat strong{
    display:block;
    font-size:28px;
    margin-top:10px;
    color:#60a5fa;
}

.livemap-layout{
    display:grid;
    grid-template-columns:240px 1fr;
    gap:20px;
}

.vehicle-sidebar{
    background:#111827;
    color:#ffffff;
    border-radius:18px;
    padding:20px;
    border:1px solid #374151;
    box-shadow:0 10px 25px rgba(0,0,0,.35);
}

.vehicle-sidebar h3{
    margin-top:0;
    margin-bottom:20px;
    color:#ffffff;
}

.vehicle-search{
    width:100%;
    box-sizing:border-box;
    background:#1f2937;
    color:#ffffff;
    border:1px solid #374151;
    border-radius:10px;
    padding:10px;
    margin-bottom:15px;
}

.vehicle-search:focus{
    outline:none;
    border-color:#60a5fa;
}

.vehicle-entry{
    background:#1f2937;
    padding:10px;
    border-radius:12px;
    border:1px solid #374151;
    margin-bottom:12px;
    cursor:pointer;
    transition:all .2s ease;
}

.vehicle-entry:hover{
    background:#374151;
    transform:translateY(-1px);
}

.vehicle-name{
    color:#ffffff;
    font-size:15px;
    font-weight:600;
}

.vehicle-status{
    margin-top:6px;
    font-size:13px;
}

.map-wrapper{
    background:#111827;
    border-radius:18px;
    padding:15px;
    border:1px solid #374151;
    box-shadow:0 10px 25px rgba(0,0,0,.35);
}

#map{
    height:620px;
    border-radius:14px;
}

.status-online{
    color:#22c55e;
    font-weight:bold;
}

.status-offline{
    color:#ef4444;
    font-weight:bold;
}

.status-unknown{
    color:#f59e0b;
    font-weight:bold;
}

.leaflet-popup-content{
    font-size:14px;
    line-height:1.5;
}

.leaflet-popup-content b{
    font-size:16px;
}

.vehicle-speed{
    color:#93c5fd;
    font-size:13px;
    margin-top:4px;
}

.speed-legend{
    display:flex;
    flex-wrap:wrap;
    gap:8px 14px;
    margin-top:12px;
    padding:12px;
    border-radius:12px;
    background:#111827;
    border:1px solid #374151;
    color:#e2e8f0;
    font-size:13px;
}

.speed-legend strong{width:100%}
.speed-legend span{white-space:nowrap}
.speed-color{display:inline-block;width:13px;height:13px;border-radius:3px;margin-right:5px;vertical-align:-2px}
.trail-direction-arrow{font-size:16px;line-height:16px;text-shadow:-1px -1px 0 #fff,1px -1px 0 #fff,-1px 1px 0 #fff,1px 1px 0 #fff}
.live-route-flag{position:relative;width:32px;height:40px;filter:drop-shadow(0 2px 3px rgba(0,0,0,.7))}.live-route-flag-pole{position:absolute;left:5px;top:2px;width:3px;height:36px;background:#f8fafc;border:1px solid #334155;border-radius:2px}.live-route-flag-cloth{position:absolute;left:8px;top:2px;min-width:22px;height:19px;padding:0 3px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:900;border:2px solid #fff;border-left:0;border-radius:0 4px 4px 0;box-sizing:border-box}

</style>
<div class="livemap-top">

    <div class="livemap-stat">
        Fahrzeuge
        <strong id="deviceCount">
            <?php echo $totalDevices; ?>
        </strong>
    </div>

    <div class="livemap-stat">
        Online
        <strong id="onlineCount">
            <?php echo $onlineDevices; ?>
        </strong>
    </div>

</div>

<div class="livemap-layout">

    <div class="vehicle-sidebar">

<h3>Fahrzeuge</h3>

<input
    type="text"
    id="vehicleSearch"
    class="vehicle-search"
    placeholder="🔍 Fahrzeug suchen..."
>

<div id="vehicleList">
            <?php foreach ($this->devices as $device): ?>

                <?php
                $position =
                    $positionsByDevice[$device['id']]
                    ?? null;
                ?>

                <div
                    class="vehicle-entry"
                    onclick="focusDevice(<?php echo $device['id']; ?>)"
                >

<div class="vehicle-name">

<?php
$meta =
    $this->vehicleMeta[$device['id']]
    ?? [];

$icon =
    $meta['marker_icon']
    ?? '';

switch ($icon)
{
    case 'car':
        echo '🚗 ';
        break;

    case 'van':
        echo '🚐 ';
        break;

    case 'truck':
        echo '🚚 ';
        break;

    case 'motorcycle':
        echo '🏍️ ';
        break;

    case 'phone':
        echo '📱 ';
        break;

    case 'tablet':
        echo '📟 ';
        break;

    case 'hearse':
        echo '⚰️ ';
        break;

    default:
        echo '📍 ';
}
?>

<?php
$portalMeta = $this->vehicleMeta[
    (int) ($device['id'] ?? 0)
] ?? [];

$displayName = trim(
    (string) (
        $portalMeta['name']
        ?? $device['name']
        ?? ''
    )
);

echo htmlspecialchars(
    $displayName,
    ENT_QUOTES,
    'UTF-8'
);
?>

</div>
                    <div class="vehicle-status">

                        <?php
                        if (
                            isset($device['status']) &&
                            $device['status'] === 'online'
                        ) {
                            echo '🟢 Online';
                        }
                        else {
                            echo '🔴 Offline';
                        }
                        ?>

                    </div>

                    <div class="vehicle-speed" data-speed-device="<?php echo (int) $device['id']; ?>">
                        <?php if ($position): ?>
                            <?php echo number_format(max(0, (float) ($position['speed'] ?? 0)) * 1.852, 0, ',', '.'); ?> km/h
                        <?php else: ?>
                            Keine Position
                        <?php endif; ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

    <div class="map-wrapper">

        <div id="map"></div>

        <div class="speed-legend" aria-label="Geschwindigkeitslegende">
            <strong>Spur nach Geschwindigkeit</strong>
            <span><i class="speed-color" style="background:#64748b"></i>0 km/h</span>
            <span><i class="speed-color" style="background:#22c55e"></i>1–50 km/h</span>
            <span><i class="speed-color" style="background:#eab308"></i>51–80 km/h</span>
            <span><i class="speed-color" style="background:#f97316"></i>81–119 km/h</span>
            <span><i class="speed-color" style="background:#ef4444"></i>ab 120 km/h</span>
            <span>➤ Fahrtrichtung</span>
            <span>🟢 Start</span>
            <span>🔴 Ende</span>
        </div>

    </div>

</div>

<div id="geofenceNotifications"></div>

<style>
#geofenceNotifications{
    position:fixed;
    right:20px;
    bottom:20px;
    z-index:99999;
}

.geofence-popup{
    background:#1f2937;
    color:white;
    padding:14px;
    margin-top:10px;
    border-radius:10px;
    min-width:320px;
    box-shadow:0 4px 20px rgba(0,0,0,.35);
    animation:fadeIn .3s ease;
}

@keyframes fadeIn{
    from{
        opacity:0;
        transform:translateY(20px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}
</style>

<script>
var rememberMapPosition = <?php echo json_encode($this->rememberMapPosition); ?>;
var popupGeofenceEvents = <?php echo json_encode($this->popupGeofenceEvents); ?>;
var showVehicleNames = <?php echo json_encode($this->showVehicleNames); ?>;
var tripEndStopMinutes = <?php echo (int) $this->tripEndStopMinutes; ?>;
var initialTrails = <?php echo json_encode(
    $this->trails ?? [],
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
); ?>;

var vehicleDisplayMode =
<?php echo json_encode(
    $this->vehicleDisplayMode ?? 'name'
); ?>;

function getVehicleDisplayName(
    vehicleName,
    licensePlate
)
{
    vehicleName =
        String(vehicleName || '').trim();

    licensePlate =
        String(licensePlate || '').trim();

    if (
        vehicleDisplayMode === 'license_plate'
        && licensePlate !== ''
    ) {
        return licensePlate;
    }

    if (
        vehicleDisplayMode === 'name_and_plate'
        && licensePlate !== ''
    ) {
        return vehicleName !== ''
            ? vehicleName + ' (' + licensePlate + ')'
            : licensePlate;
    }

    return vehicleName !== ''
        ? vehicleName
        : licensePlate;
}
var firstPosition =
<?php echo json_encode($this->positions[0] ?? null); ?>;

var startLat = firstPosition
    ? Number(firstPosition.latitude)
    : 53.55;

var startLng = firstPosition
    ? Number(firstPosition.longitude)
    : 10.0;

var startZoom = 14;

if (
    rememberMapPosition &&
    localStorage.getItem('gpsportal_map_lat')
)
{
    startLat =
        parseFloat(
            localStorage.getItem(
                'gpsportal_map_lat'
            )
        );

    startLng =
        parseFloat(
            localStorage.getItem(
                'gpsportal_map_lng'
            )
        );

    startZoom =
        parseInt(
            localStorage.getItem(
                'gpsportal_map_zoom'
            )
        );
}

var map = L.map('map').setView(
    [startLat, startLng],
    startZoom
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
<?php if ($this->showGeofences): ?>

<?php foreach ($this->geofences as $zone): ?>
L.circle(
    [
        <?php echo (float)$zone->latitude; ?>,
        <?php echo (float)$zone->longitude; ?>
    ],
    {
        radius: <?php echo (int)$zone->radius; ?>,
        color: '#3b82f6',
        fillColor: '#60a5fa',
        fillOpacity: 0.20
    }
)
.addTo(map)
.bindPopup(
    '<strong><?php echo addslashes($zone->name); ?></strong><br>' +
    'Radius: <?php echo (int)$zone->radius; ?> m'
);

<?php endforeach; ?>

<?php endif; ?>
var markers = {};
var trailLayers = {};
var trailLastPositions = {};
var trailPointCounters = {};
var trailEndMarkers = {};
var trailArrowDistances = {};
var vehicleMeta =
<?php echo json_encode($this->vehicleMeta ?? []); ?>;

function getColor(color)
{
    switch(color)
    {
        case 'Schwarz':
            return '#111111';

        case 'Blau':
            return '#2563eb';

        case 'Rot':
            return '#dc2626';

        case 'Grün':
            return '#16a34a';

        case 'Gelb':
            return '#eab308';

        case 'Orange':
            return '#f97316';

        case 'Grau':
            return '#6b7280';

        case 'Weiß':
            return '#ffffff';

        default:
            return '#2563eb';
    }
}

function getSymbol(icon)
{
    switch(icon)
    {
        case 'car':
            return '🚗';

        case 'van':
            return '🚐';

        case 'truck':
            return '🚚';

        case 'motorcycle':
            return '🏍️';

        case 'phone':
            return '📱';

        case 'tablet':
            return '📟';

        case 'hearse':
            return '⚰️';

        default:
            return '📍';
    }
}

function speedKmh(position)
{
    if (
        position
        && Number.isFinite(Number(position.speedKmh))
    ) {
        return Math.max(0, Number(position.speedKmh));
    }

    return Math.max(0, Number(position && position.speed || 0) * 1.852);
}

function speedColor(speed)
{
    speed = Number(speed || 0);

    if (speed < 1) return '#64748b';
    if (speed <= 50) return '#22c55e';
    if (speed <= 80) return '#eab308';
    if (speed < 120) return '#f97316';
    return '#ef4444';
}

function escapeMapHtml(value)
{
    var element = document.createElement('div');
    element.textContent = String(value || '');
    return element.innerHTML;
}

function ensureTrailLayer(deviceId)
{
    if (!trailLayers[deviceId]) {
        trailLayers[deviceId] = L.featureGroup().addTo(map);
    }

    return trailLayers[deviceId];
}

function liveRouteFlagIcon(color, label)
{
    return L.divIcon({
        className: '',
        html: '<div class="live-route-flag"><span class="live-route-flag-pole"></span><span class="live-route-flag-cloth" style="background:' + color + '">' + label + '</span></div>',
        iconSize: [32, 40],
        iconAnchor: [6, 38],
        popupAnchor: [8, -35]
    });
}

function trailBearing(previous, current)
{
    var lat1 = Number(previous.latitude) * Math.PI / 180;
    var lat2 = Number(current.latitude) * Math.PI / 180;
    var deltaLongitude = (
        Number(current.longitude) - Number(previous.longitude)
    ) * Math.PI / 180;
    var y = Math.sin(deltaLongitude) * Math.cos(lat2);
    var x = Math.cos(lat1) * Math.sin(lat2)
        - Math.sin(lat1) * Math.cos(lat2) * Math.cos(deltaLongitude);

    return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
}

function trailDistanceKm(previous, current)
{
    var earthRadius = 6371;
    var lat1 = Number(previous.latitude) * Math.PI / 180;
    var lat2 = Number(current.latitude) * Math.PI / 180;
    var deltaLat = lat2 - lat1;
    var deltaLon = (
        Number(current.longitude) - Number(previous.longitude)
    ) * Math.PI / 180;
    var value = Math.sin(deltaLat / 2) ** 2
        + Math.cos(lat1) * Math.cos(lat2) * Math.sin(deltaLon / 2) ** 2;

    return earthRadius * 2 * Math.atan2(
        Math.sqrt(value),
        Math.sqrt(Math.max(0, 1 - value))
    );
}

function addTrailArrow(previous, position, group)
{
    /* Das Zeichen ➤ zeigt ohne Drehung nach Osten (90 Grad). */
    var rotation = trailBearing(previous, position) - 90;
    var icon = L.divIcon({
        className: 'trail-direction-arrow',
        html: '<span style="display:block;color:#111111'
            + ';transform:rotate(' + rotation + 'deg)">➤</span>',
        iconSize: [16, 16],
        iconAnchor: [8, 8]
    });

    L.marker(
        [Number(position.latitude), Number(position.longitude)],
        {icon: icon, interactive: false}
    ).addTo(group);
}

function drawInitialTrails(deviceFilter)
{
    var source = initialTrails || {};
    var deviceIds = deviceFilter
        ? [String(deviceFilter)]
        : Object.keys(source);

    deviceIds.forEach(function(deviceId) {
        var trail = initialTrails[deviceId] || {};
        var trip = trail.trip || {};
        var positions = Array.isArray(trip.positions) ? trip.positions : [];

        if (positions.length === 0) return;

        if (deviceFilter && trailLayers[deviceId]) {
            map.removeLayer(trailLayers[deviceId]);
            delete trailLayers[deviceId];
        }

        var group = ensureTrailLayer(deviceId);

        var arrowSpacingKm = Math.max(
            0.4,
            Number(trip.distanceKm || 0) / 60
        );
        var distanceSinceArrow = 0;

        for (var index = 1; index < positions.length; index++) {
            var previous = positions[index - 1];
            var current = positions[index];

            L.polyline(
                [
                    [Number(previous.latitude), Number(previous.longitude)],
                    [Number(current.latitude), Number(current.longitude)]
                ],
                {
                    color: speedColor(speedKmh(current)),
                    weight: 6,
                    opacity: 0.9
                }
            ).addTo(group);

            distanceSinceArrow += trailDistanceKm(previous, current);

            if (distanceSinceArrow >= arrowSpacingKm) {
                addTrailArrow(previous, current, group);
                distanceSinceArrow = 0;
            }
        }

        var start = positions[0];
        var end = positions[positions.length - 1];

        L.marker(
            [Number(start.latitude), Number(start.longitude)],
            {icon: liveRouteFlagIcon('#16a34a', 'S')}
        ).bindPopup('Start der Tour').addTo(group);

        trailEndMarkers[deviceId] = L.marker(
            [Number(end.latitude), Number(end.longitude)],
            {icon: liveRouteFlagIcon('#dc2626', 'Z')}
        ).bindPopup('Aktuelles Ziel der Tour').addTo(group);

        (trail.stops || []).forEach(function(stop) {
            L.circleMarker(
                [Number(stop.latitude), Number(stop.longitude)],
                {radius: 6, color: '#fff', weight: 2, fillColor: '#dc2626', fillOpacity: 1}
            ).bindPopup(
                '<strong>Stopp</strong><br>'
                + new Date(stop.startTime).toLocaleString('de-DE')
                + '<br>Dauer: ' + Number(stop.durationMinutes) + ' Minuten'
            ).addTo(group);
        });

        trailLastPositions[deviceId] = end;
        trailPointCounters[deviceId] = positions.length;
        trailArrowDistances[deviceId] = distanceSinceArrow;
    });
}

function appendLiveTrail(position)
{
    var deviceId = Number(position.deviceId || 0);
    var previous = trailLastPositions[deviceId];

    if (!previous) {
        trailLastPositions[deviceId] = position;
        return;
    }

    var previousTime = Date.parse(
        previous.fixTime || previous.deviceTime || previous.serverTime || ''
    );
    var currentTime = Date.parse(
        position.fixTime || position.deviceTime || position.serverTime || ''
    );

    if (Number.isFinite(previousTime) && Number.isFinite(currentTime) && currentTime <= previousTime) {
        return;
    }

    if (
        Number.isFinite(previousTime)
        && Number.isFinite(currentTime)
        && currentTime - previousTime >= tripEndStopMinutes * 60 * 1000
        && speedKmh(position) >= 1
    ) {
        if (trailLayers[deviceId]) {
            map.removeLayer(trailLayers[deviceId]);
            delete trailLayers[deviceId];
            delete trailEndMarkers[deviceId];
        }

        var newGroup = ensureTrailLayer(deviceId);
        L.marker(
            [Number(position.latitude), Number(position.longitude)],
            {icon: liveRouteFlagIcon('#16a34a', 'S')}
        ).bindPopup('Start der neuen Tour').addTo(newGroup);
        trailLastPositions[deviceId] = position;
        trailPointCounters[deviceId] = 1;
        trailArrowDistances[deviceId] = 0;
        return;
    }

    var group = ensureTrailLayer(deviceId);

    L.polyline(
        [
            [Number(previous.latitude), Number(previous.longitude)],
            [Number(position.latitude), Number(position.longitude)]
        ],
        {color: speedColor(speedKmh(position)), weight: 6, opacity: 0.9}
    ).addTo(group);

    trailPointCounters[deviceId] = Number(trailPointCounters[deviceId] || 0) + 1;

    trailArrowDistances[deviceId] = Number(
        trailArrowDistances[deviceId] || 0
    ) + trailDistanceKm(previous, position);

    if (trailArrowDistances[deviceId] >= 0.4) {
        addTrailArrow(previous, position, group);
        trailArrowDistances[deviceId] = 0;
    }

    if (trailEndMarkers[deviceId]) {
        trailEndMarkers[deviceId].setLatLng([
            Number(position.latitude),
            Number(position.longitude)
        ]);
    } else {
        trailEndMarkers[deviceId] = L.marker(
            [Number(position.latitude), Number(position.longitude)],
            {icon: liveRouteFlagIcon('#dc2626', 'Z')}
        ).bindPopup('Aktuelles Ziel der Tour').addTo(group);
    }

    trailLastPositions[deviceId] = position;
}

<?php foreach ($this->positions as $position): ?>

var popupHtml = '';

var meta =
vehicleMeta[
    <?php echo $position['deviceId']; ?>
] || {};

var traccarDeviceName =
<?php echo json_encode(
    $this->devices[
        array_search(
            $position['deviceId'],
            array_column(
                $this->devices,
                'id'
            )
        )
    ]['name'] ?? ''
); ?>;

var deviceName =
    String(
        meta.name
        || traccarDeviceName
        || ''
    );


popupHtml += '<b>'
+ getVehicleDisplayName(deviceName, meta.license_plate || '')
+ '</b><br>';

if(meta.license_plate)
{
    popupHtml +=
        'Kennzeichen: '
        + meta.license_plate
        + '<br>';
}

if(meta.driver)
{
    popupHtml +=
        'Fahrer: '
        + meta.driver
        + '<br>';
}

popupHtml +=
    'Geschwindigkeit: '
    + Math.round(
        <?php echo max(0, (float) ($position['speed'] ?? 0)) * 1.852; ?>
    )
    + ' km/h<br>';
var markerColor =
    getColor(meta.color);

var markerSymbol =
    getSymbol(meta.marker_icon);

var vehicleIcon =
L.divIcon({
    className: '',

html:
    '<div style="text-align:center;">'

    + '<div style="'
    + 'background:'
    + markerColor
    + ';width:42px;'
    + 'height:42px;'
    + 'border-radius:50%;'
    + 'display:flex;'
    + 'align-items:center;'
    + 'justify-content:center;'
    + 'font-size:22px;'
    + 'border:3px solid white;'
    + 'box-shadow:0 2px 8px rgba(0,0,0,.35);'
    + 'margin:auto;'
    + '">'
    + markerSymbol
    + '</div>'

+ (
    showVehicleNames
    ?
    '<div style="'
    + 'background:rgba(255,255,255,0.95);'
    + 'padding:3px 8px;'
    + 'border-radius:6px;'
    + 'margin-top:4px;'
    + 'font-size:13px;'
    + 'font-weight:bold;'
    + 'color:#000;'
    + 'white-space:nowrap;'
    + 'border:1px solid #ccc;'
    + 'box-shadow:0 2px 6px rgba(0,0,0,.4);'
    + '">'
    + getVehicleDisplayName(deviceName, meta.license_plate || '')
    + '</div>'
    :
    ''
)
    + '</div>',
iconSize:[120,70],
iconAnchor:[60,21]
});
markers[
    <?php echo $position['deviceId']; ?>
] =
L.marker(
[
    <?php echo $position['latitude']; ?>,
    <?php echo $position['longitude']; ?>
],
{
    icon: vehicleIcon
}
)
.addTo(map)
.bindPopup(
    popupHtml
);

<?php endforeach; ?>

drawInitialTrails();

function focusDevice(deviceId)
{
    if(!markers[deviceId])
    {
        return;
    }

    map.setView(
        markers[deviceId].getLatLng(),
        16
    );

    markers[deviceId].openPopup();

    if (!initialTrails[deviceId]) {
        fetch(
            '/?option=com_gpsportal&view=api&trailDevice='
            + encodeURIComponent(deviceId)
        )
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Spur konnte nicht geladen werden.');
            }

            return response.json();
        })
        .then(function(data) {
            if (data && data.trail) {
                initialTrails[deviceId] = data.trail;
                drawInitialTrails(deviceId);

                if (
                    trailLayers[deviceId]
                    && trailLayers[deviceId].getBounds().isValid()
                ) {
                    map.fitBounds(
                        trailLayers[deviceId].getBounds(),
                        {padding: [30, 30]}
                    );
                }
            }
        })
        .catch(function(error) {
            console.error(error);
        });
    }
}
var lastGeofenceEventId = null;

function showGeofencePopup(message)
{
if (!popupGeofenceEvents)
{
    return;
}
    var container =
        document.getElementById(
            'geofenceNotifications'
        );

    var popup =
        document.createElement('div');

    popup.className =
        'geofence-popup';

    popup.innerHTML =
        message;

    container.appendChild(
        popup
    );

    setTimeout(function(){

        popup.remove();

    }, 6000);
}
function updateMap()
{
    fetch('/?option=com_gpsportal&view=api')
    .then(response => response.json())
    .then(data => {
        if(data.geofenceEvents)
        {
            if (
                lastGeofenceEventId === null
                &&
                data.geofenceEvents.length > 0
            )
            {
                lastGeofenceEventId =
                    data.geofenceEvents[0].id;

                return;
            }        
    data.geofenceEvents
            .reverse()
            .forEach(function(event){

                if(
                    event.id >
                    lastGeofenceEventId
                )
                {
                    var icon =
                        event.event_type === 'enter'
                        ? '🟢'
                        : '🔴';

                    var text =
                        icon
                        + ' '
                        + event.vehicle_name
                        + ' hat '
                        + event.geofence_name
                        + ' '
                        + (
                            event.event_type === 'enter'
                            ? 'betreten'
                            : 'verlassen'
                        );

                    showGeofencePopup(
                        text
                    );

                    lastGeofenceEventId =
                        event.id;
                }
            });
        }
        var vehicleDisplayMode =
            <?php echo json_encode(
                $this->vehicleDisplayMode ?? 'name'
            ); ?>;

        function getVehicleDisplayName(
            vehicleName,
            licensePlate
        )
        {
            vehicleName =
                String(vehicleName || '').trim();

            licensePlate =
                String(licensePlate || '').trim();

            if (
                vehicleDisplayMode === 'license_plate'
                && licensePlate !== ''
            ) {
                return licensePlate;
            }

            if (
                vehicleDisplayMode === 'name_and_plate'
                && licensePlate !== ''
            ) {
                return vehicleName !== ''
                    ? vehicleName + ' (' + licensePlate + ')'
                    : licensePlate;
            }

            return vehicleName !== ''
                ? vehicleName
                : licensePlate;
        }

        var onlineCount = 0;
        var vehicleHtml = '';

        data.devices.forEach(function(device){

            var meta = {};

            if (
                data.vehicleMeta
                && data.vehicleMeta[device.id]
            ) {
                meta = data.vehicleMeta[device.id];
            }

            var displayName =
                getVehicleDisplayName(
                    meta.name
                        || device.name
                        || '',
                    meta.license_plate
                        || device.license_plate
                        || ''
                );

            var statusText = '🔴 Offline';

            if(device.status === 'online')
            {
                statusText = '🟢 Online';
                onlineCount++;
            }

            vehicleHtml += `
                <div
                    class="vehicle-entry"
                    onclick="focusDevice(${device.id})"
                >
                    <div class="vehicle-name">
                        ${displayName}
                    </div>

                    <div class="vehicle-status">
                        ${statusText}
                    </div>

                    <div
                        class="vehicle-speed"
                        data-speed-device="${device.id}"
                    >
                        Geschwindigkeit wird geladen …
                    </div>
                </div>
            `;

        });

        document.getElementById(
            'vehicleList'
        ).innerHTML =
            vehicleHtml;

        document.getElementById(
            'deviceCount'
        ).innerText =
            data.devices.length;

        document.getElementById(
            'onlineCount'
        ).innerText =
            onlineCount;

        if (Array.isArray(data.positions)) {
            data.positions.forEach(function(position) {

                var deviceId =
                    Number(position.deviceId || 0);

                var latitude =
                    Number(position.latitude);

                var longitude =
                    Number(position.longitude);

                if (
                    deviceId <= 0
                    || !Number.isFinite(latitude)
                    || !Number.isFinite(longitude)
                ) {
                    return;
                }

                var newPos = [
                    latitude,
                    longitude
                ];

                appendLiveTrail(position);

                var speedElement = document.querySelector(
                    '[data-speed-device="' + deviceId + '"]'
                );

                if (speedElement) {
                    speedElement.textContent =
                        Math.round(speedKmh(position)) + ' km/h';
                }

                if (markers[deviceId]) {
                    markers[deviceId].setLatLng(newPos);

                    var currentMeta = data.vehicleMeta
                        && data.vehicleMeta[deviceId]
                        ? data.vehicleMeta[deviceId]
                        : {};
                    var currentDevice = Array.isArray(data.devices)
                        ? data.devices.find(function(item) {
                            return Number(item.id) === deviceId;
                        })
                        : null;
                    var currentName = getVehicleDisplayName(
                        currentMeta.name || (currentDevice && currentDevice.name) || '',
                        currentMeta.license_plate || ''
                    );

                    markers[deviceId].setPopupContent(
                        '<b>' + escapeMapHtml(currentName) + '</b><br>'
                        + 'Geschwindigkeit: '
                        + Math.round(speedKmh(position))
                        + ' km/h<br>'
                        + 'Letzte Position: '
                        + new Date(
                            position.fixTime
                            || position.deviceTime
                            || position.serverTime
                        ).toLocaleString('de-DE')
                    );
                    return;
                }

                var meta = {};

                if (
                    data.vehicleMeta
                    && data.vehicleMeta[deviceId]
                ) {
                    meta = data.vehicleMeta[deviceId];
                }

                var device = null;

                if (Array.isArray(data.devices)) {
                    device = data.devices.find(
                        function(item) {
                            return Number(item.id) === deviceId;
                        }
                    ) || null;
                }

                var deviceName =
                    String(
                        meta.name
                        || (
                            device
                            && device.name
                        )
                        || ''
                    );

                var markerColor =
                    getColor(meta.color);

                var markerSymbol =
                    getSymbol(meta.marker_icon);

                var markerLabel =
                    getVehicleDisplayName(
                        deviceName,
                        meta.license_plate
                            || (
                                device
                                && device.license_plate
                            )
                            || ''
                    );

                var vehicleIcon =
                    L.divIcon({
                        className: '',
                        html:
                            '<div style="text-align:center;">'
                            + '<div style="'
                            + 'background:' + markerColor + ';'
                            + 'width:42px;'
                            + 'height:42px;'
                            + 'border-radius:50%;'
                            + 'display:flex;'
                            + 'align-items:center;'
                            + 'justify-content:center;'
                            + 'font-size:22px;'
                            + 'border:3px solid white;'
                            + 'box-shadow:0 2px 8px rgba(0,0,0,.35);'
                            + 'margin:auto;'
                            + '">'
                            + markerSymbol
                            + '</div>'
                            + (
                                showVehicleNames
                                ? '<div style="'
                                    + 'background:rgba(255,255,255,0.95);'
                                    + 'padding:3px 8px;'
                                    + 'border-radius:6px;'
                                    + 'margin-top:4px;'
                                    + 'font-size:13px;'
                                    + 'font-weight:bold;'
                                    + 'color:#000;'
                                    + 'white-space:nowrap;'
                                    + 'border:1px solid #ccc;'
                                    + 'box-shadow:0 2px 6px rgba(0,0,0,.4);'
                                    + '">'
                                    + markerLabel
                                    + '</div>'
                                : ''
                            )
                            + '</div>',
                        iconSize: [120, 70],
                        iconAnchor: [60, 21]
                    });

                markers[deviceId] =
                    L.marker(
                        newPos,
                        {
                            icon: vehicleIcon
                        }
                    )
                    .addTo(map)
                    .bindPopup(
                        '<b>' + escapeMapHtml(markerLabel) + '</b><br>'
                        + 'Geschwindigkeit: '
                        + Math.round(speedKmh(position))
                        + ' km/h'
                    );
            });
        }

    });
}
document
.getElementById('vehicleSearch')
.addEventListener('keyup', function(){

    var search =
        this.value.toLowerCase();

    document
    .querySelectorAll('.vehicle-entry')
    .forEach(function(item){

        if(
            item.innerText
            .toLowerCase()
            .includes(search)
        )
        {
            item.style.display = '';
        }
        else
        {
            item.style.display = 'none';
        }

    });

});
updateMap();
if (rememberMapPosition)
{
    map.on(
        'moveend',
        function()
        {
            localStorage.setItem(
                'gpsportal_map_lat',
                map.getCenter().lat
            );

            localStorage.setItem(
                'gpsportal_map_lng',
                map.getCenter().lng
            );

            localStorage.setItem(
                'gpsportal_map_zoom',
                map.getZoom()
            );
        }
    );
}
setInterval(updateMap, 5000);

</script>
