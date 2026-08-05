<?php
function plugin_init_whatsappsimples(): void
{
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['csrf_compliant']['whatsappsimples'] = true;
    $PLUGIN_HOOKS['change_profile']['whatsappsimples'] = ['PluginWhatsappsimplesProfile', 'changeProfile'];

    Plugin::registerClass('PluginWhatsappsimplesProfile', ['addtabon' => 'Profile']);

    if (Session::getLoginUserID()) {
        if (Session::haveRight('plugin_whatsappsimples', READ)) {
            $PLUGIN_HOOKS['menu_toadd']['whatsappsimples'] = ['tools' => 'PluginWhatsappsimplesMenu'];
        }
        if (Session::haveRight('config', UPDATE)) {
            $PLUGIN_HOOKS['config_page']['whatsappsimples'] = 'front/config.php';
        }
        $PLUGIN_HOOKS['add_css']['whatsappsimples'] = [
            'public/css/whatsappsimples.css'
        ];
    }
    $PLUGIN_HOOKS['item_add']['whatsappsimples'] = [
        'TicketValidation' => ['PluginWhatsappsimplesValidation', 'notifyValidator'],
    ];
}
function plugin_version_whatsappsimples(): array
{
    return [
        'name'         => 'WhatsApp Simples',
        'version'      => '1.0.0',
        'author'       => 'GLPI Admin',
        'license'      => 'GPLv3',
        'homepage'     => '',
        'requirements' => [
            'glpi' => ['min' => '11.0.0'],
        ],
    ];
}
function plugin_whatsappsimples_check_prerequisites(): bool
{
    return true;
}
function plugin_whatsappsimples_check_config(): bool
{
    return true;
}