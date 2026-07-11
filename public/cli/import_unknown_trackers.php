<?php

$mysqli = new mysqli(
    'localhost',
    'joomla',
    'Nico192570##Nico192570##',
    'joomla_gps'
);

if ($mysqli->connect_error)
{
    die(
        "DB Fehler: "
        . $mysqli->connect_error
        . PHP_EOL
    );
}

$logFile =
    '/opt/traccar/logs/tracker-server.log';

if (!file_exists($logFile))
{
    die(
        "Logdatei nicht gefunden"
        . PHP_EOL
    );
}

$lines = file($logFile);

foreach ($lines as $line)
{
if (
    strpos($line, 'Unknown device -') === false
)
{
    continue;
}

if (
    !preg_match(
        '/Unknown device - ([0-9]{8,20})/',
        $line,
        $matches
    )
)
{
    continue;
}

$uniqueId = trim($matches[1]);

    $sourceIp = null;

    if (
        preg_match(
            '/\(([0-9\.]+)\)/',
            $line,
            $ipMatches
        )
    )
    {
        $sourceIp =
            $ipMatches[1];
    }

    $stmt =
        $mysqli->prepare(
            "SELECT id
             FROM eusdi_gpsportal_pending_devices
             WHERE unique_id=?"
        );

    $stmt->bind_param(
        's',
        $uniqueId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result->num_rows > 0)
    {
        $mysqli->query(
            "UPDATE eusdi_gpsportal_pending_devices
             SET last_seen=NOW()
             WHERE unique_id='"
             . $mysqli->real_escape_string(
                 $uniqueId
             )
             . "'"
        );

        continue;
    }

    $stmt =
        $mysqli->prepare(
            "INSERT INTO
             eusdi_gpsportal_pending_devices
             (
                unique_id,
                source_ip,
                first_seen,
                last_seen
             )
             VALUES
             (
                ?,
                ?,
                NOW(),
                NOW()
             )"
        );

    $stmt->bind_param(
        'ss',
        $uniqueId,
        $sourceIp
    );

    $stmt->execute();

    echo
        "Neuer Tracker gefunden: "
        . $uniqueId
        . PHP_EOL;
}

$mysqli->close();
