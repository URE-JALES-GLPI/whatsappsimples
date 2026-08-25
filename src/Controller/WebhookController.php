<?php   

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractsController
{
    #[Route('/ajax/whatsappsimples/webhook', name: 'whatsappsimples_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        //1 Le o body request JSON enviado pela EvolutionAPI
        $content = $request->getContent();
        $payload = json_decode($content, true);
    
        if(!$payload || !is_array($payload)){
            return new JsonResponse(['success' => false, 'error' => 'Payload JSON invalido'], 400);
        }

        //2 Filtra apenas eventos de mensagem recebida
        $event = $payload['event'] ?? '';
        if($event !== 'messages.upsert'){
            return new JsonResponse(['success' => true, 'message' => 'Evento ignorado']);
        }

        $data = $payload['data'] ?? [];
        $key = $data['key'] ?? [];

        //Se a mensagem foi enviada por nós mesmos (fromMe = true) ignoramos no webhook
        if(!empty($key['fromMe'])) {
            return new JsonResponse(['success' => true, 'message' => 'Mensagem propria ignorada']);
        }

        //3 Extrai os dados do remetente
        $remoteJid = $key['remoteJid'] ?? ''; //ex: 5517996478201@s.whatsapp.net
        $phoneNumber = preg_replace('/[^0-9]/', "", str_replace('@s.whatsapp.net', '', $remoteJid));
        $contactName = $data['pushName'] ?? 'Contato não salvo';
        $messageId = $key['id'] ?? '';

        //4 Extrai o texto da mensagem
        $messageData = $data['message'] ?? [];
        $text = $messageData['conversation'] ?? $messageData['extendedTextMessage']['text'] ?? '';

        if(empty($phoneNumber) || empty($text)){
            return new JsonResponse(['success' => true, 'message' => 'Dados insuficientes para gravar']);
        }
         
        $now = date('Y-m-d H:i:s');

        //5 Verifica se já existe um Chat ativo ou anterior para este número de telefone
        $chatIterator = $DB->request([
            'SELECT' => ['id'],
            'FROM' => 'glpi_plugin_whatsappsimples_chats',
            'WHERE' => ['phone_number' => $phoneNumber],
            'LIMIT' => 1
        ]);

        $chatId = 0;
        if($row = $chatIterator->current()){
            $chatId = (int) $row['id'];
            //Atualiza a data de modificação e o nome do contato se foi enviado preenchido
            $DB->update('glpi_plugin_whatsappsimples_chats', [
                'contact_name' => $contactName,
                'date_mod' => $now
            ], ['id' => $chatId]);
        }else {
            //Cria um novo Chat na Fila (status = pending, users_id = 0)
            $DB->insert('glpi_plugin_whatsappsimples_chats', [
                'phone_number' => $phoneNumber,
                'contact_name' => $contactName,
                'users_id' => 0,
                'status' => 'pending',
                'date_creation' => $now,
                'date_mod' => $now
            ]);
            $chatId = (int) $DB->insertId();
        };

        //6 Grava a mensagem recbida vinculada ao chatId
        if($chatId > 0){
            $DB->insert('glpi_plugin_whatsappsimples_messages', [
                'chats_id' => $chatId,
                'message_id' => $messageId,
                'sender_type' => 'user',
                'message_text' => $text,
                'date_creation' => $now
            ]);
        }

        return new JsonResponse(['success' => true, 'message' => 'Mensagem recebida e gravada com sucesso']);
    }
}