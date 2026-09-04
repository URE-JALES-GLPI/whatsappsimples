<?php

namespace GlpiPlugin\Whatsappsimples\DTO;

class IncomingMessageDTO
{
    private string $remoteJid;
    private string $pushName;
    private string $text;
    private string $messageId;
    private bool $isFromMe;
    private int $timestamp;
    
    // O JID que chegou originalmente no webhook
    private string $originalJid;
    private ?string $mediaUrl;

    public function __construct(
        string $remoteJid,
        string $pushName,
        string $text,
        string $messageId,
        bool $isFromMe,
        int $timestamp,
        string $originalJid,
        ?string $mediaUrl = null
    ) {
        $this->remoteJid = $remoteJid;
        $this->pushName = $pushName;
        $this->text = $text;
        $this->messageId = $messageId;
        $this->isFromMe = $isFromMe;
        $this->timestamp = $timestamp;
        $this->originalJid = $originalJid;
        $this->mediaUrl = $mediaUrl;
    }

    public static function fromPayload(array $payload, string $resolvedPhoneNumber): self
    {
        $data  = $payload['data'] ?? $payload;
        $key   = $data['key'] ?? [];
        
        $isFromMe = !empty($key['fromMe']);
        $pushName = $data['pushName'] ?? $resolvedPhoneNumber;
        $messageId = $key['id'] ?? ('msg_' . time() . '_' . rand(100, 999));
        $timestamp = $data['messageTimestamp'] ?? time();
        $originalJid = !empty($key['participant']) ? $key['participant'] : (!empty($key['remoteJid']) ? $key['remoteJid'] : '');

        // Extração do conteúdo da mensagem
        $messageData = $data['message'] ?? [];
        $text = $messageData['conversation'] 
            ?? $messageData['extendedTextMessage']['text'] 
            ?? $messageData['imageMessage']['caption'] 
            ?? $messageData['videoMessage']['caption'] 
            ?? $messageData['documentMessage']['caption'] 
            ?? '';

        if (empty($text) && !empty($messageData['imageMessage'])) {
            $text = '📷 Imagem recebida';
        } elseif (empty($text) && !empty($messageData['audioMessage'])) {
            $text = '🎵 Áudio recebido';
        } elseif (empty($text) && !empty($messageData['documentMessage'])) {
            $text = '📄 Documento recebido';
        }

        $mediaUrl = null;
        $extractedBase64 = '';
        $mimetype = '';
        
        // Forçar busca de áudio e vídeo pela API para evitar o bug de truncamento do Webhook na Evolution API
        if (!empty($messageData['audioMessage'])) {
            unset($data['base64']);
            unset($messageData['audioMessage']['base64']);
        } elseif (!empty($messageData['videoMessage'])) {
            unset($data['base64']);
            unset($messageData['videoMessage']['base64']);
        }

        if (!empty($data['base64'])) {
            $extractedBase64 = $data['base64'];
            $mimetype = $messageData['imageMessage']['mimetype'] ?? $messageData['videoMessage']['mimetype'] ?? $messageData['audioMessage']['mimetype'] ?? $messageData['documentMessage']['mimetype'] ?? 'application/octet-stream';
        } elseif (!empty($messageData['imageMessage']['base64'])) {
            $extractedBase64 = $messageData['imageMessage']['base64'];
            $mimetype = $messageData['imageMessage']['mimetype'] ?? 'image/jpeg';
        } elseif (!empty($messageData['videoMessage']['base64'])) {
            $extractedBase64 = $messageData['videoMessage']['base64'];
            $mimetype = $messageData['videoMessage']['mimetype'] ?? 'video/mp4';
        } elseif (!empty($messageData['audioMessage']['base64'])) {
            $extractedBase64 = $messageData['audioMessage']['base64'];
            $mimetype = $messageData['audioMessage']['mimetype'] ?? 'audio/ogg';
        } elseif (!empty($messageData['documentMessage']['base64'])) {
            $extractedBase64 = $messageData['documentMessage']['base64'];
            $mimetype = $messageData['documentMessage']['mimetype'] ?? 'application/pdf';
        }

        if (empty($extractedBase64)) {
            // Tenta buscar da API usando o endpoint se o webhook não enviou
            $hasMedia = !empty($messageData['imageMessage']) || !empty($messageData['videoMessage']) || !empty($messageData['audioMessage']) || !empty($messageData['documentMessage']);
            if ($hasMedia && class_exists('\GlpiPlugin\Whatsappsimples\Service\EvolutionApiService')) {
                $apiRes = \GlpiPlugin\Whatsappsimples\Service\EvolutionApiService::getBase64FromMediaMessage($messageId);
                if (!empty($apiRes['success']) && !empty($apiRes['base64'])) {
                    $extractedBase64 = $apiRes['base64'];
                    
                    // Como usamos convertToMp4 = true na API, forçamos o mimetype para audio/mp4 no caso de áudios
                    $mimetype = $messageData['imageMessage']['mimetype'] ?? $messageData['videoMessage']['mimetype'] ?? (!empty($messageData['audioMessage']) ? 'audio/mp4' : null) ?? $messageData['documentMessage']['mimetype'] ?? 'application/octet-stream';
                }
            }
        }

        if (!empty($extractedBase64)) {
            // Se o base64 não vier com o prefixo 'data:', a gente coloca.
            if (!str_starts_with($extractedBase64, 'data:')) {
                $mediaUrl = 'data:' . $mimetype . ';base64,' . $extractedBase64;
            } else {
                $mediaUrl = $extractedBase64;
            }
            
            // Limpa o texto padrao de placeholder pra nao poluir a tela se não houver legenda de verdade
            if (in_array($text, ['📷 Imagem recebida', '🎵 Áudio recebido', '📄 Documento recebido'])) {
                $text = ''; 
            }
        }

        return new self(
            $resolvedPhoneNumber,
            $pushName,
            $text,
            $messageId,
            $isFromMe,
            $timestamp,
            $originalJid,
            $mediaUrl
        );
    }

    public function getRemoteJid(): string
    {
        return $this->remoteJid;
    }

    public function getPushName(): string
    {
        return $this->pushName;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function isFromMe(): bool
    {
        return $this->isFromMe;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getOriginalJid(): string
    {
        return $this->originalJid;
    }

    public function getMediaUrl(): ?string
    {
        return $this->mediaUrl;
    }
}
