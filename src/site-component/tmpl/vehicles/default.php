<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use TKKundendienst\Component\Gpsportal\Site\Model\VehiclesModel;

$app = Factory::getApplication();
$input = $app->input;

$model = new VehiclesModel();

$editVehicle = null;
$editId = $input->getInt('edit', 0);

if ($editId > 0) {
    $editVehicle = $model->getVehicle(
        $editId
    );

    if (!$editVehicle) {
        $app->enqueueMessage(
            'Das Fahrzeug wurde nicht gefunden oder gehört nicht zu Ihrem Konto.',
            'error'
        );

        $app->redirect(
            Route::_(
                'index.php?option=com_gpsportal&view=vehicles',
                false
            )
        );

        return;
    }
}

if (
    strtoupper($input->getMethod()) === 'POST'
) {
    if (!Session::checkToken('post')) {
        throw new \RuntimeException(
            'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte laden Sie die Seite neu.'
        );
    }

    $action = $input->post->getCmd(
        'vehicle_action',
        ''
    );

    try {
        if ($action === 'delete') {
            $vehicleId = $input->post->getInt(
                'vehicle_id',
                0
            );

            $model->deleteVehicle(
                $vehicleId
            );

            $app->enqueueMessage(
                'Das Fahrzeug wurde aus Ihrem Konto entfernt.'
            );
        }

        if ($action === 'save') {
            $vehicleId = $input->post->getInt(
                'vehicle_id',
                0
            );

            $vehicleData = [
                'name' =>
                    $input->post->getString(
                        'name',
                        ''
                    ),

                'license_plate' =>
                    $input->post->getString(
                        'license_plate',
                        ''
                    ),

                'tracker_unique_id' =>
                    $input->post->getString(
                        'tracker_unique_id',
                        ''
                    ),

                'vehicle_type' =>
                    $input->post->getString(
                        'vehicle_type',
                        ''
                    ),

                'manufacturer' =>
                    $input->post->getString(
                        'manufacturer',
                        ''
                    ),

                'model' =>
                    $input->post->getString(
                        'model',
                        ''
                    ),

                'driver' =>
                    $input->post->getString(
                        'driver',
                        ''
                    ),

                'cost_center' =>
                    $input->post->getString(
                        'cost_center',
                        ''
                    ),

                'initial_odometer_km' =>
                    $input->post->getFloat(
                        'initial_odometer_km',
                        0
                    ),

                'color' =>
                    $input->post->getString(
                        'color',
                        ''
                    ),

                'marker_icon' =>
                    $input->post->getString(
                        'marker_icon',
                        ''
                    ),

                'notes' =>
                    $input->post->getString(
                        'notes',
                        ''
                    )
            ];

            if ($vehicleId > 0) {
                $model->updateVehicle(
                    $vehicleId,
                    $vehicleData
                );

                $app->enqueueMessage(
                    'Das Fahrzeug wurde aktualisiert.'
                );
            } else {
                $model->saveVehicle(
                    $vehicleData
                );

                $app->enqueueMessage(
                    'Das Fahrzeug wurde gespeichert.'
                );
            }
        }
    } catch (\Throwable $exception) {
        $app->enqueueMessage(
            $exception->getMessage(),
            'error'
        );
    }

    $app->redirect(
        Route::_(
            'index.php?option=com_gpsportal&view=vehicles',
            false
        )
    );

    return;
}

$escape = static function (
    mixed $value
): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$vehiclesUrl = Route::_(
    'index.php?option=com_gpsportal&view=vehicles'
);

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
    margin: 0 0 12px 0;
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
    min-height: 70px;
}

.form-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.save-btn {
    min-height: 38px;
    padding: 0 16px;
    cursor: pointer;
}

.cancel-btn {
    display: inline-flex;
    align-items: center;
    min-height: 38px;
    padding: 0 16px;
    background: #475569;
    border-radius: 8px;
    color: #fff !important;
    text-decoration: none;
}

.vehicle-table {
    width: 100%;
    border-collapse: collapse;
}

.vehicle-table th,
.vehicle-table td {
    padding: 10px;
}

.action-buttons {
    white-space: nowrap;
}

.btn-edit {
    display: inline-block;
    background: #2563eb;
    color: #fff !important;
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    margin-right: 6px;
}

.delete-form {
    display: inline-block;
    margin: 0;
}

.btn-delete {
    display: inline-block;
    border: 0;
    background: #dc2626;
    color: #fff !important;
    padding: 8px 14px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}

@media (max-width: 900px) {
    .vehicle-form {
        grid-template-columns: 1fr;
    }

    .vehicle-form .full {
        grid-column: auto;
    }

    .vehicle-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<div class="portal-box">

    <h2>
        <?php if ($editVehicle): ?>
            Fahrzeug bearbeiten
        <?php else: ?>
            Neues Fahrzeug anlegen
        <?php endif; ?>
    </h2>

    <form
        method="post"
        action="<?php echo $escape($vehiclesUrl); ?>"
        class="vehicle-form"
    >
        <input
            type="hidden"
            name="vehicle_action"
            value="save"
        >

        <input
            type="hidden"
            name="vehicle_id"
            value="<?php echo $escape($editVehicle->id ?? ''); ?>"
        >

        <div>
            <label for="vehicle-name">
                Fahrzeugname
            </label>

            <input
                id="vehicle-name"
                type="text"
                name="name"
                required
                value="<?php echo $escape($editVehicle->name ?? ''); ?>"
            >
        </div>

        <div>
            <label for="license-plate">
                Kennzeichen
            </label>

            <input
                id="license-plate"
                type="text"
                name="license_plate"
                value="<?php echo $escape($editVehicle->license_plate ?? ''); ?>"
            >
        </div>

        <div>
            <label for="tracker-unique-id">
                Tracker Unique ID
            </label>

            <input
                id="tracker-unique-id"
                type="text"
                name="tracker_unique_id"
                required
                value="<?php echo $escape($editVehicle->tracker_unique_id ?? ''); ?>"
            >
        </div>

        <div>
            <label for="vehicle-type">
                Fahrzeugtyp
            </label>

            <input
                id="vehicle-type"
                type="text"
                name="vehicle_type"
                value="<?php echo $escape($editVehicle->vehicle_type ?? ''); ?>"
            >
        </div>

        <div>
            <label for="manufacturer">
                Hersteller
            </label>

            <input
                id="manufacturer"
                type="text"
                name="manufacturer"
                value="<?php echo $escape($editVehicle->manufacturer ?? ''); ?>"
            >
        </div>

        <div>
            <label for="vehicle-model">
                Modell
            </label>

            <input
                id="vehicle-model"
                type="text"
                name="model"
                value="<?php echo $escape($editVehicle->model ?? ''); ?>"
            >
        </div>

        <div>
            <label for="driver">
                Fahrer
            </label>

            <input
                id="driver"
                type="text"
                name="driver"
                value="<?php echo $escape($editVehicle->driver ?? ''); ?>"
            >
        </div>

        <div>
            <label for="cost-center">
                Kostenstelle
            </label>

            <input
                id="cost-center"
                type="text"
                name="cost_center"
                value="<?php echo $escape($editVehicle->cost_center ?? ''); ?>"
            >
        </div>

        <div>
            <label for="initial-odometer">
                Anfangskilometerstand
            </label>

            <input
                id="initial-odometer"
                type="number"
                name="initial_odometer_km"
                step="0.1"
                min="0"
                value="<?php echo $escape($editVehicle->initial_odometer_km ?? '0'); ?>"
            >
        </div>

        <div>
            <label for="vehicle-color">
                Farbe
            </label>

            <select
                id="vehicle-color"
                name="color"
            >
                <?php
                $colors = [
                    ''        => 'Standard',
                    'Schwarz' => '⬛ Schwarz',
                    'Grau'    => '⬜ Grau',
                    'Blau'    => '🟦 Blau',
                    'Rot'     => '🟥 Rot',
                    'Grün'    => '🟩 Grün',
                    'Orange'  => '🟧 Orange',
                    'Gelb'    => '🟨 Gelb',
                    'Weiß'    => '⬜ Weiß'
                ];

                $selectedColor = (string) (
                    $editVehicle->color ?? ''
                );
                ?>

                <?php foreach ($colors as $value => $label): ?>
                    <option
                        value="<?php echo $escape($value); ?>"
                        <?php echo $selectedColor === $value ? 'selected' : ''; ?>
                    >
                        <?php echo $escape($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="marker-icon">
                Kartensymbol
            </label>

            <select
                id="marker-icon"
                name="marker_icon"
            >
                <?php
                $markerIcons = [
                    ''           => 'Standard',
                    'car'        => '🚗 PKW',
                    'van'        => '🚐 Transporter',
                    'truck'      => '🚚 LKW',
                    'motorcycle' => '🏍 Motorrad',
                    'phone'      => '📱 Handy',
                    'tablet'     => '📟 Tablet',
                    'hearse'     => '⚰ Leichenwagen'
                ];

                $selectedMarker = (string) (
                    $editVehicle->marker_icon ?? ''
                );
                ?>

                <?php foreach ($markerIcons as $value => $label): ?>
                    <option
                        value="<?php echo $escape($value); ?>"
                        <?php echo $selectedMarker === $value ? 'selected' : ''; ?>
                    >
                        <?php echo $escape($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="full">
            <label for="vehicle-notes">
                Notizen
            </label>

            <textarea
                id="vehicle-notes"
                name="notes"
                rows="4"
            ><?php echo $escape($editVehicle->notes ?? ''); ?></textarea>
        </div>

        <div class="full form-actions">
            <button
                type="submit"
                class="save-btn"
            >
                Fahrzeug speichern
            </button>

            <?php if ($editVehicle): ?>
                <a
                    href="<?php echo $escape($vehiclesUrl); ?>"
                    class="cancel-btn"
                >
                    Abbrechen
                </a>
            <?php endif; ?>
        </div>

        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

</div>

<div class="portal-box">

    <h2>Meine Fahrzeuge</h2>

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
                    <td>
                        <?php echo $escape($vehicle->name ?? ''); ?>
                    </td>

                    <td>
                        <?php echo $escape($vehicle->license_plate ?? ''); ?>
                    </td>

                    <td>
                        <?php echo $escape($vehicle->tracker_unique_id ?? ''); ?>
                    </td>

                    <td>
                        <?php echo $escape($vehicle->vehicle_type ?? ''); ?>
                    </td>

                    <td>
                        <?php echo $escape($vehicle->driver ?? ''); ?>
                    </td>

                    <td>
                        <?php
                        echo number_format(
                            (float) (
                                $vehicle->initial_odometer_km
                                ?? 0
                            ),
                            1,
                            ',',
                            '.'
                        );
                        ?> km
                    </td>

                    <td class="action-buttons">
                        <a
                            class="btn-edit"
                            href="<?php
                            echo $escape(
                                Route::_(
                                    'index.php?option=com_gpsportal&view=vehicles&edit='
                                    . (int) $vehicle->id
                                )
                            );
                            ?>"
                        >
                            ✏ Bearbeiten
                        </a>

                        <form
                            method="post"
                            action="<?php echo $escape($vehiclesUrl); ?>"
                            class="delete-form"
                            onsubmit="return confirm('Fahrzeug wirklich aus Ihrem Konto entfernen?');"
                        >
                            <input
                                type="hidden"
                                name="vehicle_action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="vehicle_id"
                                value="<?php echo (int) $vehicle->id; ?>"
                            >

                            <button
                                type="submit"
                                class="btn-delete"
                            >
                                🗑 Löschen
                            </button>

                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($this->vehicles)): ?>
                <tr>
                    <td colspan="7">
                        Es sind noch keine Fahrzeuge zugeordnet.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>