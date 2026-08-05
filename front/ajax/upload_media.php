<?php
include('../../../../inc/includes.php');
Session::checkLoginUser();
header('Content-Type: application/json');

$mediaName = $_POST['media_name'] ?? 'arquivo';
$fname     = $_POST['fname']      ?? '';
$ticketId  = (int)($_POST['ticket_id'] ?? 0);

if (!$fname || !$ticketId) {
    echo json_encode(['ok' => false, 'error' => 'Parametros invalidos']);
    exit;
}

// Busca o arquivo na pasta media do plugin
$mediaDir = __DIR__ . '/../../media/';
$found    = null;
foreach (glob($mediaDir . $fname . '*') as $f) {
    if (is_file($f)) { $found = $f; break; }
}

if (!$found) {
    echo json_encode(['ok' => false, 'error' => 'Arquivo nao encontrado: ' . $fname]);
    exit;
}

$realName = $mediaName;
$ext      = pathinfo($found, PATHINFO_EXTENSION);
if ($ext && !str_ends_with($mediaName, '.' . $ext)) {
    $realName = $mediaName . '.' . $ext;
}

// Usa sempre 127.0.0.1 para evitar problemas de IP/DNS
$glpiBase  = 'http://127.0.0.1/glpi';
$appToken  = 'Qy0ocv7BYvG363PY6O4CVSJvlHGAWI34t9Ex93BH';
$userToken = 'RYRtYjeUqLMLzlm3DqhMYxDzC64j97DQz79Oxmq9';

// Inicia sessao
$ch = curl_init("$glpiBase/apirest.php/initSession");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["App-Token: $appToken", "Authorization: user_token $userToken"],
    CURLOPT_TIMEOUT        => 10,
]);
$res     = curl_exec($ch);
$session = json_decode($res, true)['session_token'] ?? '';
curl_close($ch);

if (!$session) {
    echo json_encode(['ok' => false, 'error' => 'Falha na sessao GLPI', 'raw' => $res]);
    exit;
}

// Upload multipart direto do disco
$manifest = json_encode(['input' => ['name' => $realName, 'filename' => [$realName]]]);
$mime     = mime_content_type($found) ?: 'application/octet-stream';

$ch = curl_init("$glpiBase/apirest.php/Document");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ["App-Token: $appToken", "Session-Token: $session"],
    CURLOPT_POSTFIELDS     => [
        'uploadManifest' => $manifest,
        'filename[0]'    => new CURLFile($found, $mime, $realName),
    ],
]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

$docId = $result['id'] ?? 0;
if (!$docId) {
    echo json_encode(['ok' => false, 'error' => 'Falha no upload', 'detail' => $result]);
    exit;
}

// Associa ao ticket
$ch = curl_init("$glpiBase/apirest.php/Document_Item");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ["App-Token: $appToken", "Session-Token: $session", "Content-Type: application/json"],
    CURLOPT_POSTFIELDS     => json_encode(['input' => [
        'documents_id' => $docId,
        'itemtype'     => 'Ticket',
        'items_id'     => $ticketId,
    ]]),
]);
curl_exec($ch);
curl_close($ch);

echo json_encode(['ok' => true, 'document_id' => $docId]);