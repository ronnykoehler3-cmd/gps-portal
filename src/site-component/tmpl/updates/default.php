<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$escape = static function (
    mixed $value
): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$version = $this->versionInfo;
$status = $this->systemStatus;
$updateCheck = $this->updateCheck;
$backupResult = $this->backupResult;

$channelLabels = [
    'development' => 'Development',
    'beta' => 'Beta',
    'stable' => 'Stable',
];

$channel = (string) (
    $version['channel']
    ?? 'development'
);

$channelLabel =
    $channelLabels[$channel]
    ?? ucfirst($channel);

$yesNo = static function (
    bool $value
): string {
    return $value
        ? 'OK'
        : 'Nicht bereit';
};

$checkUrl = Route::_(
    'index.php?option=com_gpsportal&view=updates&check=1'
);

?>

<style>
.update-page {
    display: grid;
    gap: 18px;
}

.update-header,
.update-card {
    background: #081327;
    border: 1px solid rgba(59, 130, 246, .18);
    border-radius: 18px;
    padding: 20px;
}

.update-header h1 {
    margin: 0 0 8px;
}

.update-header p {
    margin: 0;
    color: #94a3b8;
}

.update-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.update-label {
    color: #94a3b8;
    font-size: 13px;
    margin-bottom: 8px;
}

.update-value {
    color: #fff;
    font-size: 24px;
    font-weight: 700;
}

.update-status-list {
    display: grid;
    gap: 10px;
}

.update-status-row {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, .07);
}

.status-ok {
    color: #86efac;
    font-weight: 700;
}

.status-warning {
    color: #facc15;
    font-weight: 700;
}

.status-update {
    color: #60a5fa;
    font-weight: 700;
}

.update-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.update-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 18px;
    border: 0;
    border-radius: 9px;
    background: #2563eb;
    color: #fff !important;
    font-weight: 700;
    text-decoration: none;
}

.update-button[disabled] {
    cursor: not-allowed;
    opacity: .5;
}

.update-note {
    color: #facc15;
    margin-bottom: 0;
}

.update-message {
    padding: 16px;
    border-radius: 12px;
    background: rgba(37, 99, 235, .12);
    border: 1px solid rgba(96, 165, 250, .3);
}

.update-error {
    background: rgba(220, 38, 38, .12);
    border-color: rgba(248, 113, 113, .3);
    color: #fca5a5;
}

.changelog {
    margin: 12px 0 0;
    padding-left: 22px;
    line-height: 1.8;
    color: #cbd5e1;
}

@media (max-width: 1000px) {
    .update-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="update-page">

    <div class="update-header">
        <h1>GPS-Portal Updates</h1>

        <p>
            Versionsverwaltung und Systemstatus
            fuer kuenftige Portal-Updates.
        </p>
    </div>

    <div class="update-grid">

        <div class="update-card">
            <div class="update-label">
                Installierte Version
            </div>

            <div class="update-value">
                <?php
                echo $escape(
                    $version['version']
                    ?? 'Unbekannt'
                );
                ?>
            </div>
        </div>

        <div class="update-card">
            <div class="update-label">
                Build
            </div>

            <div class="update-value">
                <?php
                echo $escape(
                    $version['build']
                    ?? 'Unbekannt'
                );
                ?>
            </div>
        </div>

        <div class="update-card">
            <div class="update-label">
                Updatekanal
            </div>

            <div class="update-value">
                <?php echo $escape($channelLabel); ?>
            </div>
        </div>

        <div class="update-card">
            <div class="update-label">
                Projekt
            </div>

            <div class="update-value">
                <?php
                echo $escape(
                    $version['project']
                    ?? 'GPS-Portal'
                );
                ?>
            </div>
        </div>

        <div class="update-card">
            <div class="update-label">
                Release-Datum
            </div>

            <div class="update-value">
                <?php
                echo $escape(
                    $version['released_at']
                    ?? 'Unbekannt'
                );
                ?>
            </div>
        </div>

        <div class="update-card">
            <div class="update-label">
                Update-Server
            </div>

            <div class="update-value">
                <?php
                echo !empty(
                    $status[
                        'update_server_configured'
                    ]
                )
                    ? 'Konfiguriert'
                    : 'Lokaler Test';
                ?>
            </div>
        </div>

    </div>

    <?php if ($updateCheck !== null): ?>

        <div class="update-card">

            <div class="update-label">
                Ergebnis der Updatepruefung
            </div>

            <?php if (!$updateCheck['success']): ?>

                <div class="update-message update-error">
                    <?php
                    echo $escape(
                        $updateCheck['error']
                    );
                    ?>
                </div>

            <?php else: ?>

                <div class="update-status-row">
                    <span>Verfuegbare Version</span>

                    <span class="<?php
                    echo $updateCheck['update_available']
                        ? 'status-update'
                        : 'status-ok';
                    ?>">
                        <?php
                        echo $escape(
                            $updateCheck[
                                'available_version'
                            ]
                        );
                        ?>
                    </span>
                </div>

                <div class="update-status-row">
                    <span>Status</span>

                    <span class="<?php
                    echo $updateCheck['update_available']
                        ? 'status-update'
                        : 'status-ok';
                    ?>">
                        <?php
                        echo $updateCheck['update_available']
                            ? 'Update verfuegbar'
                            : 'System aktuell';
                        ?>
                    </span>
                </div>
<div class="update-status-row">
    <span>Updatepaket vorhanden</span>

    <span class="<?php
    echo !empty(
        $updateCheck['package_exists']
    )
        ? 'status-ok'
        : 'status-warning';
    ?>">
        <?php
        echo !empty(
            $updateCheck['package_exists']
        )
            ? 'OK'
            : 'Fehlt';
        ?>
    </span>
</div>

<div class="update-status-row">
    <span>SHA-256-Pruefsumme</span>

    <span class="<?php
    echo !empty(
        $updateCheck['checksum_valid']
    )
        ? 'status-ok'
        : 'status-warning';
    ?>">
        <?php
        echo !empty(
            $updateCheck['checksum_valid']
        )
            ? 'Gueltig'
            : 'Ungueltig';
        ?>
    </span>
</div>

<div class="update-status-row">
    <span>ZIP-Paketstruktur</span>

    <span class="<?php
    echo !empty(
        $updateCheck['zip_valid']
    )
        ? 'status-ok'
        : 'status-warning';
    ?>">
        <?php
        echo !empty(
            $updateCheck['zip_valid']
        )
            ? 'Gueltig'
            : 'Ungueltig';
        ?>
    </span>
</div>
                <div class="update-status-row">
                    <span>Letzte Pruefung</span>

                    <span>
                        <?php
                        echo $escape(
                            $updateCheck['checked_at']
                        );
                        ?>
                    </span>
                </div>

                <?php if (!empty($updateCheck['changelog'])): ?>

                    <div class="update-label" style="margin-top:18px;">
                        Changelog
                    </div>

                    <ul class="changelog">
                        <?php
                        foreach (
                            $updateCheck['changelog']
                            as $entry
                        ):
                        ?>
                            <li>
                                <?php echo $escape($entry); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                <?php endif; ?>

            <?php endif; ?>

        </div>

    <?php endif; ?>

    <?php if ($backupResult !== null): ?>

        <div class="update-card">

            <div class="update-label">
                Backup-Ergebnis
            </div>

            <?php if (!empty($backupResult['success'])): ?>

                <div class="update-message">

                    <strong>
                        Backup erfolgreich erstellt
                    </strong>

                    <div class="update-status-row">
                        <span>Datei</span>

                        <span class="status-ok">
                            <?php
                            echo $escape(
                                $backupResult['filename']
                                ?? ''
                            );
                            ?>
                        </span>
                    </div>

                    <div class="update-status-row">
                        <span>Groesse</span>

                        <span>
                            <?php
                            echo $escape(
                                number_format(
                                    (
                                        (int) (
                                            $backupResult[
                                                'size_bytes'
                                            ] ?? 0
                                        )
                                    ) / 1024 / 1024,
                                    2,
                                    ',',
                                    '.'
                                )
                            );
                            ?> MB
                        </span>
                    </div>

                    <div class="update-status-row">
                        <span>Datenbanktabellen</span>

                        <span>
                            <?php
                            echo $escape(
                                $backupResult[
                                    'table_count'
                                ] ?? 0
                            );
                            ?>
                        </span>
                    </div>

                    <div class="update-status-row">
                        <span>Erstellt</span>

                        <span>
                            <?php
                            echo $escape(
                                $backupResult[
                                    'created_at'
                                ] ?? ''
                            );
                            ?>
                        </span>
                    </div>

                    <div class="update-status-row">
                        <span>SHA-256</span>

                        <span style="word-break:break-all;">
                            <?php
                            echo $escape(
                                $backupResult[
                                    'sha256'
                                ] ?? ''
                            );
                            ?>
                        </span>
                    </div>

                </div>

            <?php else: ?>

                <div class="update-message update-error">
                    <?php
                    echo $escape(
                        $backupResult['error']
                        ?? 'Backup fehlgeschlagen.'
                    );
                    ?>
                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

    <div class="update-card">

        <div class="update-label">
            Systempruefung
        </div>

        <div class="update-status-list">

            <div class="update-status-row">
                <span>PHP-Version</span>

                <span class="<?php
                echo !empty(
                    $status['php_supported']
                )
                    ? 'status-ok'
                    : 'status-warning';
                ?>">
                    <?php
                    echo $escape(
                        $status['php_version']
                        ?? 'Unbekannt'
                    );
                    ?>
                </span>
            </div>

            <div class="update-status-row">
                <span>Datenbankverbindung</span>

                <span class="<?php
                echo !empty($status['database'])
                    ? 'status-ok'
                    : 'status-warning';
                ?>">
                    <?php
                    echo $yesNo(
                        !empty($status['database'])
                    );
                    ?>
                </span>
            </div>

            <div class="update-status-row">
                <span>Komponentenordner beschreibbar</span>

                <span class="<?php
                echo !empty(
                    $status['component_writable']
                )
                    ? 'status-ok'
                    : 'status-warning';
                ?>">
                    <?php
                    echo $yesNo(
                        !empty(
                            $status[
                                'component_writable'
                            ]
                        )
                    );
                    ?>
                </span>
            </div>

            <div class="update-status-row">
                <span>Backup-/Storage-Ordner</span>

                <span class="<?php
                echo !empty(
                    $status['backup_directory']
                )
                    ? 'status-ok'
                    : 'status-warning';
                ?>">
                    <?php
                    echo $yesNo(
                        !empty(
                            $status[
                                'backup_directory'
                            ]
                        )
                    );
                    ?>
                </span>
            </div>

        </div>

    </div>

    <div class="update-card">

        <div class="update-label">
            Updateaktionen
        </div>

        <div class="update-actions">

            <a
                href="<?php echo $escape($checkUrl); ?>"
                class="update-button"
            >
                Nach Updates suchen
            </a>

            <form
                method="post"
                action="<?php
                echo $escape(
                    Route::_(
                        'index.php?option=com_gpsportal&view=updates'
                    )
                );
                ?>"
                style="display:inline-flex;"
                onsubmit="return confirm('Jetzt ein vollstaendiges GPS-Portal-Backup erstellen?');"
            >
                <input
                    type="hidden"
                    name="update_action"
                    value="backup"
                >

                <button
                    type="submit"
                    class="update-button"
                >
                    Backup erstellen
                </button>

                <?php
                echo HTMLHelper::_(
                    'form.token'
                );
                ?>
            </form>

            <button
                type="button"
                class="update-button"
                disabled
            >
                Update installieren
            </button>

            <button
                type="button"
                class="update-button"
                disabled
            >
                Rollback
            </button>

        </div>

        <p class="update-note">
            Die Updatepruefung arbeitet derzeit mit
            einem lokalen Test-Manifest.
        </p>

    </div>

</div>