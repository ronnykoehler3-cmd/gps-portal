<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$user = Factory::getApplication()->getIdentity();

?>

<header class="gps-header">

    <div class="header-left">

        <strong>
            GPS Portal
        </strong>

    </div>

    <div class="header-right">

        Willkommen zurück,
        <strong>
            <?php echo htmlspecialchars($user->name); ?>
        </strong>

    </div>

</header>
