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
    #[Route('/ajax/send', name: 'whatsappsimples_api_send', methods: ['GET', 'POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        global $DB;

        if (!$DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
            return new JsonResponse(['success' => false, 'error' => 'Tabela de chats não existe no banco'], 400);
        }

        $chatId      = (int) ($request->request->get('chat_id') ?? $request->query->get('chat_id') ?? 0);
        $phoneNumber = trim((string) ($request->request->get('phone_number') ?? $request->query->get('phone_number') ?? ''));
        $text        = trim((string) ($request->request->get('text') ?? $request->query->get('text') ?? ''));

        if ($chatId <= 0 && empty($phoneNumber)) {
            return new JsonResponse(['success' => false, 'error' => 'Dados inválidos: informe o atendimento ou número'], 400);
        }

        if (empty($text)) {
            return new JsonResponse(['success' => false, 'error' => 'Digite um texto para enviar'], 400);
        }

        $currentUserId = (int) Session::getLoginUserID();
        $now           = date('Y-m-d H:i:s');

        $chat = null;
        if ($chatId > 0) {
            $chat = $DB->request([
                'SELECT' => ['id', 'phone_number', 'first_response_date', 'users_id'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'WHERE'  => ['id' => $chatId],
                'LIMIT'  => 1
            ])->current();
        }

        if (!$chat && !empty($phoneNumber)) {
            // Localiza ou cria atendimento ativo para o número fornecido
            $chat = $DB->request([
                'SELECT' => ['id', 'phone_number', 'first_response_date', 'users_id'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'WHERE'  => [
                    'phone_number' => $phoneNumber,
                    'status'       => ['pending', 'in_progress']
                ],
                'ORDER'  => ['id DESC'],
                'LIMIT'  => 1
            ])->current();

            if (!$chat) {
                $DB->insert('glpi_plugin_whatsappsimples_chats', [
                    'phone_number'  => $phoneNumber,
                    'contact_name'  => $phoneNumber,
                    'users_id'      => $currentUserId,
                    'status'        => 'in_progress',
                    'date_creation' => $now,
                    'date_mod'      => $now
                ]);
                $chatId = (int) $DB->insertId();
                $chat   = [
                    'id'                  => $chatId,
                    'phone_number'        => $phoneNumber,
                    'first_response_date' => null,
                    'users_id'            => $currentUserId
                ];
            } else {
                $chatId = (int) $chat['id'];
            }
        }

        if (!$chat) {
            return new JsonResponse(['success' => false, 'error' => 'Chat não encontrado'], 404);
        }

        // Dispara mensagem via EvolutionAPI
        $result = EvolutionApiService::sendMessage($chatId, (string) $chat['phone_number'], $text);

        if (!empty($result['success'])) {
            $updateData = [
                'users_id' => $currentUserId,
                'status'   => 'in_progress',
                'date_mod' => $now
            ];

            if (empty($chat['first_response_date'])) {
                $updateData['first_response_date'] = $now;
            }

            $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['id' => $chatId]);
        }

        return new JsonResponse($result);
    }
}
