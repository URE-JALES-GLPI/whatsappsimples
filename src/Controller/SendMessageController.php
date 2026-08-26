<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SendMessageController extends AbstractController
{
    #[Route('/ajax/send', name: 'whatsappsimples_api_send', methods: ['GET', 'POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        try {
            Session::checkLoginUser();
            global $DB;

            if (!$DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
                return new JsonResponse(['success' => false, 'error' => 'Tabela glpi_plugin_whatsappsimples_chats não existe'], 400);
            }

            $chatId      = (int) ($request->request->get('chat_id') ?? $request->query->get('chat_id') ?? 0);
            $phoneNumber = trim((string) ($request->request->get('phone_number') ?? $request->query->get('phone_number') ?? ''));
            $text        = trim((string) ($request->request->get('text') ?? $request->query->get('text') ?? ''));

            self::logDebug("REQUISICAO_ENVIO_RECEBIDA", ['chat_id' => $chatId, 'phone_number' => $phoneNumber, 'has_text' => !empty($text), 'has_file' => $request->files->has('file')]);

            if ($chatId <= 0 && empty($phoneNumber)) {
                return new JsonResponse(['success' => false, 'error' => 'Dados inválidos: informe o atendimento ou número'], 400);
            }

            $currentUserId = (int) Session::getLoginUserID();
            $now           = date('Y-m-d H:i:s');

            $chat = null;
            if ($chatId > 0) {
                $chat = $DB->request([
                    'SELECT' => ['id', 'phone_number', 'first_response_date', 'users_id'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                    'WHERE'  => ['id' => $chatId],
                    'LIMIT'  => 1
                ])->current();
            }

            if (!$chat && !empty($phoneNumber)) {
                $chat = $DB->request([
                    'SELECT' => ['id', 'phone_number', 'first_response_date', 'users_id'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                    'WHERE'  => ['phone_number' => $phoneNumber],
                    'ORDER'  => 'id DESC',
                    'LIMIT'  => 1
                ])->current();

                if (!$chat) {
                    $DB->insert('glpi_plugin_whatsappsimples_chats', [
                        'phone_number'  => $phoneNumber,
                        'contact_name'  => $phoneNumber,
                        'users_id'      => $currentUserId,
                        'status'        => 'in_progress',
                        'date_creation' => $now,
                        'date_mod'      => $now
                    ]);
                    $chatId = (int) $DB->insertId();
                    $chat   = [
                        'id'                  => $chatId,
                        'phone_number'        => $phoneNumber,
                        'first_response_date' => null,
                        'users_id'            => $currentUserId
                    ];
                } else {
                    $chatId = (int) $chat['id'];
                }
            }

            if (!$chat) {
                return new JsonResponse(['success' => false, 'error' => 'Atendimento não encontrado no banco de dados'], 404);
            }

            $phoneToUpdate = (string) $chat['phone_number'];

            // 1. Envio de Arquivos de Mídia
            $uploadedFile = $request->files->get('file');
            if ($uploadedFile) {
                $fileName  = $uploadedFile->getClientOriginalName();
                $mimeType  = $uploadedFile->getClientMimeType() ?: 'application/octet-stream';
                $fileData  = file_get_contents($uploadedFile->getPathname());
                $base64    = 'data:' . $mimeType . ';base64,' . base64_encode($fileData);

                $mediaType = 'document';
                if (str_starts_with($mimeType, 'image/')) {
                    $mediaType = 'image';
                } elseif (str_starts_with($mimeType, 'audio/')) {
                    $mediaType = 'audio';
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $mediaType = 'video';
                }

                $result = EvolutionApiService::sendMedia($chatId, $phoneToUpdate, $mediaType, $base64, $fileName, $text);
                
                // Atribui o contato ao usuário logado em TODAS as linhas do número para remover da Fila
                $DB->update('glpi_plugin_whatsappsimples_chats', [
                    'users_id' => $currentUserId,
                    'status'   => 'in_progress',
                    'date_mod' => $now
                ], ['phone_number' => $phoneToUpdate]);

                self::logDebug("RESULTADO_ENVIO_MIDIA", $result);
                return new JsonResponse($result);
            }

            if (empty($text)) {
                return new JsonResponse(['success' => false, 'error' => 'Digite um texto ou selecione um arquivo para enviar'], 400);
            }

            // 2. Envio de Mensagem de Texto
            $result = EvolutionApiService::sendMessage($chatId, $phoneToUpdate, $text);
            self::logDebug("RESULTADO_ENVIO_TEXTO", $result);

            if (!empty($result['success'])) {
                $updateData = [
                    'users_id' => $currentUserId,
                    'status'   => 'in_progress',
                    'date_mod' => $now
                ];

                if (empty($chat['first_response_date'])) {
                    $updateData['first_response_date'] = $now;
                }

                // Atualiza o status de TODOS os registros deste telefone para 'in_progress' e 'users_id = currentUserId'
                $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['phone_number' => $phoneToUpdate]);
            }

            return new JsonResponse($result);

        } catch (\Throwable $e) {
            self::logDebug("ERRO_EXCEPTION_SEND_MESSAGE", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return new JsonResponse([
                'success' => false,
                'error'   => 'Erro interno ao processar envio: ' . $e->getMessage()
            ], 200);
        }
    }

    private static function logDebug(string $action, array $data = []): void
    {
        $logFile = GLPI_ROOT . '/files/_log/whatsappsimples.log';
        $logDir  = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $entry = sprintf("[%s] [SendMessageController] [%s] %s\n", date('Y-m-d H:i:s'), $action, json_encode($data, JSON_UNESCAPED_UNICODE));
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
