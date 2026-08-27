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
    // 0. Auto-Heal: Garante que as colunas do banco (ex: unread_count) existem antes de prosseguir
    EvolutionApiService::ensureMessageColumns();

    $expectedToken = EvolutionApiService::getConfig('api_token') ?: 'ure_jales_evolution_token_2026';
    $providedToken = $_SERVER['HTTP_APIKEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? $_GET['token'] ?? '';

    if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['success' => true, 'message' => 'Webhook ativo']);
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

    // 1. Extração via EvolutionApiService
    $phoneNumber = EvolutionApiService::resolvePhoneNumber($payload);
    
    if (empty($phoneNumber)) {
        echo json_encode(['success' => true, 'message' => 'Número vazio']);
        exit;
    }

    logWebhook("NUMERO_RESOLVIDO", ['phone' => $phoneNumber]);

    // 2. DTO
    $messageDTO = IncomingMessageDTO::fromPayload($payload, $phoneNumber);

    if (empty($messageDTO->getText())) {
        echo json_encode(['success' => true, 'message' => 'Sem conteúdo de texto']);
        exit;
    }

    // 3. Inicializa Dependências da nova arquitetura
    $repository = new ChatRepository();
    $lifecycleService = new ChatLifecycleService($repository);
    $dispatcher = new MessageDispatcherService($repository, $lifecycleService);

    // 4. Delega o processamento
    $success = $dispatcher->dispatchIncomingMessage($messageDTO);

    if ($success) {
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
