<?php

namespace GlpiPlugin\Whatsappsimples\Service;

class MercurePublisherService
{
    // Atenção: Aqui usamos o IP do servidor Docker onde o Mercure está rodando!
    private string $mercureUrl = 'http://10.180.152.29:3001/.well-known/mercure';
    private string $jwtKey = 'UmaChaveSuperSecretaParaUreOmnichannel2026';

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
