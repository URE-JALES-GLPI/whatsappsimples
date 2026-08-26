<?php

include_once(__DIR__ . '/../../../inc/includes.php');

Session::checkLoginUser();
header('Content-Type: application/json');

global $DB;

if (!$DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
    echo json_encode(['success' => false, 'error' => 'Tabela de chats não existe no banco']);
    exit;
}

if (isset($_POST['_glpi_csrf_token'])) {
    Session::checkCSRF($_POST);
}

$chatId = (int) ($_POST['chat_id'] ?? $_GET['chat_id'] ?? 0);
$text   = trim((string) ($_POST['text'] ?? $_GET['text'] ?? ''));

if ($chatId <= 0 || empty($text)) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

$chat = $DB->request([
    'SELECT' => ['id', 'phone_number', 'first_response_date', 'users_id'],
    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
    'WHERE'  => ['id' => $chatId],
    'LIMIT'  => 1
])->current();

if (!$chat) {
    echo json_encode(['success' => false, 'error' => 'Chat não encontrado']);
    exit;
}

use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;

$result = EvolutionApiService::sendMessage($chatId, (string) $chat['phone_number'], $text);

if (!empty($result['success'])) {
    $currentUserId = (int) Session::getLoginUserID();
    $updateData = [
        'users_id' => $currentUserId,
        'status'   => 'in_progress'
    ];

    if (empty($chat['first_response_date'])) {
        $updateData['first_response_date'] = date('Y-m-d H:i:s');
    }

    $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['id' => $chatId]);
}

echo json_encode($result);
