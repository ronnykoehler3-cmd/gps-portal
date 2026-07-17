<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use TKKundendienst\Component\Gpsportal\Site\Model\DocumentsModel;

$vehicleId = (int) ($_GET['vehicle_id'] ?? 0);

$app = Factory::getApplication();

$model = new DocumentsModel();

/*
|--------------------------------------------------------------------------
| Dokument anzeigen
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['preview'])
    && (int) $_GET['preview'] > 0
)
{
    $model->previewDocument(
        (int) $_GET['preview']
    );

    return;
}

/*
|--------------------------------------------------------------------------
| Dokument herunterladen
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Dokument löschen
|--------------------------------------------------------------------------
*/

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
        'index.php?option=com_gpsportal'
        . '&view=documents'
        . '&vehicle_id='
        . $vehicleId
    );

    return;
}

/*
|--------------------------------------------------------------------------
| Dokument speichern
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_document'])
)
{
    $ok = $model->saveDocument(
        $_FILES['document_file'],
        [
            'vehicle_id' =>
                (int) $_POST['vehicle_id'],

            'document_type' =>
                $_POST['document_type'] ?? '',

            'valid_until' =>
                !empty($_POST['valid_until'])
                    ? $_POST['valid_until']
                    : null,

            'reminder_days' =>
                (int) ($_POST['reminder_days'] ?? 30),

            'notes' =>
                $_POST['notes'] ?? ''
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
            $model->getLastError()
                ?: 'Upload fehlgeschlagen',
            'error'
        );
    }

    $app->redirect(
        $_SERVER['REQUEST_URI']
    );

    return;
}

/*
|--------------------------------------------------------------------------
| Suche
|--------------------------------------------------------------------------
*/

$search =
    trim(
        $_GET['search']
        ?? ''
    );

/*
|--------------------------------------------------------------------------
| Dokumenttyp
|--------------------------------------------------------------------------
*/

$filterType =
    trim(
        $_GET['filter_type']
        ?? ''
    );

/*
|--------------------------------------------------------------------------
| Sortierung
|--------------------------------------------------------------------------
*/

$sort =
    trim(
        $_GET['sort']
        ?? 'created'
    );

/*
|--------------------------------------------------------------------------
| Hilfsfunktionen
|--------------------------------------------------------------------------
*/

function gpsFileIcon(string $file): string
{
    $ext = strtolower(
        pathinfo(
            $file,
            PATHINFO_EXTENSION
        )
    );

    return match ($ext)
    {
        'pdf'  => '📄',
        'jpg',
        'jpeg',
        'png'  => '🖼️',
        default => '📁'
    };
}

function gpsDocumentState($date): string
{
    if (empty($date))
    {
        return '';
    }

    $today = new DateTime();

    $valid =
        new DateTime($date);

    if ($valid < $today)
    {
        return 'expired';
    }

    if (
        $today->diff($valid)->days <= 30
    )
    {
        return 'warning';
    }

    return 'ok';
}

?>

<style>
<style>

.logbook-card{
    background:#111827;
    border:1px solid #374151;
    border-radius:18px;
    padding:22px;
    color:#fff;
    box-shadow:0 10px 30px rgba(0,0,0,.35);
}

.logbook-title{
    font-size:28px;
    font-weight:700;
    margin-bottom:20px;
}

.top-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:15px;
}

.tile{
    background:#1f2937;
    border:1px solid #374151;
    border-radius:12px;
    padding:12px;
}

.tile label{
    display:block;
    font-size:13px;
    color:#cbd5e1;
    margin-bottom:6px;
}

.tile input,
.tile select,
.tile textarea{
    width:100%;
    padding:9px;
    border-radius:8px;
    border:1px solid #374151;
    background:#111827;
    color:#fff;
}

.route-btn{
    background:#f97316;
    color:#fff;
    border:none;
    border-radius:10px;
    padding:10px 20px;
    cursor:pointer;
    font-weight:600;
    transition:.2s;
}

.route-btn:hover{
    background:#ea580c;
}

.signature-box{
    margin-top:18px;
    background:#1f2937;
    border-radius:12px;
    border:1px solid #374151;
    padding:18px;
}

.toolbar{
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
    margin:25px 0;
}

.toolbar input,
.toolbar select{
    background:#111827;
    color:#fff;
    border:1px solid #374151;
    border-radius:8px;
    padding:8px 12px;
}

.toolbar button{
    background:#2563eb;
    color:#fff;
    border:none;
    border-radius:8px;
    padding:9px 16px;
    cursor:pointer;
}

.toolbar button:hover{
    background:#1d4ed8;
}

.logbook-table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

.logbook-table th{
    background:#1f2937;
    border-bottom:2px solid #374151;
    padding:12px;
    text-align:left;
    font-size:14px;
}

.logbook-table td{
    padding:12px;
    border-bottom:1px solid #374151;
    vertical-align:middle;
}

.logbook-table tr:hover{
    background:#172033;
}

.doc-icon{
    font-size:24px;
    width:40px;
    text-align:center;
}

.status-expired{
    background:#7f1d1d !important;
}

.status-warning{
    background:#78350f !important;
}

.status-ok{
    background:transparent;
}

.btn-preview,
.btn-download,
.btn-delete{

    display:inline-block;

    margin:2px;

    padding:7px 12px;

    border-radius:7px;

    text-decoration:none;

    color:#fff;

    font-size:13px;

    transition:.2s;
}

.btn-preview{
    background:#16a34a;
}

.btn-preview:hover{
    background:#15803d;
}

.btn-download{
    background:#2563eb;
}

.btn-download:hover{
    background:#1d4ed8;
}

.btn-delete{
    background:#dc2626;
}

.btn-delete:hover{
    background:#b91c1c;
}

.badge{

    display:inline-block;

    padding:4px 8px;

    border-radius:6px;

    font-size:12px;

    font-weight:bold;
}

.badge-expired{
    background:#dc2626;
}

.badge-warning{
    background:#f59e0b;
    color:#000;
}

.badge-ok{
    background:#16a34a;
}

@media(max-width:1200px){

.top-grid{
grid-template-columns:1fr;
}

.toolbar{
flex-direction:column;
align-items:stretch;
}

.logbook-table{
display:block;
overflow-x:auto;
white-space:nowrap;
}

}

</style>

<div class="logbook-card">

<div class="logbook-title">

📂 Dokumentenverwaltung

</div>

<form method="get">

<input type="hidden" name="option" value="com_gpsportal">
<input type="hidden" name="view" value="documents">

<div class="top-grid">

<div class="tile">

<label>Fahrzeug</label>

<select
name="vehicle_id"
onchange="this.form.submit();">

<option value="">Bitte Fahrzeug auswählen</option>

<?php foreach ($this->vehicles as $vehicle): ?>

<option
value="<?= $vehicle->id; ?>"
<?= $vehicleId==$vehicle->id?'selected':'';?>>

<?= htmlspecialchars($vehicle->name); ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

<div class="toolbar">

<input
type="text"
name="search"
placeholder="Dokument suchen..."
value="<?= htmlspecialchars($search); ?>">

<select name="filter_type">

<option value="">Alle Typen</option>

<option <?= $filterType=='Fahrzeugschein'?'selected':'';?>>
Fahrzeugschein
</option>

<option <?= $filterType=='Fahrzeugbrief'?'selected':'';?>>
Fahrzeugbrief
</option>

<option <?= $filterType=='TÜV'?'selected':'';?>>
TÜV
</option>

<option <?= $filterType=='Versicherung'?'selected':'';?>>
Versicherung
</option>

<option <?= $filterType=='Tankkarte'?'selected':'';?>>
Tankkarte
</option>

<option <?= $filterType=='Sonstiges'?'selected':'';?>>
Sonstiges
</option>

</select>

<select name="sort">

<option value="created">Neueste zuerst</option>

<option value="name"
<?= $sort=='name'?'selected':'';?>>

Dateiname

</option>

<option value="type"
<?= $sort=='type'?'selected':'';?>>

Dokumenttyp

</option>

<option value="expiry"
<?= $sort=='expiry'?'selected':'';?>>

Ablaufdatum

</option>

</select>

<button type="submit">

🔍 Filtern

</button>

</div>

</form>

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
value="<?= $vehicleId; ?>">

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

<input
type="file"
name="document_file"
required>

</div>

<div class="tile">

<label>Gültig bis</label>

<input
type="date"
name="valid_until">

</div>

<div class="tile">

<label>Erinnerung</label>

<input
type="number"
name="reminder_days"
value="30">

</div>

</div>

<div class="signature-box">

<label>Notiz</label>

<textarea
name="notes"
rows="4"
style="width:100%;min-height:120px;"></textarea>

<br><br>

<button
class="route-btn"
type="submit"
name="save_document">

📤 Dokument speichern

</button>

</div>

</form>

<table class="logbook-table">

<thead>

<tr>

<th></th>

<th>Datei</th>

<th>Typ</th>

<th>Größe</th>

<th>Gültig bis</th>

<th>Status</th>

<th>Hochgeladen</th>

<th>Aktionen</th>

</tr>

</thead>

<tbody>

<?php

$documents = $this->documents;

/*
 * Suche
 */

if ($search !== '')
{
    $documents = array_filter(
        $documents,
        function($d) use ($search)
        {
            return
                stripos(
                    $d->original_name,
                    $search
                ) !== false

                ||

                stripos(
                    $d->document_type,
                    $search
                ) !== false;
        }
    );
}

/*
 * Filter
 */

if ($filterType !== '')
{
    $documents = array_filter(
        $documents,
        function($d) use ($filterType)
        {
            return
                $d->document_type
                ===
                $filterType;
        }
    );
}

?>

<?php foreach ($documents as $document): ?>

<?php

$status =
    gpsDocumentState(
        $document->valid_until
    );

$rowClass =
    match($status)
    {
        'expired' => 'status-expired',
        'warning' => 'status-warning',
        default => 'status-ok'
    };

?>

<tr class="<?= $rowClass; ?>">

<td class="doc-icon">

<?= gpsFileIcon(
    $document->original_name
); ?>

</td>

<td>

<strong>

<?= htmlspecialchars(
    $document->original_name
); ?>

</strong>

</td>

<td>

<?= htmlspecialchars(
    $document->document_type
); ?>

</td>

<td>

<?= round(
($document->file_size ?? 0)
/1024,
1
); ?>

KB

</td>

<td>

<?= $document->valid_until ?: '-'; ?>

</td>

<td>

<?php

switch($status)
{

case 'expired':

echo '<span class="badge badge-expired">Abgelaufen</span>';

break;

case 'warning':

echo '<span class="badge badge-warning">läuft bald ab</span>';

break;

default:

echo '<span class="badge badge-ok">OK</span>';

}

?>

</td>

<td>

<?= date(
'd.m.Y',
strtotime(
$document->created
)
); ?>

</td>

<td>

<a
class="btn-preview"
target="_blank"
href="?option=com_gpsportal&view=documents&vehicle_id=<?= $vehicleId; ?>&preview=<?= $document->id; ?>">

👁️

</a>

<a
class="btn-download"
href="?option=com_gpsportal&view=documents&vehicle_id=<?= $vehicleId; ?>&download=<?= $document->id; ?>">

⬇️

</a>

<a
class="btn-delete"
href="?option=com_gpsportal&view=documents&vehicle_id=<?= $vehicleId; ?>&delete=<?= $document->id; ?>"
onclick="return confirm('Dokument wirklich löschen?');">

🗑️

</a>

</td>

</tr>

<?php endforeach; ?>

<?php if (empty($documents)): ?>

<tr>

<td colspan="8">

<div class="signature-box">

Noch keine Dokumente vorhanden.

</div>

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

<?php endif; ?>

</div>