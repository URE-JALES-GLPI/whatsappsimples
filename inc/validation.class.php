<?php

class PluginWhatsappsimplesValidation
{
    static function notifyValidator(array $input): void
    {
        if (empty($input['items_id']) || empty($input['users_id_validate'])) {
            return;
        }

        $ticketId    = (int)$input['items_id'];
        $validatorId = (int)$input['users_id_validate'];

        // Busca dados do ticket
        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) return;

        // Busca celular do validador
        $user = new User();
        if (!$user->getFromDB($validatorId)) return;

        $mobile = trim($user->fields['mobile'] ?? '');
        if (empty($mobile)) {
            // Adiciona followup avisando que celular não está cadastrado
            $followup = new ITILFollowup();
            $followup->add([
                'itemtype'   => 'Ticket',
                'items_id'   => $ticketId,
                'content'    => '<b>[WhatsApp Simples]</b> Não foi possível notificar o validador <b>' . $user->getFriendlyName() . '</b> pois ele não possui celular cadastrado.',
                'is_private' => 1,
            ]);
            return;
        }

        // Limpa o número
        $mobile = preg_replace('/\D/', '', $mobile);

        // Monta a mensagem
        $title   = htmlspecialchars_decode(strip_tags($ticket->fields['name']));
        $content = htmlspecialchars_decode(strip_tags($ticket->fields['content'] ?? ''));
        $content = mb_substr($content, 0, 300);

        $message  = "? *Pedido de Validação — Ticket #${ticketId}*\n\n";
        $message .= "*Título:* {$title}\n\n";
        $message .= "*Descrição:* {$content}\n\n";
        $message .= "Responda com:\n*1* — Aprovar\n*2* — Recusar";

        // Envia via servidor Node
        $serverUrl = PluginWhatsappsimplesConfig::getConfig('server_url', 'http://localhost:3001');
        $apiToken  = PluginWhatsappsimplesConfig::getConfig('api_token',  'glpi_whatsapp_token_2025');

        $payload = json_encode([
            'number'     => $mobile,
            'text'       => $message,
            'validation' => [
                'ticket_id'    => $ticketId,
                'validator_id' => $validatorId,
                'mobile'       => $mobile,
            ]
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nx-api-token: {$apiToken}\r\n",
                'content' => $payload,
                'timeout' => 5,
            ]
        ]);

        @file_get_contents("{$serverUrl}/api/send", false, $ctx);
    }
}