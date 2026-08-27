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
        $contactName = $message->getPushName();

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
        $newChatId = $this->repository->createChat([
            'phone_number' => $phoneNumber, // Por enquanto, guarda o LID. (Será a chave principal do chat)
            'contact_name' => $contactName,
            'status'       => 'pending',
            'users_id'     => 0
        ]);

        return [
            'id'           => $newChatId,
            'phone_number' => $phoneNumber,
            'contact_name' => $contactName,
            'status'       => 'pending',
            'users_id'     => 0,
            'linked_lid'   => null
        ];
    }

    /**
     * Encerra um chat (muda status para closed).
     */
    public function closeChat(int $chatId, int $userId): bool
    {
        $now = date('Y-m-d H:i:s');
        return $this->repository->updateChat($chatId, [
            'status'      => 'closed',
            'users_id'    => $userId, // Marca quem encerrou
            'date_closed' => $now,
            'date_mod'    => $now
        ]);
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
}
