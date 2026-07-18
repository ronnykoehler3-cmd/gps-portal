<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use TKKundendienst\Component\Gpsportal\Site\Model\DemovehiclesModel;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$app = Factory::getApplication();
$input = $app->input;
$gpsStatus = '';
$gpsMessage = '';
$editVehicle = $this->editVehicle;
$editDestinations = [];
if ($editVehicle) {
    foreach (json_decode((string) $editVehicle->destinations_json, true) ?: [] as $destination) {
        $editDestinations[] = (string) ($destination['address'] ?? '');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!Session::checkToken('post')) {
            throw new RuntimeException('Das Sicherheitstoken ist abgelaufen. Bitte Seite neu laden.');
        }

        $model = new DemovehiclesModel();
        $action = $input->post->getCmd('demo_action');

        if ($action === 'assign_initial') {
            $assigned = $model->assignInitialVehicles($input->post->getInt('user_id'), 4);
            $gpsMessage = $assigned . ' Demofahrzeuge wurden dem Benutzer zugeordnet.';
        } elseif ($action === 'assign_vehicle') {
            $model->assignVehicle(
                $input->post->getInt('device_id'),
                $input->post->getInt('user_id'),
                $input->post->getInt('fixed_assignment') === 1
            );
            $gpsMessage = 'Die Fahrzeugzuordnung wurde gespeichert.';
        } elseif ($action === 'unassign_vehicle') {
            $model->unassignVehicle($input->post->getInt('device_id'));
            $gpsMessage = 'Die Zuordnung wurde gelöst. Das Fahrzeug ist wieder frei.';
        } elseif ($action === 'delete_vehicle') {
            $model->deleteDemoVehicle($input->post->getInt('device_id'));
            $gpsMessage = 'Das Demofahrzeug wurde endgültig gelöscht.';
        } elseif ($action === 'save_vehicle') {
            $model->saveDemoVehicle([
                'user_id' => $input->post->getInt('user_id'),
                'fixed_assignment' => $input->post->getInt('fixed_assignment'),
                'name' => $input->post->getString('name'),
                'unique_id' => $input->post->getString('unique_id'),
                'license_plate' => $input->post->getString('license_plate'),
                'region' => $input->post->getString('region'),
                'start_address' => $input->post->getString('start_address'),
                'destinations' => $input->post->getRaw('destinations', ''),
                'minimum_speed_kmh' => $input->post->getInt('minimum_speed_kmh'),
                'maximum_speed_kmh' => $input->post->getInt('maximum_speed_kmh'),
                'active' => $input->post->getInt('active'),
            ]);
            $gpsMessage = 'Das Dummyfahrzeug wurde zentral gespeichert.';
        } else {
            throw new RuntimeException('Die Formularaktion ist unbekannt.');
        }

        $gpsStatus = 'success';
        $this->users = $model->getUsers();
        $this->vehicles = $model->getDemoVehicles();
    } catch (Throwable $error) {
        $gpsStatus = 'error';
        $gpsMessage = $error->getMessage();
    }
}
?>

<style>
.demo-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.demo-card{background:#081327;border:1px solid rgba(59,130,246,.25);border-radius:16px;padding:18px}.demo-card-wide{grid-column:1/-1}.demo-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.demo-form .full{grid-column:1/-1}.demo-form label{display:block;margin-bottom:5px;color:#93c5fd}.demo-form input,.demo-form select,.demo-form textarea{width:100%;padding:10px;background:#0b1d3a;color:#fff;border:1px solid #29466f;border-radius:8px}.demo-form textarea{min-height:110px}.demo-button{padding:10px 16px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-weight:700;cursor:pointer}.demo-button-danger{background:#dc2626}.demo-button-muted{background:#475569}.demo-table{width:100%;border-collapse:collapse}.demo-table th,.demo-table td{text-align:left;padding:10px;border-bottom:1px solid #1e3557;vertical-align:top}.demo-actions{display:flex;gap:8px;flex-wrap:wrap}.demo-actions form{display:inline}.sync-pending{color:#facc15}.demo-notice{margin:0 0 18px;padding:14px 16px;border-radius:10px;font-weight:700}.demo-notice-success{color:#86efac;background:#052e1a;border:1px solid #15803d}.demo-notice-error{color:#fecaca;background:#3f1018;border:1px solid #dc2626}@media(max-width:900px){.demo-grid,.demo-form{grid-template-columns:1fr}.demo-card-wide,.demo-form .full{grid-column:1}.demo-table{display:block;overflow:auto}}
</style>

<h1>Dummyfahrzeuge verwalten</h1>
<p>Fahrzeuge werden ausschließlich hier zentral vom Administrator verwaltet. Benutzer können zugeordnete Fahrzeuge nur in ihrer eigenen Ansicht ausblenden.</p>

<?php if ($gpsMessage !== ''): ?>
<div class="demo-notice demo-notice-<?php echo $gpsStatus === 'success' ? 'success' : 'error'; ?>"><?php echo $escape($gpsMessage); ?></div>
<?php endif; ?>

<div class="demo-grid">
    <section class="demo-card demo-card-wide">
        <h2>Benutzer mit vier Demofahrzeugen ausstatten</h2>
        <p>Es werden zuerst freie Fahrzeuge aus dem Demofahrzeug-Bestand verwendet.</p>
        <form method="post" action="<?php echo Route::_('index.php?option=com_gpsportal&view=demovehicles'); ?>" class="demo-form">
            <input type="hidden" name="demo_action" value="assign_initial">
            <div><label>Joomla-Benutzer</label><select name="user_id" required><option value="">Bitte wählen</option><?php foreach ($this->users as $user): ?><option value="<?php echo (int) $user->id; ?>"><?php echo $escape($user->name); ?> – <?php echo $escape($user->username); ?></option><?php endforeach; ?></select></div>
            <div><label>&nbsp;</label><button class="demo-button">Vier Demofahrzeuge zuweisen</button></div>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </section>

    <section class="demo-card demo-card-wide">
        <h2><?php echo $editVehicle ? 'Dummyfahrzeug bearbeiten' : 'Neues Dummyfahrzeug zentral anlegen'; ?></h2>
        <form method="post" action="<?php echo Route::_('index.php?option=com_gpsportal&view=demovehicles'); ?>" class="demo-form">
            <input type="hidden" name="demo_action" value="save_vehicle">
            <div><label>Benutzer – optional</label><select name="user_id"><option value="0">Noch nicht zuordnen</option><?php foreach ($this->users as $user): ?><option value="<?php echo (int) $user->id; ?>"<?php echo $editVehicle && (int) $editVehicle->user_id === (int) $user->id ? ' selected' : ''; ?>><?php echo $escape($user->name); ?> – <?php echo $escape($user->username); ?></option><?php endforeach; ?></select></div>
            <div><label>Fahrzeugname</label><input name="name" value="<?php echo $escape($editVehicle->name ?? ''); ?>" required></div>
            <div><label>Unique ID</label><input name="unique_id" value="<?php echo $escape($editVehicle->tracker_unique_id ?? ''); ?>" placeholder="DEMO-0001"<?php echo $editVehicle ? ' readonly' : ''; ?> required></div>
            <div><label>Kennzeichen</label><input name="license_plate" value="<?php echo $escape($editVehicle->license_plate ?? ''); ?>"></div>
            <div><label>Region</label><input name="region" value="<?php echo $escape($editVehicle->region ?? ''); ?>" placeholder="z. B. Schwarzenbek"></div>
            <div><label>Startadresse</label><input name="start_address" value="<?php echo $escape($editVehicle->start_address ?? ''); ?>" placeholder="Straße, PLZ Ort" required></div>
            <div><label>Minimale Geschwindigkeit</label><input type="number" min="1" max="200" name="minimum_speed_kmh" value="<?php echo (int) ($editVehicle->minimum_speed_kmh ?? 20); ?>" required></div>
            <div><label>Maximale Geschwindigkeit</label><input type="number" min="1" max="200" name="maximum_speed_kmh" value="<?php echo (int) ($editVehicle->maximum_speed_kmh ?? 100); ?>" required></div>
            <div class="full"><label>Zieladressen – eine Adresse pro Zeile</label><textarea name="destinations" required><?php echo $escape(implode("\n", $editDestinations)); ?></textarea></div>
            <div><label><input type="checkbox" name="fixed_assignment" value="1"<?php echo $editVehicle && $editVehicle->fixed_assignment ? ' checked' : ''; ?>> Feste Zuordnung</label></div>
            <div><label><input type="checkbox" name="active" value="1"<?php echo !$editVehicle || $editVehicle->active ? ' checked' : ''; ?>> Simulator aktiv</label></div>
            <div class="full"><button class="demo-button"><?php echo $editVehicle ? 'Änderungen speichern' : 'Fahrzeug speichern'; ?></button><?php if ($editVehicle): ?> <a class="demo-button demo-button-muted" href="index.php?option=com_gpsportal&view=demovehicles">Abbrechen</a><?php endif; ?></div>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </section>

    <section class="demo-card demo-card-wide">
        <h2>Zentraler Demofahrzeug-Bestand</h2>
        <table class="demo-table"><thead><tr><th>Fahrzeug</th><th>Zuordnung</th><th>Start</th><th>Geschwindigkeit</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>
        <?php foreach ($this->vehicles as $vehicle): ?><tr>
            <td><?php echo $escape($vehicle->name); ?><br><small><?php echo $escape($vehicle->tracker_unique_id); ?></small></td>
            <td><?php echo $vehicle->user_id ? $escape($vehicle->assigned_user_name . ' – ' . $vehicle->assigned_username) : '<strong>Frei</strong>'; ?><?php if ($vehicle->fixed_assignment): ?><br><small>Fest zugeordnet</small><?php endif; ?></td>
            <td><?php echo $escape($vehicle->start_address); ?></td><td><?php echo (int) $vehicle->minimum_speed_kmh; ?>–<?php echo (int) $vehicle->maximum_speed_kmh; ?> km/h</td><td class="sync-<?php echo $escape($vehicle->sync_status); ?>"><?php echo $escape($vehicle->sync_status); ?></td>
            <td><div class="demo-actions">
                <a class="demo-button" href="index.php?option=com_gpsportal&view=demovehicles&edit=<?php echo (int) $vehicle->device_id; ?>">Bearbeiten</a>
                <form method="post"><input type="hidden" name="demo_action" value="assign_vehicle"><input type="hidden" name="device_id" value="<?php echo (int) $vehicle->device_id; ?>"><select name="user_id" required><option value="">Benutzer wählen</option><?php foreach ($this->users as $user): ?><option value="<?php echo (int) $user->id; ?>"<?php echo (int) $vehicle->user_id === (int) $user->id ? ' selected' : ''; ?>><?php echo $escape($user->name); ?></option><?php endforeach; ?></select><label><input type="checkbox" name="fixed_assignment" value="1"<?php echo $vehicle->fixed_assignment ? ' checked' : ''; ?>> fest</label><button class="demo-button">Zuordnen</button><?php echo HTMLHelper::_('form.token'); ?></form>
                <?php if ($vehicle->user_id): ?><form method="post" onsubmit="return confirm('Zuordnung lösen? Das Fahrzeug bleibt erhalten und wird wieder frei.');"><input type="hidden" name="demo_action" value="unassign_vehicle"><input type="hidden" name="device_id" value="<?php echo (int) $vehicle->device_id; ?>"><button class="demo-button demo-button-muted">Zuordnung lösen</button><?php echo HTMLHelper::_('form.token'); ?></form><?php endif; ?>
                <form method="post" onsubmit="return confirm('Fahrzeug wirklich überall endgültig löschen?');"><input type="hidden" name="demo_action" value="delete_vehicle"><input type="hidden" name="device_id" value="<?php echo (int) $vehicle->device_id; ?>"><button class="demo-button demo-button-danger">Endgültig löschen</button><?php echo HTMLHelper::_('form.token'); ?></form>
            </div></td>
        </tr><?php endforeach; ?></tbody></table>
    </section>
</div>
