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
     * Extrai o número de celular real (55...) diretamente do payload do Webhook por inspecao profunda do JSON
     */
    public static function extractRealPhoneNumberFromPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // 1. Procura por JID completo no formato 55179xxxxxxx@s.whatsapp.net ou @c.us
        if (preg_match('/(55\d{10,11})@(s\.whatsapp\.net|c\.us)/', $json, $matches)) {
            return $matches[1];
        }

        // 2. Procura por sequência numérica brasileira de 12 a 13 dígitos iniciada com 55
        if (preg_match('/(?<!\d)(55\d{10,11})(?!\d)/', $json, $matches)) {
            return $matches[1];
        }

        // 3. Fallback: extrai do remoteJid ou sender
        $data   = $payload['data'] ?? [];
        $key    = $data['key'] ?? [];
        $rawJid = $key['remoteJid'] ?? $data['sender'] ?? $key['participant'] ?? '';

        return preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $rawJid));
    }

    /**
     * Tenta resolver um LID do WhatsApp Meta para o numero de celular real (55...) via EvolutionAPI de forma 100% dinamica e escalavel
     */
    public static function fetchRealJid(string $numberOrLid): string
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        $clean = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $numberOrLid));
        if (empty($clean)) {
            return $numberOrLid;
        }

        // Se já é um número brasileiro válido de celular (inicia com 55 e tem pelo menos 12 dígitos)
        if (str_starts_with($clean, '55') && strlen($clean) >= 12 && strlen($clean) <= 13) {
            return $clean;
        }

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return $clean;
        }

        // Tentativas com formato limpo e com sufixo @lid
        $targetsToTest = [$clean . '@lid', $clean];

        foreach ($targetsToTest as $targetNumber) {
            // 1. Consulta perfil do contato na EvolutionAPI (/chat/fetchProfile)
            $endpoint = "{$baseUrl}/chat/fetchProfile/{$instance}";
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'apikey: ' . $apiToken
                ],
                CURLOPT_POSTFIELDS     => json_encode(['number' => $targetNumber]),
                CURLOPT_TIMEOUT        => 4
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                $realJid = $data['jid'] ?? $data['number'] ?? '';
                if (!empty($realJid)) {
                    $cleanReal = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $realJid));
                    if (!empty($cleanReal) && str_starts_with($cleanReal, '55') && strlen($cleanReal) >= 12) {
                        return $cleanReal;
                    }
                }
            }
        }

        // 2. Fallback: Consulta validação de número (/chat/findWhatsappNumber)
        $endpointCheck = "{$baseUrl}/chat/findWhatsappNumber/{$instance}";
        $ch2 = curl_init($endpointCheck);
        curl_setopt_array($ch2, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken
            ],
            CURLOPT_POSTFIELDS     => json_encode(['numbers' => [$clean . '@lid', $clean]]),
            CURLOPT_TIMEOUT        => 4
        ]);

        $response2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($httpCode2 >= 200 && $httpCode2 < 300) {
            $data2 = json_decode($response2, true);
            if (is_array($data2)) {
                foreach ($data2 as $item) {
                    if (!empty($item['exists']) && !empty($item['jid'])) {
                        $cleanFound = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $item['jid']));
                        if (!empty($cleanFound) && str_starts_with($cleanFound, '55') && strlen($cleanFound) >= 12) {
                            return $cleanFound;
                        }
                    }
                }
            }
        }

        return $clean;
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

        // Tenta resolver o LID para o número real de celular antes de disparar
        $numberToSend = self::fetchRealJid($phoneNumber);

        // Se após a consulta for um número resolvido (começa com 55), atualiza no banco do chat para manter limpo
        if (str_starts_with($numberToSend, '55') && $numberToSend !== $phoneNumber) {
            $DB->update('glpi_plugin_whatsappsimples_chats', ['phone_number' => $numberToSend], ['id' => $chatId]);
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

        // Se falhou com HTTP 400, faz uma segunda tentativa enviando com sufixo @lid se necessário
        if ($httpCode >= 400 && !str_contains($numberToSend, '@')) {
            $lidEndpoint = "{$baseUrl}/message/sendText/{$instance}";
            $lidBody = [
                'number'      => $phoneNumber . '@lid',
                'text'        => $text,
                'textMessage' => ['text' => $text]
            ];
            $chLid = curl_init($lidEndpoint);
            curl_setopt_array($chLid, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'apikey: ' . $apiToken
                ],
                CURLOPT_POSTFIELDS     => json_encode($lidBody),
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

        $numberToSend = self::fetchRealJid($phoneNumber);

        if (str_starts_with($numberToSend, '55') && $numberToSend !== $phoneNumber) {
            $DB->update('glpi_plugin_whatsappsimples_chats', ['phone_number' => $numberToSend], ['id' => $chatId]);
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
