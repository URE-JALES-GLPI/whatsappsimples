<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetChatsController
{
    #[Route('/ajax/chats', name: 'whatsappsimples_api_chats', methods: ['GET', 'POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        Session::checkRight('plugin_whatsappsimples', READ);
        global $DB;

        if (!$DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
            return new JsonResponse(['chats' => []]);
        }

        $currentUserId = (int) Session::getLoginUserID();
        $tab = $request->query->get('tab', 'mine');

        $chats = [];

        try {
            // 1. Busca os registros mais recentes por numero de telefone e faz JOIN para trazer o nome do técnico
            $iterator = $DB->request([
                'SELECT' => [
                    'c.id', 'c.phone_number', 'c.contact_name', 'c.users_id', 'c.status', 'c.date_mod', 'c.unread_count',
                    'u.realname', 'u.firstname'
                ],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats AS c',
                'LEFT JOIN' => [
                    'glpi_users AS u' => [
                        'ON' => [
                            'c' => 'users_id',
                            'u' => 'id'
                        ]
                    ]
                ],
                'ORDER'  => 'c.date_mod DESC, c.id DESC'
            ]);

            $latestByPhone = [];
            foreach ($iterator as $row) {
                $phone = $row['phone_number'];
                if (empty($phone)) {
                    continue;
                }

                if (!isset($latestByPhone[$phone])) {
                    $displayName = !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'];

                    $technicianName = null;
                    if (!empty($row['users_id'])) {
                        $technicianName = trim(($row['firstname'] ?? '') . ' ' . ($row['realname'] ?? ''));
                        if (empty($technicianName)) {
                            $technicianName = 'Técnico ID ' . $row['users_id'];
                        }
                    }

                    $latestByPhone[$phone] = [
                        'id'              => (int) $row['id'],
                        'phone_number'    => $row['phone_number'],
                        'contact_name'    => $displayName,
                        'users_id'        => (int) $row['users_id'],
                        'technician_name' => $technicianName,
                        'status'          => $row['status'],
                        'date_mod'        => $row['date_mod'],
                        'unread_count'    => (int) ($row['unread_count'] ?? 0),
                    ];
                }
            }

            // 2. Aplica filtro de exibição estrito por aba sem mesclar contatos
            foreach ($latestByPhone as $c) {
                if ($tab === 'mine') {
                    // Chats: Atendimentos ativos vinculados ao técnico logado
                    if ($c['users_id'] === $currentUserId && $c['status'] !== 'closed') {
                        $chats[] = $c;
                    }
                } elseif ($tab === 'queue') {
                    // Fila: Apenas chamados não atribuídos (users_id == 0) e pendentes.
                    if ($c['users_id'] === 0 && $c['status'] === 'pending') {
                        $chats[] = $c;
                    }
                } elseif ($tab === 'all') {
                    // Contatos: Exibe todos os contatos únicos cadastrados no sistema
                    $chats[] = $c;
                }
            }

        } catch (\Throwable $e) {
            return new JsonResponse(['chats' => [], 'error' => $e->getMessage()]);
        }

        return new JsonResponse(['chats' => $chats]);
    }
}
