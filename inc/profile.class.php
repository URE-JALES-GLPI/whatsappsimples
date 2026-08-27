<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginWhatsappsimplesProfile extends CommonDBTM
{
    public static $rightname = 'profile';

    public static function install(): bool
    {
        global $DB;
        $DB->doQuery("
            INSERT IGNORE INTO `glpi_profilerights` (`profiles_id`, `name`, `rights`)
            VALUES
                (4, 'plugin_whatsappsimples', 31),
                (3, 'plugin_whatsappsimples', 31)
        ");
        self::changeProfile();
        return true;
    }

    public static function uninstall(): bool
    {
        global $DB;
        $DB->doQuery("DELETE FROM `glpi_profilerights` WHERE `name` = 'plugin_whatsappsimples'");
        return true;
    }

    public static function changeProfile(): void
    {
        global $DB;
        $pid = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($pid <= 0) return;

        $row = $DB->request([
            'SELECT' => ['rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['profiles_id' => $pid, 'name' => 'plugin_whatsappsimples'],
        ])->current();

        $_SESSION['glpiactiveprofile']['plugin_whatsappsimples'] = (is_array($row) && isset($row['rights'])) ? (int) $row['rights'] : 31;
    }
}
