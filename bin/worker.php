<?php

/**
 * Worker Assíncrono do WhatsAppSimples
 * Deve ser rodado via CLI (linha de comando / systemd)
 */

$glpiRoot = dirname(__DIR__, 3);
chdir($glpiRoot);

// 1. Carrega o autoloader do Composer (fornece Toolbox, Symfony e Kernel)
if (file_exists($glpiRoot . '/vendor/autoload.php')) {
    require_once $glpiRoot . '/vendor/autoload.php';
}

// 2. Inicializa o Kernel moderno do GLPI 11/12 se disponível
if (class_exists(\Glpi\Kernel\Kernel::class)) {
    $kernel = new \Glpi\Kernel\Kernel();
    $kernel->boot();
}

// 3. Garante carregamento do $DB e compatibilidade retroativa
global $DB;
if (!$DB && file_exists($glpiRoot . '/inc/includes.php')) {
    include_once $glpiRoot . '/inc/includes.php';
}

if (!extension_loaded('redis') && !class_exists('\Redis')) {
    fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] ERRO: A extensão PHP 'redis' não está instalada no PHP CLI.\n");
    fwrite(STDERR, "Instale no servidor executando: apt update && apt install -y php-redis\n");
    exit(1);
}

if (file_exists(dirname(__DIR__) . '/src/compat.php')) {
    require_once dirname(__DIR__) . '/src/compat.php';
}

// Garante o carregamento das classes do Plugin (PSR-4) mesmo no ambiente CLI puro
spl_autoload_register(function ($class) {
    $prefix = 'GlpiPlugin\\Whatsappsimples\\';
    $base_dir = dirname(__DIR__) . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use GlpiPlugin\Whatsappsimples\DTO\IncomingMessageDTO;
use GlpiPlugin\Whatsappsimples\Repository\ChatRepository;
use GlpiPlugin\Whatsappsimples\Service\ChatLifecycleService;
use GlpiPlugin\Whatsappsimples\Service\MessageDispatcherService;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use GlpiPlugin\Whatsappsimples\Service\MercurePublisherService;

class RedisWorker
{
    private ?\Redis $redis = null;
    private string $redisHost;
    private int $redisPort;
    private string $queueName = 'ure_mensagens_fila';
    private string $errorQueue = 'ure_mensagens_erros';

    public function __construct()
    {
        $this->redisHost = function_exists('wa_env') ? wa_env('REDIS_HOST', '10.180.152.29') : (getenv('REDIS_HOST') ?: '10.180.152.29');
        $this->redisPort = (int) (function_exists('wa_env') ? wa_env('REDIS_PORT', 6380) : (getenv('REDIS_PORT') ?: 6380));
        $this->connectRedis();
    }

    private function connectRedis(): void
    {
        try {
            $this->redis = new \Redis();
            $ok = @$this->redis->connect($this->redisHost, $this->redisPort, 3.0);
            if ($ok) {
                // Configuração vital: Evita que o PHP corte a conexão após 60 segundos de ócio na fila
                $this->redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);
                echo "[" . date('Y-m-d H:i:s') . "] Conectado ao Redis em {$this->redisHost}:{$this->redisPort}\n";
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Falha ao conectar no Redis {$this->redisHost}:{$this->redisPort}\n";
            }
        } catch (\Throwable $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Exceção ao conectar no Redis: " . $e->getMessage() . "\n";
        }
    }

    public function run(): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] Worker iniciado! Escutando fila '{$this->queueName}' em {$this->redisHost}:{$this->redisPort}...\n";

        $repository = new ChatRepository();
        $lifecycleService = new ChatLifecycleService($repository);
        $dispatcher = new MessageDispatcherService($repository, $lifecycleService);

        while (true) {
            $rawMessage = null;
            $payload = null;

            try {
                // Tenta reconectar se a conexão caiu
                if (!$this->redis || !$this->redis->isConnected()) {
                    $this->connectRedis();
                    sleep(2);
                    continue;
                }

                // Aguarda indefinidamente até chegar mensagem na fila (blPop com timeout 0)
                $mensagem = $this->redis->blPop($this->queueName, 0);

                if ($mensagem && !empty($mensagem[1])) {
                    $rawMessage = $mensagem[1];
                    $payload = json_decode($rawMessage, true);
                    echo "[" . date('Y-m-d H:i:s') . "] [NOVA_MENSAGEM] Payload recebido da fila. Processando...\n";

                    if (!$payload || !is_array($payload)) {
                        echo "[" . date('Y-m-d H:i:s') . "] Ignorado: JSON inválido.\n";
                        continue;
                    }

                    $phoneNumber = EvolutionApiService::resolvePhoneNumber($payload);
                    if (empty($phoneNumber)) {
                        echo "[" . date('Y-m-d H:i:s') . "] Ignorado: Número vazio.\n";
                        continue;
                    }

                    $messageDTO = IncomingMessageDTO::fromPayload($payload, $phoneNumber);
                    $dtoText = $messageDTO->getText();
                    $hasText = ($dtoText !== null && trim((string) $dtoText) !== '');
                    $hasMedia = !empty($messageDTO->getMediaUrl());

                    if (!$hasText && !$hasMedia) {
                        echo "[" . date('Y-m-d H:i:s') . "] Ignorado: Sem texto e sem mídia.\n";
                        continue;
                    }

                    $success = $dispatcher->dispatchIncomingMessage($messageDTO);

                    if ($success) {
                        try {
                            MercurePublisherService::notify('new_message', [
                                'phone_number' => $phoneNumber,
                                'text'         => current(explode("\n", wordwrap(strip_tags((string)$messageDTO->getText()), 50)))
                            ]);
                        } catch (\Throwable $e) {
                            echo "[" . date('Y-m-d H:i:s') . "] [AVISO_MERCURE] " . $e->getMessage() . "\n";
                        }
                    }

                    echo "[" . date('Y-m-d H:i:s') . "] [CONCLUIDO] Status: " . ($success ? "Sucesso" : "Falha") . "\n";
                }
            } catch (\Throwable $e) {
                echo "[" . date('Y-m-d H:i:s') . "] [ERRO_FATAL_WORKER] " . $e->getMessage() . "\n";

                // Salva falha na Dead-Letter Queue para que nenhuma mensagem se perca
                if ($rawMessage && $this->redis && $this->redis->isConnected()) {
                    try {
                        $this->redis->rPush($this->errorQueue, json_encode([
                            'error'     => $e->getMessage(),
                            'payload'   => $payload ?? $rawMessage,
                            'failed_at' => date('Y-m-d H:i:s')
                        ], JSON_UNESCAPED_UNICODE));
                    } catch (\Throwable $dlqErr) {
                        // ignore
                    }
                }
                sleep(2); 
            }
        }
    }
}

$worker = new RedisWorker();
$worker->run();

