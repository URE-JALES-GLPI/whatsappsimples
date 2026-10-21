<?php

/**
 * Webhook endpoint (front/) — fallback para GLPI.
 * Usa as novas classes da arquitetura isolada.
 */

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', dirname(__DIR__, 2));
    define('GLPI_API', 1);
    include_once(GLPI_ROOT . "/inc/includes.php");
}

use GlpiPlugin\Whatsappsimples\DTO\IncomingMessageDTO;
use GlpiPlugin\Whatsappsimples\Repository\ChatRepository;
use GlpiPlugin\Whatsappsimples\Service\ChatLifecycleService;
use GlpiPlugin\Whatsappsimples\Service\MessageDispatcherService;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;

header('Content-Type: application/json');

function logWebhook(string $action, array $data = []): void
{
    $logFile = GLPI_ROOT . '/files/_log/whatsappsimples.log';
    $logDir  = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $entry = sprintf("[%s] [front/webhook] [%s] %s\n", date('Y-m-d H:i:s'), $action, json_encode($data, JSON_UNESCAPED_UNICODE));
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

try {
    $expectedToken = EvolutionApiService::getConfig('api_token') ?: (function_exists('wa_env') ? wa_env('EVOLUTION_API_KEY', '') : (getenv('EVOLUTION_API_KEY') ?: ''));
    $providedToken = $_SERVER['HTTP_APIKEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? $_GET['token'] ?? '';

    if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
        logWebhook("ACESSO_NEGADO_TOKEN", [
            'provided' => $providedToken ? substr($providedToken, 0, 4) . '***' : '(vazio)',
            'expected_set' => !empty($expectedToken),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }

    @ini_set('memory_limit', '512M');
    @set_time_limit(120);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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

                    // Se não há worker ativo e há mensagens acumuladas, drena-as agora
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
        echo json_encode(['success' => true, 'message' => 'Webhook ativo', 'status' => $redisStatus]);
        exit;
    }

    $content = file_get_contents('php://input');
    $payload = json_decode($content, true);

    if (!$payload || !is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'JSON inválido']);
        exit;
    }

    logWebhook("PAYLOAD_BRUTO", ['payload' => $content]);

    $event = strtolower($payload['event'] ?? '');
    if ($event !== 'messages.upsert' && $event !== 'messages_upsert') {
        echo json_encode(['success' => true, 'message' => 'Evento ignorado']);
        exit;
    }

    // 1. Envia para a Fila Redis APENAS se o Worker estiver ativo (comprovado via heartbeat)
    $redisHost = function_exists('wa_env') ? wa_env('REDIS_HOST', '10.180.152.29') : (getenv('REDIS_HOST') ?: '10.180.152.29');
    $redisPort = (int) (function_exists('wa_env') ? wa_env('REDIS_PORT', 6380) : (getenv('REDIS_PORT') ?: 6380));

    if (class_exists('\Redis')) {
        try {
            $redis = new \Redis();
            if (@$redis->connect($redisHost, $redisPort, 1.0)) {
                $workerAlive = (bool) $redis->get('ure_worker_heartbeat');
                if ($workerAlive) {
                    $redis->rPush('ure_mensagens_fila', json_encode($payload));
                    logWebhook("ENFILEIRADO_REDIS", ['event' => $event]);
                    echo json_encode(['success' => true, 'message' => 'Enfileirado com sucesso']);
                    exit;
                }

                // Worker offline: resgata mensagens presas na fila antes de processar síncrono
                $pendingCount = (int) $redis->lLen('ure_mensagens_fila');
                if ($pendingCount > 0) {
                    logWebhook("DRENANDO_FILA_SEM_WORKER", ['count' => $pendingCount]);
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
        } catch (\Throwable $re) {
            logWebhook("AVISO_REDIS_FALLBACK", ['error' => $re->getMessage()]);
        }
    }

    // 2. Fallback de Contingência: Se o Redis estiver offline, processa de forma síncrona
    $phoneNumber = EvolutionApiService::resolvePhoneNumber($payload);
    
    if (empty($phoneNumber)) {
        echo json_encode(['success' => true, 'message' => 'Número vazio']);
        exit;
    }

    logWebhook("NUMERO_RESOLVIDO", ['phone' => $phoneNumber]);

    // 2. DTO
    $messageDTO = IncomingMessageDTO::fromPayload($payload, $phoneNumber);

    $dtoText = $messageDTO->getText();
    $hasText = ($dtoText !== null && trim((string) $dtoText) !== '');
    $hasMedia = !empty($messageDTO->getMediaUrl());

    if (!$hasText && !$hasMedia) {
        echo json_encode(['success' => true, 'message' => 'Sem conteúdo de texto ou mídia']);
        exit;
    }

    // 3. Inicializa Dependências da nova arquitetura
    $repository = new ChatRepository();
    $lifecycleService = new ChatLifecycleService($repository);
    $dispatcher = new MessageDispatcherService($repository, $lifecycleService);

    // 4. Delega o processamento
    $success = $dispatcher->dispatchIncomingMessage($messageDTO);

    if ($success) {
        \GlpiPlugin\Whatsappsimples\Service\MercurePublisherService::notify('new_message', ['phone_number' => $phoneNumber]);
        echo json_encode(['success' => true, 'message' => 'Mensagem processada']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Falha ao processar mensagem no despachante']);
    }

} catch (\Throwable $e) {
    logWebhook("ERRO_EXCEPTION", ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
