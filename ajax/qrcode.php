<?php
define('GLPI_KEEP_CSRF_TOKEN', true);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;

try {
    include_once(__DIR__ . '/../../../inc/includes.php');

    Session::checkLoginUser();
    header('Content-Type: application/json');

    global $CFG_GLPI;

    $stateResult = EvolutionApiService::getConnectionState();

    if (!empty($stateResult['state']) && $stateResult['state'] === 'open') {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '10.180.152.27';
        $root   = $CFG_GLPI['root_doc'] ?? '/glpi';
        $token  = urlencode(EvolutionApiService::getConfig('api_token') ?: 'ure_jales_evolution_token_2026');
        $webhookUrl = "{$scheme}://{$host}{$root}/plugins/whatsappsimples/front/webhook.php?token={$token}";

        EvolutionApiService::setWebhook($webhookUrl);

        echo json_encode([
            'success' => true,
            'state'   => 'open',
            'message' => 'WhatsApp Conectado com Sucesso!'
        ]);
        exit;
    }

    $qrResult = EvolutionApiService::getQrCode();

    echo json_encode([
        'success' => true,
        'state'   => $stateResult['state'] ?? 'close',
        'base64'  => $qrResult['base64'] ?? '',
        'code'    => $qrResult['code'] ?? '',
        'error'   => $qrResult['error'] ?? null
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
