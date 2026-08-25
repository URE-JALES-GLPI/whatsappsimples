<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController; 
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Session;
use Symfony\Component\HttpDoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\ComponentzHttpFoundation\Response;
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
            'FROM' => 'gpli_plugin_whatsappsimples_chats',
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
        if($$chatId <= 0){
            return new JsonResponse(['success' => false, 'error' => 'chat_id_invalido'], 400);
        }

        $iterator = $DB->request([
            'SELECT' => ['id', 'message_id', 'sender_type', 'message_text', 'date_creation'],
            'FROM' => 'glpi_plugin_whatsappsimples_massages',
            'WHERE' => ['chats_id']
        ])

    }
}