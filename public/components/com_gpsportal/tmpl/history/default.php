<?php
defined('_JEXEC') or die;

$jsonOptions = JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT;
$monthDate = new DateTimeImmutable($this->calendarMonth . '-01');
$firstWeekday = (int) $monthDate->format('N');
$daysInMonth = (int) $monthDate->format('t');
$availableDays = array_fill_keys($this->availableDays, true);
$displayTimezone = new DateTimeZone($this->displayTimezone ?: 'Europe/Berlin');
$formatTime = static function (int $timestamp, string $format) use ($displayTimezone): string {
    return (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone($displayTimezone)
        ->format($format);
};
$monthNames = [
    1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
];
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
.history-card{background:#0f172a;border:1px solid #243b63;border-radius:18px;padding:20px;color:#fff;box-shadow:0 10px 25px rgba(0,0,0,.3)}
.history-title{font-size:28px;font-weight:750;margin:0 0 5px}.history-subtitle{color:#93c5fd;margin-bottom:18px}
.history-controls{display:grid;grid-template-columns:300px 300px minmax(420px,1fr);gap:14px;align-items:start}
.control-card,.map-card,.stops-card,.trip-card,.stat{background:#111c31;border:1px solid #29436d;border-radius:14px;padding:14px}
.history-controls>.control-card{height:323px;box-sizing:border-box}.history-controls>.control-card:not(.top-trips-card){width:300px}.top-trips-card{overflow:hidden}.top-trips-card .trips-heading{margin-bottom:8px}.top-trips-card .trips-heading h2{margin:0;font-size:18px}.top-trips-card .trip-list{max-height:253px;overflow-y:auto;padding:2px 3px 2px 2px}
.control-card label{display:block;color:#bfdbfe;font-size:13px;margin-bottom:6px}.control-card select{width:100%;background:#1e293b;color:#fff;border:1px solid #475569;border-radius:9px;padding:10px}
.selection-modes{display:flex;gap:20px;margin:15px 0}.selection-modes label{font-size:15px;color:#fff;cursor:pointer}.selection-summary{background:#17233a;border-radius:9px;padding:10px;margin-top:12px;color:#dbeafe}
.calendar-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}.calendar-title{font-size:18px;font-weight:700}.calendar-nav{background:#2563eb;border:0;color:#fff;border-radius:8px;padding:7px 11px;cursor:pointer}
.calendar-grid{display:grid;grid-template-columns:repeat(7,34px);justify-content:center;gap:4px}.weekday{text-align:center;color:#93c5fd;font-size:10px;padding:3px}.calendar-day{width:32px;height:31px;border-radius:7px;border:1px solid #334155;background:#1e293b;color:#fff;cursor:pointer}.calendar-day.has-trips{background:#166534;border-color:#22c55e;font-weight:700}.calendar-day.no-trips{background:#273244;color:#64748b;cursor:not-allowed}.calendar-day.selected{outline:2px solid #60a5fa;background:#1d4ed8}.calendar-day.in-range{background:#1e40af}.calendar-empty{height:31px}
.calendar-legend{display:flex;gap:14px;flex-wrap:wrap;margin-top:10px;font-size:12px;color:#cbd5e1}.legend-dot{display:inline-block;width:11px;height:11px;border-radius:3px;margin-right:5px}.legend-dot.drive{background:#16a34a}.legend-dot.empty{background:#334155}
.route-btn{width:100%;margin-top:14px;padding:11px;background:#f97316;color:#fff;border:0;border-radius:9px;font-weight:700;cursor:pointer}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:18px}.stat small{display:block;color:#93c5fd}.stat strong{display:block;font-size:22px;margin-top:5px;color:#fff}
.history-layout{display:grid;grid-template-columns:280px 1fr;gap:14px;margin-top:14px}.stops-card{max-height:520px;overflow:auto}.stop-item{width:100%;text-align:left;background:#17233a;color:#fff;border:1px solid #334d75;border-left:5px solid #64748b;border-radius:9px;padding:10px;margin-bottom:9px;cursor:pointer}.stop-item:hover{background:#223454}.stop-item small{display:block;color:#bfdbfe;margin-top:4px}.map-card{padding:10px}#historyMap{height:500px;border-radius:10px}
.map-legend{background:rgba(15,23,42,.94);color:#fff;padding:10px 12px;border-radius:9px;box-shadow:0 2px 8px rgba(0,0,0,.4);line-height:1.65}.map-legend i{display:inline-block;width:13px;height:13px;border-radius:50%;margin-right:6px}.direction-arrow{color:#111;font-size:17px;text-shadow:-1px -1px 0 #fff,1px -1px 0 #fff,-1px 1px 0 #fff,1px 1px 0 #fff;line-height:18px}
.route-flag{position:relative;width:32px;height:40px;filter:drop-shadow(0 2px 3px rgba(0,0,0,.7))}.route-flag-pole{position:absolute;left:5px;top:2px;width:3px;height:36px;background:#f8fafc;border:1px solid #334155;border-radius:2px}.route-flag-cloth{position:absolute;left:8px;top:2px;min-width:22px;height:19px;padding:0 3px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:900;border:2px solid #fff;border-left:0;border-radius:0 4px 4px 0;box-sizing:border-box}
.trips-heading{display:flex;align-items:center;justify-content:space-between;gap:8px}.show-all-trips{background:#2563eb;color:#fff;border:0;border-radius:7px;padding:7px 9px;font-size:12px;font-weight:700;cursor:pointer}.show-all-trips:hover{background:#1d4ed8}.trip-list{display:grid;gap:8px}.trip-card{display:grid;grid-template-columns:7px 66px minmax(170px,1fr) auto;gap:9px;align-items:center;padding:9px;cursor:pointer}.trip-card:hover,.trip-card.active{background:#172744}.trip-card.active{outline:2px solid #60a5fa}.trip-card.muted{opacity:.58}.trip-color{height:100%;min-height:58px;border-radius:7px}.trip-time{font-size:13px;font-weight:700}.trip-route{min-width:0;font-size:13px}.trip-route strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.trip-route small{display:block;color:#bfdbfe;margin-top:4px}.trip-actions{text-align:right;white-space:nowrap}.trip-actions strong{display:block}.open-trip-map{margin-top:5px;background:#0f766e;color:#fff;border:0;border-radius:6px;padding:5px 7px;font-size:11px;font-weight:700;cursor:pointer}.open-trip-map:hover{background:#0d9488}.empty-result{margin-top:18px;padding:18px;border-radius:12px;background:#172033;color:#cbd5e1;text-align:center}
@media(max-width:1400px){.history-controls{grid-template-columns:300px 300px}.top-trips-card{grid-column:1/3;width:614px}}
@media(max-width:1100px){.history-controls{grid-template-columns:300px}.top-trips-card{grid-column:auto;width:300px}.history-layout{grid-template-columns:1fr}.stats-grid{grid-template-columns:repeat(2,1fr)}.stops-card{max-height:260px}.trip-card{grid-template-columns:7px 58px 1fr}.trip-actions{grid-column:3;text-align:left}#historyMap{height:440px}}
@media(max-width:620px){.stats-grid{grid-template-columns:1fr}.calendar-grid{gap:3px}.history-card{padding:12px}.trip-card{grid-template-columns:7px 1fr}.trip-time,.trip-route,.trip-metrics{grid-column:2}}
</style>

<div class="history-card">
    <h1 class="history-title">Fahrhistorie</h1>
    <div class="history-subtitle">Touren, Stopps und Fahrtrichtungen auf einen Blick</div>

    <form method="get" id="historyForm" autocomplete="off">
        <input type="hidden" name="option" value="com_gpsportal">
        <input type="hidden" name="view" value="history">
        <input type="hidden" name="calendar_month" id="calendarMonth" autocomplete="off" value="<?php echo htmlspecialchars($this->calendarMonth, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="date" id="selectedDate" autocomplete="off" value="<?php echo htmlspecialchars($this->selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="from" id="fromDate" autocomplete="off" value="<?php echo htmlspecialchars($this->fromDate, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="to" id="toDate" autocomplete="off" value="<?php echo htmlspecialchars($this->toDate, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="history-controls">
            <div class="control-card">
                <label for="historyVehicle">Fahrzeug</label>
                <select name="vehicle" id="historyVehicle">
                    <?php foreach ($this->vehicles as $vehicle): ?>
                        <option value="<?php echo (int) $vehicle->traccar_device_id; ?>" <?php echo (int) $this->vehicleId === (int) $vehicle->traccar_device_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $vehicle->name, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="selection-modes">
                    <label><input type="radio" name="selection_mode" value="day" <?php echo $this->selectionMode === 'day' ? 'checked' : ''; ?>> Ein Tag</label>
                    <label><input type="radio" name="selection_mode" value="range" <?php echo $this->selectionMode === 'range' ? 'checked' : ''; ?>> Zeitraum</label>
                </div>

                <div class="selection-summary" id="selectionSummary"></div>
                <button type="submit" class="route-btn">Touren anzeigen</button>
            </div>

            <div class="control-card">
                <div class="calendar-header">
                    <button type="button" class="calendar-nav" data-month="<?php echo $monthDate->modify('-1 month')->format('Y-m'); ?>" aria-label="Vorheriger Monat">‹</button>
                    <div class="calendar-title"><?php echo $monthNames[(int) $monthDate->format('n')] . ' ' . $monthDate->format('Y'); ?></div>
                    <button type="button" class="calendar-nav" data-month="<?php echo $monthDate->modify('+1 month')->format('Y-m'); ?>" aria-label="Nächster Monat">›</button>
                </div>
                <div class="calendar-grid">
                    <?php foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $weekday): ?>
                        <div class="weekday"><?php echo $weekday; ?></div>
                    <?php endforeach; ?>
                    <?php for ($empty = 1; $empty < $firstWeekday; $empty++): ?><div class="calendar-empty"></div><?php endfor; ?>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++):
                        $date = $this->calendarMonth . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                        $hasTrips = isset($availableDays[$date]);
                        $selected = $this->selectionMode === 'day'
                            ? $date === $this->selectedDate
                            : ($date === $this->fromDate || $date === $this->toDate);
                        $inRange = $this->selectionMode === 'range' && $date >= $this->fromDate && $date <= $this->toDate;
                    ?>
                        <button type="button" class="calendar-day <?php echo $hasTrips ? 'has-trips' : 'no-trips'; ?><?php echo $selected ? ' selected' : ''; ?><?php echo $inRange ? ' in-range' : ''; ?>" data-date="<?php echo $date; ?>" <?php echo $hasTrips ? '' : 'disabled'; ?>><?php echo $day; ?></button>
                    <?php endfor; ?>
                </div>
                <div class="calendar-legend">
                    <span><i class="legend-dot drive"></i>Tag mit Fahrten</span>
                    <span><i class="legend-dot empty"></i>Keine Fahrt – nicht auswählbar</span>
                </div>
            </div>

            <?php if (!empty($this->trips)): ?>
                <section class="control-card top-trips-card">
                    <div class="trips-heading">
                        <h2>Touren</h2>
                        <button type="button" class="show-all-trips" id="showAllTrips">Alle anzeigen</button>
                    </div>
                    <div class="trip-list">
                        <?php foreach ($this->trips as $index => $trip): ?>
                            <?php
                            $startLabel = trim((string) ($trip['startAddress'] ?? ''));
                            $endLabel = trim((string) ($trip['endAddress'] ?? ''));
                            ?>
                            <article class="trip-card" data-trip-index="<?php echo $index; ?>">
                                <div class="trip-color" style="background:<?php echo htmlspecialchars($trip['color'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                                <div class="trip-time"><?php echo $formatTime((int) $trip['startTimestamp'], 'H:i'); ?>–<?php echo $formatTime((int) $trip['endTimestamp'], 'H:i'); ?></div>
                                <div class="trip-route">
                                    <strong>
                                        <span class="trip-location" data-latitude="<?php echo htmlspecialchars((string) $trip['start']['latitude'], ENT_QUOTES, 'UTF-8'); ?>" data-longitude="<?php echo htmlspecialchars((string) $trip['start']['longitude'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($startLabel !== '' ? $startLabel : 'Ort wird ermittelt …', ENT_QUOTES, 'UTF-8'); ?></span>
                                        →
                                        <span class="trip-location" data-latitude="<?php echo htmlspecialchars((string) $trip['end']['latitude'], ENT_QUOTES, 'UTF-8'); ?>" data-longitude="<?php echo htmlspecialchars((string) $trip['end']['longitude'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($endLabel !== '' ? $endLabel : 'Ort wird ermittelt …', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </strong>
                                    <small><?php echo $formatTime((int) $trip['startTimestamp'], 'd.m.Y'); ?> · <?php echo (int) $trip['durationMinutes']; ?> Min.</small>
                                </div>
                                <div class="trip-actions">
                                    <strong><?php echo number_format((float) $trip['distanceKm'], 1, ',', '.'); ?> km</strong>
                                    <button type="button" class="open-trip-map" data-open-trip="<?php echo $index; ?>">Karte öffnen ↗</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!empty($this->history)): ?>
        <div class="history-layout">
            <aside class="stops-card">
                <h2>Stopps</h2>
                <?php if (empty($this->stops)): ?><p>Keine Stopps erkannt.</p><?php endif; ?>
                <?php foreach ($this->stops as $index => $stop): ?>
                    <?php
                    $stopTripIndex = $stop['tripIndex'];
                    $stopColor = $stopTripIndex !== null
                        && isset($this->trips[(int) $stopTripIndex]['color'])
                        ? (string) $this->trips[(int) $stopTripIndex]['color']
                        : '#64748b';
                    ?>
                    <button type="button" class="stop-item" data-stop-index="<?php echo $index; ?>" style="border-left-color:<?php echo htmlspecialchars($stopColor, ENT_QUOTES, 'UTF-8'); ?>">
                        <strong><?php echo (int) $stop['durationMinutes']; ?> Minuten</strong>
                        <small><?php echo $formatTime((int) $stop['timestamp'], 'd.m.Y H:i'); ?> Uhr</small>
                        <?php if ($stop['endsTrip']): ?><small>Beendet die Tour nach <?php echo (int) $this->tripEndStopMinutes; ?> Minuten</small><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </aside>
            <div class="map-card"><div id="historyMap"></div></div>
        </div>

        <div class="stats-grid">
            <div class="stat"><small>Touren</small><strong><?php echo count($this->trips); ?></strong></div>
            <div class="stat"><small>Stopps</small><strong><?php echo count($this->stops); ?></strong></div>
            <div class="stat"><small>Strecke</small><strong><?php echo number_format($this->distanceKm, 1, ',', '.'); ?> km</strong></div>
            <div class="stat"><small>Tourende nach Stillstand</small><strong><?php echo (int) $this->tripEndStopMinutes; ?> Min.</strong></div>
        </div>

    <?php else: ?>
        <div class="empty-result">Für die ausgewählte Zeit wurden keine Positionen gefunden.</div>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';

    const form = document.getElementById('historyForm');
    const dateInput = document.getElementById('selectedDate');
    const fromInput = document.getElementById('fromDate');
    const toInput = document.getElementById('toDate');
    const summary = document.getElementById('selectionSummary');

    function mode() {
        return form.querySelector('input[name="selection_mode"]:checked').value;
    }

    function germanDate(value) {
        if (!value) return '–';
        const parts = value.split('-');
        return parts[2] + '.' + parts[1] + '.' + parts[0];
    }

    function updateSummary() {
        summary.textContent = mode() === 'day'
            ? 'Ausgewählter Tag: ' + germanDate(dateInput.value)
            : 'Zeitraum: ' + germanDate(fromInput.value) + ' bis ' + germanDate(toInput.value);
    }

    document.querySelectorAll('input[name="selection_mode"]').forEach(function (radio) {
        radio.addEventListener('change', updateSummary);
    });

    document.querySelectorAll('.calendar-day.has-trips').forEach(function (button) {
        button.addEventListener('click', function () {
            const value = button.dataset.date;

            if (mode() === 'day') {
                dateInput.value = value;
            } else if (!fromInput.dataset.pending || fromInput.dataset.pending === '0') {
                fromInput.value = value;
                toInput.value = value;
                fromInput.dataset.pending = '1';
            } else {
                if (value < fromInput.value) {
                    toInput.value = fromInput.value;
                    fromInput.value = value;
                } else {
                    toInput.value = value;
                }
                fromInput.dataset.pending = '0';
            }

            updateSummary();
            document.querySelectorAll('.calendar-day').forEach(function (dayButton) {
                const day = dayButton.dataset.date;
                const selected = mode() === 'day'
                    ? day === dateInput.value
                    : day === fromInput.value || day === toInput.value;
                dayButton.classList.toggle('selected', selected);
                dayButton.classList.toggle('in-range', mode() === 'range' && day >= fromInput.value && day <= toInput.value);
            });
        });
    });

    document.querySelectorAll('.calendar-nav').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('calendarMonth').value = button.dataset.month;
            form.submit();
        });
    });

    document.getElementById('historyVehicle').addEventListener('change', function () {
        form.submit();
    });

    updateSummary();

    function locationCacheKey(latitude, longitude) {
        return 'gpsportal.location.'
            + Number(latitude).toFixed(4)
            + '.'
            + Number(longitude).toFixed(4);
    }

    function formatLocation(data) {
        const address = data && data.address ? data.address : {};
        const locality = address.city
            || address.town
            || address.village
            || address.municipality
            || address.county
            || '';
        const district = address.suburb
            || address.city_district
            || address.quarter
            || '';
        const road = address.road
            || address.pedestrian
            || address.residential
            || '';

        if (road && locality) return road + ', ' + locality;
        if (district && locality && district !== locality) return district + ', ' + locality;
        if (locality) return locality;
        return String(data && data.display_name || '').split(',').slice(0, 2).join(',').trim();
    }

    async function resolveMissingLocations() {
        const elements = Array.from(document.querySelectorAll('.trip-location'));
        const pending = new Map();

        elements.forEach(function (element) {
            if (element.textContent.trim() !== 'Ort wird ermittelt …') return;
            const latitude = Number(element.dataset.latitude);
            const longitude = Number(element.dataset.longitude);
            const key = locationCacheKey(latitude, longitude);
            const cached = localStorage.getItem(key);

            if (cached) {
                element.textContent = cached;
                return;
            }

            if (!pending.has(key)) pending.set(key, {latitude: latitude, longitude: longitude, elements: []});
            pending.get(key).elements.push(element);
        });

        for (const [key, item] of pending) {
            try {
                const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat='
                    + encodeURIComponent(item.latitude)
                    + '&lon=' + encodeURIComponent(item.longitude);
                const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                if (!response.ok) throw new Error('Ortsabfrage fehlgeschlagen');
                const location = formatLocation(await response.json()) || 'Ort nicht ermittelbar';
                localStorage.setItem(key, location);
                item.elements.forEach(function (element) { element.textContent = location; });
            } catch (error) {
                item.elements.forEach(function (element) { element.textContent = 'Ort nicht ermittelbar'; });
            }

            await new Promise(function (resolve) { window.setTimeout(resolve, 1100); });
        }
    }

    resolveMissingLocations();

    const mapElement = document.getElementById('historyMap');
    if (!mapElement) return;

    const trips = <?php echo json_encode($this->trips, $jsonOptions); ?>;
    const stops = <?php echo json_encode($this->stops, $jsonOptions); ?>;
    const map = L.map('historyMap');
    const baseLayer = L.tileLayer('https://api.maptiler.com/maps/streets-v4-dark/256/{z}/{x}/{y}.png?key=3sqnkHzyCcWTYRCiB3Yw', {attribution: '&copy; MapTiler &copy; OpenStreetMap contributors', maxZoom: 22}).addTo(map);
    const tripLayers = [];
    const stopMarkers = [];
    const allBounds = L.latLngBounds();

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value || '');
        return div.innerHTML;
    }

    function bearingBetween(previous, current) {
        const lat1 = Number(previous.latitude) * Math.PI / 180;
        const lat2 = Number(current.latitude) * Math.PI / 180;
        const deltaLongitude = (
            Number(current.longitude) - Number(previous.longitude)
        ) * Math.PI / 180;
        const y = Math.sin(deltaLongitude) * Math.cos(lat2);
        const x = Math.cos(lat1) * Math.sin(lat2)
            - Math.sin(lat1) * Math.cos(lat2) * Math.cos(deltaLongitude);

        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    }

    function pointDistanceKm(previous, current) {
        const earthRadius = 6371;
        const lat1 = Number(previous.latitude) * Math.PI / 180;
        const lat2 = Number(current.latitude) * Math.PI / 180;
        const deltaLat = lat2 - lat1;
        const deltaLon = (
            Number(current.longitude) - Number(previous.longitude)
        ) * Math.PI / 180;
        const value = Math.sin(deltaLat / 2) ** 2
            + Math.cos(lat1) * Math.cos(lat2) * Math.sin(deltaLon / 2) ** 2;

        return earthRadius * 2 * Math.atan2(
            Math.sqrt(value),
            Math.sqrt(Math.max(0, 1 - value))
        );
    }

    function addArrow(previous, current, color) {
        /* Das Zeichen ➤ zeigt ohne Drehung nach Osten (90 Grad). */
        const rotation = bearingBetween(previous, current) - 90;
        const icon = L.divIcon({className: 'direction-arrow', html: '<span style="display:block;color:#111111;transform:rotate(' + rotation + 'deg)">➤</span>', iconSize: [18, 18], iconAnchor: [9, 9]});
        return L.marker([current.latitude, current.longitude], {icon: icon, interactive: false});
    }

    function routeFlagIcon(color, label) {
        return L.divIcon({
            className: '',
            html: '<div class="route-flag"><span class="route-flag-pole"></span><span class="route-flag-cloth" style="background:' + color + '">' + label + '</span></div>',
            iconSize: [32, 40],
            iconAnchor: [6, 38],
            popupAnchor: [8, -35]
        });
    }

    trips.forEach(function (trip, tripIndex) {
        const points = trip.positions.map(function (position) { return [position.latitude, position.longitude]; });
        if (points.length < 2) return;
        const group = L.featureGroup().addTo(map);
        L.polyline(points, {color: trip.color, weight: 6, opacity: 0.9}).addTo(group);
        const arrowSpacingKm = Math.max(0.4, Number(trip.distanceKm || 0) / 60);
        let distanceSinceArrow = 0;
        for (let index = 1; index < trip.positions.length; index++) {
            const previous = trip.positions[index - 1];
            const current = trip.positions[index];
            distanceSinceArrow += pointDistanceKm(previous, current);

            if (distanceSinceArrow >= arrowSpacingKm) {
                addArrow(previous, current, trip.color).addTo(group);
                distanceSinceArrow = 0;
            }
        }
        L.marker(points[0], {icon: routeFlagIcon('#16a34a', 'S')}).bindPopup('Start Tour ' + trip.number).addTo(group);
        L.marker(points[points.length - 1], {icon: routeFlagIcon('#dc2626', 'Z')}).bindPopup('Ziel Tour ' + trip.number).addTo(group);
        points.forEach(function (point) { allBounds.extend(point); });
        tripLayers[tripIndex] = group;
    });

    stops.forEach(function (stop, index) {
        const stopTrip = stop.tripIndex !== null
            ? trips[Number(stop.tripIndex)]
            : null;
        const stopColor = stopTrip && stopTrip.color
            ? stopTrip.color
            : '#64748b';
        const marker = L.marker([stop.latitude, stop.longitude], {
            icon: L.divIcon({className: '', html: '<div style="width:28px;height:28px;background:' + stopColor + ';border:3px solid white;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold">P</div>', iconSize: [28, 28], iconAnchor: [14, 14]})
        }).addTo(map).bindPopup('<strong>Stopp</strong><br>' + escapeHtml(new Date(stop.startTime).toLocaleString('de-DE')) + '<br>Dauer: ' + Number(stop.durationMinutes) + ' Minuten');
        stopMarkers[index] = marker;
    });

    function showOnlyTrip(selectedIndex) {
        const selectedLayer = tripLayers[selectedIndex];

        tripLayers.forEach(function (layer) {
            if (layer && map.hasLayer(layer)) map.removeLayer(layer);
        });
        stopMarkers.forEach(function (marker) {
            if (marker && map.hasLayer(marker)) map.removeLayer(marker);
        });

        if (selectedLayer) selectedLayer.addTo(map);

        stops.forEach(function (stop, stopIndex) {
            if (
                stop.tripIndex !== null
                && Number(stop.tripIndex) === Number(selectedIndex)
                && stopMarkers[stopIndex]
            ) {
                stopMarkers[stopIndex].addTo(map);
            }
        });

        document.querySelectorAll('.trip-card').forEach(function (card) {
            const active = Number(card.dataset.tripIndex) === Number(selectedIndex);
            card.classList.toggle('active', active);
            card.classList.toggle('muted', !active);
        });

        if (selectedLayer && selectedLayer.getBounds().isValid()) {
            map.fitBounds(selectedLayer.getBounds(), {padding: [30, 30]});
        }
    }

    function showAllTrips() {
        tripLayers.forEach(function (layer) {
            if (layer && !map.hasLayer(layer)) layer.addTo(map);
        });
        stopMarkers.forEach(function (marker) {
            if (marker && !map.hasLayer(marker)) marker.addTo(map);
        });
        document.querySelectorAll('.trip-card').forEach(function (card) {
            card.classList.remove('active', 'muted');
        });
        if (allBounds.isValid()) map.fitBounds(allBounds, {padding: [25, 25]});
    }

    document.querySelectorAll('.stop-item').forEach(function (button) {
        button.addEventListener('click', function () {
            const stopIndex = Number(button.dataset.stopIndex);
            const stop = stops[stopIndex];
            const marker = stopMarkers[stopIndex];
            if (stop && stop.tripIndex !== null) showOnlyTrip(Number(stop.tripIndex));
            if (marker && !map.hasLayer(marker)) marker.addTo(map);
            if (marker) { map.setView(marker.getLatLng(), 17); marker.openPopup(); }
        });
    });

    document.querySelectorAll('.trip-card').forEach(function (card) {
        card.addEventListener('click', function () {
            showOnlyTrip(Number(card.dataset.tripIndex));
        });
    });

    const showAllButton = document.getElementById('showAllTrips');
    if (showAllButton) showAllButton.addEventListener('click', showAllTrips);

    function openTripWindow(tripIndex) {
        const trip = trips[tripIndex];
        if (!trip || !Array.isArray(trip.positions) || trip.positions.length < 2) return;

        const popup = window.open(
            '',
            '_blank',
            'width=1200,height=780,resizable=yes,scrollbars=no'
        );

        if (!popup) {
            window.alert('Das Kartenfenster wurde vom Browser blockiert. Bitte Pop-ups für das GPS-Portal erlauben.');
            return;
        }

        popup.document.documentElement.lang = 'de';
        popup.document.head.replaceChildren();
        popup.document.body.replaceChildren();
        popup.document.title = 'GPS-Portal – Tour ' + Number(trip.number);

        const popupStyle = popup.document.createElement('style');
        popupStyle.textContent = 'html,body{height:100%;margin:0;background:#0f172a;color:#fff;font-family:Arial,sans-serif}'
            + 'header{height:64px;box-sizing:border-box;padding:12px 18px;background:#111c31;border-bottom:1px solid #29436d}'
            + 'header strong{font-size:20px}header span{display:block;color:#bfdbfe;margin-top:4px}'
            + '#tripPopupMap{height:calc(100% - 64px)}'
            + '.popup-flag{position:relative;width:32px;height:40px;filter:drop-shadow(0 2px 3px rgba(0,0,0,.7))}'
            + '.popup-pole{position:absolute;left:5px;top:2px;width:3px;height:36px;background:#fff;border:1px solid #334155}'
            + '.popup-cloth{position:absolute;left:8px;top:2px;width:24px;height:19px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:900;border:2px solid #fff;border-left:0;border-radius:0 4px 4px 0;box-sizing:border-box}'
            + '.popup-arrow{font-size:17px;text-shadow:-1px -1px 0 #fff,1px -1px 0 #fff,-1px 1px 0 #fff,1px 1px 0 #fff}';
        popup.document.head.appendChild(popupStyle);

        const popupHeader = popup.document.createElement('header');
        const popupTitle = popup.document.createElement('strong');
        const popupDetails = popup.document.createElement('span');
        popupTitle.textContent = 'Tour ' + Number(trip.number);
        popupDetails.textContent = Number(trip.distanceKm || 0).toLocaleString(
            'de-DE',
            {minimumFractionDigits: 1, maximumFractionDigits: 1}
        ) + ' km · ' + Number(trip.durationMinutes || 0) + ' Minuten';
        popupHeader.append(popupTitle, popupDetails);

        const popupMapElement = popup.document.createElement('div');
        popupMapElement.id = 'tripPopupMap';
        popup.document.body.append(popupHeader, popupMapElement);

        const leafletCss = popup.document.createElement('link');
        leafletCss.rel = 'stylesheet';
        leafletCss.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        popup.document.head.appendChild(leafletCss);

        const leafletScript = popup.document.createElement('script');
        leafletScript.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        leafletScript.onload = function () {
            const popupMap = popup.L.map('tripPopupMap');
            popup.L.tileLayer(
                'https://api.maptiler.com/maps/streets-v4-dark/256/{z}/{x}/{y}.png?key=3sqnkHzyCcWTYRCiB3Yw',
                {attribution: '&copy; MapTiler &copy; OpenStreetMap contributors', maxZoom: 22}
            ).addTo(popupMap);
            const group = popup.L.featureGroup().addTo(popupMap);
            const points = trip.positions.map(function (position) {
                return [Number(position.latitude), Number(position.longitude)];
            });
            popup.L.polyline(points, {color: trip.color, weight: 6, opacity: 0.92}).addTo(group);

            const flagIcon = function (color, label) {
                return popup.L.divIcon({className: '', html: '<div class="popup-flag"><span class="popup-pole"></span><span class="popup-cloth" style="background:' + color + '">' + label + '</span></div>', iconSize: [32, 40], iconAnchor: [6, 38]});
            };
            popup.L.marker(points[0], {icon: flagIcon('#16a34a', 'S')}).addTo(group);
            popup.L.marker(points[points.length - 1], {icon: flagIcon('#dc2626', 'Z')}).addTo(group);

            const spacing = Math.max(0.4, Number(trip.distanceKm || 0) / 60);
            let accumulated = 0;
            for (let index = 1; index < trip.positions.length; index++) {
                const previous = trip.positions[index - 1];
                const current = trip.positions[index];
                accumulated += pointDistanceKm(previous, current);
                if (accumulated < spacing) continue;
                const rotation = bearingBetween(previous, current) - 90;
                const arrowIcon = popup.L.divIcon({className: 'popup-arrow', html: '<span style="display:block;color:#111;transform:rotate(' + rotation + 'deg)">➤</span>', iconSize: [18, 18], iconAnchor: [9, 9]});
                popup.L.marker([current.latitude, current.longitude], {icon: arrowIcon, interactive: false}).addTo(group);
                accumulated = 0;
            }

            popupMap.fitBounds(group.getBounds(), {padding: [35, 35]});
        };
        popup.document.head.appendChild(leafletScript);
    }

    document.querySelectorAll('.open-trip-map').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            openTripWindow(Number(button.dataset.openTrip));
        });
    });

    const legend = L.control({position: 'bottomright'});
    legend.onAdd = function () {
        const div = L.DomUtil.create('div', 'map-legend');
        div.innerHTML = '<strong>Legende</strong><br><i style="background:#22c55e"></i>Start<br><i style="background:#ef4444"></i>Ende / Stopp<br><span style="font-size:17px">➤</span> Fahrtrichtung<br>Touren besitzen unterschiedliche Farben';
        return div;
    };
    legend.addTo(map);

    if (allBounds.isValid()) map.fitBounds(allBounds, {padding: [25, 25]});
    else map.setView([53.55, 10.0], 9);
})();
</script>
