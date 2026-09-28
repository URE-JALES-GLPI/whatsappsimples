<?php
require_once __DIR__ . '/../../inc/includes.php';

global $DB;

echo "<h3>Tentando alterar a coluna media_url para LONGTEXT...</h3>";

$query = "ALTER TABLE `glpi_plugin_whatsappsimples_messages` MODIFY COLUMN `media_url` longtext DEFAULT NULL";

if ($DB->query($query)) {
    echo "<p style='color:green;'>SUCESSO! A coluna foi alterada para LONGTEXT.</p>";
} else {
    echo "<p style='color:red;'>ERRO AO ALTERAR A TABELA:</p>";
    echo "<pre>" . $DB->error() . "</pre>";
}

echo "<h3>Verificando o tamanho máximo suportado atual:</h3>";
$res = $DB->query("DESCRIBE glpi_plugin_whatsappsimples_messages media_url");
if ($row = $DB->fetchAssoc($res)) {
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}
