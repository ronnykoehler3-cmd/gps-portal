<?php

defined('_JEXEC') or die;

require_once dirname(__DIR__) . '/navigation.php';
?>

<div class="container-fluid">

    <h1>Fahrzeug Historie</h1>
<div class="row mb-3">

    <div class="col-md-3">

        <div class="card border-primary">

            <div class="card-body text-center">

                <h5>🚗 Fahrten</h5>

                <h2>
                    <?php echo $this->tripCount; ?>
                </h2>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card border-success">

            <div class="card-body text-center">

                <h5>🛣 Kilometer</h5>

                <h2>
                    <?php echo $this->totalDistance; ?>
                    km
                </h2>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card border-warning">

            <div class="card-body text-center">

                <h5>⏱ Fahrzeit</h5>

                <h2>
                    <?php echo round($this->totalMinutes / 60, 1); ?>
                    h
                </h2>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card border-danger">

            <div class="card-body text-center">

                <h5>🏆 Längste Fahrt</h5>

                <h2>
                    <?php echo $this->longestTrip; ?>
                    Min.
                </h2>

            </div>

        </div>

    </div>

</div>
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
                    value="history">

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
<div class="card mb-3">

    <div class="card-header">
        Erkannte Fahrten
    </div>

    <div class="card-body">

        <table class="table table-striped">

            <thead>

<tr>
    <th>#</th>
    <th>Startadresse</th>
    <th>Zieladresse</th>
    <th>Strecke</th>
    <th>Dauer</th>
</tr>
            </thead>

<tbody>

<?php $tripNumber = 1; ?>

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

$stop =
    new DateTime(
        $trip['endTime']
    );

$stop->setTimezone(
    new DateTimeZone(
        'Europe/Berlin'
    )
);
                ?>

<tr>

    <td>
        #<?php echo $tripNumber; ?>
    </td>

    <td>

        <?php

        echo htmlspecialchars(
            $trip['startAddress']
            ?? '-'
        );

        ?>

        <br>

        <small>

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

            echo $start->format(
                'd.m.Y H:i'
            );

            ?>

        </small>

    </td>

    <td>

        <?php

        echo htmlspecialchars(
            $trip['endAddress']
            ?? '-'
        );

        ?>

        <br>

        <small>

            <?php

            $stop =
                new DateTime(
                    $trip['endTime']
                );

            $stop->setTimezone(
                new DateTimeZone(
                    'Europe/Berlin'
                )
            );

            echo $stop->format(
                'd.m.Y H:i'
            );

            ?>

        </small>

    </td>

    <td>

        <?php

        echo round(
            ($trip['distance'] ?? 0)
            / 1000,
            2
        );

        ?>

        km

    </td>

    <td>

        <?php

        echo round(
            ($trip['duration'] ?? 0)
            / 60000
        );

        ?>

        Min.

    </td>

</tr>

<?php $tripNumber++; ?>
            <?php endforeach; ?>

            </tbody>

        </table>
<div class="alert alert-info mt-3">

    <strong>Auswertung:</strong>

    <?php echo $this->tripCount; ?>
    Fahrten erkannt.

    Gesamtfahrzeit:

    <?php echo round($this->totalMinutes / 60, 1); ?>
    Stunden.

</div>
</div>

    </div>

</div>

</div>
