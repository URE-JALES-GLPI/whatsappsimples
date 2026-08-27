<?php

namespace GlpiPlugin\Whatsappsimples\Repository;

class ChatRepository
{
    /**
     * Busca um chat ativo pelo número de telefone exato ou pelo LID vinculado.
     */
    public function findActiveChatByPhoneOrLid(string $phoneNumber): ?array
    {
        global $DB;
        
        $chat = $DB->request([
            'SELECT' => ['id', 'status', 'users_id', 'contact_name', 'phone_number', 'linked_lid'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => [
                'OR' => [
                    'phone_number' => $phoneNumber,
                    'linked_lid'   => $phoneNumber
                ],
                'status'       => ['pending', 'in_progress']
            ],
            'ORDER'  => 'id DESC',
            'LIMIT'  => 1
        ])->current();

        return $chat ?: null;
    }

    /**
     * Busca um chat ativo por busca parcial do LID.
     */
    public function findActiveChatByPartialLid(string $phoneNumber): ?array
    {
        global $DB;
        
        $rawLidDigits = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (empty($rawLidDigits)) {
            return null;
        }

        $chat = $DB->request([
            'SELECT' => ['id', 'status', 'users_id', 'contact_name', 'phone_number', 'linked_lid'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => [
                'phone_number' => ['LIKE', '%' . $rawLidDigits . '%'],
                'status'       => ['pending', 'in_progress']
            ],
            'ORDER'  => 'id DESC',
            'LIMIT'  => 1
        ])->current();

        return $chat ?: null;
    }

    /**
     * Busca chats ativos exatamente pelo nome do contato (útil para fallbacks).
     */
    public function findActiveChatsByContactName(string $contactName): array
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['id', 'status', 'users_id', 'contact_name', 'phone_number', 'linked_lid'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => [
                'contact_name' => $contactName,
                'status'       => ['pending', 'in_progress']
            ],
            'ORDER'  => 'id DESC'
        ]);

        $chats = [];
        foreach ($iterator as $row) {
            $chats[] = $row;
        }

        return $chats;
    }

    public function createChat(array $data): int
    {
        global $DB;
        
        // Assegura valores defaults do banco
        if (!isset($data['date_creation'])) {
            $data['date_creation'] = date('Y-m-d H:i:s');
        }
        
        if ($DB->insert('glpi_plugin_whatsappsimples_chats', $data)) {
            return (int) $DB->insertId();
        }
        
        return 0;
    }

    public function updateChat(int $chatId, array $data): bool
    {
        global $DB;
        return $DB->update('glpi_plugin_whatsappsimples_chats', $data, ['id' => $chatId]);
    }

    public function messageExists(string $messageId): bool
    {
        global $DB;
        
        if (empty($messageId)) {
            return false;
        }

        $count = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_whatsappsimples_messages',
            'WHERE'  => ['message_id' => $messageId]
        ])->count();

        return $count > 0;
    }

    public function saveMessage(array $messageData): int
    {
        global $DB;
        
        if (!isset($messageData['date_creation'])) {
            $messageData['date_creation'] = date('Y-m-d H:i:s');
        }

        if ($DB->insert('glpi_plugin_whatsappsimples_messages', $messageData)) {
            return (int) $DB->insertId();
        }
        
        return 0;
    }

    public function incrementUnreadCount(int $chatId): bool
    {
        global $DB;
        return $DB->doQuery("UPDATE `glpi_plugin_whatsappsimples_chats` SET `unread_count` = `unread_count` + 1 WHERE `id` = $chatId");
    }

    public function resetUnreadCount(int $chatId): bool
    {
        global $DB;
        return $DB->update('glpi_plugin_whatsappsimples_chats', ['unread_count' => 0], ['id' => $chatId]);
    }
}
