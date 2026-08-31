<?php
$token = 'ure_jales_evolution_token_2026';
$url = 'http://localhost:8080/contact/find/atendimento';
$headers = ['Content-Type: application/json', 'apikey: ' . $token];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$res = curl_exec($ch);
curl_close($ch);
file_put_contents('contacts_dump.json', $res);
echo "Dumped to contacts_dump.json\n";
