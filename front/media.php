<?php
include ("../../../inc/includes.php");
Session::checkLoginUser();

$file = $_GET['file'] ?? '';
if (empty($file) || strpos($file, '..') !== false || strpos($file, '/') !== false || strpos($file, '\\') !== false) {
    http_response_code(400);
    die("Invalid file parameter");
}

$mediaDir = GLPI_PLUGIN_DOC_DIR . '/whatsappsimples/media';
$filePath = $mediaDir . '/' . $file;

if (!file_exists($filePath)) {
    http_response_code(404);
    die("File not found");
}

$mime = mime_content_type($filePath);
if (!$mime) {
    $mime = 'application/octet-stream';
}

$filesize = filesize($filePath);
$offset = 0;
$length = $filesize;

if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);
    $offset = intval($matches[1]);
    $length = (isset($matches[2]) && $matches[2] !== '') ? intval($matches[2]) - $offset + 1 : $filesize - $offset;

    http_response_code(206);
    header("Content-Range: bytes $offset-" . ($offset + $length - 1) . "/$filesize");
} else {
    http_response_code(200);
}

header("Content-Type: $mime");
header("Content-Length: $length");
header("Accept-Ranges: bytes");
header("Cache-Control: public, max-age=31536000"); // Cache for 1 year

$fileHandle = fopen($filePath, 'rb');
if ($fileHandle !== false) {
    fseek($fileHandle, $offset);
    $buffer = 1024 * 8;
    while (!feof($fileHandle) && ($pos = ftell($fileHandle)) <= ($offset + $length - 1)) {
        if ($pos + $buffer > $offset + $length) {
            $buffer = $offset + $length - $pos;
        }
        echo fread($fileHandle, $buffer);
        flush();
    }
    fclose($fileHandle);
}
