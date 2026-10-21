<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;

final class ContactProfileController
{
    #[Route('/ajax/contact-profile', name: 'whatsappsimples_api_contact_profile', methods: ['GET', 'POST'], options: ['prevent_csrf' => true])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        if (!Session::haveRight('plugin_whatsappsimples', READ)) {
            return new JsonResponse(['success' => false, 'error' => 'Acesso negado'], 403);
        }
        global $DB;

        EvolutionApiService::ensureMessageColumns();

        $phoneNumber = trim((string) ($request->get('phone_number') ?? $request->query->get('phone_number', '')));
        $chatId      = (int) ($request->get('chat_id') ?? $request->query->get('chat_id', 0));
        $forceRefresh = (bool) ($request->get('refresh') ?? $request->query->get('refresh', false));

        if ($chatId > 0 && empty($phoneNumber)) {
            $chat = $DB->request([
                'SELECT' => ['phone_number'],
                'FROM'   => 'glpi_plugin_whatsappsimples_chats',
                'WHERE'  => ['id' => $chatId],
                'LIMIT'  => 1
            ])->current();
            if ($chat) {
                $phoneNumber = $chat['phone_number'];
            }
        }

        if (empty($phoneNumber)) {
            return new JsonResponse(['success' => false, 'error' => 'Número de telefone não informado'], 400);
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);

        // 1. Obter perfil da Evolution API
        $profile = EvolutionApiService::fetchProfile($phoneNumber);

        // 2. Buscar chats associados a esse número
        $chatIds = [];
        $contactName = $profile['name'] ?? null;
        $chatStatus = 'none';
        $assignedTech = 'Sem Atendente';
        $chatCreated = null;

        $chatsIterator = $DB->request([
            'SELECT' => ['c.id', 'c.contact_name', 'c.status', 'c.users_id', 'c.date_creation', 'u.firstname', 'u.realname'],
            'FROM'   => 'glpi_plugin_whatsappsimples_chats AS c',
            'LEFT JOIN' => [
                'glpi_users AS u' => [
                    'ON' => ['c' => 'users_id', 'u' => 'id']
                ]
            ],
            'WHERE'  => ['c.phone_number' => $phoneNumber],
            'ORDER'  => 'c.id DESC'
        ]);

        foreach ($chatsIterator as $row) {
            $chatIds[] = (int) $row['id'];
            if (empty($contactName) && !empty($row['contact_name'])) {
                $contactName = $row['contact_name'];
            }
            if ($chatStatus === 'none' || ($row['status'] !== 'closed' && $chatStatus === 'closed')) {
                $chatStatus = $row['status'];
                $chatCreated = $row['date_creation'];
                if (!empty($row['users_id'])) {
                    $assignedTech = trim(($row['firstname'] ?? '') . ' ' . ($row['realname'] ?? ''));
                }
            }
        }

        if (empty($chatIds) && $chatId > 0) {
            $chatIds = [$chatId];
        }

        // 3. Buscar mídias trocadas
        $mediaPreviews = [];
        $totalMedia = 0;

        if (!empty($chatIds) && $DB->tableExists('glpi_plugin_whatsappsimples_messages')) {
            $mediaIterator = $DB->request([
                'SELECT' => ['id', 'sender_type', 'message_text', 'media_url', 'media_status', 'date_creation'],
                'FROM'   => 'glpi_plugin_whatsappsimples_messages',
                'WHERE'  => [
                    'chats_id'  => $chatIds,
                    'NOT'       => ['media_url' => null],
                    'media_url' => ['!=', '']
                ],
                'ORDER'  => 'id DESC',
                'LIMIT'  => 30
            ]);

            $rootDoc = function_exists('wa_web_dir') ? wa_web_dir('whatsappsimples') : '/plugins/whatsappsimples';

            foreach ($mediaIterator as $m) {
                $rawUrl = $m['media_url'] ?? '';
                if (empty($rawUrl)) continue;

                $totalMedia++;
                $type = 'doc';
                $finalUrl = $rawUrl;

                if (str_starts_with($rawUrl, 'data:')) {
                    if (str_starts_with($rawUrl, 'data:image/')) {
                        $type = 'image';
                    } elseif (str_starts_with($rawUrl, 'data:video/')) {
                        $type = 'video';
                    } elseif (str_starts_with($rawUrl, 'data:audio/')) {
                        $type = 'audio';
                    }
                } elseif (str_starts_with($rawUrl, 'doc_')) {
                    $filename = substr($rawUrl, 4);
                    $finalUrl = "{$rootDoc}/front/media.php?id={$m['id']}&file={$filename}";
                    $lowerFile = strtolower($filename);
                    if (str_ends_with($lowerFile, '.jpg') || str_ends_with($lowerFile, '.jpeg') || str_ends_with($lowerFile, '.png') || str_ends_with($lowerFile, '.webp')) {
                        $type = 'image';
                    } elseif (str_ends_with($lowerFile, '.mp4') || str_ends_with($lowerFile, '.webm')) {
                        $type = 'video';
                    } elseif (str_ends_with($lowerFile, '.ogg') || str_ends_with($lowerFile, '.mp3') || str_ends_with($lowerFile, '.m4a')) {
                        $type = 'audio';
                    }
                }

                if (count($mediaPreviews) < 18) {
                    $mediaPreviews[] = [
                        'id'            => (int) $m['id'],
                        'type'          => $type,
                        'url'           => $finalUrl,
                        'caption'       => $m['message_text'] ?? '',
                        'sender_type'   => $m['sender_type'],
                        'date_creation' => $m['date_creation']
                    ];
                }
            }
        }

        $avatarUrl = $profile['picture_url'] ?? null;
        if (empty($avatarUrl) && !empty($cleanPhone)) {
            $baseDir = defined('GLPI_PLUGIN_DOC_DIR') 
                ? GLPI_PLUGIN_DOC_DIR . '/whatsappsimples/avatars' 
                : (defined('GLPI_ROOT') ? GLPI_ROOT . '/files/_plugins/whatsappsimples/avatars' : '/var/www/html/glpi/files/_plugins/whatsappsimples/avatars');
            if (file_exists($baseDir . '/' . $cleanPhone . '.jpg')) {
                $avatarUrl = (function_exists('wa_web_dir') ? wa_web_dir('whatsappsimples') : '/plugins/whatsappsimples') . '/front/avatar.php?phone=' . $cleanPhone;
            }
        }

        return new JsonResponse([
            'success'        => true,
            'phone_number'   => $phoneNumber,
            'name'           => $contactName ?: $phoneNumber,
            'picture_url'    => $avatarUrl,
            'status'         => $profile['status'] ?? 'Disponível',
            'set_at'         => $profile['set_at'] ?? null,
            'total_media'    => $totalMedia,
            'media_previews' => $mediaPreviews,
            'chat_info'      => [
                'status'          => $chatStatus,
                'technician_name' => $assignedTech,
                'date_creation'   => $chatCreated
            ]
        ]);
    }
}
