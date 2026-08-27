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

            // 1. Extração via EvolutionApiService
            $phoneNumber = EvolutionApiService::resolvePhoneNumber($payload);
            
            if (empty($phoneNumber)) {
                return new JsonResponse(['success' => true, 'message' => 'Número de telefone vazio']);
            }

            self::logDebug("NUMERO_RESOLVIDO", ['phoneNumber' => $phoneNumber]);

            // 2. DTO
            $messageDTO = IncomingMessageDTO::fromPayload($payload, $phoneNumber);

            if (empty($messageDTO->getText())) {
                return new JsonResponse(['success' => true, 'message' => 'Sem conteúdo de texto']);
            }

            // 3. Inicializa Dependências
            $repository = new ChatRepository();
            $lifecycleService = new ChatLifecycleService($repository);
            $dispatcher = new MessageDispatcherService($repository, $lifecycleService);

            // 4. Delega tudo para o Service (A mágica da amarração de LID e gravação de histórico acontece aqui)
            $success = $dispatcher->dispatchIncomingMessage($messageDTO);

            return new JsonResponse([
                'success' => $success,
                'message' => $success ? 'Mensagem processada e salva no banco' : 'Falha ao processar mensagem'
            ]);

        } catch (\Exception $e) {
            self::logDebug("ERRO_WEBHOOK_EXCEPTION", ['error' => $e->getMessage()]);
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}