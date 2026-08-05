<?php
class PluginWhatsappsimplesConfig extends CommonDBTM
{
    static $rightname = 'config';
    static $table     = 'glpi_plugin_whatsappsimples_configs';

    static function getTypeName($nb = 0): string
    {
        return 'Configurações WhatsApp Simples';
    }

    static function getConfig(string $name, $default = null)
    {
        global $DB;
        $iterator = $DB->request([
            'SELECT' => ['value'],
            'FROM'   => self::$table,
            'WHERE'  => ['name' => $name],
            'LIMIT'  => 1
        ]);
        if (count($iterator)) {
            foreach ($iterator as $row) {
                return $row['value'];
            }
        }
        return $default;
    }

    static function setConfig(string $name, $value): bool
    {
        global $DB;
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::$table,
            'WHERE'  => ['name' => $name],
            'LIMIT'  => 1
        ]);
        if (count($iterator)) {
            foreach ($iterator as $row) {
                $DB->update(self::$table, ['value' => $value], ['id' => $row['id']]);
            }
        } else {
            $DB->insert(self::$table, ['name' => $name, 'value' => $value]);
        }
        return true;
    }

    static function getAllConfigs(): array
    {
        global $DB;
        $configs  = [];
        $iterator = $DB->request([
            'SELECT' => ['name', 'value'],
            'FROM'   => self::$table
        ]);
        foreach ($iterator as $row) {
            $configs[$row['name']] = $row['value'];
        }
        return $configs;
    }

    function showForm($ID = 0, array $options = [])
    {
        global $CFG_GLPI;

        $configs   = self::getAllConfigs();
        $serverUrl = $configs['server_url'] ?? 'http://localhost:3001';
        $apiToken  = $configs['api_token']  ?? 'glpi_whatsapp_token_2025';
        $asEnabled = $configs['as_enabled'] ?? '1';

        $action = $CFG_GLPI['root_doc'] . '/plugins/whatsappsimples/front/config.form.php';

        echo '<form method="post" action="' . $action . '">';
        echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';
        echo '<table class="tab_cadre_fixe">';
        echo '<tr class="headerRow"><th colspan="4">Configurações do Servidor WhatsApp</th></tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td style="width:25%">URL do Servidor</td>';
        echo '<td colspan="3"><input type="text" name="server_url" value="' . htmlspecialchars($serverUrl) . '" class="form-control" style="width:400px"><br><span style="font-size:11px;color:#888;">Endereço completo com porta. Ex: http://localhost:3001</span></td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>Token de Autenticação</td>';
        echo '<td colspan="3"><input type="text" name="api_token" value="' . htmlspecialchars($apiToken) . '" class="form-control" style="width:400px"></td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1">';
        echo '<td>Plugin Ativo</td>';
        echo '<td colspan="3"><input type="checkbox" name="as_enabled" value="1"' . ($asEnabled == '1' ? ' checked' : '') . '></td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1"><td colspan="4" class="center">';
        echo '<button type="submit" class="btn btn-primary">Salvar</button>';
        echo '</td></tr>';

        echo '</table></form>';
    }
}