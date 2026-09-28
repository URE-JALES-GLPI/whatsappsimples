<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use GlpiPlugin\Whatsappsimples\Repository\ChatRepository;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResetUnreadController
{
    #[Route('/ajax/reset-unread', name: 'whatsappsimples_api_reset_unread', methods: ['POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        Session::checkRight('plugin_whatsappsimples', READ);

        $chatId = (int) ($request->request->get('chat_id') ?? $request->query->get('chat_id', 0));
        
        if ($chatId <= 0) {
            return new JsonResponse(['success' => false, 'error' => 'Chat inválido'], 400);
        }

        $repository = new ChatRepository();
        $success = $repository->resetUnreadCount($chatId);

        return new JsonResponse([
            'success' => $success,
            'message' => $success ? 'Contador zerado com sucesso' : 'Falha ao zerar contador'
        ]);
    }
}
