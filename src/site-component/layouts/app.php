<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<link
rel="stylesheet"
href="/components/com_gpsportal/media/css/gpsportal-dark.css?v=<?php echo time(); ?>"
>

</head>
<body>

<div class="gps-layout">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="gps-main">

        <?php require __DIR__ . '/header.php'; ?>

        <div class="gps-content">

            <?php echo $content; ?>

        </div>

        <footer class="footer">

            <span>Sicher & Verschlüsselt</span>

            <span>DSGVO Konform</span>

            <span>Made in Germany</span>

<a href="/index.php?option=com_gpsportal&view=datenschutz">
                Datenschutz
            </a>

<a href="/?option=com_gpsportal&view=impressum">
                Impressum
            </a>

            <div class="footer-copy">
                © 2026 TK-Kundendienst • Powered by Das Fräulein*innen außen
            </div>

        </footer>

    </div>

</div>

</body>
</html>
