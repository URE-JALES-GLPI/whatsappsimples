<?php

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

global $CFG_GLPI;
$rootDoc = $CFG_GLPI['root_doc'] ?? '';
Html::redirect($rootDoc . '/plugins/whatsappsimples/Config');