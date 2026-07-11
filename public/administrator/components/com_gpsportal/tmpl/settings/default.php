<?php

defined('_JEXEC') or die;

require_once dirname(__DIR__) . '/navigation.php';
?>
<div class="container-fluid">

    <h1>GPS Portal Einstellungen</h1>

    <form method="post">

        <div class="card">
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label">Traccar URL</label>
                    <input
                        type="text"
                        class="form-control"
                        name="traccar_url"
                        value="<?php echo htmlspecialchars($this->traccarUrl ?? ''); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">API Benutzer</label>
                    <input
                        type="text"
                        class="form-control"
                        name="traccar_user"
                        value="<?php echo htmlspecialchars($this->traccarUser ?? ''); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">API Passwort</label>
                    <input
                        type="password"
                        class="form-control"
                        name="traccar_password"
                        value="<?php echo htmlspecialchars($this->traccarPassword ?? ''); ?>">
                </div>

                <button
                    type="submit"
                    name="action"
                    value="save"
                    class="btn btn-primary">
                    Speichern
                </button>

                <button
                    type="submit"
                    name="action"
                    value="test"
                    class="btn btn-success">
                    Verbindung testen
                </button>

            </div>
        </div>

    </form>

</div>
