<?php

if (php_sapi_name() !== 'cli') {
    die("Este script deve ser executado pelo terminal (CLI).\n");
}

define('GLPI_ROOT', '/var/www/html/glpi');
include_once(GLPI_ROOT . '/inc/includes.php');

global $DB;

echo "========================================================\n";
echo "   DIAGNÓSTICO E CORREÇÃO DE AMBIENTE - WHATSAPP URE\n";
echo "========================================================\n\n";

// 1. Corrigir IP da Evolution API para o Docker (VM 103 -> 10.180.152.29)
$novoIp = 'http://10.180.152.29:8080';
echo "[+] Atualizando o IP da Evolution API no banco de dados para: $novoIp\n";

if ($DB->tableExists('glpi_plugin_whatsappsimples_configs')) {
    $DB->update('glpi_plugin_whatsappsimples_configs', ['value' => $novoIp], ['name' => 'server_url']);
    echo "    -> Sucesso! O sistema agora apontará para o Docker no final .29\n";
} else {
    echo "    -> ERRO: Tabela de configurações não encontrada.\n";
}

// 2. Verificar conexão com o Banco de Dados do GLPI
echo "\n[+] Testando comunicação com o Banco de Dados...\n";
if ($DB->connected) {
    echo "    -> Sucesso! GLPI está se conectando ao banco de dados perfeitamente.\n";
} else {
    echo "    -> ERRO: Sem conexão ao banco de dados (Verifique a senha no config_db.php).\n";
}

// 3. Re-registrar Webhook na Evolution API
echo "\n[+] Reconfigurando o Webhook na Evolution API...\n";
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;

$stateResult = EvolutionApiService::getConnectionState();
if (isset($stateResult['state']) && $stateResult['state'] === 'open') {
    global $CFG_GLPI;
    $scheme = 'http';
    $host   = '10.180.152.27';
    $root   = '/glpi';
    $token  = urlencode(EvolutionApiService::getConfig('api_token') ?: 'ure_jales_evolution_token_2026');
    $webhookUrl = "{$scheme}://{$host}{$root}/plugins/whatsappsimples/front/webhook.php?token={$token}";

    EvolutionApiService::setWebhook($webhookUrl);
    echo "    -> Sucesso! Webhook registrado com a nova URL do GLPI: $webhookUrl\n";
} else {
    echo "    -> AVISO: A API retornou estado fechado ou inacessível. O QR Code precisa ser lido ou o container está desligado.\n";
    echo "    -> Detalhes: " . json_encode($stateResult) . "\n";
}

echo "\n========================================================\n";
echo " TUDO PRONTO! AGORA EXECUTE UM 'git pull origin marco'  \n";
echo "========================================================\n";
