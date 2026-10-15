<?php

namespace GlpiPlugin\Whatsappsimples\Service;

class MercurePublisherService
{
    public const HUB_URL = 'http://10.180.152.29:3001/.well-known/mercure';
    public const TOPIC   = 'chats_ure_jales';

    // Atenção: Aqui usamos o IP do servidor Docker onde o Mercure está rodando!
    private string $mercureUrl = self::HUB_URL;
    private string $jwtKey = 'UmaChaveSuperSecretaParaUreOmnichannel2026';

    /**
     * Dispara um evento de tempo real sem nunca quebrar o fluxo principal.
     */
    public static function notify(string $action, array $extra = []): void
    {
        try {
            (new self())->publish(self::TOPIC, array_merge(['action' => $action], $extra));
        } catch (\Throwable $e) {
            // Mercure fora do ar nao pode impedir recebimento/envio de mensagens
        }
    }

    public function publish(string $topic, array $payload): bool
    {
        $token = $this->generateJwt();

        $postData = http_build_query([
            'topic' => $topic,
            'data'  => json_encode($payload)
        ]);

        $ch = curl_init($this->mercureUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1500);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 2500);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (php_sapi_name() === 'cli') {
            echo "Mercure Publish Status: HTTP $httpCode | Resp: $response" . ($curlError ? " | Curl Err: $curlError" : "") . "\n";
        }

        return $httpCode >= 200 && $httpCode < 300;
    }

    private function generateJwt(): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $now = time();
        $payload = json_encode([
            'iat' => $now,
            'exp' => $now + 86400, // 24h
            'mercure' => ['publish' => ['*']],
            'authorization_details' => [
                [
                    'type' => 'https://mercure.rocks/authorization-detail',
                    'actions' => ['publish'],
                    'topics' => [
                        ['match' => '*']
                    ]
                ]
            ]
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->jwtKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
}
