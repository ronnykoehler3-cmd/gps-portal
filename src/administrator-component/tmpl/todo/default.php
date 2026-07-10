<?php

defined('_JEXEC') or die;

require_once dirname(__DIR__) . '/navigation.php';

$total = count($this->todos);
$done = 0;

foreach ($this->todos as $todo)
{
    if ($todo['status'])
    {
        $done++;
    }
}

$progress = $total > 0
    ? round(($done / $total) * 100)
    : 0;
?>

<div class="container-fluid">

    <h1>Projekt-ToDo</h1>

    <div class="alert alert-info">
        Fortschritt: <?php echo $progress; ?> %
    </div>

    <table class="table table-striped">

        <thead>
            <tr>
                <th>Status</th>
                <th>Aufgabe</th>
                <th>Priorität</th>
            </tr>
        </thead>

        <tbody>

        <?php foreach ($this->todos as $todo): ?>

            <tr>

                <td>

			<a href="index.php?option=com_gpsportal&view=todo&task=todo.toggle&id=<?php echo (int)$todo['id']; ?>">
                    <?php
                    echo $todo['status']
                        ? '✅'
                        : '⬜';
                    ?>

                    </a>

                </td>

                <td>
                    <?php echo htmlspecialchars($todo['title']); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($todo['priority']); ?>
                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

</div>
