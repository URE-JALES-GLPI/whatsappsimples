<?php   

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
final class WebhookController extends AbstractController
{
    public function isPublic(): bool
    {
        return true;
    }

    public function checkAccess(): bool
    {
        return true;
    }

    #[Route('/webhook', name: 'whatsappsimples_webhook', methods: ['GET', 'POST'], options: ['no_login' => true, 'public' => true, 'prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        global $DB;

        $expectedToken = EvolutionApiService::getConfig('api_token') ?: 'ure_jales_evolution_token_2026';

        $providedToken = $request->headers->get('apikey') 
            ?? $request->headers->get('x-api-key') 
            ?? $request->query->get('token') 
            ?? '';

        if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado: Token de autenticacao invalido ou ausente'], 401);
        }

        if ($request->isMethod('GET')) {
            return new JsonResponse(['success' => true, 'message' => 'Webhook do WhatsAppSimples autenticado e ativo!']);
        }

        $content = $request->getContent();
        $payload = json_decode($content, true);

        if (!$payload || !is_array($payload)) {
            return new JsonResponse(['success' => false, 'error' => 'Payload JSON invalido'], 400);
        }

        $event = strtolower($payload['event'] ?? '');

        if ($event !== 'messages.upsert' && $event !== 'messages_upsert') {
            return new JsonResponse(['success' => true, 'message' => 'Evento ignorado']);
        }

        $data = $payload['data'] ?? [];
        $key  = $data['key'] ?? [];

        if (!empty($key['fromMe'])) {
            return new JsonResponse(['success' => true, 'message' => 'Mensagem propria ignorada']);
        }

        $rawJid = '';
        if (!empty($data['sender']) && str_contains($data['sender'], '@s.whatsapp.net')) {
            $rawJid = $data['sender'];
        } elseif (!empty($key['participant']) && str_contains($key['participant'], '@s.whatsapp.net')) {
            $rawJid = $key['participant'];
        } elseif (!empty($key['remoteJid']) && str_contains($key['remoteJid'], '@s.whatsapp.net')) {
            $rawJid = $key['remoteJid'];
        } else {
            $rawJid = $data['sender'] ?? $key['participant'] ?? $key['remoteJid'] ?? '';
        }

        $phoneNumber = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $rawJid));

        $contactName = $data['pushName'] ?? 'Contato não salvo';
        $messageId   = $key['id'] ?? '';

        $messageData = $data['message'] ?? [];
        $text        = $messageData['conversation'] ?? $messageData['extendedTextMessage']['text'] ?? '';

        if (empty($phoneNumber) || empty($text)) {
            return new JsonResponse(['success' => true, 'message' => 'Dados insuficientes para gravar']);
        }

        $now = date('Y-m-d H:i:s');

        // Busca o único registro de chat existente para este número (independente de status)
        $chatIterator = $DB->request([
            'SELECT' => ['id', 'status', 'users_id'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => ['phone_number' => $phoneNumber],
            'ORDER'  => ['id ASC'],
            'LIMIT'  => 1
        ]);

        $chatId = 0;
        if ($row = $chatIterator->current()) {
            $chatId = (int) $row['id'];
            $updateData = [
                'contact_name' => $contactName,
                'date_mod'     => $now
            ];
            // Se estava encerrado, reabre na Fila para atendimento
            if ($row['status'] === 'closed') {
                $updateData['status']   = 'pending';
                $updateData['users_id'] = 0;
            }
            $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['id' => $chatId]);
        } else {
            // Cria o registro unico de chat na Fila
            $DB->insert('glpi_plugin_whatsappsimples_chats', [
                'phone_number'  => $phoneNumber,
                'contact_name'  => $contactName,
                'users_id'      => 0,
                'status'        => 'pending',
                'date_creation' => $now,
                'date_mod'      => $now
            ]);
            $chatId = (int) $DB->insertId();
        }

        if ($chatId > 0) {
            $DB->insert('glpi_plugin_whatsappsimples_messages', [
                'chats_id'      => $chatId,
                'message_id'    => $messageId,
                'sender_type'   => 'user',
                'message_text'  => $text,
                'date_creation' => $now
            ]);
        }

        return new JsonResponse(['success' => true, 'message' => 'Mensagem recebida e gravada com sucesso']);
    }
}