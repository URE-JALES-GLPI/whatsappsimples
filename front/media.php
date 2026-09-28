<?php
include ("../../../inc/includes.php");
Session::checkLoginUser();
session_write_close(); // Impede travamento do GLPI enquanto faz streaming de vídeo longo

$file = $_GET['file'] ?? '';
$id = $_GET['id'] ?? 0;

global $DB;
$filePath = '';
$mime = 'application/octet-stream';

if ($id > 0) {
    // Fase 2: Recebe ID da mensagem e busca no banco
    $res = $DB->request([
        'SELECT' => ['media_path', 'media_mime'],
        'FROM'   => 'glpi_plugin_whatsappsimples_messages',
        'WHERE'  => ['id' => $id]
    ]);
    if ($row = $res->current()) {
        $file = $row['media_path'];
        $mime = $row['media_mime'] ?: $mime;
    }
}

if (empty($file) || strpos($file, '..') !== false) {
    http_response_code(400);
    die("Invalid request");
}

$baseDir = GLPI_PLUGIN_DOC_DIR . '/whatsappsimples/media';
$filePath = realpath($baseDir . '/' . $file);

// Valida se o realpath está dentro do baseDir (Segurança)
if ($filePath === false || strpos($filePath, realpath($baseDir)) !== 0 || !file_exists($filePath)) {
    http_response_code(404);
    die("File not found");
}

// Limpeza de buffers para streaming correto
while (ob_get_level() > 0) {
    ob_end_clean();
}
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 'Off');
set_time_limit(0);

$filesize = filesize($filePath);
$offset = 0;
$length = $filesize;

header("Content-Type: $mime");
header("Accept-Ranges: bytes");
header("X-Content-Type-Options: nosniff");
header("Content-Disposition: inline; filename=\"media\"");

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches)) {
        $offset = intval($matches[1]);
        $end = (isset($matches[2]) && $matches[2] !== '') ? intval($matches[2]) : $filesize - 1;

        if ($offset > $end || $end >= $filesize) {
            http_response_code(416);
            header("Content-Range: bytes */$filesize");
            exit;
        }
        $length = $end - $offset + 1;
        http_response_code(206);
        header("Content-Range: bytes $offset-$end/$filesize");
    } else {
        http_response_code(416);
        header("Content-Range: bytes */$filesize");
        exit;
    }
} else {
    http_response_code(200);
}

header("Content-Length: $length");

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

$fileHandle = fopen($filePath, 'rb');
if ($fileHandle !== false) {
    fseek($fileHandle, $offset);
    $bufferSize = 256 * 1024; // ~256 KB
    $bytesSent = 0;

    while (!feof($fileHandle) && $bytesSent < $length) {
        if (connection_aborted()) {
            break;
        }
        $readSize = min($bufferSize, $length - $bytesSent);
        $buffer = fread($fileHandle, $readSize);
        if ($buffer === false) {
            break;
        }
        echo $buffer;
        flush();
        $bytesSent += strlen($buffer);
    }
    fclose($fileHandle);
}
