<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CloseChatController
{
    #[Route('/ajax/close', name: 'whatsappsimples_api_close', methods: ['POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        $chatId = (int) ($request->request->get('chat_id') ?? $request->query->get('chat_id', 0));
        if ($chatId <= 0) {
            return new JsonResponse(['success' => false, 'error' => 'Chat inválido'], 400);
        }

        $userId = \Session::getLoginUserID();
        
        $repository = new \GlpiPlugin\Whatsappsimples\Repository\ChatRepository();
        $lifecycleService = new \GlpiPlugin\Whatsappsimples\Service\ChatLifecycleService($repository);
        
        $success = $lifecycleService->closeChat($chatId, $userId);

        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Atendimento encerrado com sucesso']);
        }

        return new JsonResponse(['success' => false, 'message' => 'Erro ao encerrar chat']);
    }
}
