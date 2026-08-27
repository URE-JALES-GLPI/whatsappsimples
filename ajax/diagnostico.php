<?php

if (php_sapi_name() !== 'cli') {
    die("Este script deve ser executado pelo terminal (CLI).\n");
}

echo "========================================================\n";
echo "   DIAGNÓSTICO E CORREÇÃO DE AMBIENTE - WHATSAPP URE\n";
echo "========================================================\n\n";

$host = 'localhost';
$dbname = 'glpi_db';
$user = 'glpi_db_user';
$pass = 'Media@2026#Jales';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "[+] Conexão com o banco de dados ($dbname) bem sucedida!\n\n";

    // 1. Corrigir IP da Evolution API para o Docker (VM 103 -> 10.180.152.29)
    $novoIp = 'http://10.180.152.29:8080';
    echo "[+] Atualizando o IP da Evolution API no banco de dados para: $novoIp\n";

    $stmt = $pdo->prepare("UPDATE glpi_plugin_whatsappsimples_configs SET value = :ip WHERE name = 'server_url'");
    $stmt->execute(['ip' => $novoIp]);

    if ($stmt->rowCount() > 0) {
        echo "    -> Sucesso! O IP foi alterado.\n";
    } else {
        echo "    -> OK. O IP já estava configurado ou a linha foi atualizada.\n";
    }

    echo "\n========================================================\n";
    echo " TUDO PRONTO! O GLPI AGORA APONTA PARA A EVOLUTION API! \n";
    echo "========================================================\n";

} catch (PDOException $e) {
    echo "\n[!] ERRO FATAL: Não foi possível conectar ao Banco de Dados.\n";
    echo "Detalhes: " . $e->getMessage() . "\n";
}
