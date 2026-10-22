<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use GlpiPlugin\Whatsappsimples\DTO\IncomingMessageDTO;
use GlpiPlugin\Whatsappsimples\Repository\ChatRepository;
use GlpiPlugin\Whatsappsimples\Service\ChatLifecycleService;
use GlpiPlugin\Whatsappsimples\Service\MessageDispatcherService;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;

class WebhookController
{
    /**
     * @param string $action Ação do log (ex: "WEBHOOK_START")
     * @param array $data Dados extras para o log
     */
    private static function logDebug(string $action, array $data = []): void
    {
        $logStr = "[" . date('Y-m-d H:i:s') . "] [WebhookController] [$action] " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        \Toolbox::logInFile('whatsappsimples', $logStr, true);
    }

    #[Route('/webhook', name: 'whatsappsimples_webhook', methods: ['GET', 'POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            // 1. Validação de Token de Autenticação
            $expectedToken = EvolutionApiService::getConfig('api_token') ?: (function_exists('wa_env') ? wa_env('EVOLUTION_API_KEY', '') : (getenv('EVOLUTION_API_KEY') ?: ''));
            $providedToken = $request->headers->get('apikey') 
                ?? $request->headers->get('x-api-key') 
                ?? $request->query->get('token') 
                ?? '';

            if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
                self::logDebug("ACESSO_NEGADO_TOKEN", [
                    'provided' => $providedToken ? substr($providedToken, 0, 4) . '***' : '(vazio)',
                    'expected_set' => !empty($expectedToken),
                    'ip' => $request->getClientIp()
                ]);
                return new JsonResponse(['success' => false, 'error' => 'Acesso negado'], 401);
            }

            if ($request->isMethod('GET')) {
                $redisStatus = ['connected' => false, 'worker_alive' => false, 'pending_messages' => 0, 'drained_messages' => 0];
                $redisHost = function_exists('wa_env') ? wa_env('REDIS_HOST', '10.180.152.29') : (getenv('REDIS_HOST') ?: '10.180.152.29');
                $redisPort = (int) (function_exists('wa_env') ? wa_env('REDIS_PORT', 6380) : (getenv('REDIS_PORT') ?: 6380));
                if (class_exists('\Redis')) {
                    try {
                        $redis = new \Redis();
                        if (@$redis->connect($redisHost, $redisPort, 1.0)) {
                            $redisStatus['connected'] = true;
                            $redisStatus['worker_alive'] = (bool) $redis->get('ure_worker_heartbeat');
                            $redisStatus['pending_messages'] = (int) $redis->lLen('ure_mensagens_fila');

                            if (!$redisStatus['worker_alive'] && $redisStatus['pending_messages'] > 0) {
                                $repository = new ChatRepository();
                                $lifecycleService = new ChatLifecycleService($repository);
                                $dispatcher = new MessageDispatcherService($repository, $lifecycleService);

                                while ($stuckJson = $redis->lPop('ure_mensagens_fila')) {
                                    $stuckPayload = json_decode($stuckJson, true);
                                    if ($stuckPayload && is_array($stuckPayload)) {
                                        $stuckPhone = EvolutionApiService::resolvePhoneNumber($stuckPayload);
                                        if (!empty($stuckPhone)) {
                                            $stuckDTO = IncomingMessageDTO::fromPayload($stuckPayload, $stuckPhone);
                                            if ($stuckDTO->getText() !== '' || !empty($stuckDTO->getMediaUrl())) {
                                                if ($dispatcher->dispatchIncomingMessage($stuckDTO)) {
                                                    $redisStatus['drained_messages']++;
                                                    \GlpiPlugin\Whatsappsimples\Service\MercurePublisherService::notify('new_message', ['phone_number' => $stuckPhone]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        $redisStatus['error'] = $e->getMessage();
                    }
                }
                return new JsonResponse(['success' => true, 'message' => 'Webhook ativo', 'status' => $redisStatus]);
            }

            $content = $request->getContent();
            $contentLen = strlen($content);
            $headerLen = (int) ($request->server->get('CONTENT_LENGTH') ?? 0);

            if ($headerLen > 0 && $contentLen === 0) {
                self::logDebug("ERRO_POST_MAX_SIZE_EXCEDIDO", [
                    'content_length_header' => $headerLen,
                    'php_post_max_size'     => ini_get('post_max_size'),
                    'msg'                   => 'Corpo da requisição foi descartado pelo PHP antes de chegar ao webhook. Aumente post_max_size e upload_max_filesize no php.ini do Apache/PHP-FPM.'
                ]);
                return new JsonResponse([
                    'success' => false,
                    'error'   => "Payload too large. Server post_max_size is " . ini_get('post_max_size') . ", but request size is {$headerLen} bytes."
                ], 413);
            }

            $payload = json_decode($content, true);

            if (!$payload || !is_array($payload)) {
                self::logDebug("JSON_INVALIDO", [
                    'content_length' => $contentLen,
                    'json_error'     => json_last_error_msg(),
                    'snippet'        => substr($content, 0, 200)
                ]);
                return new JsonResponse(['success' => false, 'error' => 'JSON inválido'], 400);
            }

            $logSummary = $payload;
            if (!empty($logSummary['data']['base64'])) {
                $logSummary['data']['base64'] = '[BASE64_OMITIDO_LEN_' . strlen($logSummary['data']['base64']) . '_BYTES]';
            }
            if (!empty($logSummary['base64'])) {
                $logSummary['base64'] = '[BASE64_OMITIDO_LEN_' . strlen($logSummary['base64']) . '_BYTES]';
            }
            self::logDebug("PAYLOAD_RECEBIDO", [
                'event'   => $payload['event'] ?? '',
                'size'    => $contentLen,
                'summary' => $logSummary
            ]);

            $event = strtolower($payload['event'] ?? '');
            if ($event !== 'messages.upsert' && $event !== 'messages_upsert') {
                return new JsonResponse(['success' => true, 'message' => 'Evento ignorado: ' . $event]);
            }

            // 2. Envia para a Fila Redis APENAS se o Worker estiver ativo (comprovado via heartbeat)
            $redisHost = function_exists('wa_env') ? wa_env('REDIS_HOST', '10.180.152.29') : (getenv('REDIS_HOST') ?: '10.180.152.29');
            $redisPort = (int) (function_exists('wa_env') ? wa_env('REDIS_PORT', 6380) : (getenv('REDIS_PORT') ?: 6380));

            if (class_exists('\Redis')) {
                try {
                    $redis = new \Redis();
                    $connected = @$redis->connect($redisHost, $redisPort, 1.5);
                    if ($connected) {
                        $workerAlive = (bool) $redis->get('ure_worker_heartbeat');
                        if ($workerAlive) {
                            $redis->rPush('ure_mensagens_fila', json_encode($payload));
                            return new JsonResponse(['success' => true, 'message' => 'Enfileirado com sucesso']);
                        }

                        // Worker offline: resgata mensagens presas na fila antes de processar síncrono
                        $pendingCount = (int) $redis->lLen('ure_mensagens_fila');
                        if ($pendingCount > 0) {
                            self::logDebug("DRENANDO_FILA_SEM_WORKER", ['count' => $pendingCount]);
                            $repository = new ChatRepository();
                            $lifecycleService = new ChatLifecycleService($repository);
                            $dispatcher = new MessageDispatcherService($repository, $lifecycleService);

                            while ($stuckJson = $redis->lPop('ure_mensagens_fila')) {
                                $stuckPayload = json_decode($stuckJson, true);
                                if ($stuckPayload && is_array($stuckPayload)) {
                                    $stuckPhone = EvolutionApiService::resolvePhoneNumber($stuckPayload);
                                    if (!empty($stuckPhone)) {
                                        $stuckDTO = IncomingMessageDTO::fromPayload($stuckPayload, $stuckPhone);
                                        if ($stuckDTO->getText() !== '' || !empty($stuckDTO->getMediaUrl())) {
                                            $dispatcher->dispatchIncomingMessage($stuckDTO);
                                            \GlpiPlugin\Whatsappsimples\Service\MercurePublisherService::notify('new_message', ['phone_number' => $stuckPhone]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $redisError) {
                    self::logDebug("AVISO_REDIS_FALLBACK", ['error' => $redisError->getMessage()]);
                }
            }

            // 3. Processamento Síncrono imediato (quando worker não está rodando ou Redis offline)
            $phoneNumber = EvolutionApiService::resolvePhoneNumber($payload);
                if (empty($phoneNumber)) {
                    return new JsonResponse(['success' => true, 'message' => 'Número vazio']);
                }

                $messageDTO = IncomingMessageDTO::fromPayload($payload, $phoneNumber);
                $dtoText = $messageDTO->getText();
                $hasText = ($dtoText !== null && trim((string) $dtoText) !== '');
                $hasMedia = !empty($messageDTO->getMediaUrl());

                if (!$hasText && !$hasMedia) {
                    return new JsonResponse(['success' => true, 'message' => 'Sem conteúdo de texto ou mídia']);
                }

                $repository = new ChatRepository();
                $lifecycleService = new ChatLifecycleService($repository);
                $dispatcher = new MessageDispatcherService($repository, $lifecycleService);

                $success = $dispatcher->dispatchIncomingMessage($messageDTO);
                if ($success) {
                    \GlpiPlugin\Whatsappsimples\Service\MercurePublisherService::notify('new_message', ['phone_number' => $phoneNumber]);
                    return new JsonResponse(['success' => true, 'message' => 'Processado via fallback síncrono']);
                }

                return new JsonResponse(['success' => false, 'error' => 'Falha ao processar mensagem no despachante'], 500);

        } catch (\Throwable $e) {
            self::logDebug("ERRO_WEBHOOK_EXCEPTION", ['error' => $e->getMessage()]);
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}