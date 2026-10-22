<?php
define('GLPI_KEEP_CSRF_TOKEN', true);

use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Symfony\Component\HttpFoundation\JsonResponse;

try {
    include_once(__DIR__ . '/../../../inc/includes.php');

    Session::checkLoginUser();
    if (!Session::haveRight('config', UPDATE)) {
        return new JsonResponse(['success' => false, 'error' => 'Acesso negado'], 403);
    }

    global $CFG_GLPI;

    $stateResult = EvolutionApiService::getConnectionState();

    if (!empty($stateResult['state']) && $stateResult['state'] === 'open') {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '10.180.152.27';
        $root   = isset($CFG_GLPI['root_doc']) ? rtrim($CFG_GLPI['root_doc'], '/') : '';
        $token  = urlencode(EvolutionApiService::getConfig('api_token') ?: (function_exists('wa_env') ? wa_env('EVOLUTION_API_KEY', '') : (getenv('EVOLUTION_API_KEY') ?: '')));
        $webhookUrl = "{$scheme}://{$host}{$root}/plugins/whatsappsimples/front/webhook.php?token={$token}";

        $whRes = EvolutionApiService::setWebhook($webhookUrl);

        return new JsonResponse([
            'success'        => true,
            'state'          => 'open',
            'message'        => 'WhatsApp Conectado com Sucesso!',
            'webhook_url'    => $webhookUrl,
            'webhook_result' => $whRes
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
