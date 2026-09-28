<?php
$token = 'ure_jales_evolution_token_2026';
$url = 'http://localhost:8080/message/sendText/atendimento';
$headers = ['Content-Type: application/json', 'apikey: ' . $token];

function testPayload($payload, $desc) {
    global $url, $headers;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "$desc\nCode: $code\nRes: $res\n\n";
}

testPayload(['number' => '181656010924208@lid', 'textMessage' => ['text' => 'test']], 'No options');
testPayload(['number' => '181656010924208@lid', 'options' => ['verifyContact' => false], 'textMessage' => ['text' => 'test']], 'verifyContact: false');
testPayload(['number' => '181656010924208@lid', 'options' => ['validateNumber' => false], 'textMessage' => ['text' => 'test']], 'validateNumber: false');
testPayload(['number' => '181656010924208@lid', 'options' => ['verify' => false], 'textMessage' => ['text' => 'test']], 'verify: false');
testPayload(['number' => '181656010924208@lid', 'options' => ['checkNumber' => false], 'textMessage' => ['text' => 'test']], 'checkNumber: false');
