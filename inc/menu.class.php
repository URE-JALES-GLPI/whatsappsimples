<?php
class PluginWhatsappsimplesMenu extends CommonGLPI
{
    static $rightname = 'plugin_whatsappsimples';

    static function getTypeName($nb = 0): string
    {
        return 'WhatsApp Simples';
    }

    static function getMenuName(): string
    {
        return 'WhatsApp Simples';
    }

    static function getIcon(): string
    {
        return 'ti ti-brand-whatsapp';
    }

    static function getMenuContent(): array
    {
        $menu = [
            'title' => self::getMenuName(),
            'page'  => '../glpi/plugins/whatsappsimples/front/index.php',
            'icon'  => self::getIcon(),
        ];

        if (Session::haveRight('config', UPDATE)) {
            $menu['options']['config'] = [
                'title' => 'Configurações',
                'page'  => '../glpi/plugins/whatsappsimples/front/config.php',
                'icon'  => 'ti ti-settings',
            ];
        }

        return $menu;
    }
}