<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

$app  = Factory::getApplication();
$user = $app->getIdentity();
$option = $app->input->getCmd('option');
$view   = $app->input->getCmd('view');

$showPublicPage =
(
    $option === 'com_gpsportal'
    &&
    in_array(
        $view,
        [
            'impressum',
            'datenschutz'
        ]
    )
);


$this->addStyleSheet(
    $this->baseurl . '/templates/tkgpsportal/css/portal.css'
);
?>
<!DOCTYPE html>
<html lang="de">
<head>

    <jdoc:include type="metas" />
    <jdoc:include type="styles" />
    <jdoc:include type="scripts" />

</head>
<body>

<?php if ($user->guest && !$showPublicPage) : ?>

<div class="layout">

    <!-- LINKS -->

    <aside class="left">

        <img
            src="/images/gpsportal/logo.png"
            class="logo"
            alt="GPS Portal"
        >

        <h1>
            <span>GPS</span> PORTAL
        </h1>

        <p class="sub">
            Intelligente Lösungen für Ihre Flotte
        </p>

<div class="menu-card">
    <h3>Live Ortung</h3>
    <p>Standorte Ihrer Fahrzeuge in Echtzeit.</p>
</div>

<div class="menu-card">
    <h3>Fahrtenbuch</h3>
    <p>Automatische Dokumentation aller Fahrten.</p>
</div>

<div class="menu-card">
    <h3>Auswertungen</h3>
    <p>Berichte und Statistiken auf Knopfdruck.</p>
</div>

<div class="menu-card">
    <h3>Wartung</h3>
    <p>Serviceintervalle und Termine im Blick.</p>
</div>

<div class="menu-card">
    <h3>Benutzerverwaltung</h3>
    <p>Rollen und Zugriffe zentral verwalten.</p>
</div>
    </aside>

    <!-- MITTE -->

    <main class="hero">

        <div class="gps-layer">

            <div class="gps-pin pin1"></div>
            <div class="gps-pin pin2"></div>
            <div class="gps-pin pin3"></div>
            <div class="gps-pin pin4"></div>
            <div class="gps-pin pin5"></div>
            <div class="gps-pin pin6"></div>

        </div>

        <div class="hero-overlay">

            <div class="hero-badge">
                GPS Tracking · Flottenmanagement · Telematik
            </div>

<h2>
    Volle Kontrolle über Ihre Flotte
</h2>

<p>
    Live-Ortung, digitales Fahrtenbuch,
    Auswertungen und Wartungsmanagement
    auf einer zentralen Plattform.
</p>
<div class="stats">

    <div class="stat">
        <span>14</span>
        Fahrzeuge
    </div>

    <div class="stat">
        <span>8</span>
        Online
    </div>

    <div class="stat">
        <span>128.547 km</span>
        Seit Plattformstart
    </div>

    <div class="stat">
        <span>24/7</span>
        Überwachung
    </div>

</div>
        </div>

    </main>

    <!-- LOGIN -->

    <aside class="right">

        <div class="login-card">

            <h2>
                Willkommen zurück!
            </h2>

            <p>
                Bitte melden Sie sich an,
                um fortzufahren.
            </p>

<form
    method="post"
    action="/index.php?Itemid=125"
>
                <label>
                    Benutzername
                </label>

                <input
                    class="input"
                    type="text"
                    name="username"
                    autocomplete="username"
                    required
                >

                <label>
                    Passwort
                </label>

                <input
                    class="input"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

                <label class="remember">

                    <input
                        type="checkbox"
                        name="remember"
                        value="yes"
                    >

                    Angemeldet bleiben

                </label>

                <button
                    class="btn"
                    type="submit"
                >
                    ANMELDEN
                </button>

<div class="login-links">

    <a href="/index.php?option=com_users&view=reset">
        Passwort vergessen?
    </a>

</div>

<input
    type="hidden"
    name="option"
    value="com_users"
>

<input
    type="hidden"
    name="task"
    value="user.login"
>

<input
    type="hidden"
    name="return"
    value="<?php echo base64_encode('index.php?Itemid=127'); ?>"
>

<?php echo HTMLHelper::_('form.token'); ?>
            </form>

        </div>

    </aside>

</div>
<footer class="footer">

    <span>Sicher & Verschlüsselt</span>
    <span>DSGVO Konform</span>
    <span>Made in Germany</span>

<a href="/index.php?option=com_gpsportal&view=datenschutz">
        Datenschutz
    </a>

<a href="/index.php?option=com_gpsportal&view=impressum">
    Impressum
</a>
    <div class="footer-copy">
        © 2026 TK-Kundendienst • Powered by DAS_Fräulein*innen*außen
    </div>

</footer>
<?php else : ?>

<jdoc:include type="component" />

<?php endif; ?>
</body>
</html>
