<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use TKKundendienst\Component\Gpsportal\Site\Model\VehiclesModel;

$app = Factory::getApplication();
$model = new VehiclesModel();
$editVehicle = null;

if (isset($_GET['edit']) && (int) $_GET['edit'] > 0) {
    $editVehicle = $model->getVehicle((int) $_GET['edit']);
}

if (isset($_GET['delete']) && (int) $_GET['delete'] > 0) {
    $model->deleteVehicle((int) $_GET['delete']);

    $app->enqueueMessage('Fahrzeug gelöscht');
    $app->redirect('index.php?option=com_gpsportal&view=vehicles');

    return;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['save_vehicle'])
) {
    $vehicleData = [
        'name' => $_POST['name'] ?? '',
        'license_plate' => $_POST['license_plate'] ?? '',
        'tracker_unique_id' => $_POST['tracker_unique_id'] ?? '',
        'vehicle_type' => $_POST['vehicle_type'] ?? '',
        'manufacturer' => $_POST['manufacturer'] ?? '',
        'model' => $_POST['model'] ?? '',
        'driver' => $_POST['driver'] ?? '',
        'cost_center' => $_POST['cost_center'] ?? '',
        'initial_odometer_km' =>
            str_replace(',', '.', (string) ($_POST['initial_odometer_km'] ?? '0')),
        'color' => $_POST['color'] ?? '',
        'marker_icon' => $_POST['marker_icon'] ?? '',
        'notes' => $_POST['notes'] ?? '',
    ];

    if (!empty($_POST['vehicle_id'])) {
        $model->updateVehicle(
            (int) $_POST['vehicle_id'],
            $vehicleData
        );

        $app->enqueueMessage('Fahrzeug aktualisiert');
    } else {
        $model->saveVehicle($vehicleData);
        $app->enqueueMessage('Fahrzeug gespeichert');
    }

    $app->redirect(
        'index.php?option=com_gpsportal&view=vehicles'
    );

    return;
}
?>

<h1>Fahrzeuge</h1>

<style>
.portal-box {
    background: #081327;
    border: 1px solid rgba(59, 130, 246, .15);
    border-radius: 18px;
    padding: 18px;
    margin-bottom: 18px;
}

.portal-box h2 {
    margin: 0 0 12px;
    font-size: 24px;
}

.vehicle-form {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.vehicle-form .full {
    grid-column: 1 / -1;
}

.vehicle-form label {
    display: block;
    font-size: 13px;
    margin-bottom: 3px;
}

.vehicle-form input,
.vehicle-form textarea,
.vehicle-form select {
    width: 100%;
    padding: 8px 10px;
    min-height: 38px;
    background: #081a37;
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 8px;
    color: #fff;
}

.vehicle-form textarea {
    height: 70px;
    resize: vertical;
}

.save-btn {
    min-height: 38px;
    padding: 0 16px;
}

.vehicle-table {
    width: 100%;
    border-collapse: collapse;
}

.vehicle-table th,
.vehicle-table td {
    padding: 10px;
    text-align: left;
}

.action-buttons {
    white-space: nowrap;
}

.btn-edit,
.btn-delete {
    display: inline-block;
    color: #fff !important;
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}

.btn-edit {
    background: #2563eb;
    margin-right: 6px;
}

.btn-delete {
    background: #dc2626;
}

@media (max-width: 1000px) {
    .vehicle-form {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="portal-box">
    <h2>
        <?php echo $editVehicle ? 'Fahrzeug bearbeiten' : 'Neues Fahrzeug anlegen'; ?>
    </h2>

    <form method="post" class="vehicle-form">
        <input
            type="hidden"
            name="vehicle_id"
            value="<?php echo (int) ($editVehicle->id ?? 0); ?>"
        >

        <div>
            <label>Fahrzeugname</label>
            <input
                type="text"
                name="name"
                required
                value="<?php echo htmlspecialchars(
                    (string) ($editVehicle->name ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        </div>

        <div>
            <label>Kennzeichen</label>
            <input
                type="text"
                name="license_plate"
                value="<?php echo htmlspecialchars(
                    (string) ($editVehicle->license_plate ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        </div>

        <div>
            <label>Tracker Unique ID</label>
            <input
                type="text"
                name="tracker_unique_id"
                required
                value="<?php echo htmlspecialchars(
                    (string) ($editVehicle->tracker_unique_id ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        </div>

        <div>
            <label>Fahrzeugtyp</label>
            <input
                type="text"
                name="vehicle_type"
                value="<?php echo htmlspecialchars(
                    (string) ($editVehicle->vehicle_type ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        </div>

        <div>
            <label>Hersteller</label>
            <input
                type="text"
                name="manufacturer"
                value="<?php echo htmlspecialchars(
                    (string) ($editVehicle->manufacturer ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        </div>

        <div>
            <label>Modell</label>
            <input
                type="text"
                name="model"
                value="<?php echo htmlspecialchars(
                    (string) ($editVehicle->model ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        </div>

        <div>
            <label>Fahrer</label>
            <input
                type="text"
                name="driver"
                value="<?php echo htmlspecialchars(
                    (string) ($editVehicle->driver ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        </div>

        <div>
            <label>Kostenstelle</label>
            <input
                type="text"
                name="cost_center"
                value="<?php echo htmlspecialchars(
                    (string) ($editVehicle->cost_center ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        </div>

        <div>
            <label>Anfangskilometerstand</label>
            <input
                type="number"
                name="initial_odometer_km"
                min="0"
                step="0.1"
                inputmode="decimal"
                value="<?php echo htmlspecialchars(
                    (string) ($editVehicle->initial_odometer_km ?? '0.0'),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        </div>

        <div>
            <label>Farbe</label>
            <select name="color">
                <?php
                $colors = [
                    '' => 'Standard',
                    'Schwarz' => 'Schwarz',
                    'Grau' => 'Grau',
                    'Blau' => 'Blau',
                    'Rot' => 'Rot',
                    'Grün' => 'Grün',
                    'Orange' => 'Orange',
                    'Gelb' => 'Gelb',
                    'Weiß' => 'Weiß',
                ];

                foreach ($colors as $value => $label):
                ?>
                    <option
                        value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo (($editVehicle->color ?? '') === $value) ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Kartensymbol</label>
            <select name="marker_icon">
                <?php
                $icons = [
                    '' => 'Standard',
                    'car' => 'PKW',
                    'van' => 'Transporter',
                    'motorcycle' => 'Motorrad',
                    'phone' => 'Handy',
                    'tablet' => 'Tablet',
                    'hearse' => 'Leichenwagen',
                    'truck' => 'LKW',
                ];

                foreach ($icons as $value => $label):
                ?>
                    <option
                        value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo (($editVehicle->marker_icon ?? '') === $value) ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="full">
            <label>Notizen</label>
            <textarea name="notes" rows="4"><?php
                echo htmlspecialchars(
                    (string) ($editVehicle->notes ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                );
            ?></textarea>
        </div>

        <div class="full">
            <button
                type="submit"
                name="save_vehicle"
                class="save-btn"
            >
                Fahrzeug speichern
            </button>
        </div>
    </form>
</div>

<div class="portal-box">
    <h2>Meine Fahrzeuge</h2>

    <div style="overflow-x:auto;">
        <table class="vehicle-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Kennzeichen</th>
                    <th>Tracker</th>
                    <th>Typ</th>
                    <th>Fahrer</th>
                    <th>Anfangs-KM</th>
                    <th>Aktionen</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($this->vehicles as $vehicle): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($vehicle->name ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($vehicle->license_plate ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($vehicle->tracker_unique_id ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($vehicle->vehicle_type ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($vehicle->driver ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format(
                            (float) ($vehicle->initial_odometer_km ?? 0),
                            1,
                            ',',
                            '.'
                        ); ?> km</td>
                        <td class="action-buttons">
                            <a
                                class="btn-edit"
                                href="?option=com_gpsportal&view=vehicles&edit=<?php echo (int) $vehicle->id; ?>"
                            >
                                Bearbeiten
                            </a>

                            <a
                                class="btn-delete"
                                href="?option=com_gpsportal&view=vehicles&delete=<?php echo (int) $vehicle->id; ?>"
                                onclick="return confirm('Fahrzeug wirklich löschen?');"
                            >
                                Löschen
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
