<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Whatsappsimples\Menu;

include_once __DIR__ . '/hook.php';

// Registra os caminhos STATELESS (API / Webhook sem sessão e sem CSRF) no GLPI 11
if (class_exists(\Glpi\Http\SessionManager::class)) {
    \Glpi\Http\SessionManager::registerPluginStatelessPath('whatsappsimples', '#^/webhook#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('whatsappsimples', '#^/front/webhook.php#');
    \Glpi\Http\SessionManager::registerPluginStatelessPath('whatsappsimples', '#^/public/webhook.php#');
}

if (class_exists(\Glpi\Http\Firewall::class)) {
    \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts('whatsappsimples', '#^/front/webhook.php#', \Glpi\Http\Firewall::STRATEGY_NO_CHECK);
    \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts('whatsappsimples', '#^/public/webhook.php#', \Glpi\Http\Firewall::STRATEGY_NO_CHECK);
    \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts('whatsappsimples', '#^/webhook#', \Glpi\Http\Firewall::STRATEGY_NO_CHECK);
    \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts('whatsappsimples', '#^/ajax/.+#', \Glpi\Http\Firewall::STRATEGY_AUTHENTICATED);
}

function plugin_init_whatsappsimples(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['whatsappsimples'] = true;
    $PLUGIN_HOOKS['change_profile']['whatsappsimples'] = ['PluginWhatsappsimplesProfile', 'changeProfile'];

    if (class_exists(\Glpi\Http\SessionManager::class)) {
        \Glpi\Http\SessionManager::registerPluginStatelessPath('whatsappsimples', '#^/webhook#');
        \Glpi\Http\SessionManager::registerPluginStatelessPath('whatsappsimples', '#^/front/webhook.php#');
        \Glpi\Http\SessionManager::registerPluginStatelessPath('whatsappsimples', '#^/public/webhook.php#');
    }

    if (class_exists(\Glpi\Http\Firewall::class)) {
        \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts('whatsappsimples', '#^/front/webhook.php#', \Glpi\Http\Firewall::STRATEGY_NO_CHECK);
        \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts('whatsappsimples', '#^/public/webhook.php#', \Glpi\Http\Firewall::STRATEGY_NO_CHECK);
        \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts('whatsappsimples', '#^/webhook#', \Glpi\Http\Firewall::STRATEGY_NO_CHECK);
        \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts('whatsappsimples', '#^/ajax/.+#', \Glpi\Http\Firewall::STRATEGY_AUTHENTICATED);
    }

    Plugin::registerClass('PluginWhatsappsimplesProfile', ['addtabon' => 'Profile']);

    if (Session::getLoginUserID()) {
        if (Session::haveRight('plugin_whatsappsimples', READ)) {
            $PLUGIN_HOOKS[Hooks::MENU_TOADD]['whatsappsimples'] = [
                'tools' => Menu::class,
            ];
        }

        if (Session::haveRight('config', UPDATE)) {
            $PLUGIN_HOOKS['config_page']['whatsappsimples'] = 'front/config.php';
        }
    }
}

function plugin_version_whatsappsimples(): array
{
    return [
        'name'         => 'WhatsApp',
        'version'      => '1.0.0',
        'author'       => 'Equipe de TI',
        'license'      => 'GPLv3+',
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