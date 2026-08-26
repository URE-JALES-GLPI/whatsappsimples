<?php

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', dirname(__DIR__, 2));
    include_once(GLPI_ROOT . "/config/config.php");
}

use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;

header('Content-Type: application/json');

function logPublicWebhookDebug(string $action, array $data = []): void
{
    $logFile = GLPI_ROOT . '/files/_log/whatsappsimples.log';
    $logDir  = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $entry = sprintf("[%s] [public/webhook.php] [%s] %s\n", date('Y-m-d H:i:s'), $action, json_encode($data, JSON_UNESCAPED_UNICODE));
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

try {
    $expectedToken = EvolutionApiService::getConfig('api_token') ?: 'ure_jales_evolution_token_2026';

    $providedToken = $_SERVER['HTTP_APIKEY'] 
        ?? $_SERVER['HTTP_X_API_KEY'] 
        ?? $_GET['token'] 
        ?? '';

    if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Acesso negado: Token de autenticacao invalido ou ausente']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['success' => true, 'message' => 'Webhook publico do WhatsAppSimples autenticado e ativo!']);
        exit;
    }

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

    $data  = $payload['data'] ?? [];
    $key   = $data['key'] ?? [];
    $isFromMe = !empty($key['fromMe']);

    $rawJid = '';
    if (!empty($key['remoteJid']) && str_contains($key['remoteJid'], '@s.whatsapp.net')) {
        $rawJid = $key['remoteJid'];
    } elseif (!empty($data['sender']) && str_contains($data['sender'], '@s.whatsapp.net')) {
        $rawJid = $data['sender'];
    } elseif (!empty($key['participant']) && str_contains($key['participant'], '@s.whatsapp.net')) {
        $rawJid = $key['participant'];
    } else {
        $rawJid = $key['remoteJid'] ?? $data['sender'] ?? $key['participant'] ?? '';
    }

    $phoneNumber = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $rawJid));
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

    $activeChat = $DB->request([
        'SELECT' => ['id', 'status', 'users_id'],
        'FROM'   => 'glpi_plugin_whatsappsimples_chats',
        'WHERE'  => [
            'phone_number' => $phoneNumber,
            'status'       => ['pending', 'in_progress']
        ],
        'ORDER'  => 'id DESC',
        'LIMIT'  => 1
    ])->current();

    $chatId = 0;
    if ($activeChat) {
        $chatId = (int) $activeChat['id'];
        $DB->update('glpi_plugin_whatsappsimples_chats', [
            'contact_name' => ($isFromMe && !empty($activeChat['contact_name'])) ? $activeChat['contact_name'] : $contactName,
            'date_mod'     => $now
        ], ['id' => $chatId]);
    } else {
        $previousChat = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => ['phone_number' => $phoneNumber],
            'ORDER'  => 'id ASC',
            'LIMIT'  => 1
        ])->current();

        if ($previousChat) {
            $chatId = (int) $previousChat['id'];
            $DB->update('glpi_plugin_whatsappsimples_chats', [
                'status'   => $isFromMe ? 'in_progress' : 'pending',
                'date_mod' => $now
            ], ['id' => $chatId]);
        } else {
            $DB->insert('glpi_plugin_whatsappsimples_chats', [
                'phone_number'  => $phoneNumber,
                'contact_name'  => $contactName,
                'users_id'      => 0,
                'status'        => $isFromMe ? 'in_progress' : 'pending',
                'date_creation' => $now,
                'date_mod'      => $now
            ]);
            $chatId = (int) $DB->insertId();
        }
    }

    if ($chatId > 0) {
        $senderType = $isFromMe ? 'attendant' : 'user';

        $alreadyExists = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_whatsappsimples_messages',
            'WHERE'  => ['message_id' => $messageId],
            'LIMIT'  => 1
        ])->count() > 0;

        if (!$alreadyExists) {
            $DB->insert('glpi_plugin_whatsappsimples_messages', [
                'chats_id'      => $chatId,
                'users_id'      => 0,
                'message_id'    => $messageId,
                'sender_type'   => $senderType,
                'message_text'  => $text,
                'date_creation' => $now
            ]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Mensagem processada com sucesso']);

} catch (\Throwable $e) {
    logPublicWebhookDebug("ERRO_WEBHOOK_EXCEPTION", ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
