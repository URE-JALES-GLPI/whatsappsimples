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
    #[Route('/ajax/qrcode', name: 'whatsappsimples_api_qrcode', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();

        $stateResult = EvolutionApiService::getConnectionState();

        if (!empty($stateResult['state']) && $stateResult['state'] === 'open') {
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
