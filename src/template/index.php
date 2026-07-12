<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

$app = Factory::getApplication();
$user = $app->getIdentity();

$option = $app->input->getCmd(
    'option'
);

$view = $app->input->getCmd(
    'view'
);

$showPublicPage = (
    $option === 'com_gpsportal'
    && in_array(
        $view,
        [
            'impressum',
            'datenschutz'
        ],
        true
    )
);

/*
 * Öffentliche Demonstrationswerte
 *
 * Diese Werte sind vollständig fiktiv und besitzen keine Verbindung
 * zu Kundendaten, echten Fahrzeugen oder der Portal-Datenbank.
 *
 * Eine automatische Erhöhung erfolgt jeweils am:
 *
 * - 1. eines Monats
 * - 11. eines Monats
 * - 21. eines Monats
 */

$timezone = new DateTimeZone(
    'Europe/Berlin'
);

$statisticsStart = new DateTimeImmutable(
    '2026-06-01 00:00:00',
    $timezone
);

$today = new DateTimeImmutable(
    'today',
    $timezone
);

$statisticsSteps = 0;

$currentMonth = $statisticsStart
    ->modify('first day of this month');

$lastMonth = $today
    ->modify('first day of this month');

while ($currentMonth <= $lastMonth) {
    foreach ([1, 11, 21] as $updateDay) {
        $updateDate = $currentMonth
            ->setDate(
                (int) $currentMonth->format('Y'),
                (int) $currentMonth->format('m'),
                $updateDay
            )
            ->setTime(
                0,
                0,
                0
            );

        if (
            $updateDate >= $statisticsStart
            && $updateDate <= $today
        ) {
            $statisticsSteps++;
        }
    }

    $currentMonth = $currentMonth
        ->modify('first day of next month');
}

/*
 * Fiktive Ausgangswerte
 *
 * Die ?ffentliche Startseite verwendet ausschlie?lich
 * fiktive Demonstrationswerte.
 */
$baseVehicles = 35;
$baseOnline = 27;
$baseKilometres = 321368;

/*
 * Drei Aktualisierungsschritte ergeben zusammen ungef?hr
 * 15 Prozent Wachstum pro Monat.
 */
$monthlyGrowthFactor = 1.15;

$growthPerStep = pow(
    $monthlyGrowthFactor,
    1 / 3
);

$totalGrowthFactor = pow(
    $growthPerStep,
    $statisticsSteps
);

/*
 * Fahrzeugzahl
 *
 * Mindestens 35 Fahrzeuge.
 */
$fictiveVehicles = max(
    35,
    (int) round(
        $baseVehicles
        * $totalGrowthFactor
    )
);

/*
 * Online-Fahrzeuge
 *
 * Der Ausgangswert w?chst proportional zur Fahrzeugzahl.
 * Zus?tzlich bleibt die Onlinezahl immer kleiner oder
 * gleich der Gesamtzahl.
 */
$fictiveOnline = max(
    1,
    (int) round(
        $baseOnline
        * $totalGrowthFactor
    )
);

$fictiveOnline = min(
    $fictiveOnline,
    $fictiveVehicles
);

/*
 * Kilometer seit Plattformstart
 *
 * Die Kilometer wachsen mit demselben Faktor wie der
 * fiktive Fahrzeugbestand.
 */
$fictiveKilometres = max(
    $baseKilometres,
    (int) round(
        $baseKilometres
        * $totalGrowthFactor
    )
);

$formattedVehicles = number_format(
    $fictiveVehicles,
    0,
    ',',
    '.'
);

$formattedOnline = number_format(
    $fictiveOnline,
    0,
    ',',
    '.'
);

$formattedKilometres = number_format(
    $fictiveKilometres,
    0,
    ',',
    '.'
);

$this->addStyleSheet(
    $this->baseurl
    . '/templates/tkgpsportal/css/portal.css'
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

            <p>
                Standorte Ihrer Fahrzeuge in Echtzeit.
            </p>
        </div>

        <div class="menu-card">
            <h3>Fahrtenbuch</h3>

            <p>
                Automatische Dokumentation aller Fahrten.
            </p>
        </div>

        <div class="menu-card">
            <h3>Auswertungen</h3>

            <p>
                Berichte und Statistiken auf Knopfdruck.
            </p>
        </div>

        <div class="menu-card">
            <h3>Wartung</h3>

            <p>
                Serviceintervalle und Termine im Blick.
            </p>
        </div>

        <div class="menu-card">
            <h3>Benutzerverwaltung</h3>

            <p>
                Rollen und Zugriffe zentral verwalten.
            </p>
        </div>

    </aside>

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
                    <span>
                        <?php echo $formattedVehicles; ?>
                    </span>

                    Fahrzeuge
                </div>

                <div class="stat">
                    <span>
                        <?php echo $formattedOnline; ?>
                    </span>

                    Online
                </div>

                <div class="stat">
                    <span>
                        <?php echo $formattedKilometres; ?> km
                    </span>

                    Seit Plattformstart
                </div>

                <div class="stat">
                    <span>24/7</span>

                    Überwachung
                </div>

            </div>

        </div>

    </main>

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

                    <a
                        href="/index.php?option=com_users&view=reset"
                    >
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
                    value="<?php
                    echo base64_encode(
                        'index.php?Itemid=127'
                    );
                    ?>"
                >

                <?php
                echo HTMLHelper::_(
                    'form.token'
                );
                ?>

            </form>

        </div>

    </aside>

</div>

<footer class="footer">

    <span>
        Sicher & Verschlüsselt
    </span>

    <span>
        DSGVO Konform
    </span>

    <span>
        Made in Germany
    </span>

    <a
        href="/index.php?option=com_gpsportal&view=datenschutz"
    >
        Datenschutz
    </a>

    <a
        href="/index.php?option=com_gpsportal&view=impressum"
    >
        Impressum
    </a>

    <div class="footer-copy">
        © 2026 TK-Kundendienst
        • Powered by Das Fräulein*innen außen
    </div>

</footer>

<?php else : ?>

<jdoc:include type="component" />

<?php endif; ?>

</body>
</html>