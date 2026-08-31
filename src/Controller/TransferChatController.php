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

        if ($chatId <= 0 || $newUserId < 0) {
            return new JsonResponse(['success' => false, 'error' => 'Dados inválidos'], 400);
        }

        // Verify if user exists if not returning to queue
        if ($newUserId > 0) {
            $userExists = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_users',
                'WHERE'  => ['id' => $newUserId]
            ])->count();

            if ($userExists === 0) {
                return new JsonResponse(['success' => false, 'error' => 'Usuário de destino não encontrado'], 404);
            }
        }

        // Find the phone number of the chat to update all related history
        $chat = $DB->request([
            'SELECT' => ['phone_number'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => ['id' => $chatId]
        ])->current();

        if (!$chat) {
            return new JsonResponse(['success' => false, 'error' => 'Chat não encontrado'], 404);
        }

        $phoneToUpdate = $chat['phone_number'];
        $success = $DB->update('glpi_plugin_whatsappsimples_chats', [
            'users_id' => $newUserId,
            'date_mod' => date('Y-m-d H:i:s')
        ], ['phone_number' => $phoneToUpdate]);

        if ($success !== false) {
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'error' => 'Falha ao atualizar o banco de dados'], 500);
    }
}
