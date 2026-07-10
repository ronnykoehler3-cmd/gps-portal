<?php
defined('_JEXEC') or die;
use Joomla\CMS\Factory;

$app  = Factory::getApplication();
$user = $app->getIdentity();

if (!$user->guest)
{
    header(
          'Location: index.php?option=com_gpsportal&view=livemap'    );
    exit;
}
?>

<style>

body{
    margin:0;
    padding:0;
    font-family:Segoe UI,Arial,sans-serif;
    background:#f4f7fb;
}

.gps-wrapper{
    min-height:100vh;
    display:flex;
}

.gps-left{
    flex:1;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
    color:#fff;
    padding:80px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.gps-left h1{
    font-size:64px;
    margin-bottom:15px;
}

.gps-left h2{
    font-size:24px;
    font-weight:400;
    margin-bottom:50px;
}

.feature{
    margin:12px 0;
    font-size:20px;
}

.stats{
    display:flex;
    gap:20px;
    margin-top:50px;
}

.stat{
    background:rgba(255,255,255,.15);
    border-radius:15px;
    padding:20px;
    min-width:140px;
}

.stat strong{
    display:block;
    font-size:30px;
}

.gps-right{
    width:500px;
    background:#fff;
    display:flex;
    justify-content:center;
    align-items:center;
    box-shadow:-5px 0 30px rgba(0,0,0,.08);
}

.login-box{
    width:360px;
}

.login-box h3{
    text-align:center;
    font-size:40px;
    margin-bottom:15px;
}

.login-box p{
    text-align:center;
    color:#666;
    margin-bottom:35px;
}

@media(max-width:1100px){

    .gps-wrapper{
        flex-direction:column;
    }

    .gps-right{
        width:100%;
        padding:40px 0;
    }

    .gps-left{
        padding:40px;
    }
}

</style>

<div class="gps-wrapper">

    <div class="gps-left">

        <h1>GPS Portal</h1>

        <h2>
            Intelligente Lösungen für Ihre Flotte
        </h2>

        <div class="feature">✓ Live Ortung</div>
        <div class="feature">✓ Fahrtenbuch</div>
        <div class="feature">✓ Sammelfahrten</div>
        <div class="feature">✓ Berichte & Auswertungen</div>
        <div class="feature">✓ Wartungsmanagement</div>
        <div class="feature">✓ Benutzerverwaltung</div>

        <div class="stats">

            <div class="stat">
                <strong>24/7</strong>
                Verfügbar
            </div>

            <div class="stat">
                <strong>LIVE</strong>
                Tracking
            </div>

            <div class="stat">
                <strong>GPS</strong>
                Portal
            </div>

        </div>

    </div>

    <div class="gps-right">

        <div class="login-box">

<h3>Anmelden</h3>

<p>
    Sicherer Zugang zum GPS Portal
</p>

<form action="/index.php?option=com_users&task=user.login" method="post">

    <div style="margin-bottom:15px;">
        <input
            type="text"
            name="username"
            placeholder="Benutzername"
            required
            style="
                width:100%;
                padding:12px;
                border:1px solid #ddd;
                border-radius:8px;
            "
        >
    </div>

    <div style="margin-bottom:15px;">
        <input
            type="password"
            name="password"
            placeholder="Passwort"
            required
            style="
                width:100%;
                padding:12px;
                border:1px solid #ddd;
                border-radius:8px;
            "
        >
    </div>

    <button
        type="submit"
        style="
            width:100%;
            padding:12px;
            background:#2563eb;
            color:white;
            border:none;
            border-radius:8px;
            cursor:pointer;
        "
    >
        Anmelden
    </button>

    <input
        type="hidden"
        name="return"
        value="<?php echo base64_encode('index.php?Itemid=125'); ?>"
    >

    <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>

</form>
        </div>

    </div>

</div>
