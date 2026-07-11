<?php

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => 'https://traccar.tk-kundendienst.de/api/session',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'email' => 'admin@tk-kundendienst.de',
        'password' => 'Nico192570##Nico192570##'
    ]),
    CURLOPT_HEADER => true
]);

$response = curl_exec($ch);

echo "<pre>";
echo htmlspecialchars($response);
echo "</pre>";
