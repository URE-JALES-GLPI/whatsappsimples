<?php

namespace GlpiPlugin\Whatsappsimples\Service;

use Session;

class EvolutionApiService
{
    /**
     * Garantia dinamica de colunas no banco de dados para evitar erro Unknown column
     */
    private static function ensureMessageColumns(): void
    {
        global $DB;
        if ($DB->tableExists('glpi_plugin_whatsappsimples_messages')) {
            if (!$DB->fieldExists('glpi_plugin_whatsappsimples_messages', 'users_id')) {
                @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_messages` ADD COLUMN `users_id` int(11) NOT NULL DEFAULT 0 AFTER `chats_id`");
            }
            if (!$DB->fieldExists('glpi_plugin_whatsappsimples_messages', 'media_url')) {
                @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_messages` ADD COLUMN `media_url` longtext DEFAULT NULL AFTER `message_text`");
            } else {
                @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_messages` MODIFY COLUMN `media_url` longtext DEFAULT NULL");
            }
        }
    }

    /**
     * Obtém valor de configuração por chave
     */
    public static function getConfig(string $key): ?string
    {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_whatsappsimples_configs')) {
            return null;
        }

        $row = $DB->request([
            'SELECT' => ['value'],
            'FROM'   => 'glpi_plugin_whatsappsimples_configs',
            'WHERE'  => ['name' => $key],
            'LIMIT'  => 1
        ])->current();

        return $row['value'] ?? null;
    }

    /**
     * Atualiza ou insere valor de configuração
     */
    public static function setConfig(string $key, string $value): bool
    {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_whatsappsimples_configs')) {
            return false;
        }

        $exists = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_whatsappsimples_configs',
            'WHERE'  => ['name' => $key]
        ])->count() > 0;

        if ($exists) {
            return $DB->update('glpi_plugin_whatsappsimples_configs', ['value' => $value], ['name' => $key]);
        } else {
            return $DB->insert('glpi_plugin_whatsappsimples_configs', ['name' => $key, 'value' => $value]);
        }
    }

    /**
     * Consulta estado de conexão da instância na EvolutionAPI
     */
    public static function getConnectionState(): array
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['state' => 'close', 'error' => 'Configurações incompletas'];
        }

        $endpoint = "{$baseUrl}/instance/connectionState/{$instance}";
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['apikey: ' . $apiToken],
            CURLOPT_TIMEOUT        => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return [
                'state' => $data['instance']['state'] ?? 'close',
                'data'  => $data
            ];
        }

        return ['state' => 'close', 'error' => "HTTP {$httpCode}: {$response}"];
    }

    /**
     * Obtém QR Code para emparelhamento WhatsApp
     */
    public static function getQrCode(): array
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['error' => 'Configurações incompletas'];
        }

        $endpoint = "{$baseUrl}/instance/connect/{$instance}";
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['apikey: ' . $apiToken],
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return [
                'base64' => $data['base64'] ?? $data['code'] ?? '',
                'code'   => $data['code'] ?? '',
                'data'   => $data
            ];
        }

        return ['error' => "HTTP {$httpCode}: {$response}"];
    }

    /**
     * Configura Webhook da EvolutionAPI
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
            'webhook' => [
                'enabled'      => true,
                'url'          => $webhookUrl,
                'byEvents'     => false,
                'base64'       => false,
                'events'       => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE']
            ]
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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => json_decode($response, true)];
        }

        return ['success' => false, 'error' => "HTTP {$httpCode}: {$response}"];
    }

    /**
     * Envia mensagem de texto via EvolutionAPI e grava no banco do GLPI
     */
    public static function sendMessage(int $chatId, string $phoneNumber, string $text): array
    {
        global $DB;

        self::ensureMessageColumns();

        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['success' => false, 'error' => 'Configurações da EvolutionAPI incompletas'];
        }

        $numberToSend = trim($phoneNumber);
        if (!str_contains($numberToSend, '@')) {
            if (!str_starts_with($numberToSend, '55') && strlen($numberToSend) > 12) {
                $numberToSend .= '@lid';
            }
        }

        $endpoint = "{$baseUrl}/message/sendText/{$instance}";
        $bodyData = [
            'number'      => $numberToSend,
            'text'        => $text,
            'textMessage' => [
                'text' => $text
            ]
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

            $currentUserId = (int) \Session::getLoginUserID();
            $DB->insert('glpi_plugin_whatsappsimples_messages', [
                'chats_id'      => $chatId,
                'users_id'      => $currentUserId,
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

    /**
     * Envia arquivo de mídia (imagem, PDF, documento) via EvolutionAPI
     */
    public static function sendMedia(int $chatId, string $phoneNumber, string $mediaType, string $base64Data, string $fileName, string $caption = ''): array
    {
        global $DB;

        self::ensureMessageColumns();

        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['success' => false, 'error' => 'Configurações da EvolutionAPI incompletas'];
        }

        $numberToSend = trim($phoneNumber);
        if (!str_contains($numberToSend, '@')) {
            if (!str_starts_with($numberToSend, '55') && strlen($numberToSend) > 12) {
                $numberToSend .= '@lid';
            }
        }

        $endpoint = "{$baseUrl}/message/sendMedia/{$instance}";
        $bodyData = [
            'number'       => $numberToSend,
            'mediaMessage' => [
                'mediatype' => $mediaType,
                'caption'   => $caption ?: $fileName,
                'media'     => $base64Data,
                'fileName'  => $fileName
            ],
            'media'        => $base64Data,
            'mediatype'    => $mediaType,
            'caption'      => $caption ?: $fileName,
            'fileName'     => $fileName
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
            CURLOPT_TIMEOUT        => 30
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $now = date('Y-m-d H:i:s');
        $currentUserId = (int) \Session::getLoginUserID();
        $messageText = "📎 Arquivo: {$fileName}" . ($caption ? "\n{$caption}" : "");

        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($responseBody, true);
            $messageId    = $responseData['key']['id'] ?? '';

            $DB->insert('glpi_plugin_whatsappsimples_messages', [
                'chats_id'      => $chatId,
                'users_id'      => $currentUserId,
                'message_id'    => $messageId,
                'sender_type'   => 'attendant',
                'message_text'  => $messageText,
                'media_url'     => $base64Data,
                'date_creation' => $now
            ]);

            $DB->update('glpi_plugin_whatsappsimples_chats', [
                'date_mod' => $now
            ], ['id' => $chatId]);

            return ['success' => true, 'message_id' => $messageId];
        }

        // Se falhar na API externa, grava no histórico do chamado no GLPI
        $DB->insert('glpi_plugin_whatsappsimples_messages', [
            'chats_id'      => $chatId,
            'users_id'      => $currentUserId,
            'message_id'    => 'media_' . time(),
            'sender_type'   => 'attendant',
            'message_text'  => $messageText,
            'media_url'     => $base64Data,
            'date_creation' => $now
        ]);

        $DB->update('glpi_plugin_whatsappsimples_chats', [
            'date_mod' => $now
        ], ['id' => $chatId]);

        return ['success' => true, 'message' => 'Arquivo anexado ao chamado com sucesso!'];
    }
}
