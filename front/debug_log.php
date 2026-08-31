<?php
include("../../../inc/includes.php");

$logFile = GLPI_ROOT . '/files/_log/php-errors.log';

if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -50);
    echo "<h1>Últimos erros do GLPI (php-errors.log)</h1>";
    echo "<pre>";
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "Arquivo de log não encontrado em $logFile";
}
