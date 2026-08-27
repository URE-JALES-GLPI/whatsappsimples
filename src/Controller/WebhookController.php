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

            $data  = $payload['data'] ?? [];
            $key   = $data['key'] ?? [];
            $isFromMe = !empty($key['fromMe']);

            // 1. Extração profunda e infalível do número de celular real (55...) diretamente do payload
            $phoneNumber = EvolutionApiService::extractRealPhoneNumberFromPayload($payload);

            // 2. Se o payload continha apenas um LID e o número extraído não inicia com 55, consulta a EvolutionAPI ao vivo
            if (!empty($phoneNumber) && (!str_starts_with($phoneNumber, '55') || strlen($phoneNumber) > 13)) {
                $resolved = EvolutionApiService::fetchRealJid($phoneNumber);
                if (!empty($resolved)) {
                    $phoneNumber = $resolved;
                }
            }

            $contactName = $data['pushName'] ?? 'Contato não salvo';
            $messageId   = $key['id'] ?? '';

            $messageData = $data['message'] ?? [];
            $text        = $messageData['conversation'] ?? $messageData['extendedTextMessage']['text'] ?? '';

            if (empty($phoneNumber) || empty($text)) {
                return new JsonResponse(['success' => true, 'message' => 'Dados insuficientes para gravar']);
            }

            $now = date('Y-m-d H:i:s');

            // 3. Localiza se já existe um CHAT ATIVO para este número de telefone
            $activeChat = $DB->request([
                'SELECT' => ['id', 'status', 'users_id', 'contact_name', 'phone_number'],
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
                    'contact_name' => ($isFromMe && !empty($activeChat['contact_name'])) ? $activeChat['contact_name'] : $contactName,
                    'date_mod'     => $now
                ], ['id' => $chatId]);
                self::logDebug("CHAT_ATIVO_ENCONTRADO", ['chat_id' => $chatId, 'phone' => $phoneNumber, 'from_me' => $isFromMe]);
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
                        'status'   => $isFromMe ? 'in_progress' : 'pending',
                        'date_mod' => $now
                    ], ['id' => $chatId]);
                    self::logDebug("CHAT_REABERTO", ['chat_id' => $chatId, 'phone' => $phoneNumber, 'from_me' => $isFromMe]);
                } else {
                    $DB->insert('glpi_plugin_whatsappsimples_chats', [
                        'phone_number'  => $phoneNumber,
                        'contact_name'  => $contactName,
                        'users_id'      => 0,
                        'status'        => $isFromMe ? 'in_progress' : 'pending',
                        'date_creation' => $now,
                        'date_mod'      => $now
                    ]);
                    $chatId = (int) $DB->insertId();
                    self::logDebug("NOVO_CHAT_CRIADO", ['chat_id' => $chatId, 'phone' => $phoneNumber, 'from_me' => $isFromMe]);
                }
            }

            if ($chatId > 0) {
                $senderType = $isFromMe ? 'attendant' : 'user';

                $alreadyExists = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_messages',
                    'WHERE'  => ['message_id' => $messageId],
                    'LIMIT'  => 1
                ])->count() > 0;

                if (!$alreadyExists) {
                    $DB->insert('glpi_plugin_whatsappsimples_messages', [
                        'chats_id'      => $chatId,
                        'users_id'      => 0,
                        'message_id'    => $messageId,
                        'sender_type'   => $senderType,
                        'message_text'  => $text,
                        'date_creation' => $now
                    ]);
                    self::logDebug("MENSAGEM_REGISTRADA", ['chat_id' => $chatId, 'sender_type' => $senderType, 'message_id' => $messageId]);
                }
            }

            return new JsonResponse(['success' => true, 'message' => 'Mensagem processada com sucesso']);

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