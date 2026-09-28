<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateChatController
{
    #[Route('/ajax/new-chat', name: 'whatsappsimples_api_new_chat', methods: ['POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        try {
            Session::checkLoginUser();
            Session::checkRight('plugin_whatsappsimples', READ);

            global $DB;

            $rawPhone = trim((string) $request->request->get('phone_number', ''));
            $contactName = trim((string) $request->request->get('contact_name', ''));

            if (empty($rawPhone)) {
                return new JsonResponse(['success' => false, 'error' => 'O número de telefone é obrigatório.'], 400);
            }

            // Remove non-digit characters
            $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
            
            if (empty($cleanPhone) || strlen($cleanPhone) < 10) {
                return new JsonResponse(['success' => false, 'error' => 'Número de telefone inválido. O formato deve conter DDI e DDD (ex: 5517999999999).'], 400);
            }

            if (empty($contactName)) {
                $contactName = $cleanPhone; // default name to number
            }

            $currentUserId = (int) Session::getLoginUserID();
            $now = date('Y-m-d H:i:s');

            // Verifica se o chat já existe
            $existingChat = $DB->request([
                'SELECT' => ['id', 'users_id', 'status'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'WHERE'  => ['phone_number' => $cleanPhone],
                'LIMIT'  => 1
            ])->current();

            if ($existingChat) {
                // Atualiza o chat existente para o usuário logado
                $DB->update('glpi_plugin_whatsappsimples_chats', [
                    'users_id' => $currentUserId,
                    'status'   => 'in_progress',
                    'contact_name' => $contactName,
                    'date_mod' => $now
                ], ['id' => $existingChat['id']]);

                return new JsonResponse([
                    'success' => true, 
                    'chat_id' => (int)$existingChat['id'],
                    'phone_number' => $cleanPhone,
                    'contact_name' => $contactName
                ]);
            } else {
                // Cria um novo chat
                $DB->insert('glpi_plugin_whatsappsimples_chats', [
                    'phone_number'  => $cleanPhone,
                    'contact_name'  => $contactName,
                    'users_id'      => $currentUserId,
                    'status'        => 'in_progress',
                    'date_creation' => $now,
                    'date_mod'      => $now
                ]);
                $chatId = (int) $DB->insertId();

                return new JsonResponse([
                    'success' => true, 
                    'chat_id' => $chatId,
                    'phone_number' => $cleanPhone,
                    'contact_name' => $contactName
                ]);
            }
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'Erro interno ao criar chat: ' . $e->getMessage()
            ], 500);
        }
    }
}
