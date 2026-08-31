<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetUsersController
{
    #[Route('/ajax/users', name: 'whatsappsimples_api_users', methods: ['GET'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        Session::checkRight('plugin_whatsappsimples', READ);

        global $DB;

        // Fetch users who are active and not deleted
        $iterator = $DB->request([
            'SELECT' => ['id', 'firstname', 'realname', 'name'],
            'FROM'   => 'glpi_users',
            'WHERE'  => [
                'is_active' => 1,
                'is_deleted' => 0
            ],
            'ORDER'  => 'realname ASC, firstname ASC, name ASC'
        ]);

        $users = [];
        foreach ($iterator as $row) {
            $displayName = trim($row['firstname'] . ' ' . $row['realname']);
            if (empty($displayName)) {
                $displayName = $row['name'];
            }
            
            $users[] = [
                'id' => (int) $row['id'],
                'name' => $displayName
            ];
        }

        return new JsonResponse(['users' => $users]);
    }
}
