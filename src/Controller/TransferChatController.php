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
        Session::checkRight('plugin_whatsappsimples', READ);
        Session::checkRight('plugin_whatsappsimples_transfer', READ); // Must have transfer right

        global $DB;

        $chatId = (int) $request->request->get('chat_id', 0);
        $newUserId = (int) $request->request->get('user_id', 0);

        if ($chatId <= 0 || $newUserId <= 0) {
            return new JsonResponse(['success' => false, 'error' => 'Dados inválidos'], 400);
        }

        // Verify if user exists
        $userExists = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['id' => $newUserId]
        ])->count();

        if ($userExists === 0) {
            return new JsonResponse(['success' => false, 'error' => 'Usuário de destino não encontrado'], 404);
        }

        $repository = new \GlpiPlugin\Whatsappsimples\Repository\ChatRepository();
        $success = $repository->updateChat($chatId, ['users_id' => $newUserId]);

        if ($success) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'error' => 'Falha ao atualizar o banco de dados'], 500);
    }
}
