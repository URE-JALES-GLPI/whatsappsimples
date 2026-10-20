<?php

namespace GlpiPlugin\Whatsappsimples\Service;

use GlpiPlugin\Whatsappsimples\DTO\IncomingMessageDTO;
use GlpiPlugin\Whatsappsimples\Repository\ChatRepository;

class MessageDispatcherService
{
    private ChatRepository $repository;
    private ChatLifecycleService $lifecycleService;

    public function __construct(ChatRepository $repository, ChatLifecycleService $lifecycleService)
    {
        $this->repository = $repository;
        $this->lifecycleService = $lifecycleService;
    }

    /**
     * Recebe um DTO de mensagem do webhook e orquestra o ciclo de vida.
     * Retorna booleano indicando sucesso.
     */
    public function dispatchIncomingMessage(IncomingMessageDTO $message): bool
    {
        if ($message->isFromMe()) {
            usleep(1000000); // 1s para garantir que envios feitos pelo próprio GLPI já tenham concluído o INSERT local
        }

        // 1. Idempotência: Se a mensagem já existe, ignora e retorna sucesso.
        if ($this->repository->messageExists($message->getMessageId())) {
            \Toolbox::logInFile('whatsappsimples', "MENSAGEM_DUPLICADA_IGNORADA ID: " . $message->getMessageId() . "\n");
            return true;
        }

        $isFromMe = $message->isFromMe();

        // 2. Resolve ou Cria o Chat via Lifecycle Service
        $chat = $this->lifecycleService->openOrReopenChat($message);
        
        $chatId = (int)$chat['id'];

        if ($chatId === 0) {
            \Toolbox::logInFile('whatsappsimples', "ERRO_CRIAR_CHAT para JID: " . $message->getRemoteJid() . "\n");
            return false;
        }

        // 3. Salva a mensagem no histórico do Chat
        // Mensagens enviadas diretamente do aparelho celular da empresa (fromMe = true) são registradas como 'attendant'
        $senderType = $isFromMe ? 'attendant' : 'contact';
        $userId     = $isFromMe ? (int)($chat['users_id'] ?? 0) : 0;

        $messageData = [
            'chats_id'     => $chatId,
            'users_id'     => $userId,
            'message_id'   => $message->getMessageId(),
            'sender_type'  => $senderType,
            'message_text' => $message->getText(),
            'media_url'    => $message->getMediaUrl(),
        ];
        
        if ($message->getMediaData() !== null) {
            $m = $message->getMediaData();
            $messageData['media_path'] = $m['media_path'];
            $messageData['media_mime'] = $m['media_mime'];
            $messageData['media_size'] = $m['media_size'];
            $messageData['media_sha256'] = $m['media_sha256'];
            $messageData['media_status'] = $m['media_status'];
        }

        $result = $this->repository->saveMessage($messageData);
        
        // 4. Incrementa o contador de mensagens não lidas APENAS para mensagens recebidas do contato externo
        if ($result > 0 && !$isFromMe) {
            $this->repository->incrementUnreadCount($chatId);
        }
        
        return $result > 0;
    }
}
