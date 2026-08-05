<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginWhatsappsimplesProfile extends CommonDBTM
{
    public static $rightname = 'profile';

    public const RIGHT_WHATSAPP = 'plugin_whatsappsimples';

    public static function getAllRights(): array
    {
        return [
            [
                'itemtype' => self::class,
                'label'    => 'WhatsApp Simples',
                'field'    => self::RIGHT_WHATSAPP,
                'rights'   => [
                    READ   => __('Read'),
                    UPDATE => __('Update'),
                ],
                'default' => 0,
            ],
        ];
    }

    public static function install(): bool
    {
        global $DB;
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_profiles']) as $profile) {
            self::addDefaultProfileInfos((int)$profile['id'], self::getDefaultRightsMap());
        }
        self::changeProfile();
        return true;
    }

    public static function uninstall(): bool
    {
        global $DB;
        foreach (self::getAllRights() as $right) {
            $DB->delete(ProfileRight::getTable(), ['name' => $right['field']]);
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
        }
        return true;
    }

    public static function addDefaultProfileInfos(int $profiles_id, array $rights): void
    {
        $profileRight = new ProfileRight();
        foreach ($rights as $right_name => $right_value) {
            if (!countElementsInTable(ProfileRight::getTable(), [
                'profiles_id' => $profiles_id,
                'name'        => $right_name,
            ])) {
                $profileRight->add([
                    'profiles_id' => $profiles_id,
                    'name'        => $right_name,
                    'rights'      => $right_value,
                ]);
            }
        }
    }

    public static function changeProfile(): void
    {
        global $DB;
        $active_profile_id = (int)($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($active_profile_id <= 0) return;
        foreach (self::getAllRights() as $right) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
        }
        $iterator = $DB->request([
            'SELECT' => ['name', 'rights'],
            'FROM'   => ProfileRight::getTable(),
            'WHERE'  => [
                'profiles_id' => $active_profile_id,
                'name'        => array_column(self::getAllRights(), 'field'),
            ],
        ]);
        foreach ($iterator as $row) {
            $_SESSION['glpiactiveprofile'][$row['name']] = (int)$row['rights'];
        }
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::RIGHT_WHATSAPP, READ);
    }

    private static function getDefaultRightsMap(): array
    {
        $rights = [];
        foreach (self::getAllRights() as $right) {
            $rights[$right['field']] = $right['default'];
        }
        return $rights;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('id')) {
            return "<span class='d-inline-flex align-items-center gap-1'>"
                . "<i class='ti ti-brand-whatsapp'></i><span>WhatsApp Simples</span></span>";
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $CFG_GLPI;
        if (!($item instanceof Profile)) return false;
        if (!$item->canView()) return false;

        $profiles_id    = (int)$item->getID();
        self::addDefaultProfileInfos($profiles_id, self::getDefaultRightsMap());
        $current_rights = self::getProfileRightValue($profiles_id, self::RIGHT_WHATSAPP, 0);
        $canedit        = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        $form_action    = $CFG_GLPI['root_doc'] . '/plugins/whatsappsimples/front/profile.form.php';

        echo "<form name='whatsappsimples_profile_form' method='post' action='" . $form_action . "'>";
        echo "<div class='spaced'><table class='tab_cadre_fixehov'>";
        echo "<tr class='headerRow'><th colspan='2'>Permissões — WhatsApp Simples</th></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td width='50%'><strong>Acesso ao WhatsApp Simples</strong><br>";
        echo "<small>Controla o acesso ao menu Ferramentas ? WhatsApp Simples</small></td>";
        echo "<td>";

        if ($canedit) {
            Dropdown::showFromArray('rights', [
                0             => '— Sem acesso —',
                READ          => 'Visualizar',
                READ | UPDATE => 'Visualizar e Usar',
            ], ['value' => $current_rights]);
        } else {
            $labels = [
                0             => 'Sem acesso',
                READ          => 'Visualizar',
                READ | UPDATE => 'Visualizar e Usar',
            ];
            echo $labels[$current_rights] ?? 'Sem acesso';
        }

        echo "</td></tr>";

        if ($canedit) {
            echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
            echo Html::hidden('profiles_id', ['value' => $profiles_id]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo "<button type='submit' name='update' value='1' class='btn btn-primary'>";
            echo "<i class='ti ti-device-floppy'></i> Salvar";
            echo "</button>";
            echo "</td></tr>";
        }

        echo "</table></div>";
        Html::closeForm();
        return true;
    }

    private static function getProfileRightValue(int $profiles_id, string $right_name, int $default = 0): int
    {
        global $DB;
        $row = $DB->request([
            'SELECT' => ['rights'],
            'FROM'   => ProfileRight::getTable(),
            'WHERE'  => ['profiles_id' => $profiles_id, 'name' => $right_name],
        ])->current();
        return (is_array($row) && isset($row['rights'])) ? (int)$row['rights'] : $default;
    }
}