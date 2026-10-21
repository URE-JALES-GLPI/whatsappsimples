<?php
include ("../../../inc/includes.php");
Session::checkLoginUser();
if (!Session::haveRight('plugin_whatsappsimples', READ)) {
    http_response_code(403);
    die("Acesso negado");
}
session_write_close();

$phone = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');
if (empty($phone)) {
    http_response_code(400);
    die("Telefone invalido");
}

$baseDir = defined('GLPI_PLUGIN_DOC_DIR') 
    ? GLPI_PLUGIN_DOC_DIR . '/whatsappsimples/avatars' 
    : (defined('GLPI_ROOT') ? GLPI_ROOT . '/files/_plugins/whatsappsimples/avatars' : '/var/www/html/glpi/files/_plugins/whatsappsimples/avatars');

$filePath = $baseDir . '/' . $phone . '.jpg';

// Se não existir em disco, tenta buscar e salvar da Evolution API
if (!file_exists($filePath) || filesize($filePath) < 300) {
    if (class_exists('\GlpiPlugin\Whatsappsimples\Service\EvolutionApiService')) {
        \GlpiPlugin\Whatsappsimples\Service\EvolutionApiService::fetchProfile($phone);
    }
}

if (!file_exists($filePath) || filesize($filePath) < 300) {
    http_response_code(404);
    die("Avatar not found");
}

// Limpa buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
