<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SendMessageController extends AbstractController
{
    #[Route('/ajax/send', name: 'whatsappsimples_api_send', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        global $DB;

        $chatId = $request->request->getInt('chat_id');
        $text   = trim($request->request->getString('text'));

        if ($chatId <= 0 || empty($text)) {
            return new JsonResponse(['success' => false, 'error' => 'Dados inválidos'], 400);
        }

        $chat = $DB->request([
            'SELECT' => ['id', 'phone_number', 'first_response_date', 'users_id'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => ['id' => $chatId],
            'LIMIT'  => 1
        ])->current();

        if (!$chat) {
            return new JsonResponse(['success' => false, 'error' => 'Chat não encontrado'], 404);
        }

        // Dispara mensagem via EvolutionAPI
        $result = EvolutionApiService::sendMessage($chatId, $chat['phone_number'], $text);

        if (!empty($result['success'])) {
            $currentUserId = (int) Session::getLoginUserID();
            $updateData = [
                'users_id' => $currentUserId,
                'status'   => 'in_progress'
            ];

            if (empty($chat['first_response_date'])) {
                $updateData['first_response_date'] = date('Y-m-d H:i:s');
            }

            $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['id' => $chatId]);
        }

        return new JsonResponse($result);
    }
}
