<?php

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

Html::header(
    'WhatsApp Simples',
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginWhatsappsimplesMenu'
);

$config = new PluginWhatsappsimplesConfig();
$config->showForm();

Html::footer();