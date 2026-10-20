<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use GlpiPlugin\Whatsappsimples\Service\MercurePublisherService;

class DeleteMessageController
{
    public function __invoke(Request $request): JsonResponse
    {
        \Session::checkLoginUser();
        if (!\Session::haveRight('plugin_whatsappsimples', READ)) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado'], 403);
        }

        $currentUserId = (int) \Session::getLoginUserID();
        if ($currentUserId <= 0) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado (não logado)'], 403);
        }

        $msgId = (int) $request->request->get('msg_id');
        if ($msgId <= 0) {
            return new JsonResponse(['success' => false, 'error' => 'ID inválido'], 400);
        }

        global $DB;
        
        $msgIter = $DB->request([
            'FROM' => 'glpi_plugin_whatsappsimples_messages',
            'WHERE' => ['id' => $msgId]
        ]);
        
        $msg = $msgIter->current();
        if (!$msg) {
            return new JsonResponse(['success' => false, 'error' => 'Mensagem não encontrada'], 404);
        }
        
        if ($msg['sender_type'] !== 'attendant' && $msg['sender_type'] !== 'agent') {
            return new JsonResponse(['success' => false, 'error' => 'Só é possível apagar mensagens enviadas por você'], 400);
        }
        
        $chatIter = $DB->request([
            'FROM' => 'glpi_plugin_whatsappsimples_chats',
            'WHERE' => ['id' => $msg['chats_id']]
        ]);
        
        $chat = $chatIter->current();
        if (!$chat) {
            return new JsonResponse(['success' => false, 'error' => 'Chat não encontrado'], 404);
        }

        $canDelete = (
            (int)($msg['users_id'] ?? 0) === $currentUserId ||
            (int)($chat['users_id'] ?? 0) === $currentUserId ||
            \Session::haveRight('config', UPDATE)
        );

        if (!$canDelete) {
            return new JsonResponse(['success' => false, 'error' => 'Você só pode apagar mensagens enviadas por você'], 403);
        }
        
        $wuid = $msg['message_id'];
        
        // Se a mensagem possui ID na API (wuid) e não for nota interna, chama o endpoint de delete da API
        if (!empty($wuid) && strlen($wuid) > 5 && empty($msg['is_internal'])) {
            $apiRes = EvolutionApiService::deleteMessage($chat['phone_number'], $wuid);
            if (!$apiRes['success']) {
                return new JsonResponse(['success' => false, 'error' => $apiRes['error']], 500);
            }
        }
        
        // Exclui do banco local
        $DB->delete('glpi_plugin_whatsappsimples_messages', ['id' => $msgId]);

        MercurePublisherService::notify('message_deleted', [
            'chat_id'      => (int) $msg['chats_id'],
            'message_id'   => $wuid,
            'id'           => $msgId,
            'phone_number' => $chat['phone_number'] ?? ''
        ]);
        
        return new JsonResponse(['success' => true, 'wuid' => $wuid]);
    }
}
