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
    #[Route('/ajax/chats', name: 'whatsappsimples_api_chats', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        global $DB;

        $tab = $request->query->get('tab', 'mine');
        $currentUserId = (int) Session::getLoginUserID();

        $where = [];
        if ($tab === 'mine') {
            $where['users_id'] = $currentUserId;
        } elseif ($tab === 'queue') {
            $where['users_id'] = 0;
            $where['status']   = 'pending';
        }

        $iterator = $DB->request([
            'SELECT' => ['id', 'phone_number', 'contact_name', 'users_id', 'status', 'date_mod'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => $where,
            'ORDER'  => ['date_mod DESC']
        ]);

        $chats = [];
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

        return new JsonResponse(['chats' => $chats]);
    }
}
