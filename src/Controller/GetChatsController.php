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
        $seenNumbers = [];

        try {
            if ($tab === 'mine') {
                $iterator = $DB->request([
                    'SELECT' => ['id', 'phone_number', 'contact_name', 'users_id', 'status', 'date_mod'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                    'WHERE'  => [
                        'users_id' => $currentUserId,
                        'status'   => ['in_progress', 'pending']
                    ],
                    'ORDER'  => 'date_mod DESC'
                ]);

                foreach ($iterator as $row) {
                    $phone = $row['phone_number'];
                    if (isset($seenNumbers[$phone])) {
                        continue; // Deduplica na memória mantendo 1 único registro mais recente por número
                    }
                    $seenNumbers[$phone] = true;

                    $chats[] = [
                        'id'           => (int) $row['id'],
                        'phone_number' => $row['phone_number'],
                        'contact_name' => !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'],
                        'users_id'     => (int) $row['users_id'],
                        'status'       => $row['status'],
                        'date_mod'     => $row['date_mod'],
                    ];
                }
            } elseif ($tab === 'queue') {
                $iterator = $DB->request([
                    'SELECT' => ['id', 'phone_number', 'contact_name', 'users_id', 'status', 'date_mod'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                    'WHERE'  => [
                        'users_id' => 0,
                        'status'   => ['pending', 'in_progress']
                    ],
                    'ORDER'  => 'date_mod DESC'
                ]);

                foreach ($iterator as $row) {
                    $phone = $row['phone_number'];
                    if (isset($seenNumbers[$phone])) {
                        continue;
                    }
                    $seenNumbers[$phone] = true;

                    $chats[] = [
                        'id'           => (int) $row['id'],
                        'phone_number' => $row['phone_number'],
                        'contact_name' => !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'],
                        'users_id'     => (int) $row['users_id'],
                        'status'       => $row['status'],
                        'date_mod'     => $row['date_mod'],
                    ];
                }
            } elseif ($tab === 'all') {
                $iterator = $DB->request([
                    'SELECT' => ['id', 'phone_number', 'contact_name', 'users_id', 'status', 'date_mod'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                    'ORDER'  => 'date_mod DESC'
                ]);

                foreach ($iterator as $row) {
                    $phone = $row['phone_number'];
                    if (isset($seenNumbers[$phone])) {
                        continue;
                    }
                    $seenNumbers[$phone] = true;

                    $chats[] = [
                        'id'           => (int) $row['id'],
                        'phone_number' => $row['phone_number'],
                        'contact_name' => !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'],
                        'users_id'     => (int) $row['users_id'],
                        'status'       => $row['status'],
                        'date_mod'     => $row['date_mod'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            return new JsonResponse(['chats' => [], 'error' => $e->getMessage()]);
        }

        return new JsonResponse(['chats' => $chats]);
    }
}
