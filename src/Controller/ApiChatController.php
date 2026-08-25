<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController; 
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiChatController extends AbstractController
{
    //Endpoint 1: Retorna a lista de chats/conversas da fila
    #[Route('/ajax/whatsappsimples/chats', name: 'whatsappsimples_api_chats', methods: ['GET'])]
    public function getChats(Request $requuest): Response
    {
        Session::checkLoginUser();
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['id', 'phone_number', 'contact_name', 'users_id', 'status', 'date_mod'],
            'FROM' => 'glpi_plugin_whatsappsimples_chats',
            'ORDER' => ['date_mod DESC']  
        ]);
    
        //Transforma cada registro em um array associativo
        $chats = [];
        foreach($iterator as $row){
            $chats[] = [
                'id' => (int) $row['id'],
                'phone_number' => $row['phone_number'],
                'contact_name' => $row['contact_name'] ?: $row['phone_number'],
                'users_id' => (int) $row['users_id'],
                'status' => $row['status'],
                'date_mod' => date('H:i', strtotime($row['date_mod']))
            ];
        }
        return new JsonResponse(['success' => true, 'chats' => $chats]);
    }

    //Endpoint 2: Retorna as mensagens de uma conversa específica
    #[Route('/ajax/whatsappsimples/messages', name:'whatsappsimples_api_messages', methods: ['GET'])]
    public function getMessages(Request $request): Response
    {
        Session::checkLoginUser();
        global $DB;

        $chatId = $request->query->getInt('chat_id');
        if($chatId <= 0){
            return new JsonResponse(['success' => false, 'error' => 'chat_id_invalido'], 400);
        }

        $iterator = $DB->request([
            'SELECT' => ['id', 'message_id', 'sender_type', 'message_text', 'date_creation'],
            'FROM' => 'glpi_plugin_whatsappsimples_messages',
            'WHERE' => ['chats_id' => $chatId],
            'ORDER' => ['id ASC']
        ]);


        $messages = [];
        foreach($iterator as $row){
            $messages[] = [
                'id' => (int) $row['id'],
                'sender_type' => $row['sender_type'],
                'message_text' => $row['message_text'],
                'date_creation' => date('H:i', strtotime($row['date_creation']))
            ];
        }

        return new JsonResponse(['success' => true, 'messages' => $messages]);
    }

    //Endpoint 3: Envia uma mensagem digitada pelo técnico e assume a conversa
    #[Route('/ajax/whatsappsimples/send', name: 'whatsappsimples_api_send', methods: ['POST'])]
    public function sendMessage(Request $request): Response
    {
        Session::checkLoginUser();
        global $DB;

        $chatId = $request->request->getInt('chat_id');
        $text = trim($request->request->getString('text'));
        
        if($chatId <= 0 || empty($text))
            return new JsonResponse(['success' => false, 'error' => 'Dados incompletos'], 400);

        //1 Busca o chat no banco
        $chat = $DB->request([
            'SELECT' => ['id', 'phone_number', 'users_id', 'date_creation', 'first_response_date'],
            'FROM' => 'glpi_plugin_whatsappsimples_chats',
            'WHERE' => ['id' => $chatId],
            'LIMIT' => 1
        ])->current();

        if(!$chat)
            return new JsonResponse(['success' => false, 'error' => 'Chat não encontrado'], 404);

        //2 Tenta enviar a mensagem para o cliente via EvolutionAPI
        $result = EvolutionApiService::sendMessage($chatId, $chat['phone_number'], $text);

        //3 O Chat SÓ É ATRIBUÍDO E MUDADO DE STATUS SE O ENVIO TEVE SUCESSO!
        if(!empty($result['success'])){
            $now = date('Y-m-d H:i:s');
            $currentUserId = (int) Session::getLoginUserID();
            
            $updateData = [
                'users_id' => $currentUserId,
                'status' => 'in_progress',
                'date_mod' => $now
            ];

            if(empty($chat['first_response_date'])){
                $updateData['first_response_date'] = $now;
            }

            $DB->update('glpi_plugin_whatsappsimples_chats', $updateData, ['id' => $chatId]);
        }
        return new JsonResponse($result);
    }
}