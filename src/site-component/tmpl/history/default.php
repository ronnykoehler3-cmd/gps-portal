<?php
defined('_JEXEC') or die;
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
.history-card{background:#111827;border:1px solid #374151;border-radius:18px;padding:15px;color:#fff;box-shadow:0 10px 25px rgba(0,0,0,.35);}
.history-title{font-size:24px;font-weight:700;margin-bottom:15px;}
.top-grid,
.stats-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:10px;
}

.tile{
    background:#111827;
    border:1px solid #374151;
    border-radius:12px;
    padding:10px;
    box-shadow:0 4px 12px rgba(0,0,0,.25);
}

.tile label{
    display:block;
    font-size:12px;
    margin-bottom:4px;
    color:#cbd5e1;
}

.tile input,
.tile select{
    width:100%;
    background:#1f2937;
    color:#fff;
    border:1px solid #374151;
    border-radius:8px;
    padding:6px 8px;
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
    font-size:13px;
    font-weight:600;
    cursor:pointer;
}

.stats-grid{
    margin-top:10px;
}

.stat-value{
    display:block;
    font-size:16px;
    font-weight:700;
    color:#60a5fa;
    margin-top:4px;
}

.main-layout{
    display:grid;
    grid-template-columns:180px 1fr;
    gap:10px;
    margin-top:10px;
    align-items:start;
}
.stops-panel{
    background:#1f2937;
    border:1px solid #374151;
    border-radius:12px;
    padding:10px;
    overflow-y:auto;

    height:fit-content;
    max-height:420px;

    align-self:start;
}
.map-wrapper{
    background:#111827;
    border:1px solid #374151;
    border-radius:12px;
    padding:10px;
}

#historyMap{
    height:500px;
    border-radius:10px;
}
.stops-panel{
    background:#1f2937;
    border:1px solid #374151;
    border-radius:12px;
    padding:10px;
    height:auto;
    min-height:0;
    overflow-y:auto;
}

.map-wrapper{
    background:#111827;
    border:1px solid #374151;
    border-radius:12px;
    padding:10px;
}

#historyMap{
    height:420px;
    border-radius:10px;
}
.stop-item{
    background:#111827;
    border:1px solid #374151;
    border-radius:8px;
    padding:8px;
    margin-bottom:8px;
    font-size:13px;
}
@media(max-width:1200px){.top-grid,.stats-grid,.main-layout{grid-template-columns:1fr;} .stops-panel{height:300px;} #historyMap{height:450px;}}
</style>

<div class="history-card">
<div class="history-title">Fahrhistorie</div>

<form method="get">
<input type="hidden" name="option" value="com_gpsportal">
<input type="hidden" name="view" value="history">

<div class="top-grid">
<div class="tile">
<label>Fahrzeug</label>
<select name="vehicle">
<?php foreach ($this->vehicles as $vehicle): ?>
<option value="<?php echo (int)$vehicle->traccar_device_id; ?>" <?php echo ((int)$this->vehicleId === (int)$vehicle->traccar_device_id) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($vehicle->name); ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="tile">
<label>Von</label>
<input type="datetime-local" name="from" value="<?php echo htmlspecialchars($this->from); ?>">
</div>

<div class="tile">
<label>Bis</label>
<input type="datetime-local" name="to" value="<?php echo htmlspecialchars($this->to); ?>">
</div>

<div class="tile">
<label>&nbsp;</label>
<button type="submit" class="route-btn">Route anzeigen</button>
</div>
</div>
</form>

<?php if (!empty($this->history)): ?>

<div class="stats-grid">
<div class="tile">Positionen<span class="stat-value"><?php echo count($this->history); ?></span></div>
<div class="tile">Stopps<span class="stat-value" id="stopCount">0</span></div>
<div class="tile">Zeitraum<span class="stat-value"><?php echo htmlspecialchars(substr($this->from,11,5)); ?> - <?php echo htmlspecialchars(substr($this->to,11,5)); ?></span></div>
<div class="tile">
Kilometer
<span class="stat-value">
<?php echo $this->distanceKm; ?> km
</span>
</div>
</div>

<div class="main-layout">
<div class="stops-panel">
<h3>Erkannte Stopps</h3>
<div id="stopsContent">Keine Stopps gefunden</div>
</div>

<div class="map-wrapper">
<div id="historyMap"></div>
</div>
</div>

<script>
var historyData = <?php echo json_encode($this->history); ?>;

var map = L.map('historyMap');
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

var routePoints=[];
historyData.forEach(function(pos){
    routePoints.push([pos.latitude,pos.longitude]);
});

if(routePoints.length>1){
    var routeLine=L.polyline(routePoints,{color:'#ff7a00',weight:5,opacity:0.9}).addTo(map);
    map.fitBounds(routeLine.getBounds());
    L.marker(routePoints[0]).addTo(map).bindPopup('🟢 Start');
    L.marker(routePoints[routePoints.length-1]).addTo(map).bindPopup('🔴 Ende');
}

var stopStart=null;
var stopsHtml='';
var stopCounter=0;

for(var i=0;i<historyData.length;i++){
    var pos=historyData[i];
    var motion=pos.attributes && pos.attributes.motion;

    if(motion===false){
        if(stopStart===null){ stopStart=pos; }
    }else{
        if(stopStart!==null){
            var stopEnd=historyData[i-1];
            var startTime=new Date(stopStart.fixTime);
            var endTime=new Date(stopEnd.fixTime);
            var duration=Math.round((endTime-startTime)/1000/60);

            if(duration>=5){
                stopCounter++;

                var stopIcon=L.divIcon({
                    className:'',
                    html:'<div style="width:28px;height:28px;background:#dc2626;border:3px solid white;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;">⛔</div>',
                    iconSize:[28,28],
                    iconAnchor:[14,14]
                });

                L.marker([stopStart.latitude,stopStart.longitude],{icon:stopIcon})
                .addTo(map)
                .bindPopup('Pause<br>'+duration+' Minuten');

                stopsHtml += '<div class="stop-item"><strong>⛔ '+duration+' Minuten</strong><br>'+startTime.toLocaleString()+'<br>'+endTime.toLocaleString()+'</div>';
            }
            stopStart=null;
        }
    }
}

if(stopsHtml!==''){
    document.getElementById('stopsContent').innerHTML=stopsHtml;
}
document.getElementById('stopCount').innerText=stopCounter;
</script>

<?php endif; ?>

</div>
