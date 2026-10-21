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
            }
            if (!$DB->fieldExists('glpi_plugin_whatsappsimples_messages', 'is_internal')) {
                @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_messages` ADD COLUMN `is_internal` tinyint(1) NOT NULL DEFAULT 0 AFTER `media_url`");
            }
            $mediaCols = [
                'media_path'   => "varchar(255) DEFAULT NULL",
                'media_mime'   => "varchar(100) DEFAULT NULL",
                'media_size'   => "bigint(20) DEFAULT 0",
                'media_sha256' => "varchar(64) DEFAULT NULL",
                'media_status' => "varchar(20) DEFAULT NULL",
            ];
            foreach ($mediaCols as $col => $def) {
                if (!$DB->fieldExists('glpi_plugin_whatsappsimples_messages', $col)) {
                    @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_messages` ADD COLUMN `$col` $def");
                }
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
            if (!$DB->fieldExists('glpi_plugin_whatsappsimples_chats', 'profile_picture_url')) {
                @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_chats` ADD COLUMN `profile_picture_url` text DEFAULT NULL AFTER `contact_name`");
            }
            if (!$DB->fieldExists('glpi_plugin_whatsappsimples_chats', 'contact_status')) {
                @$DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_chats` ADD COLUMN `contact_status` text DEFAULT NULL AFTER `profile_picture_url`");
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

    // ──────────────────────────────────────────────────
    // DELETAR MENSAGEM
    // ──────────────────────────────────────────────────

    public static function deleteMessage(string $phoneNumber, string $messageId): array
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance) || empty($messageId) || empty($phoneNumber)) {
            return ['success' => false, 'error' => 'Configurações incompletas, número ou Message ID vazios'];
        }

        $endpoint = "{$baseUrl}/chat/deleteMessageForEveryone/{$instance}";
        
        $remoteJid = strpos($phoneNumber, '@') !== false ? $phoneNumber : $phoneNumber . '@s.whatsapp.net';
        
        $bodyData = [
            'id' => $messageId,
            'remoteJid' => $remoteJid,
            'fromMe' => true
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken
            ],
            CURLOPT_POSTFIELDS     => json_encode($bodyData),
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true];
        }

        self::log("ERRO_DELETE_MENSAGEM", ['httpCode' => $httpCode, 'response' => $response]);
        return ['success' => false, 'error' => "HTTP {$httpCode}: {$response}"];
    }

    public static function updateMessage(string $phoneNumber, string $messageId, string $newText): array
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance) || empty($messageId) || empty($phoneNumber)) {
            return ['success' => false, 'error' => 'Configurações incompletas, número ou Message ID vazios'];
        }

        $endpoint = "{$baseUrl}/chat/updateMessage/{$instance}";
        
        $remoteJid = strpos($phoneNumber, '@') !== false ? $phoneNumber : $phoneNumber . '@s.whatsapp.net';
        $numberToSend = self::formatNumberForSending($phoneNumber);
        
        $bodyData = [
            'number' => $numberToSend,
            'text'   => $newText,
            'key'    => [
                'remoteJid' => $remoteJid,
                'fromMe'    => true,
                'id'        => $messageId
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
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true];
        }

        self::log("ERRO_UPDATE_MENSAGEM", ['httpCode' => $httpCode, 'response' => $response]);
        return ['success' => false, 'error' => "HTTP {$httpCode}: {$response}"];
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
        $data = !empty($payload['data']) ? $payload['data'] : $payload;
        $key  = !empty($data['key']) ? $data['key'] : [];

        // Busca recursiva por remoteJid para garantir que pegamos o JID do grupo
        $findRemoteJid = function(array $arr) use (&$findRemoteJid) {
            if (isset($arr['remoteJid'])) return $arr['remoteJid'];
            foreach ($arr as $v) {
                if (is_array($v)) {
                    $res = $findRemoteJid($v);
                    if ($res) return $res;
                }
            }
            return '';
        };

        $remoteJid = $findRemoteJid($payload);
        
        // IGNORAR GRUPOS E LISTAS DE TRANSMISSÃO
        if (strpos($remoteJid, '@g.us') !== false || strpos($remoteJid, '@broadcast') !== false) {
            self::log("MENSAGEM_IGNORADA_GRUPO", ['jid' => $remoteJid]);
            return '';
        }

        // O JID do contato está SEMPRE em data.key.remoteJid
        // Para grupos, o remetente individual fica em data.key.participant
        // root-level 'sender' = NOSSO número (linha da URE), NUNCA usar como identificador do contato
        $contactJid = !empty($key['participant']) ? $key['participant'] : $remoteJid;

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
    // PERFIL E FOTO DO CONTATO (WHATSAPP)
    // ──────────────────────────────────────────────────

    /**
     * Consulta o perfil do contato (Foto, Nome e Recado/Status) na Evolution API.
     * Tenta os endpoints /chat/fetchProfile, /chat/fetchProfilePictureUrl e /chat/findChats.
     * Se encontrar foto, baixa e armazena em cache local para nunca expirar.
     */
    public static function fetchProfile(string $phoneNumber): array
    {
        global $DB;
        $baseUrl  = rtrim(self::getConfig('server_url') ?? '', '/');
        $apiToken = self::getConfig('api_token') ?? '';
        $instance = self::getConfig('instance_name') ?? '';

        if (empty($baseUrl) || empty($apiToken) || empty($instance) || empty($phoneNumber)) {
            return ['success' => false, 'error' => 'Configuração ou número ausente'];
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        $numberToQuery = str_contains($phoneNumber, '@') ? $phoneNumber : $cleanPhone;

        $name = null;
        $pictureUrl = null;
        $statusText = null;
        $setAt = null;

        // 1. Tentar /chat/fetchProfile/{instance}
        $ch = curl_init("{$baseUrl}/chat/fetchProfile/{$instance}");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken
            ],
            CURLOPT_POSTFIELDS     => json_encode(['number' => $numberToQuery])
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300 && !empty($res)) {
            $data = json_decode($res, true);
            if (is_array($data)) {
                $name = $data['name'] ?? null;
                $pictureUrl = $data['picture'] ?? null;
                if (!empty($data['status'])) {
                    if (is_array($data['status'])) {
                        $statusText = $data['status']['status'] ?? null;
                        $setAt = $data['status']['setAt'] ?? null;
                    } else {
                        $statusText = (string) $data['status'];
                    }
                }
            }
        }

        // 2. Se a foto for nula, tentar /chat/fetchProfilePictureUrl/{instance}
        if (empty($pictureUrl)) {
            $ch = curl_init("{$baseUrl}/chat/fetchProfilePictureUrl/{$instance}");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'apikey: ' . $apiToken
                ],
                CURLOPT_POSTFIELDS     => json_encode(['number' => $numberToQuery])
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300 && !empty($res)) {
                $data = json_decode($res, true);
                if (!empty($data['profilePictureUrl'])) {
                    $pictureUrl = $data['profilePictureUrl'];
                }
            }
        }

        // 3. Se ainda for nula, consultar o banco interno do Evolution API via /chat/findChats
        if (empty($pictureUrl)) {
            $remoteJid = str_contains($phoneNumber, '@') ? $phoneNumber : "{$cleanPhone}@s.whatsapp.net";
            $ch = curl_init("{$baseUrl}/chat/findChats/{$instance}");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'apikey: ' . $apiToken
                ],
                CURLOPT_POSTFIELDS     => json_encode(['where' => ['remoteJid' => $remoteJid]])
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300 && !empty($res)) {
                $chats = json_decode($res, true);
                if (is_array($chats) && !empty($chats[0]['profilePicUrl'])) {
                    $pictureUrl = $chats[0]['profilePicUrl'];
                    if (empty($name) && !empty($chats[0]['pushName'])) {
                        $name = $chats[0]['pushName'];
                    }
                }
            }
        }

        // 4. Se obteve foto, faz download para o cache local
        $localAvatarUrl = null;
        if (!empty($pictureUrl)) {
            $cached = self::cacheProfilePicture($phoneNumber, $pictureUrl);
            if ($cached) {
                $localAvatarUrl = $cached;
            } else {
                // Fallback para a URL do WhatsApp diretamente se o download falhou
                $localAvatarUrl = $pictureUrl;
            }
        }

        // 5. Persistir na base do GLPI se a tabela e colunas existirem
        if (isset($DB) && is_object($DB) && $DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
            $updateData = [];
            if ($localAvatarUrl) {
                $updateData['profile_picture_url'] = $localAvatarUrl;
            }
            if ($statusText !== null) {
                $updateData['contact_status'] = $statusText;
            }
            if (!empty($updateData)) {
                $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['phone_number' => $phoneNumber]);
            }
        }

        return [
            'success'     => true,
            'name'        => $name,
            'picture_url' => $localAvatarUrl,
            'status'      => $statusText,
            'set_at'      => $setAt
        ];
    }

    /**
     * Faz download da imagem da foto de perfil e salva na pasta de documentos do plugin.
     * Retorna a URL pública relativa ou null em caso de falha.
     */
    public static function cacheProfilePicture(string $phoneNumber, string $pictureUrl): ?string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (empty($cleanPhone) || empty($pictureUrl)) {
            return null;
        }

        $baseDir = defined('GLPI_PLUGIN_DOC_DIR') 
            ? GLPI_PLUGIN_DOC_DIR . '/whatsappsimples/avatars' 
            : (defined('GLPI_ROOT') ? GLPI_ROOT . '/files/_plugins/whatsappsimples/avatars' : '/var/www/html/glpi/files/_plugins/whatsappsimples/avatars');

        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        $fileName = $cleanPhone . '.jpg';
        $filePath = $baseDir . '/' . $fileName;

        // Se o arquivo local já existe e tem menos de 24 horas, reutiliza
        if (file_exists($filePath) && (time() - filemtime($filePath) < 86400) && filesize($filePath) > 300) {
            return (function_exists('wa_web_dir') ? wa_web_dir('whatsappsimples') : '/plugins/whatsappsimples') . '/front/avatar.php?phone=' . $cleanPhone;
        }

        $ch = curl_init($pictureUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && !empty($data) && strlen($data) > 300) {
            @file_put_contents($filePath, $data);
            return (function_exists('wa_web_dir') ? wa_web_dir('whatsappsimples') : '/plugins/whatsappsimples') . '/front/avatar.php?phone=' . $cleanPhone;
        }

        return null;
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
            CURLOPT_TIMEOUT        => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return [
                'base64' => $data['base64'] ?? '',
                'code'   => $data['code'] ?? ''
            ];
        }

        return ['error' => "HTTP {$httpCode}: {$response}"];
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
                'base64' => true,
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

    public static function getBase64FromMediaMessage(string $messageId): array
    {
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance) || empty($messageId)) {
            return ['success' => false, 'error' => 'Configurações incompletas ou Message ID vazio'];
        }

        $endpoint = "{$baseUrl}/chat/getBase64FromMediaMessage/{$instance}";
        $payload = [
            'message' => [
                'key' => [
                    'id' => $messageId
                ]
            ],
            'convertToMp4' => false
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
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return ['success' => true, 'base64' => $data['base64'] ?? ''];
        }

        self::log("ERRO_GET_BASE64", ['httpCode' => $httpCode, 'response' => $response]);
        return ['success' => false, 'error' => "HTTP {$httpCode}: {$response}"];
    }

    // ──────────────────────────────────────────────────
    // ENVIO DE MENSAGEM DE TEXTO
    // ──────────────────────────────────────────────────

    public static function sendMessage(int $chatId, string $phoneNumber, string $text): array
    {
        global $DB;

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

        $dbText = $text;
        $quoteId = null;
        if (str_starts_with($text, '[QUOTE:{')) {
            $endPos = strpos($text, "}]\n");
            $endLen = 3;
            if ($endPos === false) {
                $endPos = strpos($text, "}]\r\n");
                $endLen = 4;
            }
            if ($endPos !== false) {
                $jsonStr = substr($text, 7, $endPos - 7 + 1); // +1 to include '}'
                $quoteData = json_decode($jsonStr, true);
                if ($quoteData && !empty($quoteData['wuid'])) {
                    $quoteId = $quoteData['wuid'];
                }
                $text = substr($text, $endPos + $endLen);
            }
        }

        $endpoint = "{$baseUrl}/message/sendText/{$instance}";
        $bodyData = [
            'number'      => $numberToSend,
            'options'     => [
                'delay'          => 1200,
                'presence'       => 'composing',
                'verifyContact'  => false,
                'validateNumber' => false
            ],
            'text'        => $text,
            'textMessage' => ['text' => $text]
        ];

        if (!empty($quoteId)) {
            $bodyData['options']['quoted'] = [
                'key' => [
                    'id' => $quoteId
                ]
            ];
            // Some older versions use:
            $bodyData['options']['quotedMessageId'] = $quoteId;
        }

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
                'message_text'  => $dbText,
                'date_creation' => $now
            ]);

            $DB->update('glpi_plugin_whatsappsimples_chats', ['date_mod' => $now], ['id' => $chatId]);

            return ['success' => true, 'message_id' => $messageId];
        }

        return ['success' => false, 'error' => "EvolutionAPI retornou HTTP {$httpCode}: {$responseBody}"];
    }

    // ──────────────────────────────────────────────────
    // ENVIO DE ÁUDIO / NOTA DE VOZ (PTT)
    // ──────────────────────────────────────────────────

    /**
     * Envia mensagem de áudio / nota de voz (PTT) via endpoint nativo da EvolutionAPI.
     * Utiliza o endpoint /message/sendWhatsAppAudio/{instance} com encoding=true para que a EvolutionAPI
     * execute a transcodificação FFmpeg (webm/mp3 -> ogg opus) exigida pelo WhatsApp para entrega garantida.
     */
    public static function sendWhatsAppAudio(int $chatId, string $phoneNumber, string $base64Data, string $fileName = 'audio.ogg', string $caption = ''): array
    {
        global $DB;

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

        $mimeType = 'audio/ogg';
        $pureBase64 = $base64Data;
        if (preg_match('/^data:(.*?);base64,(.*)$/', $base64Data, $matches)) {
            $mimeType = $matches[1];
            $pureBase64 = $matches[2];
        }

        $dbCaption = $caption;
        $quoteId = null;
        if (str_starts_with($caption, '[QUOTE:{')) {
            $endPos = strpos($caption, "}]\n");
            $endLen = 3;
            if ($endPos === false) {
                $endPos = strpos($caption, "}]\r\n");
                $endLen = 4;
            }
            if ($endPos !== false) {
                $jsonStr = substr($caption, 7, $endPos - 7 + 1);
                $quoteData = json_decode($jsonStr, true);
                if ($quoteData && !empty($quoteData['wuid'])) {
                    $quoteId = $quoteData['wuid'];
                }
                $caption = substr($caption, $endPos + $endLen);
            }
        }

        $endpoint = "{$baseUrl}/message/sendWhatsAppAudio/{$instance}";
        $bodyData = [
            'number'   => $numberToSend,
            'audio'    => $pureBase64,
            'delay'    => 1200,
            'encoding' => true,
            'options'  => [
                'delay'          => 1200,
                'presence'       => 'recording',
                'encoding'       => true,
                'verifyContact'  => false,
                'validateNumber' => false
            ],
            'audioMessage' => [
                'audio' => $pureBase64
            ]
        ];

        if (!empty($quoteId)) {
            $quotedObj = [
                'key' => [
                    'id' => $quoteId
                ]
            ];
            $bodyData['quoted'] = $quotedObj;
            $bodyData['options']['quoted'] = $quotedObj;
            $bodyData['options']['quotedMessageId'] = $quoteId;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken,
                'Expect:'
            ],
            CURLOPT_POSTFIELDS     => json_encode($bodyData),
            CURLOPT_TIMEOUT        => 45
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Se a API rejeitou com Base64 puro (ex.: 400), tenta enviar com o prefixo Data URI
        if (($httpCode < 200 || $httpCode >= 300) && !str_starts_with($bodyData['audio'], 'data:')) {
            self::log("RETRY_AUDIO_DATA_URI", ['prev_code' => $httpCode, 'prev_resp' => $responseBody]);
            $dataUri = 'data:' . $mimeType . ';base64,' . $pureBase64;
            $bodyData['audio'] = $dataUri;
            if (isset($bodyData['audioMessage']['audio'])) {
                $bodyData['audioMessage']['audio'] = $dataUri;
            }

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'apikey: ' . $apiToken,
                    'Expect:'
                ],
                CURLOPT_POSTFIELDS     => json_encode($bodyData),
                CURLOPT_TIMEOUT        => 45
            ]);
            $responseBody = curl_exec($ch);
            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        $now = date('Y-m-d H:i:s');
        $currentUserId = (int) \Session::getLoginUserID();
        $messageText = "📎 Arquivo: {$fileName}" . ($dbCaption !== '' ? "\n{$dbCaption}" : "");

        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($responseBody, true);
            $messageId    = $responseData['key']['id'] ?? '';

            $mediaDir = GLPI_PLUGIN_DOC_DIR . '/whatsappsimples/media/' . date('Y/m');
            if (!is_dir($mediaDir)) {
                @mkdir($mediaDir, 0775, true);
            }

            $safeFileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileName);
            $localHash = md5(uniqid(rand(), true)) . '_' . $safeFileName;
            $localPath = $mediaDir . '/' . $localHash;

            file_put_contents($localPath, base64_decode($pureBase64));

            $dbMediaPath = date('Y/m') . '/' . $localHash;
            $dbMediaUrl = 'doc_' . $dbMediaPath;

            $messageData = [
                'chats_id'      => $chatId,
                'users_id'      => $currentUserId,
                'message_id'    => $messageId,
                'sender_type'   => 'attendant',
                'message_text'  => $messageText,
                'media_url'     => $dbMediaUrl,
                'date_creation' => $now
            ];

            if ($DB->fieldExists('glpi_plugin_whatsappsimples_messages', 'media_path')) {
                $messageData['media_path'] = $dbMediaPath;
                $messageData['media_mime'] = $mimeType;
            }

            $DB->insert('glpi_plugin_whatsappsimples_messages', $messageData);
            $DB->update('glpi_plugin_whatsappsimples_chats', ['date_mod' => $now], ['id' => $chatId]);

            self::log("SEND_AUDIO_SUCCESS", ['message_id' => $messageId, 'number' => $numberToSend]);
            return ['success' => true, 'message_id' => $messageId];
        }

        self::log("ERRO_ENVIO_AUDIO", ['http_code' => $httpCode, 'response' => $responseBody]);
        return ['success' => false, 'error' => "A Evolution API rejeitou o áudio (HTTP {$httpCode}). Resposta: " . substr($responseBody, 0, 100)];
    }

    // ──────────────────────────────────────────────────
    // ENVIO DE MÍDIA
    // ──────────────────────────────────────────────────

    public static function sendMedia(int $chatId, string $phoneNumber, string $mediaType, string $base64Data, string $fileName, string $caption = '', bool $isPtt = false): array
    {
        global $DB;

        if ($mediaType === 'audio' || $isPtt || str_starts_with($mediaType, 'audio')) {
            return self::sendWhatsAppAudio($chatId, $phoneNumber, $base64Data, $fileName, $caption);
        }

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

        // Extrai o mimetype e o base64 puro da string (Evolution API 1.8.2 rejeita o prefixo)
        $mimeType = 'application/octet-stream';
        $pureBase64 = $base64Data;
        if (preg_match('/^data:(.*?);base64,(.*)$/', $base64Data, $matches)) {
            $mimeType = $matches[1];
            $pureBase64 = $matches[2];
        }

        $dbCaption = $caption;
        $quoteId = null;
        if (str_starts_with($caption, '[QUOTE:{')) {
            $endPos = strpos($caption, "}]\n");
            $endLen = 3;
            if ($endPos === false) {
                $endPos = strpos($caption, "}]\r\n");
                $endLen = 4;
            }
            if ($endPos !== false) {
                $jsonStr = substr($caption, 7, $endPos - 7 + 1);
                $quoteData = json_decode($jsonStr, true);
                if ($quoteData && !empty($quoteData['wuid'])) {
                    $quoteId = $quoteData['wuid'];
                }
                $caption = substr($caption, $endPos + $endLen);
            }
        }

        $endpoint = "{$baseUrl}/message/sendMedia/{$instance}";
        $bodyData = [
            'number'       => $numberToSend,
            'options'      => [
                'delay'    => 1200,
                'presence' => 'composing',
                'verifyContact' => false,
                'validateNumber' => false
            ],
            'mediatype' => $mediaType,
            'fileName'  => $fileName,
            'caption'   => $caption !== '' ? $caption : '',
            'media'     => $pureBase64,
            'mimetype'  => $mimeType,
            'mediaMessage' => [
                'mediatype' => $mediaType,
                'fileName'  => $fileName,
                'caption'   => $caption !== '' ? $caption : '',
                'media'     => $pureBase64,
                'mimetype'  => $mimeType
            ]
        ];

        if (!empty($quoteId)) {
            $bodyData['options']['quoted'] = [
                'key' => [
                    'id' => $quoteId
                ]
            ];
            $bodyData['options']['quotedMessageId'] = $quoteId;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: ' . $apiToken,
                'Expect:'
            ],
            CURLOPT_POSTFIELDS     => json_encode($bodyData),
            CURLOPT_TIMEOUT        => 30
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $now = date('Y-m-d H:i:s');
        $currentUserId = (int) \Session::getLoginUserID();
        $messageText = "📎 Arquivo: {$fileName}" . ($dbCaption !== '' ? "\n{$dbCaption}" : "");

        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($responseBody, true);
            $messageId    = $responseData['key']['id'] ?? '';

            // Ao invés de salvar base64 gigante no MySQL, salva fisicamente
            $mediaDir = GLPI_PLUGIN_DOC_DIR . '/whatsappsimples/media/' . date('Y/m');
            if (!is_dir($mediaDir)) {
                @mkdir($mediaDir, 0775, true);
            }
            
            $safeFileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileName);
            $localHash = md5(uniqid(rand(), true)) . '_' . $safeFileName;
            $localPath = $mediaDir . '/' . $localHash;
            
            file_put_contents($localPath, base64_decode($pureBase64));
            
            $dbMediaPath = date('Y/m') . '/' . $localHash;
            $dbMediaUrl = 'doc_' . $dbMediaPath;
            
            $messageData = [
                'chats_id'      => $chatId,
                'users_id'      => $currentUserId,
                'message_id'    => $messageId,
                'sender_type'   => 'attendant',
                'message_text'  => $messageText,
                'media_url'     => $dbMediaUrl,
                'date_creation' => $now
            ];
            
            if ($DB->fieldExists('glpi_plugin_whatsappsimples_messages', 'media_path')) {
                $messageData['media_path'] = $dbMediaPath;
                $messageData['media_mime'] = $mimeType;
            }

            $DB->insert('glpi_plugin_whatsappsimples_messages', $messageData);

            $DB->update('glpi_plugin_whatsappsimples_chats', ['date_mod' => $now], ['id' => $chatId]);

            return ['success' => true, 'message_id' => $messageId];
        }

        self::log("ERRO_ENVIO_MIDIA", ['http_code' => $httpCode, 'response' => $responseBody]);
        return ['success' => false, 'error' => "A Evolution API rejeitou o arquivo (HTTP {$httpCode}). Resposta: " . substr($responseBody, 0, 100)];
    }
}
