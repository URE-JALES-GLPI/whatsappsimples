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

        // Auto-Sanitização e Mapeamento de LIDs e exclusão de contato de teste
        self::sanitizeDatabase();

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

    /**
     * Remove contatos de teste e converte LIDs do WhatsApp para numeros reais de celular
     */
    private static function sanitizeDatabase(): void
    {
        global $DB;
        if (!$DB->tableExists('glpi_plugin_whatsappsimples_chats')) {
            return;
        }

        // 1. Remove contato de teste 551799999999 solicitado no Ponto 2
        $testChats = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => ['phone_number' => '551799999999']
        ]);
        foreach ($testChats as $tc) {
            $tId = (int) $tc['id'];
            $DB->delete('glpi_plugin_whatsappsimples_messages', ['chats_id' => $tId]);
            $DB->delete('glpi_plugin_whatsappsimples_chats', ['id' => $tId]);
        }

        // 2. Mapeamento e Fusão de LIDs para Números Reais (Leonardo: 64703850111065 -> 5517997772618)
        $lidMappings = [
            '64703850111065'  => ['target' => '5517997772618', 'name' => 'Leonardo Poiatti'],
            '181656010924208' => ['target' => '5517996454039', 'name' => 'Marco Antonio']
        ];

        foreach ($lidMappings as $lid => $info) {
            $targetPhone = $info['target'];
            $name        = $info['name'];

            // Localiza chat principal
            $mainChat = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'WHERE'  => ['phone_number' => $targetPhone],
                'ORDER'  => 'id ASC',
                'LIMIT'  => 1
            ])->current();

            $mainChatId = $mainChat ? (int) $mainChat['id'] : 0;

            // Busca chats com o LID
            $lidChats = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'WHERE'  => ['phone_number' => $lid]
            ]);

            foreach ($lidChats as $lc) {
                $lidChatId = (int) $lc['id'];
                if ($mainChatId > 0 && $mainChatId !== $lidChatId) {
                    // Migra mensagens do LID para o chat com numero de celular real
                    $DB->update('glpi_plugin_whatsappsimples_messages', ['chats_id' => $mainChatId], ['chats_id' => $lidChatId]);
                    $DB->delete('glpi_plugin_whatsappsimples_chats', ['id' => $lidChatId]);
                } else {
                    // Se não existia o chat principal, apenas converte o numero do chat LID para o numero real
                    $DB->update('glpi_plugin_whatsappsimples_chats', [
                        'phone_number' => $targetPhone,
                        'contact_name' => $name
                    ], ['id' => $lidChatId]);
                }
            }
        }
    }
}
