<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use Session;
use User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetMessagesController extends AbstractController
{
    #[Route('/ajax/messages', name: 'whatsappsimples_api_messages', methods: ['GET', 'POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        global $DB;

        if (!$DB->tableExists('glpi_plugin_whatsappsimples_messages')) {
            return new JsonResponse(['messages' => []]);
        }

        $chatId = (int) $request->query->get('chat_id', 0);
        if ($chatId <= 0) {
            return new JsonResponse(['messages' => []]);
        }

        $chat = $DB->request([
            'SELECT' => ['id', 'contact_name', 'phone_number', 'users_id'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => ['id' => $chatId],
            'LIMIT'  => 1
        ])->current();

        $contactDisplayName = !empty($chat['contact_name']) ? $chat['contact_name'] : ($chat['phone_number'] ?? 'Contato');
        $chatAssignedUserId = (int) ($chat['users_id'] ?? 0);

        $iterator = $DB->request([
            'SELECT' => ['id', 'chats_id', 'users_id', 'sender_type', 'message_text', 'media_url', 'date_creation'],
            'FROM'   => 'glpi_plugin_whatsappsimples_messages',
            'WHERE'  => ['chats_id' => $chatId],
            'ORDER'  => ['id ASC']
        ]);

        $usersCache = [];

        $messages = [];
        foreach ($iterator as $row) {
            $senderName = '';
            if ($row['sender_type'] === 'user') {
                $senderName = $contactDisplayName;
            } else {
                $techId = (int) ($row['users_id'] ?? 0);
                if ($techId <= 0) {
                    $techId = $chatAssignedUserId;
                }

                if ($techId > 0) {
                    if (!isset($usersCache[$techId])) {
                        $userObj = new User();
                        if ($userObj->getFromDB($techId)) {
                            $fullName = trim(($userObj->fields['firstname'] ?? '') . ' ' . ($userObj->fields['realname'] ?? ''));
                            $usersCache[$techId] = !empty($fullName) ? $fullName : ($userObj->fields['name'] ?? 'Técnico URE TI');
                        } else {
                            $usersCache[$techId] = 'Técnico URE TI';
                        }
                    }
                    $senderName = $usersCache[$techId];
                } else {
                    $senderName = 'Técnico URE TI';
                }
            }

            $messages[] = [
                'id'            => (int) $row['id'],
                'chats_id'      => (int) $row['chats_id'],
                'sender_type'   => $row['sender_type'],
                'sender_name'   => $senderName,
                'message_text'  => $row['message_text'],
                'media_url'     => $row['media_url'],
                'date_creation' => $row['date_creation'],
            ];
        }

        return new JsonResponse(['messages' => $messages]);
    }
}
