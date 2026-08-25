<?php

namespace GlpiPlugin\Whatsappsimples\Service;

class EvolutionApiService
{
    /**
     * Busca uma configuração salva na tabela glpi_plugin_whatsappsimples_configs
     */
    public static function getConfig(string $name): string
    {
        global $DB;
        $row = $DB->request([
            'SELECT' => ['value'],
            'FROM'   => 'glpi_plugin_whatsappsimples_configs',
            'WHERE'  => ['name' => $name],
            'LIMIT'  => 1
        ])->current();

        return $row ? (string) $row['value'] : '';
    }

    /**
     * Envia mensagem de texto via EvolutionAPI e grava no banco do GLPI
     */
    public static function sendMessage(int $chatId, string $phoneNumber, string $text): array
    {
        global $DB;

        // 1. Carrega as configurações da API salvas no banco
        $baseUrl  = rtrim(self::getConfig('server_url'), '/');
        $apiToken = self::getConfig('api_token');
        $instance = self::getConfig('instance_name');

        if (empty($baseUrl) || empty($apiToken) || empty($instance)) {
            return ['success' => false, 'error' => 'Configuracoes da EvolutionAPI incompletas'];
        }

        // 2. Prepara a URL final e o Payload JSON da EvolutionAPI
        $endpoint = "{$baseUrl}/message/sendText/{$instance}";

        $bodyData = [
            'number' => $phoneNumber,
            'text'   => $text
        ];

        // 3. Executa a chamada HTTP cURL para a EvolutionAPI
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
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => 'Erro na conexao cURL: ' . $curlError];
        }

        // 4. Se a EvolutionAPI aceitou o envio (HTTP 200 a 299)
        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($responseBody, true);
            $messageId    = $responseData['key']['id'] ?? '';
            $now          = date('Y-m-d H:i:s');

            // Salva a mensagem enviada no nosso banco do GLPI (sender_type = attendant)
            $DB->insert('glpi_plugin_whatsappsimples_messages', [
                'chats_id'      => $chatId,
                'message_id'    => $messageId,
                'sender_type'   => 'attendant',
                'message_text'  => $text,
                'date_creation' => $now
            ]);

            // Atualiza a data de modificação da conversa
            $DB->update('glpi_plugin_whatsappsimples_chats', [
                'date_mod' => $now
            ], ['id' => $chatId]);

            return ['success' => true, 'message_id' => $messageId];
        }

        return ['success' => false, 'error' => "EvolutionAPI retornou HTTP {$httpCode}: {$responseBody}"];
    }
}
