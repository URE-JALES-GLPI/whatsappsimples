<?php

namespace GlpiPlugin\Whatsappsimples\Service;

use Session;

class EvolutionApiService
{
    // ──────────────────────────────────────────────────
    // UTILITÁRIOS DE INFRAESTRUTURA
    // ──────────────────────────────────────────────────

    public static function ensureMessageColumns(): void
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
            if (!$DB->fieldExists('glpi_plugin_whatsappsimples_messages', 'is_internal')) {
                @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_messages` ADD COLUMN `is_internal` tinyint(1) NOT NULL DEFAULT 0 AFTER `media_url`");
            }
        }
        if ($DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
            if (!$DB->fieldExists('glpi_plugin_whatsappsimples_chats', 'linked_lid')) {
                @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_chats` ADD COLUMN `linked_lid` varchar(50) DEFAULT NULL AFTER `phone_number`");
                @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_chats` ADD INDEX `linked_lid_idx` (`linked_lid`)");
            }
            if (!$DB->fieldExists('glpi_plugin_whatsappsimples_chats', 'unread_count')) {
                @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_chats` ADD COLUMN `unread_count` int(11) NOT NULL DEFAULT 0 AFTER `status`");
            }
        }
    }

    private static function log(string $action, array $data = []): void
    {
        $logFile = (defined('GLPI_ROOT') ? GLPI_ROOT : '/var/www/html/glpi') . '/files/_log/whatsappsimples.log';
        $logDir  = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $entry = sprintf("[%s] [EvolutionApiService] [%s] %s\n", date('Y-m-d H:i:s'), $action, json_encode($data, JSON_UNESCAPED_UNICODE));
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }

    // ──────────────────────────────────────────────────
    // RESOLUÇÃO DE NÚMERO REAL A PARTIR DO PAYLOAD
    // ──────────────────────────────────────────────────

    /**
     * Verifica se uma string de dígitos é um número brasileiro válido (55 + DDD + 8-9 dígitos).
     */
    public static function isValidBrazilianNumber(string $digits): bool
    {
        $clean = preg_replace('/[^0-9]/', '', $digits);
        return str_starts_with($clean, '55') && strlen($clean) >= 12 && strlen($clean) <= 13;
    }

    /**
     * RESOLUÇÃO DO NÚMERO DO CONTATO A PARTIR DO PAYLOAD DA EVOLUTIONAPI
     *
     * IMPORTANTE: No payload da EvolutionAPI:
     *   - data.key.remoteJid  = JID do CONTATO (quem enviou/recebeu a mensagem)
     *   - sender (root)       = NOSSO número (o WhatsApp Business conectado à instância)
     *
     * Portanto NUNCA usamos o campo root 'sender' como identificador do contato.
     *
     * Ordem de resolução:
     *   1. data.key.remoteJid → se já é número BR válido, usa direto
     *   2. data.key.remoteJid → se é LID, consulta API para resolver
     *   3. Fallback: dígitos brutos do LID (evita usar número errado)
     */
    public static function resolvePhoneNumber(array $payload): string
    {
        $data = $payload['data'] ?? $payload;
        $key  = $data['key'] ?? [];

        // O JID do contato está SEMPRE em data.key.remoteJid
        // Para grupos, o remetente individual fica em data.key.participant
        // root-level 'sender' = NOSSO número (linha da URE), NUNCA usar como identificador do contato
        $contactJid = $key['participant'] ?? $key['remoteJid'] ?? '';

        if (empty($contactJid)) {
            self::log("CONTACT_JID_VAZIO", ['key' => $key]);
            return '';
        }

        self::log("JID_BRUTO_CONTATO", ['jid' => $contactJid]);

        // Se o JID já é um número brasileiro válido (5517...@s.whatsapp.net)
        $digitsOnly = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $contactJid));
        if (self::isValidBrazilianNumber($digitsOnly)) {
            self::log("NUMERO_BR_DIRETO", ['jid' => $contactJid, 'numero' => $digitsOnly]);
            return $digitsOnly;
        }

        // É um LID da Meta — guarda o JID COMPLETO com @lid para usar no envio
        if (str_contains($contactJid, '@lid')) {
            self::log("LID_JID_COMPLETO_PRESERVADO", ['jid' => $contactJid]);
            return $contactJid; // ex: "258522822520959@lid"
        }

        // Fallback: retorna o que tiver
        self::log("FALLBACK_JID", ['jid' => $contactJid]);
        return $contactJid;
    }

    /**
     * @deprecated Use resolvePhoneNumber() em vez desta função.
     * Mantido apenas por compatibilidade.
     */
    public static function extractRealPhoneNumberFromPayload(array $payload): ?string
    {
        $result = self::resolvePhoneNumber($payload);
        return !empty($result) ? $result : null;
    }

    /**
     * Consulta a EvolutionAPI para tentar resolver um LID para o número real.
     * Tenta múltiplos endpoints em cascata.
     * Retorna o número real (ex: "5517996194229") ou null.
     */
    public static function fetchRealJidFromApi(string $lidDigits): ?string
    {
        $baseUrl  = rtrim(self::getConfig('server_url') ?? '', '/');
        $apiToken = self::getConfig('api_token') ?? '';
        $instance = self::getConfig('instance_name') ?? '';

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return null;
        }

        // Tentativa 1: /chat/findContacts
        $endpoints = [
            ['method' => 'POST', 'url' => "{$baseUrl}/chat/findContacts/{$instance}",    'body' => ['where' => ['id' => "{$lidDigits}@lid"]]],
            ['method' => 'GET',  'url' => "{$baseUrl}/contact/find/{$instance}?where.id=" . urlencode("{$lidDigits}@lid"), 'body' => null],
            ['method' => 'POST', 'url' => "{$baseUrl}/chat/fetchProfile/{$instance}",     'body' => ['number' => "{$lidDigits}@lid"]],
        ];

        foreach ($endpoints as $ep) {
            $ch = curl_init($ep['url']);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'apikey: ' . $apiToken
                ],
                CURLOPT_TIMEOUT => 5
            ];
            if ($ep['method'] === 'POST' && $ep['body']) {
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_POSTFIELDS] = json_encode($ep['body']);
            }
            curl_setopt_array($ch, $opts);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300 && !empty($response)) {
                // Procura um número brasileiro na resposta
                if (preg_match('/(55\d{10,11})/', $response, $m)) {
                    self::log("LID_RESOLVIDO_API", ['lid' => $lidDigits, 'endpoint' => $ep['url'], 'resultado' => $m[1]]);
                    return $m[1];
                }
            }
        }

        self::log("LID_NAO_RESOLVIDO_API", ['lid' => $lidDigits]);
        return null;
    }


    // ──────────────────────────────────────────────────
    // FORMATAÇÃO PARA ENVIO
    // ──────────────────────────────────────────────────

    /**
     * Formata o número/JID para envio pela EvolutionAPI.
     * - Números brasileiros (55...): envia como string de dígitos
     * - JIDs com @lid: envia o JID completo (ex: "258522822520959@lid")
     */
    public static function formatNumberForSending(string $phoneNumber): string
    {
        // Se já tem @lid ou @s.whatsapp.net, retorna como está
        if (str_contains($phoneNumber, '@')) {
            return $phoneNumber;
        }

        $digits = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (self::isValidBrazilianNumber($digits)) {
            return $digits;
        }

        // Número não-brasileiro sem sufixo: adiciona @lid
        return $digits . '@lid';
    }

    // ──────────────────────────────────────────────────
    // CONFIGURAÇÃO
    // ──────────────────────────────────────────────────

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

    // ──────────────────────────────────────────────────
    // CONEXÃO
    // ──────────────────────────────────────────────────

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

    // ──────────────────────────────────────────────────
    // WEBHOOK
    // ──────────────────────────────────────────────────

    public static function setWebhook(string $url): array
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['success' => false, 'error' => 'Configurações incompletas'];
        }

        $endpoint = "{$baseUrl}/webhook/set/{$instance}";
        $payload = [
            'webhook' => [
                'enabled' => true,
                'url' => $url,
                'byEvents' => false,
                'base64' => false,
                'events' => ['MESSAGES_UPSERT']
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken
            ],
            CURLOPT_TIMEOUT        => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => json_decode($response, true)];
        }

        return ['success' => false, 'error' => "HTTP {$httpCode}: {$response}"];
    }

    // ──────────────────────────────────────────────────
    // ENVIO DE MENSAGEM DE TEXTO
    // ──────────────────────────────────────────────────

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

        $numberToSend = self::formatNumberForSending($phoneNumber);
        self::log("SEND_NUMERO_FORMATADO", ['input' => $phoneNumber, 'formatted' => $numberToSend]);

        // Para LIDs, tenta buscar o número real antes de enviar (sem bloquear se não resolver)
        if (!self::isValidBrazilianNumber($phoneNumber) && !str_contains($phoneNumber, '@lid')) {
            $rawDigits = preg_replace('/[^0-9]/', '', $phoneNumber);
            $resolved = self::fetchRealJidFromApi($rawDigits);
            if (!empty($resolved)) {
                self::log("SEND_LID_RESOLVIDO", ['original' => $phoneNumber, 'resolvido' => $resolved]);
                $DB->update('glpi_plugin_whatsappsimples_chats', ['phone_number' => $resolved], ['id' => $chatId]);
                $phoneNumber = $resolved;
                $numberToSend = self::formatNumberForSending($resolved);
            }
        }

        $endpoint = "{$baseUrl}/message/sendText/{$instance}";
        $bodyData = [
            'number'      => $numberToSend,
            'text'        => $text,
            'textMessage' => ['text' => $text]
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

        // Se falhou e não era @lid, tenta com @lid como fallback
        if ($httpCode >= 400 && !str_contains($numberToSend, '@lid')) {
            $lidNumber = preg_replace('/[^0-9]/', '', $phoneNumber) . '@lid';
            self::log("SEND_FALLBACK_LID", ['lidNumber' => $lidNumber]);
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

            $DB->update('glpi_plugin_whatsappsimples_chats', ['date_mod' => $now], ['id' => $chatId]);

            return ['success' => true, 'message_id' => $messageId];
        }

        return ['success' => false, 'error' => "EvolutionAPI retornou HTTP {$httpCode}: {$responseBody}"];
    }

    // ──────────────────────────────────────────────────
    // ENVIO DE MÍDIA
    // ──────────────────────────────────────────────────

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

        // Se o número armazenado é um LID, tenta resolver
        if (!self::isValidBrazilianNumber($phoneNumber)) {
            $rawDigits = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $phoneNumber));
            $resolved = self::fetchRealJidFromApi($rawDigits);
            if (!empty($resolved)) {
                $DB->update('glpi_plugin_whatsappsimples_chats', ['phone_number' => $resolved], ['id' => $chatId]);
                $phoneNumber = $resolved;
            }
        }

        $numberToSend = self::formatNumberForSending($phoneNumber);

        // Extrai o mimetype da string base64 se possível
        $mimeType = 'application/octet-stream';
        if (preg_match('/^data:(.*?);base64,/', $base64Data, $matches)) {
            $mimeType = $matches[1];
            // EvolutioAPI geralmente aceita a string inteira, então deixamos o prefixo
        }

        $endpoint = "{$baseUrl}/message/sendMedia/{$instance}";
        $bodyData = [
            'number'       => $numberToSend,
            'mediatype'    => $mediaType,
            'mimetype'     => $mimeType,
            'caption'      => $caption ?: '',
            'media'        => $base64Data,
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

            $DB->update('glpi_plugin_whatsappsimples_chats', ['date_mod' => $now], ['id' => $chatId]);

            return ['success' => true, 'message_id' => $messageId];
        }

        self::log("ERRO_ENVIO_MIDIA", ['http_code' => $httpCode, 'response' => $responseBody]);
        return ['success' => false, 'error' => "A Evolution API rejeitou o arquivo (HTTP {$httpCode}). Resposta: " . substr($responseBody, 0, 100)];
    }
}
