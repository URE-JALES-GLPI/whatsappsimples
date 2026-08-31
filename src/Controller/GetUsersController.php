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

        // Fetch users who are active and have the plugin_whatsappsimples right
        $iterator = $DB->request([
            'SELECT'   => ['glpi_users.id', 'glpi_users.firstname', 'glpi_users.realname', 'glpi_users.name'],
            'DISTINCT' => true,
            'FROM'     => 'glpi_users',
            'INNER JOIN' => [
                'glpi_profiles_users' => ['FKEY' => ['glpi_profiles_users' => 'users_id', 'glpi_users' => 'id']],
                'glpi_profilerights'  => ['FKEY' => ['glpi_profilerights' => 'profiles_id', 'glpi_profiles_users' => 'profiles_id']]
            ],
            'WHERE'  => [
                'glpi_users.is_active' => 1,
                'glpi_users.is_deleted' => 0,
                'glpi_profilerights.name' => 'plugin_whatsappsimples',
                'glpi_profilerights.rights' => ['>', 0]
            ],
            'ORDER'  => 'glpi_users.realname ASC, glpi_users.firstname ASC, glpi_users.name ASC'
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
