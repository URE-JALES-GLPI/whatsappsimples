<?php
use Glpi\Plugin\Hooks;
function plugin_init_whatsappsimples(): void
{
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['csrf_compliant']['whatsappsimples'] = true;
    $PLUGIN_HOOKS['change_profile']['whatsappsimples'] = ['PluginWhatsappsimplesProfile', 'changeProfile'];

    Plugin::registerClass('PluginWhatsappsimplesProfile', ['addtabon' => 'Profile']);

    if (Session::getLoginUserID()) {
        if (Session::haveRight('plugin_whatsappsimples', READ)) {
            $PLUGIN_HOOKS[Hooks::MENU_TOADD]['whatsappsimples'] = ['tools' => 
            \GlpiPlugin\Whatsappsimples\Controller\ChatPageController::class,];
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
        'name'         => 'WhatsApp',
        'version'      => '1.0.0',
        'author'       => 'GLPI Admin',
        'license'      => 'GPLv3',
        'homepage'     => '',
        'requirements' => [
            'glpi' => ['min' => '11.0.0'],
            'php'  => ['min' => '8.1'],
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