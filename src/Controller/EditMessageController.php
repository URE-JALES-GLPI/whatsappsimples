<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use GlpiPlugin\Whatsappsimples\Service\MercurePublisherService;

class EditMessageController
{
    public function __invoke(Request $request): JsonResponse
    {
        $currentUserId = (int) \Session::getLoginUserID();
        if ($currentUserId <= 0) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado (não logado)'], 403);
        }

        $msgId = (int) $request->request->get('msg_id');
        $newText = trim((string) $request->request->get('text', ''));

        if ($msgId <= 0 || $newText === '') {
            return new JsonResponse(['success' => false, 'error' => 'Parâmetros inválidos ou texto vazio'], 400);
        }

        global $DB;

        $msg = $DB->request([
            'FROM'  => 'glpi_plugin_whatsappsimples_messages',
            'WHERE' => ['id' => $msgId]
        ])->current();

        if (!$msg) {
            return new JsonResponse(['success' => false, 'error' => 'Mensagem não encontrada'], 404);
        }

        if ($msg['sender_type'] !== 'attendant' && $msg['sender_type'] !== 'agent') {
            return new JsonResponse(['success' => false, 'error' => 'Só é possível editar mensagens enviadas por você'], 400);
        }

        $chat = $DB->request([
            'FROM'  => 'glpi_plugin_whatsappsimples_chats',
            'WHERE' => ['id' => $msg['chats_id']]
        ])->current();

        if (!$chat) {
            return new JsonResponse(['success' => false, 'error' => 'Chat não encontrado'], 404);
        }

        $wuid = $msg['message_id'];

        // Se a mensagem possui ID na API (wuid) e não for nota interna, chama o endpoint da Evolution API
        if (!empty($wuid) && strlen($wuid) > 5 && empty($msg['is_internal'])) {
            $apiRes = EvolutionApiService::updateMessage($chat['phone_number'], $wuid, $newText);
            if (!$apiRes['success']) {
                $err = $apiRes['error'] ?? '';
                return new JsonResponse([
                    'success' => false, 
                    'error'   => 'A Evolution API não conseguiu editar no WhatsApp. Nota: O WhatsApp só permite editar mensagens enviadas há menos de 15 minutos.'
                ], 500);
            }
        }

        // Atualiza no banco local
        $DB->update('glpi_plugin_whatsappsimples_messages', [
            'message_text' => $newText
        ], ['id' => $msgId]);

        MercurePublisherService::notify('message_updated', [
            'chat_id'      => (int) $msg['chats_id'],
            'phone_number' => $chat['phone_number']
        ]);

        return new JsonResponse(['success' => true, 'new_text' => $newText]);
    }
}
