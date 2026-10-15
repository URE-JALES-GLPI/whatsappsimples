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

    public function publish(string $topic, array $payload): void
    {
        $token = $this->generateJwt();

        $postData = http_build_query([
            'topic' => $topic,
            'data'  => json_encode($payload)
        ]);

        $ch = curl_init($this->mercureUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 800);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
    }

    private function generateJwt(): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        // Permissão para publicar em qualquer tópico
        $payload = json_encode(['mercure' => ['publish' => ['*']]]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->jwtKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
}
