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

            self::logDebug("PAYLOAD_RECEBIDO", ['event' => $payload['event'] ?? '']);

            $event = strtolower($payload['event'] ?? '');
            if ($event !== 'messages.upsert' && $event !== 'messages_upsert') {
                return new JsonResponse(['success' => true, 'message' => 'Evento ignorado: ' . $event]);
            }

            // 2. Envia para a Fila Redis (Processamento Assíncrono via Worker)
            $redisHost = function_exists('wa_env') ? wa_env('REDIS_HOST', '10.180.152.29') : (getenv('REDIS_HOST') ?: '10.180.152.29');
            $redisPort = (int) (function_exists('wa_env') ? wa_env('REDIS_PORT', 6380) : (getenv('REDIS_PORT') ?: 6380));

            try {
                $redis = new \Redis();
                $connected = @$redis->connect($redisHost, $redisPort, 1.5);
                if ($connected) {
                    $redis->rPush('ure_mensagens_fila', json_encode($payload));
                    return new JsonResponse(['success' => true, 'message' => 'Enfileirado com sucesso']);
                }
                throw new \RuntimeException("Não foi possível conectar ao Redis em {$redisHost}:{$redisPort}");
            } catch (\Throwable $redisError) {
                self::logDebug("FALLBACK_SINC_REDIS_OFFLINE", ['error' => $redisError->getMessage()]);

                // Contingência: Processamento Síncrono imediato caso o Redis esteja offline/inacessível
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
            }

        } catch (\Throwable $e) {
            self::logDebug("ERRO_WEBHOOK_EXCEPTION", ['error' => $e->getMessage()]);
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}