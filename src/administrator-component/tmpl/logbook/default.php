<?php

defined('_JEXEC') or die;

require_once dirname(__DIR__) . '/navigation.php';
?>

<div class="container-fluid">

    <h1>Fahrtenbuch</h1>
<div class="card mb-3">

    <div class="card-header">
        Monatsübersicht
    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-4">

                <div class="alert alert-success">

                    <strong>Geschäftlich</strong><br>

                    <?php echo $this->summary['Geschäftlich'] ?? 0; ?>
                    km

                </div>

            </div>

            <div class="col-md-4">

                <div class="alert alert-warning">

                    <strong>Privat</strong><br>

                    <?php echo $this->summary['Privat'] ?? 0; ?>
                    km

                </div>

            </div>

            <div class="col-md-4">

                <div class="alert alert-info">

                    <strong>Arbeitsweg</strong><br>

                    <?php echo $this->summary['Arbeitsweg'] ?? 0; ?>
                    km

                </div>

            </div>

        </div>

    </div>

</div>
    <?php if (!empty($this->message)) : ?>

        <div class="alert alert-success">
            <?php echo htmlspecialchars($this->message); ?>
        </div>

    <?php endif; ?>

    <?php if (!empty($this->groups)) : ?>

        <div class="card mb-3">

            <div class="card-header">
                Sammelfahrten
            </div>

            <div class="card-body">

                <div class="accordion" id="tripGroups">

                    <?php foreach ($this->groups as $group): ?>

                        <div class="accordion-item">

<h2
    class="accordion-header"
    id="heading<?php echo (int)$group['id']; ?>">

    <div class="d-flex align-items-center w-100">

        <button
            class="accordion-button collapsed"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#group<?php echo (int)$group['id']; ?>"
            style="flex:1;">

            #<?php echo (int)$group['id']; ?>

            &nbsp;|&nbsp;

            <?php echo htmlspecialchars($group['name']); ?>

            &nbsp;|&nbsp;

            <?php echo $group['distance']; ?> km

            &nbsp;|&nbsp;

            <?php echo $group['duration']; ?> Min.

        </button>

<a
    href="index.php?option=com_gpsportal&view=logbook&action=pdf&group_id=<?php echo (int)$group['id']; ?>"
    class="btn btn-success btn-sm ms-2">

    PDF

</a>

<a
    href="index.php?option=com_gpsportal&view=logbook&action=deletegroup&group_id=<?php echo (int)$group['id']; ?>"
    class="btn btn-danger btn-sm ms-2"
    onclick="return confirm('Sammelfahrt wirklich löschen?');">

    Löschen

</a>
    </div>

</h2>
                            <div
                                id="group<?php echo (int)$group['id']; ?>"
                                class="accordion-collapse collapse">

                                <div class="accordion-body">

                                    <table class="table table-sm table-bordered">

                                        <thead>

                                            <tr>
						<th>Startadresse</th>
						<th>Zieladresse</th>
						<th>KM</th>
						<th>Dauer</th>
						<th>Start KM-Stand</th>
						<th>End KM-Stand</th>
						<th>Typ</th>
						<th>Typ</th>
						<th>Kunde</th>
						<th>Auftragsnummer</th>
						<th>Bemerkung</th>
						<th>Aktion</th>
                                            </tr>

                                        </thead>

                                        <tbody>

                                        <?php foreach ($group['items'] as $item): ?>

                                            <tr>

                                                <td>
                                                    <?php echo htmlspecialchars($item['start_address'] ?? '-'); ?>
                                                </td>

                                                <td>
                                                    <?php echo htmlspecialchars($item['end_address'] ?? '-'); ?>
                                                </td>

                                                <td>
                                                    <?php echo round(($item['distance'] ?? 0) / 1000, 2); ?>
                                                    km
                                                </td>

                                                <td>
                                                    <?php echo round(($item['duration'] ?? 0) / 60000); ?>
                                                    Min.
                                                </td>
<td>
    <?php echo (int)($item['start_odometer'] ?? 0); ?>
</td>

<td>
    <?php echo (int)($item['end_odometer'] ?? 0); ?>
</td>

<td>

<td>

<form method="post">

<input type="hidden" name="option" value="com_gpsportal">
<input type="hidden" name="view" value="logbook">
<input type="hidden" name="action" value="savetrip">
<input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">

<select
    name="trip_type"
    class="form-select form-select-sm">

    <option value="Geschäftlich"
        <?php echo (($item['trip_type'] ?? 'Geschäftlich') === 'Geschäftlich') ? 'selected' : ''; ?>>
        Geschäftlich
    </option>

    <option value="Privat"
        <?php echo (($item['trip_type'] ?? '') === 'Privat') ? 'selected' : ''; ?>>
        Privat
    </option>

    <option value="Arbeitsweg"
        <?php echo (($item['trip_type'] ?? '') === 'Arbeitsweg') ? 'selected' : ''; ?>>
        Arbeitsweg
    </option>

</select>

</td>

<td>

<input
    type="text"
    name="customer"
    class="form-control form-control-sm"
    value="<?php echo htmlspecialchars($item['customer'] ?? ''); ?>">

</td>

<td>

<input
    type="text"
    name="order_number"
    class="form-control form-control-sm"
    value="<?php echo htmlspecialchars($item['order_number'] ?? ''); ?>">

</td>

<td>

<textarea
    name="note"
    class="form-control form-control-sm"
    rows="2"><?php echo htmlspecialchars($item['note'] ?? ''); ?></textarea>

</td>

<td>

<button
    type="submit"
    class="btn btn-primary btn-sm">

    Speichern

</button>

</form>

</td>
                                            </tr>

                                        <?php endforeach; ?>

                                        <tr class="table-primary">

                                            <td colspan="9">
                                                <strong>Gesamt</strong>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?php echo $group['distance']; ?> km
                                                </strong>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?php echo $group['duration']; ?> Min.
                                                </strong>
                                            </td>

                                        </tr>

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

    <?php endif; ?>

    <div class="card mb-3">

        <div class="card-body">

            <form method="get">

                <input
                    type="hidden"
                    name="option"
                    value="com_gpsportal">

                <input
                    type="hidden"
                    name="view"
                    value="logbook">

                <div class="row">

                    <div class="col-md-4">

                        <label class="form-label">
                            Fahrzeug
                        </label>

                        <select
                            class="form-select"
                            name="deviceId">

                            <?php foreach ($this->devices as $device): ?>

                                <option
                                    value="<?php echo (int)$device['id']; ?>"
                                    <?php echo ((int)$device['id'] === (int)$this->deviceId) ? 'selected' : ''; ?>>

                                    <?php echo htmlspecialchars($device['name']); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-3">

                        <label class="form-label">
                            Von
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="from"
                            value="<?php echo $this->from; ?>">

                    </div>

                    <div class="col-md-3">

                        <label class="form-label">
                            Bis
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="to"
                            value="<?php echo $this->to; ?>">

                    </div>

                    <div class="col-md-2">

                        <label class="form-label">
                            &nbsp;
                        </label>

                        <button
                            class="btn btn-primary w-100"
                            type="submit">

                            Laden

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <div class="card">

        <div class="card-header">
            Fahrtenbuch
        </div>

        <div class="card-body">

            <form method="post">

                <input
                    type="hidden"
                    name="option"
                    value="com_gpsportal">

                <input
                    type="hidden"
                    name="view"
                    value="logbook">

                <input
                    type="hidden"
                    name="deviceId"
                    value="<?php echo (int)$this->deviceId; ?>">

                <input
                    type="hidden"
                    name="from"
                    value="<?php echo htmlspecialchars($this->from); ?>">

                <input
                    type="hidden"
                    name="to"
                    value="<?php echo htmlspecialchars($this->to); ?>">

                <table class="table table-striped">

                    <thead>

                        <tr>

                            <th width="50">

                                <input
                                    type="checkbox"
                                    onclick="document.querySelectorAll('.trip-check').forEach(c => c.checked = this.checked);">

                            </th>

                            <th>Datum</th>
                            <th>Startadresse</th>
                            <th>Zieladresse</th>
                            <th>Kilometer</th>
                            <th>Dauer</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($this->trips as $trip): ?>

                        <?php

                        $start =
                            new DateTime(
                                $trip['startTime']
                            );

                        $start->setTimezone(
                            new DateTimeZone(
                                'Europe/Berlin'
                            )
                        );

                        ?>

                        <tr>

                            <td>

                                <input
                                    class="trip-check"
                                    type="checkbox"
                                    name="selectedTrips[]"
                                    value="<?php echo ($trip['startPositionId'] ?? 0); ?>_<?php echo ($trip['endPositionId'] ?? 0); ?>">

                            </td>

                            <td>
                                <?php echo $start->format('d.m.Y H:i'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($trip['startAddress'] ?? '-'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($trip['endAddress'] ?? '-'); ?>
                            </td>

                            <td>
                                <?php echo round(($trip['distance'] ?? 0) / 1000, 2); ?>
                                km
                            </td>

                            <td>
                                <?php echo round(($trip['duration'] ?? 0) / 60000); ?>
                                Min.
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

                <button
                    type="submit"
                    class="btn btn-success">

                    Sammelfahrt erstellen

                </button>

            </form>

        </div>

    </div>

</div>
