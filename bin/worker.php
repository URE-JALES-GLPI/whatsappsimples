<?php

/**
 * Worker Assíncrono do WhatsAppSimples
 * Deve ser rodado via CLI (linha de comando / systemd)
 */

$glpiRoot = dirname(__DIR__, 3);
if (file_exists($glpiRoot . '/inc/includes.php')) {
    include_once $glpiRoot . '/inc/includes.php';
} else {
    die("Erro: Arquivo includes.php do GLPI não encontrado em {$glpiRoot}\n");
}

use GlpiPlugin\Whatsappsimples\DTO\IncomingMessageDTO;
use GlpiPlugin\Whatsappsimples\Repository\ChatRepository;
use GlpiPlugin\Whatsappsimples\Service\ChatLifecycleService;
use GlpiPlugin\Whatsappsimples\Service\MessageDispatcherService;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use GlpiPlugin\Whatsappsimples\Service\MercurePublisherService;

class RedisWorker
{
    private \Redis $redis;
    private string $redisHost = '10.180.152.29';
    private int $redisPort = 6380;
    private string $queueName = 'ure_mensagens_fila';

    public function __construct()
    {
        $this->connectRedis();
    }

    private function connectRedis()
    {
        try {
            $this->redis = new \Redis();
            $this->redis->connect($this->redisHost, $this->redisPort);
        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Falha ao conectar no Redis: " . $e->getMessage() . "\n";
        }
    }

    public function run()
    {
        echo "[" . date('Y-m-d H:i:s') . "] Worker iniciado! Escutando fila '{$this->queueName}' em {$this->redisHost}:{$this->redisPort}...\n";

        $repository = new ChatRepository();
        $lifecycleService = new ChatLifecycleService($repository);
        $dispatcher = new MessageDispatcherService($repository, $lifecycleService);
        $mercure = new MercurePublisherService();

        while (true) {
            try {
                // Tenta reconectar se a conexao caiu
                if (!$this->redis->isConnected()) {
                    $this->connectRedis();
                    sleep(2);
                    continue;
                }

                // Aguarda infinitamente até chegar mensagem na fila
                $mensagem = $this->redis->blPop($this->queueName, 0);

                if ($mensagem) {
                    $payload = json_decode($mensagem[1], true);
                    echo "[" . date('Y-m-d H:i:s') . "] [NOVA_MENSAGEM] Payload recebido da fila. Processando...\n";

                    $phoneNumber = EvolutionApiService::resolvePhoneNumber($payload);
                    if (empty($phoneNumber)) {
                        echo "[" . date('Y-m-d H:i:s') . "] Ignorado: Numero vazio.\n";
                        continue;
                    }

                    $messageDTO = IncomingMessageDTO::fromPayload($payload, $phoneNumber);
                    if (empty($messageDTO->getText()) && empty($messageDTO->getMediaUrl())) {
                        echo "[" . date('Y-m-d H:i:s') . "] Ignorado: Sem texto e sem midia.\n";
                        continue;
                    }

                    $success = $dispatcher->dispatchIncomingMessage($messageDTO);

                    if ($success) {
                        try {
                            $mercure->publish('chats_ure_jales', [
                                'action'       => 'new_message',
                                'phone_number' => $phoneNumber,
                                'text'         => current(explode("\n", wordwrap(strip_tags($messageDTO->getText()), 50)))
                            ]);
                        } catch (\Exception $e) {
                            echo "[" . date('Y-m-d H:i:s') . "] [ERRO_MERCURE] " . $e->getMessage() . "\n";
                        }
                    }

                    echo "[" . date('Y-m-d H:i:s') . "] [CONCLUIDO] Status: " . ($success ? "Sucesso" : "Falha") . "\n";
                }
            } catch (\Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] [ERRO_FATAL_WORKER] " . $e->getMessage() . "\n";
                sleep(2); 
            }
        }
    }
}

$worker = new RedisWorker();
$worker->run();
