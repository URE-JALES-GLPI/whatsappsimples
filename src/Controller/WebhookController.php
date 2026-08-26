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
        try {
            global $DB;

            $expectedToken = EvolutionApiService::getConfig('api_token') ?: 'ure_jales_evolution_token_2026';

            $providedToken = $request->headers->get('apikey') 
                ?? $request->headers->get('x-api-key') 
                ?? $request->query->get('token') 
                ?? '';

            if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
                self::logDebug("ACESSO_NEGADO_TOKEN", ['provided' => $providedToken]);
                return new JsonResponse(['success' => false, 'error' => 'Acesso negado: Token de autenticação inválido ou ausente'], 401);
            }

            if ($request->isMethod('GET')) {
                return new JsonResponse(['success' => true, 'message' => 'Webhook do WhatsAppSimples autenticado e ativo!']);
            }

            $content = $request->getContent();
            $payload = json_decode($content, true);

            if (!$payload || !is_array($payload)) {
                return new JsonResponse(['success' => false, 'error' => 'Payload JSON inválido'], 400);
            }

            $event = strtolower($payload['event'] ?? '');
            if ($event !== 'messages.upsert' && $event !== 'messages_upsert') {
                return new JsonResponse(['success' => true, 'message' => 'Evento ignorado']);
            }

            $data = $payload['data'] ?? [];
            $key  = $data['key'] ?? [];

            if (!empty($key['fromMe'])) {
                return new JsonResponse(['success' => true, 'message' => 'Mensagem própria ignorada']);
            }

            // ISOLAMENTO TOTAL POR NUMERO DE TELEFONE:
            // Prioriza JID real do WhatsApp com @s.whatsapp.net (ex: 5517996194229)
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

            // 1. Busca se já existe um CHAT ATIVO EXCLUSIVO para este número de telefone (phone_number)
            $activeChat = $DB->request([
                'SELECT' => ['id', 'status', 'users_id'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'WHERE'  => [
                    'phone_number' => $phoneNumber,
                    'status'       => ['pending', 'in_progress']
                ],
                'ORDER'  => 'id DESC',
                'LIMIT'  => 1
            ])->current();

            $chatId = 0;
            if ($activeChat) {
                $chatId = (int) $activeChat['id'];
                $DB->update('glpi_plugin_whatsappsimples_chats', [
                    'contact_name' => $contactName,
                    'date_mod'     => $now
                ], ['id' => $chatId]);
                self::logDebug("CHAT_ATIVO_ENCONTRADO", ['chat_id' => $chatId, 'phone' => $phoneNumber]);
            } else {
                $previousChat = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                    'WHERE'  => ['phone_number' => $phoneNumber],
                    'ORDER'  => 'id ASC',
                    'LIMIT'  => 1
                ])->current();

                if ($previousChat) {
                    $chatId = (int) $previousChat['id'];
                    $DB->update('glpi_plugin_whatsappsimples_chats', [
                        'contact_name' => $contactName,
                        'status'       => 'pending',
                        'users_id'     => 0,
                        'date_mod'     => $now
                    ], ['id' => $chatId]);
                    self::logDebug("CHAT_REABERTO_NA_FILA", ['chat_id' => $chatId, 'phone' => $phoneNumber]);
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
                    self::logDebug("NOVO_CHAT_CRIADO_NA_FILA", ['chat_id' => $chatId, 'phone' => $phoneNumber]);
                }
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

            return new JsonResponse(['success' => true, 'message' => 'Mensagem recebida e registrada com sucesso']);

        } catch (\Throwable $e) {
            self::logDebug("ERRO_WEBHOOK_EXCEPTION", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private static function logDebug(string $action, array $data = []): void
    {
        $logFile = GLPI_ROOT . '/files/_log/whatsappsimples.log';
        $logDir  = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $entry = sprintf("[%s] [WebhookController] [%s] %s\n", date('Y-m-d H:i:s'), $action, json_encode($data, JSON_UNESCAPED_UNICODE));
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}