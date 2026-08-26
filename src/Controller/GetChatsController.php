<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetChatsController extends AbstractController
{
    #[Route('/ajax/chats', name: 'whatsappsimples_api_chats', methods: ['GET', 'POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        global $DB;

        if (!$DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
            return new JsonResponse(['chats' => []]);
        }

        $tab = $request->query->get('tab', 'mine');
        $currentUserId = (int) Session::getLoginUserID();

        $chats = [];

        try {
            // Unificação Simples e Direta: GROUP BY phone_number garante 1 único contato por linha na lista da esquerda!
            if ($tab === 'mine') {
                $iterator = $DB->request([
                    'SELECT'  => ['MAX(id) AS id', 'phone_number', 'MAX(contact_name) AS contact_name', 'MAX(users_id) AS users_id', 'MAX(status) AS status', 'MAX(date_mod) AS max_date_mod'],
                    'FROM'    => 'glpi_plugin_whatsappsimples_chats',
                    'WHERE'   => [
                        'users_id' => $currentUserId,
                        'status'   => ['in_progress', 'pending']
                    ],
                    'GROUPBY' => 'phone_number',
                    'ORDER'   => 'max_date_mod DESC'
                ]);

                foreach ($iterator as $row) {
                    $chats[] = [
                        'id'           => (int) $row['id'],
                        'phone_number' => $row['phone_number'],
                        'contact_name' => !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'],
                        'users_id'     => (int) $row['users_id'],
                        'status'       => $row['status'],
                        'date_mod'     => $row['max_date_mod'] ?? date('Y-m-d H:i:s'),
                    ];
                }
            } elseif ($tab === 'queue') {
                $iterator = $DB->request([
                    'SELECT'  => ['MAX(id) AS id', 'phone_number', 'MAX(contact_name) AS contact_name', 'MAX(users_id) AS users_id', 'MAX(status) AS status', 'MAX(date_mod) AS max_date_mod'],
                    'FROM'    => 'glpi_plugin_whatsappsimples_chats',
                    'WHERE'   => [
                        'users_id' => 0,
                        'status'   => ['pending', 'in_progress']
                    ],
                    'GROUPBY' => 'phone_number',
                    'ORDER'   => 'max_date_mod DESC'
                ]);

                foreach ($iterator as $row) {
                    $chats[] = [
                        'id'           => (int) $row['id'],
                        'phone_number' => $row['phone_number'],
                        'contact_name' => !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'],
                        'users_id'     => (int) $row['users_id'],
                        'status'       => $row['status'],
                        'date_mod'     => $row['max_date_mod'] ?? date('Y-m-d H:i:s'),
                    ];
                }
            } elseif ($tab === 'all') {
                $iterator = $DB->request([
                    'SELECT'  => ['MAX(id) AS id', 'phone_number', 'MAX(contact_name) AS contact_name', 'MAX(users_id) AS users_id', 'MAX(status) AS status', 'MAX(date_mod) AS max_date_mod'],
                    'FROM'    => 'glpi_plugin_whatsappsimples_chats',
                    'GROUPBY' => 'phone_number',
                    'ORDER'   => 'max_date_mod DESC'
                ]);

                foreach ($iterator as $row) {
                    $chats[] = [
                        'id'           => (int) $row['id'],
                        'phone_number' => $row['phone_number'],
                        'contact_name' => !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'],
                        'users_id'     => (int) $row['users_id'],
                        'status'       => $row['status'],
                        'date_mod'     => $row['max_date_mod'] ?? date('Y-m-d H:i:s'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            return new JsonResponse(['chats' => [], 'error' => $e->getMessage()]);
        }

        return new JsonResponse(['chats' => $chats]);
    }
}
