<?php

// Webhook público para recebimento de eventos da EvolutionAPI (mesmo padrão do Protocolo/front/verify.php)
define('GLPI_ROOT', dirname(__DIR__, 2));
include_once(GLPI_ROOT . "/inc/includes.php");

header('Content-Type: application/json');

$content = file_get_contents('php://input');
$payload = json_decode($content, true);

if (!$payload || !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload JSON invalido']);
    exit;
}

$event = strtolower($payload['event'] ?? '');
if ($event !== 'messages.upsert' && $event !== 'messages_upsert') {
    echo json_encode(['success' => true, 'message' => 'Evento ignorado']);
    exit;
}

$data = $payload['data'] ?? [];
$key  = $data['key'] ?? [];

if (!empty($key['fromMe'])) {
    echo json_encode(['success' => true, 'message' => 'Mensagem propria ignorada']);
    exit;
}

$remoteJid   = $key['remoteJid'] ?? '';
$phoneNumber = preg_replace('/[^0-9]/', "", str_replace('@s.whatsapp.net', '', $remoteJid));
$contactName = $data['pushName'] ?? 'Contato não salvo';
$messageId   = $key['id'] ?? '';

$messageData = $data['message'] ?? [];
$text        = $messageData['conversation'] ?? $messageData['extendedTextMessage']['text'] ?? '';

if (empty($phoneNumber) || empty($text)) {
    echo json_encode(['success' => true, 'message' => 'Dados insuficientes para gravar']);
    exit;
}

global $DB;
$now = date('Y-m-d H:i:s');

$chatIterator = $DB->request([
    'SELECT' => ['id'],
    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
    'WHERE'  => ['phone_number' => $phoneNumber],
    'LIMIT'  => 1
]);

$chatId = 0;
if ($row = $chatIterator->current()) {
    $chatId = (int) $row['id'];
    $DB->update('glpi_plugin_whatsappsimples_chats', [
        'contact_name' => $contactName,
        'date_mod'     => $now
    ], ['id' => $chatId]);
} else {
    $DB->insert('glpi_plugin_whatsappsimples_chats', [
        'phone_number'  => $phoneNumber,
        'contact_name'  => $contactName,
        'users_id'      => 0,
        'status'        => 'pending',
        'date_creation' => $now,
        'date_mod'      => $now
    ]);
    $chatId = (int) $DB->insertId();
}

if ($chatId > 0) {
    $DB->insert('glpi_plugin_whatsappsimples_messages', [
        'chats_id'      => $chatId,
        'message_id'    => $messageId,
        'sender_type'   => 'user',
        'message_text'  => $text,
        'date_creation' => $now
    ]);
}

echo json_encode(['success' => true, 'message' => 'Mensagem recebida e gravada com sucesso']);
