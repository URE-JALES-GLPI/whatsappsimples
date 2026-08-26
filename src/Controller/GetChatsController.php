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

        try {
            self::consolidateDuplicateChats();
        } catch (\Throwable $e) {
            // Silencia falhas secundarias de consolidação para não interromper a API
        }

        $tab = $request->query->get('tab', 'mine');
        $currentUserId = (int) Session::getLoginUserID();

        $chats = [];

        try {
            if ($tab === 'mine') {
                // Meus Atendimentos Ativos (Atribuídos a mim e não encerrados)
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
                // Fila de Atendimento (Não atribuídos users_id = 0 e não encerrados)
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
                // Todos os Contatos (1 único registro por número)
                $iterator = $DB->request([
                    'SELECT' => ['id', 'phone_number', 'contact_name', 'users_id', 'status', 'date_mod'],
                    'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                    'ORDER'  => 'date_mod DESC'
                ]);

                foreach ($iterator as $row) {
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

    /**
     * Mescla e consolida registros duplicados no banco garantindo 1 unico chat por numero de telefone
     */
    private static function consolidateDuplicateChats(): void
    {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
            return;
        }

        // 1. Corrige LIDs conhecidos
        $DB->update('glpi_plugin_whatsappsimples_chats', ['phone_number' => '5517997772618'], ['phone_number' => '64703850111065']);
        $DB->update('glpi_plugin_whatsappsimples_chats', ['phone_number' => '5517996454039'], ['phone_number' => '181656010924208']);

        // 2. Busca telefones com múltiplos registros
        $duplicates = $DB->request([
            'SELECT'  => ['phone_number', 'COUNT(*) AS total', 'MIN(id) AS keep_id'],
            'FROM'    => 'glpi_plugin_whatsappsimples_chats',
            'GROUPBY' => 'phone_number'
        ]);

        foreach ($duplicates as $dup) {
            if ((int) ($dup['total'] ?? 0) <= 1) {
                continue;
            }

            $phone  = $dup['phone_number'];
            $keepId = (int) $dup['keep_id'];

            $otherRows = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'WHERE'  => [
                    'phone_number' => $phone,
                    'id'           => ['NE', $keepId]
                ]
            ]);

            $removeIds = [];
            foreach ($otherRows as $r) {
                $removeIds[] = (int) $r['id'];
            }

            if (!empty($removeIds)) {
                // Relinca todas as mensagens para o registro unico mantido
                $DB->update('glpi_plugin_whatsappsimples_messages', ['chats_id' => $keepId], ['chats_id' => $removeIds]);
                // Remove os registros duplicados de chats
                $DB->delete('glpi_plugin_whatsappsimples_chats', ['id' => $removeIds]);
            }
        }
    }
}
