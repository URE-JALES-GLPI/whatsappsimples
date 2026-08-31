<?php
define('GLPI_KEEP_CSRF_TOKEN', true);

include_once(__DIR__ . '/../../../inc/includes.php');

$controller = new \GlpiPlugin\Whatsappsimples\Controller\GetMessagesController();
$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $controller($request);
$response->send();
