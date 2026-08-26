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

        $currentUserId = (int) Session::getLoginUserID();
        $tab = $request->query->get('tab', 'mine');

        $chats = [];

        try {
            // 1. Busca os registros mais recentes por numero de telefone (isolamento 100% individual por numero)
            $iterator = $DB->request([
                'SELECT' => ['id', 'phone_number', 'contact_name', 'users_id', 'status', 'date_mod'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'ORDER'  => 'date_mod DESC, id DESC'
            ]);

            $latestByPhone = [];
            foreach ($iterator as $row) {
                $phone = $row['phone_number'];
                if (empty($phone)) {
                    continue;
                }
                if (!isset($latestByPhone[$phone])) {
                    $latestByPhone[$phone] = [
                        'id'           => (int) $row['id'],
                        'phone_number' => $row['phone_number'],
                        'contact_name' => !empty($row['contact_name']) ? $row['contact_name'] : $row['phone_number'],
                        'users_id'     => (int) $row['users_id'],
                        'status'       => $row['status'],
                        'date_mod'     => $row['date_mod'],
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
