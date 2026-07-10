<?php

defined('_JEXEC') or die;

require_once dirname(__DIR__) . '/navigation.php';
?>

<div class="container-fluid">

<h1>Neue Tracker</h1>

<table class="table table-striped">

<thead>
<tr>
    <th>ID</th>
    <th>IP</th>
    <th>Erste Sichtung</th>
</tr>
</thead>

<tbody>

<?php foreach ($this->devices as $device): ?>

<tr>

<td>
    <?php echo htmlspecialchars(
        $device['unique_id']
    ); ?>
</td>

<td>
    <?php echo htmlspecialchars(
        $device['source_ip']
    ); ?>
</td>

<td>
    <?php echo htmlspecialchars(
        $device['first_seen']
    ); ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>
