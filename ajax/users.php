<?php
define('GLPI_KEEP_CSRF_TOKEN', true);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include_once(__DIR__ . '/../../../inc/includes.php');

    $controller = new \GlpiPlugin\Whatsappsimples\Controller\GetUsersController();
    $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $response = $controller($request);
    $response->send();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Fatal Error: ' . $e->getMessage()
    ]);
}
