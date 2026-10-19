<?php
define('GLPI_KEEP_CSRF_TOKEN', true);

use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Symfony\Component\HttpFoundation\JsonResponse;

try {
    include_once(__DIR__ . '/../../../inc/includes.php');

    Session::checkLoginUser();

    global $CFG_GLPI;

    $stateResult = EvolutionApiService::getConnectionState();

    if (!empty($stateResult['state']) && $stateResult['state'] === 'open') {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '10.180.152.27';
        $root   = $CFG_GLPI['root_doc'] ?? '/glpi';
        $token  = urlencode(EvolutionApiService::getConfig('api_token') ?: 'ure_jales_evolution_token_2026');
        $webhookUrl = "{$scheme}://{$host}{$root}/plugins/whatsappsimples/front/webhook.php?token={$token}";

        EvolutionApiService::setWebhook($webhookUrl);

        return new JsonResponse([
            'success' => true,
            'state'   => 'open',
            'message' => 'WhatsApp Conectado com Sucesso!'
        ]);
    }

    $qrResult = EvolutionApiService::getQrCode();

    return new JsonResponse([
        'success' => true,
        'state'   => $stateResult['state'] ?? 'close',
        'base64'  => $qrResult['base64'] ?? '',
        'code'    => $qrResult['code'] ?? '',
        'error'   => $qrResult['error'] ?? null
    ]);

} catch (\Throwable $e) {
    return new JsonResponse([
        'success' => false,
        'error' => 'Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], 500);
}
