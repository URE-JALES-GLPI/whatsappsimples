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
                return new JsonResponse(['success' => false, 'error' => 'Acesso negado'], 401);
            }

            if ($request->isMethod('GET')) {
                return new JsonResponse(['success' => true, 'message' => 'Webhook ativo']);
            }

            $content = $request->getContent();
            $payload = json_decode($content, true);

            if (!$payload || !is_array($payload)) {
                return new JsonResponse(['success' => false, 'error' => 'JSON inválido'], 400);
            }

            // LOG COMPLETO DO PAYLOAD BRUTO (para diagnóstico)
            self::logDebug("PAYLOAD_BRUTO_RECEBIDO", ['payload' => $content]);

            $event = strtolower($payload['event'] ?? '');
            if ($event !== 'messages.upsert' && $event !== 'messages_upsert') {
                return new JsonResponse(['success' => true, 'message' => 'Evento ignorado: ' . $event]);
            }

            $data  = $payload['data'] ?? [];
            $key   = $data['key'] ?? [];
            $isFromMe = !empty($key['fromMe']);

            // ══════════════════════════════════════════
            // RESOLUÇÃO DO NÚMERO — O PONTO CENTRAL
            // ══════════════════════════════════════════
            $phoneNumber = EvolutionApiService::resolvePhoneNumber($payload);

            if (empty($phoneNumber)) {
                self::logDebug("PHONE_VAZIO_DESCARTANDO", []);
                return new JsonResponse(['success' => true, 'message' => 'Número de telefone vazio']);
            }

            self::logDebug("NUMERO_RESOLVIDO", ['phoneNumber' => $phoneNumber, 'isLidFormat' => !EvolutionApiService::isValidBrazilianNumber($phoneNumber)]);

            $contactName = $data['pushName'] ?? $phoneNumber;
            $messageId   = $key['id'] ?? ('msg_' . time() . '_' . rand(100, 999));

            // Extração do conteúdo da mensagem
            $messageData = $data['message'] ?? [];
            $text = $messageData['conversation'] 
                ?? $messageData['extendedTextMessage']['text'] 
                ?? $messageData['imageMessage']['caption'] 
                ?? $messageData['videoMessage']['caption'] 
                ?? $messageData['documentMessage']['caption'] 
                ?? '';

            if (empty($text) && !empty($messageData['imageMessage'])) {
                $text = '📷 Imagem recebida';
            } elseif (empty($text) && !empty($messageData['audioMessage'])) {
                $text = '🎵 Áudio recebido';
            } elseif (empty($text) && !empty($messageData['documentMessage'])) {
                $text = '📄 Documento recebido';
            }

            if (empty($text)) {
                return new JsonResponse(['success' => true, 'message' => 'Sem conteúdo de texto']);
            }

            $now = date('Y-m-d H:i:s');

            // ══════════════════════════════════════════
            // BUSCA OU CRIA O CHAT
            // ══════════════════════════════════════════
            $activeChat = $DB->request([
                'SELECT' => ['id', 'status', 'users_id', 'contact_name'],
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
                $updateData = ['date_mod' => $now];
                if (!$isFromMe && !empty($contactName) && $contactName !== $phoneNumber) {
                    $updateData['contact_name'] = $contactName;
                }
                $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['id' => $chatId]);
                self::logDebug("CHAT_ATIVO_ENCONTRADO", ['chat_id' => $chatId, 'phone' => $phoneNumber]);
            } else {
                // Procura chat existente (fechado) para este número
                $previousChat = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                    'WHERE'  => ['phone_number' => $phoneNumber],
                    'ORDER'  => 'id ASC',
                    'LIMIT'  => 1
                ])->current();

                if ($previousChat) {
                    $chatId = (int) $previousChat['id'];
                    $updateData = [
                        'status'   => $isFromMe ? 'in_progress' : 'pending',
                        'date_mod' => $now
                    ];
                    if (!$isFromMe && !empty($contactName) && $contactName !== $phoneNumber) {
                        $updateData['contact_name'] = $contactName;
                    }
                    $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['id' => $chatId]);
                    self::logDebug("CHAT_REABERTO", ['chat_id' => $chatId, 'phone' => $phoneNumber]);
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
                    self::logDebug("NOVO_CHAT_CRIADO", ['chat_id' => $chatId, 'phone' => $phoneNumber, 'contactName' => $contactName]);
                }
            }

            // ══════════════════════════════════════════
            // GRAVA A MENSAGEM (com deduplicação)
            // ══════════════════════════════════════════
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
                    self::logDebug("MSG_REGISTRADA", ['chat_id' => $chatId, 'sender' => $senderType, 'text' => mb_substr($text, 0, 50)]);
                }
            }

            return new JsonResponse(['success' => true, 'message' => 'Mensagem processada']);

        } catch (\Throwable $e) {
            self::logDebug("ERRO_EXCEPTION", ['error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
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