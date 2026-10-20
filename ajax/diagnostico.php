<?php
define('GLPI_KEEP_CSRF_TOKEN', true);

if (php_sapi_name() !== 'cli') {
    die("Este script deve ser executado pelo terminal (CLI).\n");
}

// Carregar compat e .env
require_once dirname(__DIR__) . '/src/compat.php';

// Tentar carregar includes do GLPI se disponível
$glpiRoot = dirname(__DIR__, 3);
if (file_exists($glpiRoot . '/inc/includes.php')) {
    include_once $glpiRoot . '/inc/includes.php';
}

echo "========================================================\n";
echo "   DIAGNÓSTICO E CORREÇÃO DE AMBIENTE - WHATSAPP URE\n";
echo "========================================================\n\n";

$novoIp = wa_env('EVOLUTION_SERVER_URL', 'http://10.180.152.29:8080');

global $DB;
if (isset($DB) && is_object($DB) && method_exists($DB, 'update')) {
    try {
        echo "[+] Conexão nativa do GLPI ativa!\n";
        echo "[+] Atualizando o IP da Evolution API no banco de dados para: $novoIp\n";

        $success = $DB->update('glpi_plugin_whatsappsimples_configs', ['value' => $novoIp], ['name' => 'server_url']);
        if ($success) {
            echo "    -> Sucesso! O IP foi atualizado no banco via GLPI.\n";
        } else {
            echo "    -> OK. O IP já estava configurado ou a linha foi atualizada.\n";
        }

        echo "\n========================================================\n";
        echo " TUDO PRONTO! O GLPI AGORA APONTA PARA A EVOLUTION API! \n";
        echo "========================================================\n";
        exit(0);
    } catch (\Throwable $e) {
        echo "[!] Erro via DB do GLPI: " . $e->getMessage() . "\n";
    }
}

// Fallback: Conexão direta via PDO utilizando variáveis do .env (sem senhas hardcoded)
$host = wa_env('DB_HOST', 'localhost');
$dbname = wa_env('DB_NAME', 'glpi_db');
$user = wa_env('DB_USER', '');
$pass = wa_env('DB_PASS', '');

if (empty($user)) {
    die("[!] Não foi possível identificar as credenciais do banco. Defina DB_USER e DB_PASS no arquivo .env.\n");
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "[+] Conexão com o banco de dados ($dbname) bem sucedida via PDO!\n\n";
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
