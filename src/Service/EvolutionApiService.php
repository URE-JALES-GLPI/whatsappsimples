<?php

namespace GlpiPlugin\Whatsappsimples\Service;

class EvolutionApiService
{
    private static function getDefaultConfig(string $name): string
    {
        return match ($name) {
            'server_url'    => 'http://10.180.152.29:8080',
            'api_token'     => 'ure_jales_evolution_token_2026',
            'instance_name' => 'atendimento',
            default         => ''
        };
    }

    /**
     * Busca uma configuração salva na tabela glpi_plugin_whatsappsimples_configs
     */
    public static function getConfig(string $name): string
    {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_whatsappsimples_configs')) {
            return self::getDefaultConfig($name);
        }

        $row = $DB->request([
            'SELECT' => ['value'],
            'FROM'   => 'glpi_plugin_whatsappsimples_configs',
            'WHERE'  => ['name' => $name],
            'LIMIT'  => 1
        ])->current();

        $val = $row ? (string) $row['value'] : '';
        return !empty($val) ? $val : self::getDefaultConfig($name);
    }

    /**
     * Salva ou atualiza uma configuração no banco do GLPI
     */
    public static function setConfig(string $name, string $value): void
    {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_whatsappsimples_configs')) {
            return;
        }

        $row = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_whatsappsimples_configs',
            'WHERE'  => ['name' => $name],
            'LIMIT'  => 1
        ])->current();

        if ($row) {
            $DB->update('glpi_plugin_whatsappsimples_configs', ['value' => $value], ['name' => $name]);
        } else {
            $DB->insert('glpi_plugin_whatsappsimples_configs', ['name' => $name, 'value' => $value]);
        }
    }

    /**
     * Cadastra a URL do Webhook do GLPI na EvolutionAPI para garantir o recebimento de mensagens
     */
    public static function setWebhook(string $webhookUrl): array
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['success' => false, 'error' => 'Configurações incompletas'];
        }

        $endpoint = "{$baseUrl}/webhook/set/{$instance}";
        $bodyData = [
            'enabled'         => true,
            'url'             => $webhookUrl,
            'webhookByEvents' => false,
            'events'          => ['MESSAGES_UPSERT']
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken
            ],
            CURLOPT_POSTFIELDS     => json_encode($bodyData),
            CURLOPT_TIMEOUT        => 10
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'response' => json_decode($responseBody, true)];
        }

        return ['success' => false, 'error' => "HTTP {$httpCode}: {$responseBody}"];
    }

    /**
     * Verifica o estado da conexão do WhatsApp na EvolutionAPI (open, close, connecting)
     */
    public static function getConnectionState(): array
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['success' => false, 'state' => 'unconfigured', 'error' => 'Configurações incompletas'];
        }

        $endpoint = "{$baseUrl}/instance/connectionState/{$instance}";
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken
            ],
            CURLOPT_TIMEOUT        => 10
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data  = json_decode($responseBody, true);
            $state = $data['instance']['state'] ?? $data['state'] ?? 'close';
            return ['success' => true, 'state' => $state];
        }

        return ['success' => false, 'state' => 'close', 'error' => "HTTP {$httpCode}"];
    }

    /**
     * Cria a instância na EvolutionAPI se ela ainda não existir
     */
    public static function createInstance(): array
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['success' => false, 'error' => 'Configurações incompletas'];
        }

        $endpoint = "{$baseUrl}/instance/create";
        $bodyData = [
            'instanceName' => $instance,
            'token'        => $apiToken,
            'qrcode'       => true
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken
            ],
            CURLOPT_POSTFIELDS     => json_encode($bodyData),
            CURLOPT_TIMEOUT        => 15
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data   = json_decode($responseBody, true);
            $base64 = $data['qrcode']['base64'] ?? $data['base64'] ?? '';
            return ['success' => true, 'base64' => $base64];
        }

        return ['success' => false, 'error' => "HTTP {$httpCode}: {$responseBody}"];
    }

    /**
     * Busca o QR Code de conexão (Base64) da EvolutionAPI. Se a instância não existir, cria automaticamente!
     */
    public static function getQrCode(): array
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['success' => false, 'error' => 'Configurações incompletas'];
        }

        $endpoint = "{$baseUrl}/instance/connect/{$instance}";
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken
            ],
            CURLOPT_TIMEOUT        => 15
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data   = json_decode($responseBody, true);
            $base64 = $data['base64'] ?? $data['qrcode']['base64'] ?? '';
            $code   = $data['code'] ?? $data['qrcode']['code'] ?? '';
            return ['success' => true, 'base64' => $base64, 'code' => $code];
        }

        // Se a instância não existia (404), tenta criar automaticamente
        if ($httpCode === 404) {
            return self::createInstance();
        }

        return ['success' => false, 'error' => "HTTP {$httpCode}: {$responseBody}"];
    }

    /**
     * Envia mensagem de texto via EvolutionAPI e grava no banco do GLPI
     */
    public static function sendMessage(int $chatId, string $phoneNumber, string $text): array
    {
        global $DB;

        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['success' => false, 'error' => 'Configuracoes da EvolutionAPI incompletas'];
        }

        $endpoint = "{$baseUrl}/message/sendText/{$instance}";
        $bodyData = [
            'number' => $phoneNumber,
            'text'   => $text
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken
            ],
            CURLOPT_POSTFIELDS     => json_encode($bodyData),
            CURLOPT_TIMEOUT        => 15
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($responseBody, true);
            $messageId    = $responseData['key']['id'] ?? '';
            $now          = date('Y-m-d H:i:s');

            $DB->insert('glpi_plugin_whatsappsimples_messages', [
                'chats_id'      => $chatId,
                'message_id'    => $messageId,
                'sender_type'   => 'attendant',
                'message_text'  => $text,
                'date_creation' => $now
            ]);

            $DB->update('glpi_plugin_whatsappsimples_chats', [
                'date_mod' => $now
            ], ['id' => $chatId]);

            return ['success' => true, 'message_id' => $messageId];
        }

        return ['success' => false, 'error' => "EvolutionAPI retornou HTTP {$httpCode}: {$responseBody}"];
    }
}
