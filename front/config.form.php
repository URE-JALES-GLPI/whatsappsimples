<?php

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

PluginWhatsappsimplesConfig::setConfig('server_url', $_POST['server_url'] ?? 'http://localhost:3001');
PluginWhatsappsimplesConfig::setConfig('api_token',  $_POST['api_token']  ?? '');
PluginWhatsappsimplesConfig::setConfig('as_enabled', isset($_POST['as_enabled']) ? '1' : '0');

Html::back();