<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TransferChatController
{
    #[Route('/ajax/transfer', name: 'whatsappsimples_api_transfer', methods: ['POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        if (!Session::haveRight('plugin_whatsappsimples', READ)) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado: Permissão de leitura ausente'], 403);
        }
        if (!Session::haveRight('plugin_whatsappsimples_transfer', READ)) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado: Sem permissão de transferência'], 403);
        }

        global $DB;

        $chatId = (int) $request->request->get('chat_id', 0);
        $newUserId = (int) $request->request->get('user_id', 0);

        if ($chatId <= 0 || $newUserId < 0) {
            return new JsonResponse(['success' => false, 'error' => 'Dados inválidos'], 400);
        }

        $currentUserId = (int) Session::getLoginUserID();
        $repository = new \GlpiPlugin\Whatsappsimples\Repository\ChatRepository();
        $lifecycleService = new \GlpiPlugin\Whatsappsimples\Service\ChatLifecycleService($repository);

        $success = $lifecycleService->transferChat($chatId, $newUserId, $currentUserId);

        if ($success) {
            \GlpiPlugin\Whatsappsimples\Service\MercurePublisherService::notify('chat_transferred', ['chat_id' => $chatId]);
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'error' => 'Falha ao transferir chat'], 500);
    }
}
