<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$edit = $this->editGeofence;
$countries = [
    'de' => 'Deutschland', 'pl' => 'Polen', 'cz' => 'Tschechien', 'at' => 'Österreich',
    'ch' => 'Schweiz', 'fr' => 'Frankreich', 'be' => 'Belgien', 'nl' => 'Niederlande',
    'dk' => 'Dänemark', 'lu' => 'Luxemburg', 'it' => 'Italien', 'es' => 'Spanien',
    'pt' => 'Portugal', 'se' => 'Schweden', 'no' => 'Norwegen', 'fi' => 'Finnland',
    'sk' => 'Slowakei', 'hu' => 'Ungarn', 'si' => 'Slowenien', 'hr' => 'Kroatien',
    'ro' => 'Rumänien', 'bg' => 'Bulgarien', 'lt' => 'Litauen', 'lv' => 'Lettland',
    'ee' => 'Estland', 'gb' => 'Großbritannien', 'ie' => 'Irland',
];
$zoneType = (string) ($edit->zone_type ?? 'address');
$color = (string) ($edit->status_color ?? 'green');
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/components/com_gpsportal/media/js/turf.min.js"></script>

<style>
.geo-shell{color:#fff}.geo-header{margin-bottom:18px}.geo-grid{display:grid;grid-template-columns:390px minmax(0,1fr);gap:18px}.geo-card{background:#081327;border:1px solid #1e3a5f;border-radius:16px;padding:18px}.geo-form{display:grid;gap:12px}.geo-form label{display:block;color:#93c5fd;margin-bottom:5px}.geo-form input,.geo-form select{width:100%;box-sizing:border-box;padding:10px;background:#0b1d3a;color:#fff;border:1px solid #29466f;border-radius:8px}.geo-type{display:grid;grid-template-columns:1fr 1fr;gap:8px}.geo-type label,.geo-colors label{padding:10px;border:1px solid #29466f;border-radius:8px;background:#0b1d3a}.geo-type input,.geo-colors input{width:auto}.geo-colors{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.geo-button{border:0;border-radius:8px;padding:11px 15px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}.geo-danger{background:#dc2626}.geo-muted{background:#475569}.geo-map{height:620px;border-radius:12px}.geo-table{width:100%;border-collapse:collapse;margin-top:18px}.geo-table th,.geo-table td{text-align:left;padding:11px;border-bottom:1px solid #1e3557;vertical-align:top}.geo-badge{display:inline-flex;align-items:center;gap:6px;font-weight:700}.geo-dot{width:12px;height:12px;border-radius:50%;display:inline-block}.geo-dot-green{background:#22c55e}.geo-dot-yellow{background:#eab308}.geo-dot-red{background:#ef4444}.geo-actions{display:flex;gap:8px;flex-wrap:wrap}.country-fields{display:none}@media(max-width:1000px){.geo-grid{grid-template-columns:1fr}.geo-map{height:480px}.geo-table{display:block;overflow:auto}}
</style>
<style>
.geo-legend{background:rgba(8,19,39,.94);color:#fff;padding:10px 12px;border:1px solid #29466f;border-radius:8px;line-height:1.65}.geo-legend-row{display:flex;align-items:center;gap:7px}.geo-legend-color{width:18px;height:10px;border-radius:2px;display:inline-block}
</style>

<div class="geo-shell">
    <div class="geo-header"><h1>Geozonen</h1><p>Adressbereiche und vollständige Länder als erlaubte, Warn- oder Verbotszone verwalten.</p></div>
    <div class="geo-grid">
        <section class="geo-card">
            <h2><?php echo $edit ? 'Geozone bearbeiten' : 'Neue Geozone'; ?></h2>
            <form method="post" action="/index.php?option=com_gpsportal&amp;task=geofences.save" class="geo-form" id="geofence-form">
                <input type="hidden" name="option" value="com_gpsportal">
                <input type="hidden" name="task" value="geofences.save">
                <input type="hidden" name="id" value="<?php echo (int) ($edit->id ?? 0); ?>">
                <div><label>Name</label><input id="geofence-name" name="name" value="<?php echo $escape($edit->name ?? ''); ?>" placeholder="Wird bei Ländern automatisch eingetragen" required></div>
                <div><label>Geozonentyp</label><div class="geo-type"><label><input type="radio" name="zone_type" value="address"<?php echo $zoneType === 'address' ? ' checked' : ''; ?>> Adresse mit Radius</label><label><input type="radio" name="zone_type" value="country"<?php echo $zoneType === 'country' ? ' checked' : ''; ?>> Komplettes Land</label></div></div>
                <div class="address-fields"><label>Adresse</label><input name="address" value="<?php echo $escape($zoneType === 'address' ? ($edit->address ?? '') : ''); ?>" placeholder="Hans-Koch-Ring 2a, 21493 Schwarzenbek"></div>
                <div class="address-fields"><label>Radius in Metern</label><input type="number" min="10" max="100000" name="radius" value="<?php echo (int) ($edit->radius ?? 100); ?>"></div>
                <div class="country-fields"><label>Land</label><select name="country_code" id="country-code"><option value="">Bitte wählen</option><?php foreach ($countries as $code => $name): ?><option value="<?php echo $code; ?>"<?php echo (string) ($edit->country_code ?? '') === $code ? ' selected' : ''; ?>><?php echo $escape($name); ?></option><?php endforeach; ?></select></div>
                <div class="country-fields"><label>Warnbereich vor der Landesgrenze</label><select name="warning_buffer_km"><option value="0">Kein Grenzpuffer</option><?php foreach (range(10, 100, 10) as $buffer): ?><option value="<?php echo $buffer; ?>"<?php echo (int) ($edit->warning_buffer_km ?? 0) === $buffer ? ' selected' : ''; ?>><?php echo $buffer; ?> km</option><?php endforeach; ?></select></div>
                <div><label>Status</label><div class="geo-colors"><label><input type="radio" name="status_color" value="green"<?php echo $color === 'green' ? ' checked' : ''; ?>> 🟢 Erlaubt</label><label><input type="radio" name="status_color" value="yellow"<?php echo $color === 'yellow' ? ' checked' : ''; ?>> 🟡 Warnung</label><label><input type="radio" name="status_color" value="red"<?php echo $color === 'red' ? ' checked' : ''; ?>> 🔴 Verboten</label></div></div>
                <div>
                    <button
                        type="submit"
                        class="geo-button"
                        id="geofence-submit"
                        form="geofence-form"
                        formmethod="post"
                        formaction="/index.php?option=com_gpsportal&amp;task=geofences.save"
                        formnovalidate
                        onclick="this.disabled=true;this.textContent='Wird gespeichert …';document.getElementById('geofence-form').submit();return false;"
                    >Geozone speichern</button>
                    <?php if ($edit): ?> <a class="geo-button geo-muted" href="index.php?option=com_gpsportal&view=geofences">Abbrechen</a><?php endif; ?>
                </div>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </section>
        <section class="geo-card"><div id="geofence-map" class="geo-map"></div></section>
    </div>

    <section class="geo-card" style="margin-top:18px"><h2>Vorhandene Geozonen</h2>
        <table class="geo-table"><thead><tr><th>Name</th><th>Typ</th><th>Bereich</th><th>Status</th><th>Grenzpuffer</th><th>Aktionen</th></tr></thead><tbody>
        <?php foreach ($this->geofences as $zone): ?><tr>
            <td><strong><?php echo $escape($zone->name); ?></strong></td><td><?php echo $zone->zone_type === 'country' ? 'Land' : 'Adresse'; ?></td>
            <td><?php echo $escape($zone->address); ?><?php if ($zone->zone_type === 'address'): ?><br><small>Radius: <?php echo (int) $zone->radius; ?> m</small><?php endif; ?></td>
            <td><span class="geo-badge"><span class="geo-dot geo-dot-<?php echo $escape($zone->status_color); ?>"></span><?php echo ['green'=>'Erlaubt','yellow'=>'Warnung','red'=>'Verboten'][$zone->status_color] ?? $escape($zone->status_color); ?></span></td>
            <td><?php echo $zone->zone_type === 'country' && (int) $zone->warning_buffer_km > 0 ? (int) $zone->warning_buffer_km . ' km' : '–'; ?></td>
            <td><div class="geo-actions"><a class="geo-button" href="index.php?option=com_gpsportal&view=geofences&edit=<?php echo (int) $zone->id; ?>">Bearbeiten</a><form method="post" action="<?php echo Route::_('index.php?option=com_gpsportal&task=geofences.delete'); ?>" onsubmit="return confirm('Geozone wirklich löschen?');"><input type="hidden" name="id" value="<?php echo (int) $zone->id; ?>"><button class="geo-button geo-danger">Löschen</button><?php echo HTMLHelper::_('form.token'); ?></form></div></td>
        </tr><?php endforeach; ?></tbody></table>
    </section>
</div>

<script>
const zoneColors={green:'#22c55e',yellow:'#eab308',red:'#ef4444'};
const bufferColors={yellow:'#3b82f6',red:'#f97316'};
const map=L.map('geofence-map').setView([51.1657,10.4515],6);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap-Mitwirkende'}).addTo(map);
const bounds=L.latLngBounds();
<?php foreach ($this->geofences as $zone): $mapColor = ['green'=>'#22c55e','yellow'=>'#eab308','red'=>'#ef4444'][$zone->status_color] ?? '#3b82f6'; ?>
<?php if ($zone->zone_type === 'country' && $zone->geometry_json): ?>
try{const countryGeometry=<?php echo $zone->geometry_json; ?>;const bufferKilometers=<?php echo (int) $zone->warning_buffer_km; ?>;const countryStatus=<?php echo json_encode((string) $zone->status_color); ?>;if(bufferKilometers>0&&countryStatus!=='green'&&window.turf){const buffered=turf.buffer(countryGeometry,bufferKilometers,{units:'kilometers'});if(buffered){L.geoJSON(buffered,{interactive:false,style:{color:bufferColors[countryStatus],weight:2,dashArray:'7 5',fillColor:bufferColors[countryStatus],fillOpacity:.28}}).addTo(map);}}const layer=L.geoJSON(countryGeometry,{style:{color:<?php echo json_encode($mapColor); ?>,weight:2,fillColor:<?php echo json_encode($mapColor); ?>,fillOpacity:.34}}).addTo(map).bindPopup(<?php echo json_encode($zone->name, JSON_UNESCAPED_UNICODE); ?>+'<br>Grenzpuffer: '+bufferKilometers+' km');bounds.extend(layer.getBounds());}catch(error){console.error('Grenzpuffer konnte nicht gezeichnet werden',error);}
<?php else: ?>
{const layer=L.circle([<?php echo (float) $zone->latitude; ?>,<?php echo (float) $zone->longitude; ?>],{radius:<?php echo (int) $zone->radius; ?>,color:<?php echo json_encode($mapColor); ?>,fillColor:<?php echo json_encode($mapColor); ?>,fillOpacity:.25}).addTo(map).bindPopup(<?php echo json_encode($zone->name, JSON_UNESCAPED_UNICODE); ?>);bounds.extend(layer.getBounds());}
<?php endif; endforeach; ?>
if(bounds.isValid()){map.fitBounds(bounds,{padding:[25,25]});}
const legend=L.control({position:'bottomright'});legend.onAdd=function(){const div=L.DomUtil.create('div','geo-legend');div.innerHTML='<strong>Legende</strong><div class="geo-legend-row"><span class="geo-legend-color" style="background:#22c55e"></span> Erlaubt</div><div class="geo-legend-row"><span class="geo-legend-color" style="background:#eab308"></span> Warnung</div><div class="geo-legend-row"><span class="geo-legend-color" style="background:#ef4444"></span> Verboten</div><div class="geo-legend-row"><span class="geo-legend-color" style="background:#f97316"></span> Puffer vor verbotenem Land</div><div class="geo-legend-row"><span class="geo-legend-color" style="background:#3b82f6"></span> Puffer vor Warnland</div>';return div;};legend.addTo(map);
function switchType(){const country=document.querySelector('input[name="zone_type"]:checked').value==='country';document.querySelectorAll('.country-fields').forEach(el=>el.style.display=country?'block':'none');document.querySelectorAll('.address-fields').forEach(el=>el.style.display=country?'none':'block');}
document.querySelectorAll('input[name="zone_type"]').forEach(el=>el.addEventListener('change',switchType));
const countrySelect=document.getElementById('country-code');
const geofenceName=document.getElementById('geofence-name');
const initiallySelectedCountry=countrySelect.options[countrySelect.selectedIndex];
let automaticCountryName=(
    initiallySelectedCountry
    && initiallySelectedCountry.value!==''
    && geofenceName.value.trim()===initiallySelectedCountry.text.trim()
)?initiallySelectedCountry.text.trim():'';
function applyCountryName(){
    const option=countrySelect.options[countrySelect.selectedIndex];
    const selectedName=option&&option.value!==''?option.text.trim():'';
    const currentName=geofenceName.value.trim();
    if(selectedName!==''&&(currentName===''||currentName===automaticCountryName)){
        geofenceName.value=selectedName;
    }
    automaticCountryName=selectedName;
}
countrySelect.addEventListener('change',applyCountryName);
switchType();
</script>
