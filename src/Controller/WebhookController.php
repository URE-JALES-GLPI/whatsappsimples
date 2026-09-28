<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $logStr = "[" . date('Y-m-d H:i:s') . "] [front/webhook] [$action] " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        \Toolbox::logInFile('whatsappsimples', $logStr, true);
    }

    public function handle(Request $request): JsonResponse
    {
        try {
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

            // Envia o payload puro para a Fila no Redis (Worker assíncrono processará)
            try {
                $redis = new \Redis();
                $redis->connect('10.180.152.29', 6380);
                $redis->rPush('ure_mensagens_fila', json_encode($payload));
                
                return new JsonResponse(['success' => true, 'message' => 'Enfileirado com sucesso']);
            } catch (\Exception $e) {
                self::logDebug("ERRO_FILA_REDIS", ['error' => $e->getMessage()]);
                return new JsonResponse(['success' => false, 'error' => 'Falha ao conectar na Fila Redis'], 500);
            }

        } catch (\Exception $e) {
            self::logDebug("ERRO_WEBHOOK_EXCEPTION", ['error' => $e->getMessage()]);
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}