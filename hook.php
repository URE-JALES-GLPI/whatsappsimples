<?php

function plugin_whatsappsimples_install(): bool
{
    global $DB;

    $table = 'glpi_plugin_whatsappsimples_configs';

    if (!$DB->tableExists($table)) {
        $DB->doQuery("
            CREATE TABLE `$table` (
                `id`       int unsigned NOT NULL AUTO_INCREMENT,
                `name`     varchar(255) NOT NULL,
                `value`    text,
                `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $DB->insert($table, ['name' => 'server_url',  'value' => 'http://localhost:3001']);
        $DB->insert($table, ['name' => 'api_token',   'value' => 'glpi_whatsapp_token_2025']);
        $DB->insert($table, ['name' => 'as_enabled',  'value' => '1']);
    }

    $DB->doQuery("
        INSERT IGNORE INTO `glpi_profilerights` (`profiles_id`, `name`, `rights`)
        VALUES
            (4, 'plugin_whatsappsimples', 31),
            (3, 'plugin_whatsappsimples', 31)
    ");

    return true;
}

function plugin_whatsappsimples_uninstall(): bool
{
    global $DB;
    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_whatsappsimples_configs`");
    $DB->doQuery("DELETE FROM `glpi_profilerights` WHERE `name` = 'plugin_whatsappsimples'");
    return true;
}