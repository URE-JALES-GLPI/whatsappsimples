<?php
require_once __DIR__ . '/../../inc/includes.php';

global $DB;
$query = "SELECT id, message_text, LENGTH(media_url) as len FROM glpi_plugin_whatsappsimples_messages WHERE media_url LIKE 'data:%' ORDER BY id DESC LIMIT 10";
$result = $DB->request($query);

echo "<pre>";
foreach ($result as $row) {
    print_r($row);
}
echo "</pre>";
