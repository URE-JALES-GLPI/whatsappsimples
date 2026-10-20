<?php

namespace GlpiPlugin\Whatsappsimples\DTO;

class IncomingMessageDTO
{

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
        } elseif (empty($text) && !empty($messageData['contactMessage'])) {
            $vcardText = self::parseVcard($messageData['contactMessage']['vcard'] ?? '');
            if ($vcardText) {
                $text = '[VCARD_SHARE:' . json_encode(['contacts' => [$vcardText]], JSON_UNESCAPED_UNICODE) . ']';
            } else {
                $text = '👤 Contato recebido';
            }
        } elseif (empty($text) && !empty($messageData['contactsArrayMessage']['contacts'])) {
            $contactsList = [];
            foreach ($messageData['contactsArrayMessage']['contacts'] as $c) {
                $v = self::parseVcard($c['vcard'] ?? '');
                if ($v) $contactsList[] = $v;
            }
            if (!empty($contactsList)) {
                $text = '[VCARD_SHARE:' . json_encode(['contacts' => $contactsList], JSON_UNESCAPED_UNICODE) . ']';
            } else {
                $text = '👤 Contatos recebidos';
            }
        }

        $mediaUrl = null;
        $extractedBase64 = '';
        $mimetype = '';
        
        // IGNORAR o base64 que vem no webhook para áudio e vídeo, pois a Evolution API frequentemente envia ele truncado (pela metade).
        // Isso forçará o sistema a entrar no "fallback" e baixar o arquivo completo pela rota dedicada getBase64FromMediaMessage.
        if (!empty($messageData['audioMessage']) || !empty($messageData['videoMessage'])) {
            unset($data['base64']);
            unset($messageData['audioMessage']['base64']);
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
                // Retentativas progressivas seguras (1.0s -> +1.5s [2.5s] -> +2.0s [4.5s total]).
                // Cobre até 4.5s com 3 chances de captura (mais seguro que o único tiro do sleep(4) antigo).
                // Se a mídia estiver pronta no 1º intervalo, libera o Apache em apenas 1s!
                $delaysMs = (!empty($messageData['videoMessage']) || !empty($messageData['audioMessage']))
                    ? [1000, 1500, 2000]
                    : [300, 800];

                foreach ($delaysMs as $delayMs) {
                    usleep($delayMs * 1000);
                    $apiRes = \GlpiPlugin\Whatsappsimples\Service\EvolutionApiService::getBase64FromMediaMessage($messageId);
                    if (!empty($apiRes['success']) && !empty($apiRes['base64'])) {
                        $extractedBase64 = $apiRes['base64'];
                        $mimetype = $messageData['imageMessage']['mimetype'] ?? $messageData['videoMessage']['mimetype'] ?? $messageData['audioMessage']['mimetype'] ?? $messageData['documentMessage']['mimetype'] ?? 'application/octet-stream';
                        break;
                    }
                }
            }
        }

        $mediaData = null;
        if (!empty($extractedBase64)) {
            if (class_exists('\GlpiPlugin\Whatsappsimples\Service\MediaStorage')) {
                $mediaData = \GlpiPlugin\Whatsappsimples\Service\MediaStorage::saveFromBase64($extractedBase64, $messageData, $messageId);
            }
            // fallback (ainda usa a string pra compatibilidade caso a Migration falhe)
            if ($mediaData) {
                $mediaUrl = 'doc_' . $mediaData['media_path'];
            } else {
                $mediaUrl = null;
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
            $mediaUrl,
            $mediaData
        );
    }

    public function __construct(
        private string $remoteJid,
        private string $pushName,
        private string $text,
        private string $messageId,
        private bool $fromMe,
        private int $timestamp,
        private string $originalJid = '',
        private ?string $mediaUrl = null,
        private ?array $mediaData = null
    ) {
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
        return $this->fromMe;
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

    public function getMediaData(): ?array
    {
        return $this->mediaData;
    }

    private static function parseVcard(string $vcard): ?array
    {
        if (empty($vcard)) return null;

        $name = 'Contato';
        $phone = '';

        if (preg_match('/FN:(.*)/', $vcard, $matches)) {
            $name = trim($matches[1]);
        }

        if (preg_match('/waid=([0-9]+)/', $vcard, $matches)) {
            $phone = $matches[1];
        } elseif (preg_match('/TEL.*:([+0-9\s\-]+)/', $vcard, $matches)) {
            $phone = preg_replace('/[^0-9]/', '', $matches[1]);
        }

        if (empty($phone)) {
            return null;
        }

        return ['name' => $name, 'phone' => $phone];
    }
}
