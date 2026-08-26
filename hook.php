<?php

function plugin_whatsappsimples_install(): bool
{
    PluginWhatsappsimplesProfile::install();
    plugin_whatsappsimples_ensureTables();
    return true;
}

function plugin_whatsappsimples_uninstall(): bool
{
    global $DB;
    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_whatsappsimples_config`");
    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_whatsappsimples_configs`");
    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_whatsappsimples_chats`");
    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_whatsappsimples_messages`");
    $DB->doQuery("DELETE FROM `glpi_profilerights` WHERE `name` = 'plugin_whatsappsimples'");
    return true;
}

function plugin_whatsappsimples_ensureTables(): void
{
    global $DB;

    // 1. Tabela de Configurações da EvolutionAPI
    if (!$DB->tableExists('glpi_plugin_whatsappsimples_configs')) {
        $DB->doQuery("
            CREATE TABLE `glpi_plugin_whatsappsimples_configs` (
                `id`       int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`     varchar(255) NOT NULL,
                `value`    text,
                `date_mod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $DB->insert('glpi_plugin_whatsappsimples_configs', ['name' => 'server_url',    'value' => 'http://localhost:8080']);
        $DB->insert('glpi_plugin_whatsappsimples_configs', ['name' => 'api_token',     'value' => 'sua_chave_global_here']);
        $DB->insert('glpi_plugin_whatsappsimples_configs', ['name' => 'instance_name', 'value' => 'atendimento']);
    }

    // 2. Tabela de Sessões/Atendimentos (Chats)
    if (!$DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
        $query = "CREATE TABLE `glpi_plugin_whatsappsimples_chats` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `phone_number` varchar(50) NOT NULL,
            `contact_name` varchar(255) DEFAULT '',
            `users_id` int(11) NOT NULL DEFAULT 0,
            `status` varchar(20) NOT NULL DEFAULT 'pending',
            `first_response_date` datetime DEFAULT NULL,
            `date_closed` datetime DEFAULT NULL,
            `date_creation` datetime NOT NULL,
            `date_mod` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `phone_number` (`phone_number`),
            KEY `users_id` (`users_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $DB->doQuery($query);
    } else {
        if (!$DB->fieldExists('glpi_plugin_whatsappsimples_chats', 'date_closed')) {
            $DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_chats` ADD COLUMN `date_closed` datetime DEFAULT NULL AFTER `first_response_date`");
        }
    }

    // 3. Tabela de Histórico de Mensagens (Messages)
    if (!$DB->tableExists('glpi_plugin_whatsappsimples_messages')) {
        $query = "CREATE TABLE `glpi_plugin_whatsappsimples_messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `chats_id` int(11) NOT NULL,
            `users_id` int(11) NOT NULL DEFAULT 0,
            `message_id` varchar(255) NOT NULL DEFAULT '',
            `sender_type` varchar(20) NOT NULL DEFAULT 'user',
            `message_text` text,
            `media_url` varchar(500) DEFAULT NULL,
            `date_creation` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `chats_id` (`chats_id`),
            KEY `users_id` (`users_id`),
            KEY `message_id` (`message_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $DB->doQuery($query);
    } else {
        if (!$DB->fieldExists('glpi_plugin_whatsappsimples_messages', 'users_id')) {
            $DB->doQuery("ALTER TABLE `glpi_plugin_whatsappsimples_messages` ADD COLUMN `users_id` int(11) NOT NULL DEFAULT 0 AFTER `chats_id`");
        }
    }
}
