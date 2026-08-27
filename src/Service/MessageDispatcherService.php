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
        // 1. Idempotência: Se a mensagem já existe, ignora e retorna sucesso.
        if ($this->repository->messageExists($message->getMessageId())) {
            \Toolbox::logInFile('whatsappsimples', "MENSAGEM_DUPLICADA_IGNORADA ID: " . $message->getMessageId() . "\n");
            return true;
        }

        // Se a mensagem foi enviada por nós (fromMe = true), podemos ignorar o fluxo de criação de chat (ou registrar como 'agent')
        if ($message->isFromMe()) {
            return true;
        }

        // 2. Resolve ou Cria o Chat via Lifecycle Service
        $chat = $this->lifecycleService->openOrReopenChat($message);
        
        $chatId = (int)$chat['id'];

        if ($chatId === 0) {
            \Toolbox::logInFile('whatsappsimples', "ERRO_CRIAR_CHAT para JID: " . $message->getRemoteJid() . "\n");
            return false;
        }

        // 3. Salva a mensagem no histórico do Chat
        $messageData = [
            'chats_id'     => $chatId,
            'users_id'     => 0, // Mensagem de contato não tem usuário associado
            'message_id'   => $message->getMessageId(),
            'sender_type'  => 'contact',
            'message_text' => $message->getText(),
        ];

        $result = $this->repository->saveMessage($messageData);
        
        return $result > 0;
    }
}
