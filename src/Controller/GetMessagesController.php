<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use Session;
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

        $chatId = $request->query->getInt('chat_id');
        if ($chatId <= 0) {
            return new JsonResponse(['messages' => []]);
        }

        $iterator = $DB->request([
            'SELECT' => ['id', 'chats_id', 'sender_type', 'message_text', 'media_url', 'date_creation'],
            'FROM'   => 'glpi_plugin_whatsappsimples_messages',
            'WHERE'  => ['chats_id' => $chatId],
            'ORDER'  => ['id ASC']
        ]);

        $messages = [];
        foreach ($iterator as $row) {
            $messages[] = [
                'id'            => (int) $row['id'],
                'chats_id'      => (int) $row['chats_id'],
                'sender_type'   => $row['sender_type'],
                'message_text'  => $row['message_text'],
                'media_url'     => $row['media_url'],
                'date_creation' => $row['date_creation'],
            ];
        }

        return new JsonResponse(['messages' => $messages]);
    }
}
