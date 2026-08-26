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

        $chatId      = (int) $request->query->get('chat_id', 0);
        $phoneNumber = trim((string) $request->query->get('phone_number', ''));

        if ($chatId <= 0 && empty($phoneNumber)) {
            return new JsonResponse(['messages' => []]);
        }

        $contactDisplayName = 'Contato';
        $chatAssignedUserId = 0;
        $chatsIds = [];

        try {
            if ($chatId > 0) {
                $chat = $DB->request([
                    'SELECT' => ['id', 'contact_name', 'phone_number', 'users_id'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                    'WHERE'  => ['id' => $chatId],
                    'LIMIT'  => 1
                ])->current();

                if ($chat) {
                    $phoneNumber = $chat['phone_number'];
                    $contactDisplayName = !empty($chat['contact_name']) ? $chat['contact_name'] : $phoneNumber;
                    $chatAssignedUserId = (int) ($chat['users_id'] ?? 0);
                }
            }

            if (!empty($phoneNumber)) {
                $allChats = $DB->request([
                    'SELECT' => ['id', 'contact_name'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                    'WHERE'  => ['phone_number' => $phoneNumber]
                ]);

                foreach ($allChats as $c) {
                    $chatsIds[] = (int) $c['id'];
                    if (!empty($c['contact_name'])) {
                        $contactDisplayName = $c['contact_name'];
                    }
                }
            }

            if (empty($chatsIds) && $chatId > 0) {
                $chatsIds = [$chatId];
            }

            if (empty($chatsIds)) {
                return new JsonResponse(['messages' => []]);
            }

            $iterator = $DB->request([
                'SELECT' => ['id', 'chats_id', 'users_id', 'sender_type', 'message_text', 'media_url', 'date_creation'],
                'FROM'   => 'glpi_plugin_whatsappsimples_messages',
                'WHERE'  => ['chats_id' => $chatsIds],
                'ORDER'  => 'id ASC'
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
        } catch (\Throwable $e) {
            return new JsonResponse(['messages' => [], 'error' => $e->getMessage()]);
        }
    }
}
