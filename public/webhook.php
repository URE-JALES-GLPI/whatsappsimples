<?php

/**
 * Webhook endpoint (public/) — idêntico ao front/webhook.php
 * Mantido como fallback alternativo.
 */

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', dirname(__DIR__, 2));
    include_once(GLPI_ROOT . "/config/config.php");
}

use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;

header('Content-Type: application/json');

function logPublicWebhook(string $action, array $data = []): void
{
    $logFile = GLPI_ROOT . '/files/_log/whatsappsimples.log';
    $logDir  = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $entry = sprintf("[%s] [public/webhook] [%s] %s\n", date('Y-m-d H:i:s'), $action, json_encode($data, JSON_UNESCAPED_UNICODE));
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

try {
    $expectedToken = EvolutionApiService::getConfig('api_token') ?: 'ure_jales_evolution_token_2026';
    $providedToken = $_SERVER['HTTP_APIKEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? $_GET['token'] ?? '';

    if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['success' => true, 'message' => 'Webhook ativo']);
        exit;
    }

    $content = file_get_contents('php://input');
    $payload = json_decode($content, true);

    if (!$payload || !is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'JSON inválido']);
        exit;
    }

    logPublicWebhook("PAYLOAD_BRUTO", ['payload' => $content]);

    $event = strtolower($payload['event'] ?? '');
    if ($event !== 'messages.upsert' && $event !== 'messages_upsert') {
        echo json_encode(['success' => true, 'message' => 'Evento ignorado']);
        exit;
    }

    $data  = $payload['data'] ?? [];
    $key   = $data['key'] ?? [];
    $isFromMe = !empty($key['fromMe']);

    $phoneNumber = EvolutionApiService::resolvePhoneNumber($payload);
    if (empty($phoneNumber)) {
        echo json_encode(['success' => true, 'message' => 'Número vazio']);
        exit;
    }

    logPublicWebhook("NUMERO_RESOLVIDO", ['phone' => $phoneNumber]);

    $contactName = $data['pushName'] ?? $phoneNumber;
    $messageId   = $key['id'] ?? ('msg_' . time() . '_' . rand(100, 999));

    $messageData = $data['message'] ?? [];
    $text = $messageData['conversation'] 
        ?? $messageData['extendedTextMessage']['text'] 
        ?? $messageData['imageMessage']['caption'] 
        ?? $messageData['videoMessage']['caption'] 
        ?? $messageData['documentMessage']['caption'] 
        ?? '';

    if (empty($text) && !empty($messageData['imageMessage'])) {
        $text = '📷 Imagem recebida';
    } elseif (empty($text) && !empty($messageData['audioMessage'])) {
        $text = '🎵 Áudio recebido';
    } elseif (empty($text) && !empty($messageData['documentMessage'])) {
        $text = '📄 Documento recebido';
    }

    if (empty($text)) {
        echo json_encode(['success' => true, 'message' => 'Sem conteúdo de texto']);
        exit;
    }

    global $DB;
    $now = date('Y-m-d H:i:s');

    $activeChat = $DB->request([
        'SELECT' => ['id', 'status', 'users_id', 'contact_name'],
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
        $updateData = ['date_mod' => $now];
        if (!$isFromMe && !empty($contactName) && $contactName !== $phoneNumber) {
            $updateData['contact_name'] = $contactName;
        }
        $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['id' => $chatId]);
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
            $updateData = [
                'status'   => $isFromMe ? 'in_progress' : 'pending',
                'date_mod' => $now
            ];
            if (!$isFromMe && !empty($contactName) && $contactName !== $phoneNumber) {
                $updateData['contact_name'] = $contactName;
            }
            $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['id' => $chatId]);
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

    echo json_encode(['success' => true, 'message' => 'Mensagem processada']);

} catch (\Throwable $e) {
    logPublicWebhook("ERRO_EXCEPTION", ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
