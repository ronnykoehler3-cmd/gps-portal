<?php

defined('_JEXEC') or die;

require_once dirname(__DIR__) . '/navigation.php';
?>

<div class="container-fluid">

    <h1>System Einstellungen</h1>

    <form method="post">

        <div class="card">

            <div class="card-body">

                <div class="form-check mb-3">

                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="live_refresh"
                        value="1"
                        <?php echo !empty($this->settings['live_refresh']) ? 'checked' : ''; ?>>

                    <label class="form-check-label">
                        Live Refresh aktiv
                    </label>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Refresh Intervall (Sekunden)
                    </label>

                    <input
                        type="number"
                        class="form-control"
                        name="refresh_interval"
                        value="<?php echo $this->settings['refresh_interval'] ?? 5; ?>">

                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="vehicle_icons" value="1"
                    <?php echo !empty($this->settings['vehicle_icons']) ? 'checked' : ''; ?>>
                    <label class="form-check-label">Fahrzeug Icons</label>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="history" value="1"
                    <?php echo !empty($this->settings['history']) ? 'checked' : ''; ?>>
                    <label class="form-check-label">Historie</label>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="geofencing" value="1"
                    <?php echo !empty($this->settings['geofencing']) ? 'checked' : ''; ?>>
                    <label class="form-check-label">Geofencing</label>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="alarms" value="1"
                    <?php echo !empty($this->settings['alarms']) ? 'checked' : ''; ?>>
                    <label class="form-check-label">Alarme</label>
                </div>

                <button
                    type="submit"
                    class="btn btn-success">
                    Speichern
                </button>

            </div>

        </div>

    </form>

</div>
