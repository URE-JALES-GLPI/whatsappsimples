<?php

namespace GlpiPlugin\Whatsappsimples\Service;

use Session;

class EvolutionApiService
{
    // ──────────────────────────────────────────────────
    // UTILITÁRIOS DE INFRAESTRUTURA
    // ──────────────────────────────────────────────────

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
     * EXTRAÇÃO PROFUNDA: varre o JSON inteiro do payload do webhook buscando
     * um número de celular brasileiro (55XXXXXXXXXXX) em QUALQUER campo.
     *
     * Ordem de prioridade:
     *   1. Campos específicos conhecidos (participant, sender, etc.)
     *   2. Regex no JSON inteiro procurando 55...@s.whatsapp.net ou @c.us
     *   3. Regex no JSON inteiro procurando 55... standalone (12-13 dígitos)
     *
     * Retorna o número limpo (só dígitos, ex: "5517996194229") ou null.
     */
    public static function extractRealPhoneNumberFromPayload(array $payload): ?string
    {
        $data = $payload['data'] ?? $payload;
        $key  = $data['key'] ?? [];

        // Lista de campos onde o número real pode estar escondido
        $fieldsToCheck = [
            $key['participant'] ?? '',
            $data['participant'] ?? '',
            $data['sender'] ?? '',
            $data['source'] ?? '',
            $data['user'] ?? '',
            $payload['sender'] ?? '',
            $payload['destination'] ?? '',
            $key['remoteJid'] ?? '',
        ];

        // Primeiro: verifica campos individuais
        foreach ($fieldsToCheck as $field) {
            if (empty($field) || !is_string($field)) {
                continue;
            }
            $digits = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $field));
            if (self::isValidBrazilianNumber($digits)) {
                self::log("NUMERO_EXTRAIDO_CAMPO", ['campo' => $field, 'resultado' => $digits]);
                return $digits;
            }
        }

        // Segundo: serializa o payload inteiro e procura via regex
        $jsonString = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // Padrão mais confiável: número antes de @s.whatsapp.net ou @c.us
        if (preg_match('/(55\d{10,11})@(?:s\.whatsapp\.net|c\.us)/', $jsonString, $m)) {
            self::log("NUMERO_EXTRAIDO_REGEX_JID", ['resultado' => $m[1]]);
            return $m[1];
        }

        // Padrão standalone: 55 seguido de 10-11 dígitos, não precedido nem seguido por outro dígito
        if (preg_match('/(?<!\d)(55\d{10,11})(?!\d)/', $jsonString, $m)) {
            self::log("NUMERO_EXTRAIDO_REGEX_STANDALONE", ['resultado' => $m[1]]);
            return $m[1];
        }

        self::log("NENHUM_NUMERO_BR_NO_PAYLOAD", ['payload_size' => strlen($jsonString)]);
        return null;
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

    /**
     * Método principal: dado um payload de webhook, retorna o melhor número de telefone possível.
     * Se o payload contiver apenas um LID, tenta resolver via API.
     * Retorna os dígitos do número real ou, como último recurso, os dígitos brutos do JID.
     */
    public static function resolvePhoneNumber(array $payload): string
    {
        $data = $payload['data'] ?? $payload;
        $key  = $data['key'] ?? [];

        // Extrai o JID bruto para ter um fallback
        $rawJid = $key['remoteJid'] ?? $data['sender'] ?? $key['participant'] ?? '';
        $rawDigits = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $rawJid));

        // 1. Tenta extração profunda do payload (0ms, sem chamada de rede)
        $realNumber = self::extractRealPhoneNumberFromPayload($payload);
        if (!empty($realNumber) && self::isValidBrazilianNumber($realNumber)) {
            return $realNumber;
        }

        // 2. Se o rawDigits já é um número brasileiro válido, usa direto
        if (self::isValidBrazilianNumber($rawDigits)) {
            return $rawDigits;
        }

        // 3. rawDigits é um LID — tenta resolver via API da EvolutionAPI
        if (!empty($rawDigits)) {
            $resolved = self::fetchRealJidFromApi($rawDigits);
            if (!empty($resolved)) {
                return $resolved;
            }
        }

        // 4. Último recurso: retorna os dígitos brutos (pode ser LID)
        self::log("FALLBACK_RAW_DIGITS", ['rawDigits' => $rawDigits]);
        return $rawDigits;
    }

    // ──────────────────────────────────────────────────
    // FORMATAÇÃO PARA ENVIO
    // ──────────────────────────────────────────────────

    /**
     * Formata o número para envio pela EvolutionAPI.
     * - Números brasileiros (55...): envia como string de dígitos
     * - LIDs da Meta: envia com sufixo @lid
     */
    public static function formatNumberForSending(string $phoneNumber): string
    {
        $clean = trim(str_replace(['@s.whatsapp.net', '@c.us'], '', $phoneNumber));

        if (str_contains($clean, '@lid')) {
            return $clean;
        }

        $digits = preg_replace('/[^0-9]/', '', $clean);

        if (self::isValidBrazilianNumber($digits)) {
            return $digits;
        }

        // É um LID ou número não-brasileiro → adiciona @lid
        if (strlen($digits) > 13 || !str_starts_with($digits, '55')) {
            return $digits . '@lid';
        }

        return $digits;
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

        // Se o número armazenado é um LID, tenta resolver antes de enviar
        if (!self::isValidBrazilianNumber($phoneNumber)) {
            $rawDigits = preg_replace('/[^0-9]/', '', str_replace(['@s.whatsapp.net', '@c.us', '@lid'], '', $phoneNumber));
            $resolved = self::fetchRealJidFromApi($rawDigits);
            if (!empty($resolved)) {
                self::log("SEND_LID_RESOLVIDO", ['original' => $phoneNumber, 'resolvido' => $resolved]);
                // Atualiza o banco com o número correto para que futuras mensagens não precisem resolver novamente
                $DB->update('glpi_plugin_whatsappsimples_chats', ['phone_number' => $resolved], ['id' => $chatId]);
                $phoneNumber = $resolved;
            }
        }

        $numberToSend = self::formatNumberForSending($phoneNumber);
        self::log("SEND_NUMERO_FORMATADO", ['input' => $phoneNumber, 'formatted' => $numberToSend]);

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

            $DB->update('glpi_plugin_whatsappsimples_chats', ['date_mod' => $now], ['id' => $chatId]);

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

        $DB->update('glpi_plugin_whatsappsimples_chats', ['date_mod' => $now], ['id' => $chatId]);

        return ['success' => true, 'message' => 'Arquivo anexado ao chamado com sucesso!'];
    }
}
