<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use TKKundendienst\Component\Gpsportal\Site\Model\DocumentsModel;

$vehicleId = (int) ($_GET['vehicle_id'] ?? 0);

$app = Factory::getApplication();
$model = new DocumentsModel();

if (
    isset($_GET['download'])
    && (int) $_GET['download'] > 0
)
{
    $model->downloadDocument(
        (int) $_GET['download']
    );

    return;
}

if (
    isset($_GET['delete'])
    && (int) $_GET['delete'] > 0
)
{
    $model->deleteDocument(
        (int) $_GET['delete']
    );

    $app->enqueueMessage(
        'Dokument gelöscht'
    );

    $app->redirect(
        'index.php?option=com_gpsportal&view=documents&vehicle_id='
        . $vehicleId
    );

    return;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_document'])
)
{
    $ok = $model->saveDocument(
        $_FILES['document_file'],
        [
            'vehicle_id'    => (int) $_POST['vehicle_id'],
            'document_type' => $_POST['document_type'] ?? '',
            'valid_until'   => !empty($_POST['valid_until'])
                ? $_POST['valid_until']
                : null,
            'reminder_days' => (int) ($_POST['reminder_days'] ?? 30),
            'notes'         => $_POST['notes'] ?? ''
        ]
    );

    if ($ok)
    {
        $app->enqueueMessage(
            'Dokument gespeichert'
        );
    }
    else
    {
$app->enqueueMessage(
    $model->getLastError() ?: 'Upload fehlgeschlagen',
    'error'
);
    }

    $app->redirect($_SERVER['REQUEST_URI']);
    return;
}
?>

<style>

.logbook-card{
    background:#111827;
    border:1px solid #374151;
    border-radius:18px;
    padding:15px;
    color:#fff;
    box-shadow:0 10px 25px rgba(0,0,0,.35);
}

.logbook-title{
    font-size:24px;
    font-weight:700;
    margin-bottom:15px;
}

.top-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:10px;
}

.tile{
    background:#111827;
    border:1px solid #374151;
    border-radius:12px;
    padding:10px;
}

.tile label{
    display:block;
    font-size:12px;
    color:#cbd5e1;
    margin-bottom:4px;
}

.tile input,
.tile select,
.tile textarea{
    width:100%;
    background:#1f2937;
    color:#fff;
    border:1px solid #374151;
    border-radius:8px;
    padding:8px;
}

.route-btn{
    width:100%;
    height:38px;
    margin-top:18px;
    background:#f97316;
    color:#fff;
    border:none;
    border-radius:8px;
    cursor:pointer;
}

.logbook-table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}

.logbook-table th{
    background:#1f2937;
    border:1px solid #374151;
    padding:10px;
    text-align:left;
}

.logbook-table td{
    border:1px solid #374151;
    padding:10px;
}

.signature-box{
    margin-top:20px;
    background:#1f2937;
    border:1px solid #374151;
    border-radius:12px;
    padding:15px;
}

.btn-download{
    background:#2563eb;
    color:#fff;
    padding:6px 10px;
    border-radius:6px;
    text-decoration:none;
}

.btn-delete{
    background:#dc2626;
    color:#fff;
    padding:6px 10px;
    border-radius:6px;
    text-decoration:none;
}

@media(max-width:1200px){
    .top-grid{
        grid-template-columns:1fr;
    }
}

</style>

<div class="logbook-card">

<div class="logbook-title">
Dokumente
</div>

<form method="get">

<input type="hidden" name="option" value="com_gpsportal">
<input type="hidden" name="view" value="documents">

<div class="top-grid">

<div class="tile">
<label>Fahrzeug</label>

<select
name="vehicle_id"
onchange="this.form.submit();"

>

<option value="">
Bitte Fahrzeug auswählen
</option>

<?php foreach ($this->vehicles as $vehicle) : ?>

<option
    value="<?php echo $vehicle->id; ?>"
    <?php echo $vehicleId === (int) $vehicle->id ? 'selected' : ''; ?>
>
<?php echo htmlspecialchars($vehicle->name); ?>
</option>

<?php endforeach; ?>

</select>

</div>

</div>

</form>

<?php if ($vehicleId > 0) : ?>

<form method="post" enctype="multipart/form-data">

<input
type="hidden"
name="vehicle_id"
value="<?php echo $vehicleId; ?>"

>

<div class="top-grid">

<div class="tile">
<label>Dokumenttyp</label>

<select name="document_type">
<option>Fahrzeugschein</option>
<option>Fahrzeugbrief</option>
<option>TÜV</option>
<option>UVV</option>
<option>Versicherung</option>
<option>Leasingvertrag</option>
<option>Tankkarte</option>
<option>Wartungsvertrag</option>
<option>Sonstiges</option>
</select>
</div>

<div class="tile">
<label>Datei</label>
<input type="file" name="document_file">
</div>

<div class="tile">
<label>Gültig bis</label>
<input type="date" name="valid_until">
</div>

<div class="tile">
<label>Erinnerung (Tage)</label>
<input type="number" name="reminder_days" value="30">
</div>

</div>

<div class="signature-box">

<label>Notiz</label>

<textarea
    name="notes"
    rows="4"
    style="width:100%;min-height:120px;"
></textarea>

<br><br>

<button
class="route-btn"
type="submit"
name="save_document"

>

Dokument speichern </button>

</div>

</form>

<table class="logbook-table">

<tr>
<th>Typ</th>
<th>Datei</th>
<th>Größe</th>
<th>Gültig bis</th>
<th>Hochgeladen</th>
<th>Aktionen</th>
</tr>

<?php foreach ($this->documents as $document) : ?>

<tr>

<td>
<?php echo htmlspecialchars($document->document_type ?? ''); ?>
</td>

<td>
<?php echo htmlspecialchars($document->original_name ?? ''); ?>
</td>

<td>
<?php echo round(($document->file_size ?? 0) / 1024,1); ?> KB
</td>

<td>
<?php echo htmlspecialchars($document->valid_until ?? '-'); ?>
</td>

<td>
<?php echo date('d.m.Y', strtotime($document->created)); ?>
</td>

<td>

<a
class="btn-download"
href="?option=com_gpsportal&view=documents&download=<?php echo $document->id; ?>"

>

Download </a>

<a
class="btn-delete"
href="?option=com_gpsportal&view=documents&delete=<?php echo $document->id; ?>&vehicle_id=<?php echo $vehicleId; ?>"
onclick="return confirm('Dokument wirklich löschen?');"

>

Löschen </a>

</td>

</tr>

<?php endforeach; ?>

</table>

<?php if (empty($this->documents)) : ?>

<div class="signature-box">
Noch keine Dokumente vorhanden.
</div>

<?php endif; ?>

<?php endif; ?>

</div>
