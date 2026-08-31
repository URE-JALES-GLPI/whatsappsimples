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

    public function __construct(
        string $remoteJid,
        string $pushName,
        string $text,
        string $messageId,
        bool $isFromMe,
        int $timestamp,
        string $originalJid
    ) {
        $this->remoteJid = $remoteJid;
        $this->pushName = $pushName;
        $this->text = $text;
        $this->messageId = $messageId;
        $this->isFromMe = $isFromMe;
        $this->timestamp = $timestamp;
        $this->originalJid = $originalJid;
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

        return new self(
            $resolvedPhoneNumber,
            $pushName,
            $text,
            $messageId,
            $isFromMe,
            $timestamp,
            $originalJid
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
}
