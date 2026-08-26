<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetQrCodeController extends AbstractController
{
    #[Route('/ajax/qrcode', name: 'whatsappsimples_api_qrcode', methods: ['GET', 'POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        global $CFG_GLPI;

        $stateResult = EvolutionApiService::getConnectionState();

        if (!empty($stateResult['state']) && $stateResult['state'] === 'open') {
            // Se o WhatsApp está conectado, garante que o Webhook do GLPI está registrado na EvolutionAPI
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
    }
}
