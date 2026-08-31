<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginWhatsappsimplesProfile extends CommonGLPI
{
    public static $rightname = 'profile';

    public static function canView()
    {
        return Session::haveRight('profile', READ);
    }

    public static function canCreate()
    {
        return Session::haveRight('profile', UPDATE);
    }

    public static function getTypeName($nb = 0)
    {
        return __('WhatsApp', 'whatsappsimples');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() === 'Profile') {
            return __('WhatsApp', 'whatsappsimples');
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() === 'Profile') {
            $profile = new self();
            $profile->showForm($item->getID());
        }
        return true;
    }

    public static function install(): bool
    {
        global $DB;
        $rights = [
            'plugin_whatsappsimples'          => 31, // Acesso ao painel e chat
            'plugin_whatsappsimples_transfer' => 1,  // Permissão de transferência (1 = sim)
            'plugin_whatsappsimples_metrics'  => 1   // Permissão de métricas (1 = sim)
        ];

        foreach (['4', '3'] as $profId) { // Super-Admin e Admin
            foreach ($rights as $rightName => $rightValue) {
                $DB->doQuery("
                    INSERT IGNORE INTO `glpi_profilerights` (`profiles_id`, `name`, `rights`)
                    VALUES ($profId, '$rightName', $rightValue)
                ");
            }
        }
        self::changeProfile();
        return true;
    }

    public static function uninstall(): bool
    {
        global $DB;
        $DB->doQuery("DELETE FROM `glpi_profilerights` WHERE `name` LIKE 'plugin_whatsappsimples%'");
        return true;
    }

    public static function changeProfile(): void
    {
        global $DB;
        $pid = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($pid <= 0) return;

        $iterator = $DB->request([
            'SELECT' => ['name', 'rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['profiles_id' => $pid, 'name' => ['LIKE', 'plugin_whatsappsimples%']],
        ]);

        foreach ($iterator as $row) {
            $_SESSION['glpiactiveprofile'][$row['name']] = (int) $row['rights'];
        }
    }

    /**
     * Define the matrix of rights
     */
    public static function getRightsMatrix(): array
    {
        return [
            'plugin_whatsappsimples' => [
                'label' => __('Acesso ao Painel e Atendimentos (Chat)', 'whatsappsimples'),
                'type'  => 'CHECKBOX'
            ],
            'plugin_whatsappsimples_transfer' => [
                'label' => __('Transferir Chats para outros usuários', 'whatsappsimples'),
                'type'  => 'CHECKBOX'
            ],
            'plugin_whatsappsimples_metrics' => [
                'label' => __('Visualizar Métricas e Relatórios', 'whatsappsimples'),
                'type'  => 'CHECKBOX'
            ]
        ];
    }

    public function showForm($profiles_id)
    {
        global $DB;

        echo "<form name='form_whatsappsimples_profile' action='" . Plugin::getWebDir('whatsappsimples') . "/front/profile.form.php' method='post'>";
        Html::generateCsrfToken();
        echo "<input type='hidden' name='profiles_id' value='$profiles_id'>";

        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='tab_bg_1'><th colspan='2'>" . __('Direitos do WhatsApp Simples', 'whatsappsimples') . "</th></tr>";
        
        // Fetch current rights for this profile
        $currentRights = [];
        $iterator = $DB->request([
            'SELECT' => ['name', 'rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['profiles_id' => $profiles_id, 'name' => ['LIKE', 'plugin_whatsappsimples%']]
        ]);
        foreach ($iterator as $row) {
            $currentRights[$row['name']] = (int) $row['rights'];
        }

        $matrix = self::getRightsMatrix();

        foreach ($matrix as $rightName => $def) {
            echo "<tr class='tab_bg_2'>";
            echo "<td>" . $def['label'] . "</td>";
            echo "<td>";

            $val = $currentRights[$rightName] ?? 0;
            $checked = ($val > 0) ? "checked='checked'" : "";
            
            // Hidden input to ensure value is passed even if unchecked
            echo "<input type='hidden' name='rights[$rightName]' value='0'>";
            echo "<input type='checkbox' name='rights[$rightName]' value='31' $checked>";

            echo "</td>";
            echo "</tr>";
        }

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2' class='center'>";
        echo "<input type='submit' name='update' value='" . _sx('button', 'Save') . "' class='submit'>";
        echo "</td>";
        echo "</tr>";

        echo "</table>";
        echo "</form>";
    }
}
