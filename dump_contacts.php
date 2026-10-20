<?php
require_once __DIR__ . '/src/compat.php';

$token    = wa_env('EVOLUTION_API_KEY', '');
$server   = rtrim(wa_env('EVOLUTION_SERVER_URL', 'http://localhost:8080'), '/');
$instance = wa_env('EVOLUTION_INSTANCE_NAME', 'atendimento');

if (empty($token)) {
    die("[!] EVOLUTION_API_KEY não configurada no .env nem no banco.\n");
}

$url = "{$server}/contact/find/{$instance}";
$headers = ['Content-Type: application/json', 'apikey: ' . $token];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$res = curl_exec($ch);
curl_close($ch);

file_put_contents(__DIR__ . '/contacts_dump.json', $res);
echo "Dumped to contacts_dump.json\n";
