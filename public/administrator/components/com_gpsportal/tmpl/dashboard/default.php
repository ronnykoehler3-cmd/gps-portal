<?php

defined('_JEXEC') or die;

require_once dirname(__DIR__) . '/navigation.php';
?>

<div class="container-fluid">

    <h1>GPS Portal Dashboard</h1>

    <div class="row mb-4">

        <div class="col-md-3">

            <div class="card text-center">

                <div class="card-body">

                    <h5>🚗 Fahrzeuge</h5>

                    <h2>
                        <?php echo $this->deviceCount; ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card text-center border-success">

                <div class="card-body">

                    <h5>🟢 Online</h5>

                    <h2>
                        <?php echo $this->onlineCount; ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card text-center border-danger">

                <div class="card-body">

                    <h5>🔴 Offline</h5>

                    <h2>
                        <?php echo $this->offlineCount; ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card text-center border-warning">

                <div class="card-body">

                    <h5>📋 Projektstatus</h5>

                    <h2>
                        <?php echo $this->todoProgress; ?>%
                    </h2>

                </div>

            </div>

        </div>

    </div>

    <div class="card">

        <div class="card-header">
            Traccar Geräte
        </div>

        <div class="card-body">

            <table class="table table-striped">

                <thead>

                    <tr>
                        <th>Name</th>
                        <th>Unique-ID</th>
                        <th>Status</th>
                        <th>Letztes Update</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($this->devices as $device): ?>

                    <tr>

                        <td>
                            <?php echo $device['name'] ?? ''; ?>
                        </td>

                        <td>
                            <?php echo $device['uniqueId'] ?? ''; ?>
                        </td>

                        <td>

                            <?php

                            $status =
                                $device['status']
                                ?? 'unknown';

                            if ($status === 'online')
                            {
                                echo '<span class="badge bg-success">ONLINE</span>';
                            }
                            elseif ($status === 'offline')
                            {
                                echo '<span class="badge bg-danger">OFFLINE</span>';
                            }
                            else
                            {
                                echo '<span class="badge bg-warning">UNBEKANNT</span>';
                            }

                            ?>

                        </td>

                        <td>
                            <?php echo $device['lastUpdate'] ?? ''; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>
