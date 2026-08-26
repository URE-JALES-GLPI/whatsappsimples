<?php   

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

        // 1. VALIDAÇÃO DE SEGURANÇA POR TOKEN SECRETO (API KEY)
        $config = EvolutionApiService::getConfig();
        $expectedToken = $config['api_key'] ?? 'ure_jales_evolution_token_2026';

        $providedToken = $request->headers->get('apikey') 
            ?? $request->headers->get('x-api-key') 
            ?? $request->query->get('token') 
            ?? '';

        // Comparação segura de hash em tempo constante para evitar ataques de temporização
        if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            error_log("[WhatsAppSimples Webhook] REJEITADO: Tentativa de acesso com token invalido ou ausente.");
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado: Token de autenticacao invalido ou ausente'], 401);
        }

        // Se for uma chamada GET para validação de saúde do webhook
        if ($request->isMethod('GET')) {
            return new JsonResponse(['success' => true, 'message' => 'Webhook do WhatsAppSimples autenticado e ativo!']);
        }

        // 2. Lê o payload JSON enviado pela EvolutionAPI
        $content = $request->getContent();
        $payload = json_decode($content, true);

        if (!$payload || !is_array($payload)) {
            return new JsonResponse(['success' => false, 'error' => 'Payload JSON invalido'], 400);
        }

        $event = strtolower($payload['event'] ?? '');
        error_log("[WhatsAppSimples Webhook] Evento autenticado recebido: {$event}");

        // 3. Aceita eventos de mensagem 'messages.upsert' e 'messages_upsert'
        if ($event !== 'messages.upsert' && $event !== 'messages_upsert') {
            return new JsonResponse(['success' => true, 'message' => 'Evento ignorado']);
        }

        $data = $payload['data'] ?? [];
        $key  = $data['key'] ?? [];

        // Ignora mensagens enviadas por nós mesmos (fromMe = true)
        if (!empty($key['fromMe'])) {
            return new JsonResponse(['success' => true, 'message' => 'Mensagem propria ignorada']);
        }

        // 4. Extrai os dados do remetente
        $remoteJid   = $key['remoteJid'] ?? '';
        $phoneNumber = preg_replace('/[^0-9]/', "", str_replace('@s.whatsapp.net', '', $remoteJid));
        $contactName = $data['pushName'] ?? 'Contato não salvo';
        $messageId   = $key['id'] ?? '';

        // 5. Extrai o conteúdo de texto da mensagem
        $messageData = $data['message'] ?? [];
        $text        = $messageData['conversation'] ?? $messageData['extendedTextMessage']['text'] ?? '';

        if (empty($phoneNumber) || empty($text)) {
            return new JsonResponse(['success' => true, 'message' => 'Dados insuficientes para gravar']);
        }

        $now = date('Y-m-d H:i:s');
        error_log("[WhatsAppSimples Webhook] Gravando mensagem autenticada do numero {$phoneNumber}: {$text}");

        // 6. Localiza ou cria a conversa na fila
        $chatIterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => ['phone_number' => $phoneNumber],
            'LIMIT'  => 1
        ]);

        $chatId = 0;
        if ($row = $chatIterator->current()) {
            $chatId = (int) $row['id'];
            $DB->update('glpi_plugin_whatsappsimples_chats', [
                'contact_name' => $contactName,
                'date_mod'     => $now
            ], ['id' => $chatId]);
        } else {
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

        // 7. Salva a mensagem recebida vinculada ao chat
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