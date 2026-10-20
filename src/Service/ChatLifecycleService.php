<?php

namespace GlpiPlugin\Whatsappsimples\Service;

use GlpiPlugin\Whatsappsimples\DTO\IncomingMessageDTO;
use GlpiPlugin\Whatsappsimples\Repository\ChatRepository;

class ChatLifecycleService
{
    private ChatRepository $repository;

    public function __construct(ChatRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Resolve qual será o chat usado (existente ou cria um novo).
     * Retorna os dados do chat.
     */
    public function openOrReopenChat(IncomingMessageDTO $message): array
    {
        $phoneNumber = $message->getRemoteJid();
        // Em eventos fromMe (enviados pelo aparelho da empresa), o pushName é da própria URE; usa o telefone
        $contactName = $message->isFromMe() ? $phoneNumber : ($message->getPushName() ?: $phoneNumber);

        // 1. Busca pelo número exato ou pelo LID já vinculado
        $chat = $this->repository->findActiveChatByPhoneOrLid($phoneNumber);

        // 2. Busca parcial retrocompatível para LIDs
        if (!$chat && str_contains($phoneNumber, '@lid')) {
            $chat = $this->repository->findActiveChatByPartialLid($phoneNumber);
            if ($chat) {
                // Atualiza o LID vinculado se não estava para facilitar na próxima vez
                if (empty($chat['linked_lid'])) {
                    $this->repository->updateChat((int)$chat['id'], ['linked_lid' => $phoneNumber]);
                }
            }
        }

        // 3. Fallback Inteligente: Tenta parear via nome (evita duplicar JIDs diferentes da mesma pessoa)
        if (!$chat && str_contains($phoneNumber, '@lid') && !empty($contactName) && $contactName !== $phoneNumber) {
            $possibleChats = $this->repository->findActiveChatsByContactName($contactName);
            
            // Só faz a amarração automática se houver EXATAMENTE UM chat pendente/em andamento com este nome
            if (count($possibleChats) === 1) {
                $chat = $possibleChats[0];
                // Vincula o LID a este chat
                $this->repository->updateChat((int)$chat['id'], [
                    'linked_lid' => $phoneNumber,
                    'date_mod'   => date('Y-m-d H:i:s')
                ]);
            }
        }

        // 4. Se encontrou, apenas atualiza a data e garante que a fila está notificada
        if ($chat) {
            $chatId = (int)$chat['id'];
            $this->repository->updateChat($chatId, ['date_mod' => date('Y-m-d H:i:s')]);
            return $chat;
        }

        // 5. Se não encontrou de forma alguma, cria um NOVO chat na fila
        $linkedLid = null;
        if (str_contains($phoneNumber, '@lid')) {
            $rawDigits = preg_replace('/[^0-9]/', '', $phoneNumber);
            $resolved = \GlpiPlugin\Whatsappsimples\Service\EvolutionApiService::fetchRealJidFromApi($rawDigits);
            if (!empty($resolved)) {
                $linkedLid = $phoneNumber;
                $phoneNumber = $resolved;
            }
        }

        $newChatId = $this->repository->createChat([
            'phone_number' => $phoneNumber,
            'contact_name' => $contactName,
            'status'       => 'pending',
            'users_id'     => 0,
            'linked_lid'   => $linkedLid
        ]);

        return [
            'id'           => $newChatId,
            'phone_number' => $phoneNumber,
            'contact_name' => $contactName,
            'status'       => 'pending',
            'users_id'     => 0,
            'linked_lid'   => $linkedLid
        ];
    }

    /**
     * Encerra um chat (muda status para closed).
     */
    public function closeChat(int $chatId, int $userId): bool
    {
        $now = date('Y-m-d H:i:s');
        $ok = $this->repository->updateChat($chatId, [
            'status'      => 'closed',
            'users_id'    => $userId, // Marca quem encerrou
            'date_closed' => $now,
            'date_mod'    => $now
        ]);

        if ($ok) {
            // Garante que nenhum outro atendimento ativo do mesmo numero fique aberto
            global $DB;
            $current = $DB->request([
                'SELECT' => ['phone_number'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'WHERE'  => ['id' => $chatId],
                'LIMIT'  => 1
            ])->current();
            if ($current && !empty($current['phone_number'])) {
                $DB->update('glpi_plugin_whatsappsimples_chats', [
                    'status'      => 'closed',
                    'users_id'    => $userId,
                    'date_closed' => $now,
                    'date_mod'    => $now
                ], [
                    'phone_number' => $current['phone_number'],
                    'status'       => ['pending', 'in_progress']
                ]);
            }

            $who = '';
            $user = new \User();
            if ($userId > 0 && $user->getFromDB($userId)) {
                $who = trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? ''));
            }
            $this->repository->saveMessage([
                'chats_id'     => $chatId,
                'users_id'     => $userId,
                'message_id'   => 'sys_close_' . $chatId . '_' . time(),
                'sender_type'  => 'system',
                'message_text' => 'Atendimento encerrado' . ($who !== '' ? ' por ' . $who : ''),
                'date_creation' => $now,
            ]);
        }

        return $ok;
    }

    /**
     * Atribui um chat a um técnico (muda status para in_progress).
     */
    public function assignToAgent(int $chatId, int $userId): bool
    {
        return $this->repository->updateChat($chatId, [
            'status'   => 'in_progress',
            'users_id' => $userId,
            'date_mod' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Transfere o chat para outro atendente ou devolve para a fila, registrando evento de sistema.
     */
    public function transferChat(int $chatId, int $newUserId, int $fromUserId): bool
    {
        global $DB;
        $now = date('Y-m-d H:i:s');

        $chat = $DB->request([
            'SELECT' => ['id', 'phone_number', 'status'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats',
            'WHERE'  => ['id' => $chatId],
            'LIMIT'  => 1
        ])->current();

        if (!$chat || $chat['status'] === 'closed') {
            return false;
        }

        $phoneToUpdate = $chat['phone_number'];
        $newStatus = ($newUserId > 0) ? 'in_progress' : 'pending';

        $ok = $DB->update('glpi_plugin_whatsappsimples_chats', [
            'users_id' => $newUserId,
            'status'   => $newStatus,
            'date_mod' => $now
        ], [
            'id'     => $chatId,
            'status' => ['pending', 'in_progress']
        ]);

        if ($ok !== false) {
            $fromName = 'Técnico';
            if ($fromUserId > 0) {
                $uFrom = new \User();
                if ($uFrom->getFromDB($fromUserId)) {
                    $fullName = trim(($uFrom->fields['firstname'] ?? '') . ' ' . ($uFrom->fields['realname'] ?? ''));
                    if (!empty($fullName)) {
                        $fromName = $fullName;
                    }
                }
            }

            if ($newUserId > 0) {
                $toName = 'outro atendente';
                $uTo = new \User();
                if ($uTo->getFromDB($newUserId)) {
                    $fullName = trim(($uTo->fields['firstname'] ?? '') . ' ' . ($uTo->fields['realname'] ?? ''));
                    if (!empty($fullName)) {
                        $toName = $fullName;
                    }
                }
                $systemText = "Atendimento transferido por {$fromName} para {$toName}";
            } else {
                $systemText = "Atendimento devolvido para a Fila por {$fromName}";
            }

            $this->repository->saveMessage([
                'chats_id'      => $chatId,
                'users_id'      => $fromUserId,
                'message_id'    => 'sys_transfer_' . $chatId . '_' . time(),
                'sender_type'   => 'system',
                'message_text'  => $systemText,
                'date_creation' => $now,
            ]);

            return true;
        }

        return false;
    }
}
