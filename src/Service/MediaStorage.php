<?php

namespace GlpiPlugin\Whatsappsimples\Service;

class MediaStorage
{
    public static function saveFromBase64(string $base64, array $messageData, string $keyId): ?array
    {
        $logFile = GLPI_LOG_DIR . '/whatsappsimples-media.log';

        // Determinar mimetype e dados esperados do payload original
        $mimetype = 'application/octet-stream';
        $fileLengthExpected = 0;
        $fileSha256Expected = '';

        if (!empty($messageData['videoMessage'])) {
            $msg = $messageData['videoMessage'];
            $mimetype = $msg['mimetype'] ?? 'video/mp4';
            $fileLengthExpected = self::parseLength($msg['fileLength'] ?? 0);
            $fileSha256Expected = self::parseSha256($msg['fileSha256'] ?? '');
        } elseif (!empty($messageData['audioMessage'])) {
            $msg = $messageData['audioMessage'];
            $mimetype = $msg['mimetype'] ?? 'audio/ogg';
            $fileLengthExpected = self::parseLength($msg['fileLength'] ?? 0);
            $fileSha256Expected = self::parseSha256($msg['fileSha256'] ?? '');
        } elseif (!empty($messageData['imageMessage'])) {
            $msg = $messageData['imageMessage'];
            $mimetype = $msg['mimetype'] ?? 'image/jpeg';
            $fileLengthExpected = self::parseLength($msg['fileLength'] ?? 0);
            $fileSha256Expected = self::parseSha256($msg['fileSha256'] ?? '');
        } elseif (!empty($messageData['documentMessage'])) {
            $msg = $messageData['documentMessage'];
            $mimetype = $msg['mimetype'] ?? 'application/pdf';
            $fileLengthExpected = self::parseLength($msg['fileLength'] ?? 0);
            $fileSha256Expected = self::parseSha256($msg['fileSha256'] ?? '');
        }

        $base64Size = strlen($base64);

        if (str_starts_with($base64, 'data:')) {
            $parts = explode(',', $base64, 2);
            $base64 = $parts[1] ?? '';
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            self::log($logFile, "[$keyId] FALHA: base64_decode retornou false");
            return null;
        }

        $decodedBytes = strlen($decoded);
        $actualSha256 = hash('sha256', $decoded, true);
        
        $hashOk = ($fileSha256Expected === '' || $actualSha256 === $fileSha256Expected) ? 'OK' : 'FALHOU';
        $status = ($decodedBytes >= $fileLengthExpected && $fileLengthExpected > 0) ? 'complete' : 'incomplete';
        if ($fileLengthExpected == 0) {
            $status = 'complete'; // Fallback se nao veio length
        }

        // Criar pasta GLPI_PLUGIN_DOC_DIR/whatsappsimples/media/AAAA/MM
        $yearMonth = date('Y/m');
        $baseDir = GLPI_PLUGIN_DOC_DIR . '/whatsappsimples/media';
        $targetDir = $baseDir . '/' . $yearMonth;
        
        if (!is_dir($targetDir)) {
            $created = mkdir($targetDir, 0775, true);
            if (!$created) {
                $error = error_get_last();
                self::log($logFile, "[$keyId] FALHA: mkdir($targetDir) falhou: " . ($error['message'] ?? 'Erro desconhecido'));
                return null;
            }
        }

        // Definir extensão
        $ext = 'bin';
        if (str_contains($mimetype, 'jpeg') || str_contains($mimetype, 'jpg')) $ext = 'jpg';
        elseif (str_contains($mimetype, 'png')) $ext = 'png';
        elseif (str_contains($mimetype, 'mp4')) $ext = 'mp4';
        elseif (str_contains($mimetype, 'ogg')) $ext = 'ogg'; // ignora os codecs=opus do mimetype
        elseif (str_contains($mimetype, 'mpeg')) $ext = 'mp3';
        elseif (str_contains($mimetype, 'pdf')) $ext = 'pdf';

        $safeKeyId = preg_replace('/[^a-zA-Z0-9_-]/', '', $keyId);
        if (empty($safeKeyId)) {
            $safeKeyId = md5(uniqid());
        }

        $filename = $safeKeyId . '.' . $ext;
        $finalPath = $targetDir . '/' . $filename;
        $partPath = $finalPath . '.part';

        // Se o arquivo final já existir, idempotência
        if (file_exists($finalPath)) {
            self::log($logFile, "[$keyId] IDEMPOTENCIA: Arquivo $finalPath já existe, pulando gravação.");
            unset($decoded);
            return [
                'media_path' => $yearMonth . '/' . $filename,
                'media_mime' => $mimetype,
                'media_size' => filesize($finalPath),
                'media_sha256' => bin2hex($actualSha256),
                'media_status' => $status
            ];
        }

        $written = file_put_contents($partPath, $decoded);
        unset($decoded); // Limpar memória imediatamente

        if ($written === false) {
            $error = error_get_last();
            self::log($logFile, "[$keyId] FALHA: file_put_contents($partPath) falhou: " . ($error['message'] ?? 'Erro desconhecido'));
            return null;
        }

        $diskSize = filesize($partPath);
        rename($partPath, $finalPath);

        $memUsage = memory_get_peak_usage(true) / 1024 / 1024;

        self::log($logFile, "[$keyId] GRAVADO: Mime=$mimetype | B64Size=$base64Size | Decoded=$decodedBytes | Expected=$fileLengthExpected | Hash=$hashOk | Disk=$diskSize | MemPeak={$memUsage}MB | Status=$status");

        return [
            'media_path' => $yearMonth . '/' . $filename,
            'media_mime' => $mimetype,
            'media_size' => $diskSize,
            'media_sha256' => bin2hex($actualSha256),
            'media_status' => $status
        ];
    }

    private static function parseLength($val): int
    {
        if (is_array($val) && isset($val['low'])) {
            return (int) $val['low'];
        }
        return (int) $val;
    }

    private static function parseSha256($val): string
    {
        if (empty($val)) return '';
        if (is_string($val)) {
            $decoded = base64_decode($val, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }
        if (is_array($val) || is_object($val)) {
            // Em NodeJS pode vir como Buffer JSON {type:"Buffer", data:[...]}
            $arr = (array) $val;
            if (isset($arr['data']) && is_array($arr['data'])) {
                return implode('', array_map('chr', $arr['data']));
            }
        }
        return '';
    }

    private static function log(string $file, string $message): void
    {
        $date = date('Y-m-d H:i:s');
        @file_put_contents($file, "[$date] $message\n", FILE_APPEND);
    }
}
