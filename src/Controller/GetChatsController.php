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

        // Correção de LIDs conhecidos
        $DB->update('glpi_plugin_whatsappsimples_chats', ['phone_number' => '5517997772618'], ['phone_number' => '64703850111065']);
        $DB->update('glpi_plugin_whatsappsimples_chats', ['phone_number' => '5517996454039'], ['phone_number' => '181656010924208']);

        $tab = $request->query->get('tab', 'mine');
        $currentUserId = (int) Session::getLoginUserID();

        $chats = [];

        if ($tab === 'mine') {
            // Meus Atendimentos (Atribuídos ao usuário logado e não encerrados)
            $iterator = $DB->request([
                'SELECT' => ['id', 'phone_number', 'contact_name', 'users_id', 'status', 'date_mod'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'WHERE'  => [
                    'users_id' => $currentUserId,
                    'status'   => ['in_progress', 'pending']
                ],
                'ORDER'  => ['date_mod DESC']
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
                'ORDER'  => ['date_mod DESC']
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
            // Lista Única de Contatos (Agrupada por telefone sem violar MySQL strict mode)
            $iterator = $DB->request([
                'SELECT'  => [
                    'phone_number',
                    'MAX(id) AS id',
                    'MAX(contact_name) AS contact_name',
                    'MAX(date_mod) AS max_date_mod'
                ],
                'FROM'    => 'glpi_plugin_whatsappsimples_chats',
                'GROUPBY' => 'phone_number',
                'ORDER'   => 'max_date_mod DESC'
            ]);

            foreach ($iterator as $row) {
                $chats[] = [
                    'id'           => (int) $row['id'],
                    'phone_number' => $row['phone_number'],
                    'contact_name' => !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'],
                    'users_id'     => 0,
                    'status'       => 'contact',
                    'date_mod'     => $row['max_date_mod'] ?? date('Y-m-d H:i:s'),
                ];
            }
        }

        return new JsonResponse(['chats' => $chats]);
    }
}
