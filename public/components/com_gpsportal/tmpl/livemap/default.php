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
    padding:;12px 15px
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
    margin-top:0;
    margin-bottom:20px;
    color:#ffffff;
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

                </div>

            <?php endforeach; ?>

        </div>

    </div>

    <div class="map-wrapper">

        <div id="map"></div>

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

var startLat =
    firstPosition.latitude;

var startLng =
    firstPosition.longitude;

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
//var trails = {};
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

                if (markers[deviceId]) {
                    markers[deviceId].setLatLng(newPos);
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
                    .addTo(map);
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
