<?php
define('GLPI_KEEP_CSRF_TOKEN', true);

$logDir = __DIR__ . '/../../../files/_log';
if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
file_put_contents($logDir . '/whatsappsimples_csrf_debug.log', 
    date('Y-m-d H:i:s') . " - SEND.PHP - POST: " . json_encode($_POST) . " - HEADERS: " . json_encode(function_exists('getallheaders') ? getallheaders() : []) . "\n", 
    FILE_APPEND
);

include_once(__DIR__ . '/../../../inc/includes.php');

$controller = new \GlpiPlugin\Whatsappsimples\Controller\SendMessageController();
$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $controller($request);
$response->send();
