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
        if (!Session::haveRight('plugin_whatsappsimples', READ)) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado'], 403);
        }
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
                'ORDER'  => 'c.date_mod DESC, c.id DESC',
                'LIMIT'  => 500
            ]);

            $latestByPhone = [];
            foreach ($iterator as $row) {
                $phone = $row['phone_number'];
                if (empty($phone)) {
                    continue;
                }

                // Um atendimento ativo (pending/in_progress) sempre prevalece sobre o historico encerrado
                $isActive = $row['status'] !== 'closed';
                $alreadyPicked = isset($latestByPhone[$phone]);
                if ($alreadyPicked && !($isActive && $latestByPhone[$phone]['status'] === 'closed')) {
                    continue;
                }

                $displayName = !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'];

                $technicianName = null;
                if (!empty($row['users_id']) && $row['status'] !== 'closed') {
                    $technicianName = trim(($row['firstname'] ?? '') . ' ' . ($row['realname'] ?? ''));
                    if (empty($technicianName)) {
                        $technicianName = 'Técnico ID ' . $row['users_id'];
                    }
                } elseif ($row['status'] === 'closed') {
                    $technicianName = 'Histórico (Encerrado)';
                    $row['users_id'] = 0;
                } elseif ($row['status'] === 'pending') {
                    $technicianName = 'Aguardando na Fila';
                    $row['users_id'] = 0;
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

            $counts = [
                'queue'       => 0,
                'mine'        => 0,
                'mine_unread' => 0,
                'all'         => count($latestByPhone),
            ];

            // 2. Aplica filtro de exibição e calcula contadores em uma única passagem
            foreach ($latestByPhone as $c) {
                $isMine  = ($c['users_id'] === $currentUserId && $c['status'] !== 'closed');
                $isQueue = ($c['users_id'] === 0 && $c['status'] === 'pending');

                if ($isMine) {
                    $counts['mine']++;
                    if ($c['unread_count'] > 0) {
                        $counts['mine_unread'] += $c['unread_count'];
                    }
                    if ($tab === 'mine') {
                        $chats[] = $c;
                    }
                } elseif ($isQueue) {
                    $counts['queue']++;
                    if ($tab === 'queue') {
                        $chats[] = $c;
                    }
                }

                if ($tab === 'all') {
                    $chats[] = $c;
                }
            }

        } catch (\Throwable $e) {
            return new JsonResponse(['chats' => [], 'counts' => ['queue' => 0, 'mine' => 0, 'all' => 0], 'error' => $e->getMessage()]);
        }

        return new JsonResponse([
            'chats'  => $chats,
            'counts' => $counts,
        ]);
    }
}
