<?php

include_once(__DIR__ . '/../../../inc/includes.php');

$controller = new \GlpiPlugin\Whatsappsimples\Controller\GetChatsController();
$request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = $controller($request);
$response->send();
