<?php

include_once(__DIR__ . '/../../../inc/includes.php');

Session::checkLoginUser();
header('Content-Type: application/json');

global $DB;

if (!$DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
    echo json_encode(['chats' => []]);
    exit;
}

$tab = $_GET['tab'] ?? $_POST['tab'] ?? 'mine';
$currentUserId = (int) Session::getLoginUserID();

$queryParams = [
    'SELECT' => ['id', 'phone_number', 'contact_name', 'users_id', 'status', 'date_mod'],
    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
    'ORDER'  => ['date_mod DESC']
];

if ($tab === 'mine') {
    $queryParams['WHERE'] = ['users_id' => $currentUserId];
} elseif ($tab === 'queue') {
    $queryParams['WHERE'] = ['users_id' => 0, 'status' => 'pending'];
}

$iterator = $DB->request($queryParams);

$chats = [];
foreach ($iterator as $row) {
    $chats[] = [
        'id'           => (int) $row['id'],
        'phone_number' => $row['phone_number'],
        'contact_name' => !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'],
        'users_id'     => (int) $row['users_id'],
        'status'       => $row['status'],
        'date_mod'     => $row['date_mod'],
    ];
}

echo json_encode(['chats' => $chats]);
