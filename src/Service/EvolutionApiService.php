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
     * Formata o número/JID para envio correto na EvolutionAPI (suportando números 55... e LIDs da Meta)
     */
    public static function formatNumberForSending(string $phoneNumber): string
    {
        $clean = trim(str_replace(['@s.whatsapp.net', '@c.us'], '', $phoneNumber));

        // Se já possui @lid, mantém intacto
        if (str_contains($clean, '@lid')) {
            return $clean;
        }

        $digits = preg_replace('/[^0-9]/', '', $clean);

        // Se for um número padrão do Brasil (55 + DDD 2 dígitos + 8 ou 9 dígitos -> 12 ou 13 dígitos)
        if (str_starts_with($digits, '55') && strlen($digits) >= 12 && strlen($digits) <= 13) {
            return $digits;
        }

        // Se for um ID da Meta / LID (ex: 258522822520959 com 14 ou mais dígitos ou não iniciado por 55)
        if (strlen($digits) > 13 || !str_starts_with($digits, '55')) {
            return $digits . '@lid';
        }

        return $digits;
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

        // Formata o número/JID adequadamente (número padrão 55... ou JID LID ex: 258522822520959@lid)
        $numberToSend = self::formatNumberForSending($phoneNumber);

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

        // Se falhou e era um número sem @lid, tenta fallback enviando com o sufixo @lid
        if ($httpCode >= 400 && !str_contains($numberToSend, '@lid')) {
            $lidNumber = preg_replace('/[^0-9]/', '', $phoneNumber) . '@lid';
            $chLid = curl_init($endpoint);
            curl_setopt_array($chLid, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'apikey: ' . $apiToken
                ],
                CURLOPT_POSTFIELDS     => json_encode([
                    'number'      => $lidNumber,
                    'text'        => $text,
                    'textMessage' => ['text' => $text]
                ]),
                CURLOPT_TIMEOUT        => 15
            ]);
            $responseBodyLid = curl_exec($chLid);
            $httpCodeLid     = curl_getinfo($chLid, CURLINFO_HTTP_CODE);
            curl_close($chLid);

            if ($httpCodeLid >= 200 && $httpCodeLid < 300) {
                $responseBody = $responseBodyLid;
                $httpCode     = $httpCodeLid;
            }
        }

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

        $numberToSend = self::formatNumberForSending($phoneNumber);

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
