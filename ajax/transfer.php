<?php
define('GLPI_KEEP_CSRF_TOKEN', true);

try {
    include_once(__DIR__ . '/../../../inc/includes.php');

    $controller = new \GlpiPlugin\Whatsappsimples\Controller\TransferChatController();
    $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $response = $controller($request);
    return $response;
} catch (\Throwable $e) {
    return new \Symfony\Component\HttpFoundation\JsonResponse([
        'success' => false,
        'error' => 'Fatal Error: ' . $e->getMessage()
    ], 500);
}
