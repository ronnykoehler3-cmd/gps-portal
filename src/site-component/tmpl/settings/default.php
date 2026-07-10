<?php
defined('_JEXEC') or die;
?>

<h1>Einstellungen</h1>

<form
    method="post"
    action="/index.php?option=com_gpsportal&task=settings.save"
>

<div class="vehicle-table">

    <h2>Kartenansicht</h2>

    <p>
        <label>
            <input
                type="checkbox"
                name="show_vehicle_names"
                value="1"
                <?php echo $this->settings->show_vehicle_names ? 'checked' : ''; ?>
            >
            Fahrzeugnamen anzeigen
        </label>
    </p>

    <p>
        <label>
            <input
                type="checkbox"
                name="show_geofences"
                value="1"
                <?php echo $this->settings->show_geofences ? 'checked' : ''; ?>
            >
            Geozonen anzeigen
        </label>
    </p>

    <p>
        <label>
            <input
                type="checkbox"
                name="remember_map_position"
                value="1"
                <?php echo $this->settings->remember_map_position ? 'checked' : ''; ?>
            >
            Kartenposition merken
        </label>
    </p>

    <p>
        Aktualisierung

        <select name="refresh_interval">

            <option value="5"
                <?php echo $this->settings->refresh_interval == 5 ? 'selected' : ''; ?>
            >
                5 Sekunden
            </option>

            <option value="10"
                <?php echo $this->settings->refresh_interval == 10 ? 'selected' : ''; ?>
            >
                10 Sekunden
            </option>

            <option value="30"
                <?php echo $this->settings->refresh_interval == 30 ? 'selected' : ''; ?>
            >
                30 Sekunden
            </option>

            <option value="60"
                <?php echo $this->settings->refresh_interval == 60 ? 'selected' : ''; ?>
            >
                60 Sekunden
            </option>

        </select>
    </p>

</div>

<br>

<div class="vehicle-table">

    <h2>Benachrichtigungen</h2>

    <p>
        <label>
            <input
                type="checkbox"
                name="popup_geofence_events"
                value="1"
                <?php echo $this->settings->popup_geofence_events ? 'checked' : ''; ?>
            >
            Geozonen-Popups anzeigen
        </label>
    </p>

    <p>
        <label>
            <input
                type="checkbox"
                name="popup_offline_events"
                value="1"
                <?php echo $this->settings->popup_offline_events ? 'checked' : ''; ?>
            >
            Offline-Popups anzeigen
        </label>
    </p>

    <p>
        <label>
            <input
                type="checkbox"
                name="email_geofence_events"
                value="1"
                <?php echo $this->settings->email_geofence_events ? 'checked' : ''; ?>
            >
            E-Mail bei Geozonen-Ereignissen
        </label>
    </p>

    <p>
        <label>
            <input
                type="checkbox"
                name="email_offline_events"
                value="1"
                <?php echo $this->settings->email_offline_events ? 'checked' : ''; ?>
            >
            E-Mail bei Offline-Fahrzeugen
        </label>
    </p>

</div>

<br>

<p>
    <button
        type="submit"
        class="btn btn-primary"
    >
        Einstellungen speichern
    </button>
</p>

</form>
