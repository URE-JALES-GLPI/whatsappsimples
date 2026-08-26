<?php

include_once(__DIR__ . '/../../inc/includes.php');

Session::checkLoginUser();
header('Content-Type: application/json');

global $DB;

if (!$DB->tableExists('glpi_plugin_whatsappsimples_messages')) {
    echo json_encode(['messages' => []]);
    exit;
}

$chatId = (int) ($_GET['chat_id'] ?? $_POST['chat_id'] ?? 0);
if ($chatId <= 0) {
    echo json_encode(['messages' => []]);
    exit;
}

$iterator = $DB->request([
    'SELECT' => ['id', 'chats_id', 'sender_type', 'message_text', 'media_url', 'date_creation'],
    'FROM'   => 'glpi_plugin_whatsappsimples_messages',
    'WHERE'  => ['chats_id' => $chatId],
    'ORDER'  => ['id ASC']
]);

$messages = [];
foreach ($iterator as $row) {
    $messages[] = [
        'id'            => (int) $row['id'],
        'chats_id'      => (int) $row['chats_id'],
        'sender_type'   => $row['sender_type'],
        'message_text'  => $row['message_text'],
        'media_url'     => $row['media_url'],
        'date_creation' => $row['date_creation'],
    ];
}

echo json_encode(['messages' => $messages]);
