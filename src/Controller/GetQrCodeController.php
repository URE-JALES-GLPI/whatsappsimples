<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetQrCodeController
{
    #[Route('/ajax/qrcode', name: 'whatsappsimples_api_qrcode', methods: ['GET', 'POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        if (!Session::haveRight('config', UPDATE)) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado'], 403);
        }
        global $CFG_GLPI;

        $stateResult = EvolutionApiService::getConnectionState();

        if (!empty($stateResult['state']) && $stateResult['state'] === 'open') {
            // Se o WhatsApp está conectado, garante que o Webhook do GLPI está registrado na EvolutionAPI
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? '10.180.152.27';
            $root   = isset($CFG_GLPI['root_doc']) ? rtrim($CFG_GLPI['root_doc'], '/') : '';
            $token  = urlencode(EvolutionApiService::getConfig('api_token') ?: (function_exists('wa_env') ? wa_env('EVOLUTION_API_KEY', '') : (getenv('EVOLUTION_API_KEY') ?: '')));
            $webhookUrl = "{$scheme}://{$host}{$root}/plugins/whatsappsimples/front/webhook.php?token={$token}";

            $whRes = EvolutionApiService::setWebhook($webhookUrl);
            $whFind = EvolutionApiService::findWebhook();

            return new JsonResponse([
                'success'        => true,
                'state'          => 'open',
                'message'        => 'WhatsApp Conectado com Sucesso!',
                'webhook_url'    => $webhookUrl,
                'webhook_result' => $whRes,
                'webhook_find'   => $whFind['data'] ?? null,
                'php_limits'     => [
                    'post_max_size'       => ini_get('post_max_size'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'memory_limit'        => ini_get('memory_limit')
                ]
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
    }
}
