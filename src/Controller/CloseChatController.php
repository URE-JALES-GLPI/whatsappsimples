<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CloseChatController extends AbstractController
{
    #[Route('/ajax/close', name: 'whatsappsimples_api_close', methods: ['POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        global $DB;

        $chatId = (int) ($request->request->get('chat_id') ?? $request->query->get('chat_id', 0));
        if ($chatId <= 0) {
            return new JsonResponse(['success' => false, 'error' => 'Chat inválido'], 400);
        }

        if (!$DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
            return new JsonResponse(['success' => false, 'error' => 'Tabela de chats não existe'], 400);
        }

        $now = date('Y-m-d H:i:s');
        $DB->update('glpi_plugin_whatsappsimples_chats', [
            'status'      => 'closed',
            'date_closed' => $now,
            'date_mod'    => $now
        ], ['id' => $chatId]);

        return new JsonResponse(['success' => true, 'message' => 'Atendimento encerrado com sucesso']);
    }
}
