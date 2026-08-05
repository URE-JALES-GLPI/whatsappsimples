<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
Session::checkRight('plugin_whatsappsimples', READ);
$serverUrl = PluginWhatsappsimplesConfig::getConfig('server_url', 'http://localhost:3001');
$apiToken  = PluginWhatsappsimplesConfig::getConfig('api_token',  'glpi_whatsapp_token_2025');
Html::header(
    'WhatsApp Simples',
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginWhatsappsimplesMenu'
);
?>
<div id="wapp-root">
    <div id="wapp-sidebar">
        <div id="wapp-sidebar-header">
            <span><i class="ti ti-brand-whatsapp"></i> WhatsApp Simples</span>
            <div style="display:flex;gap:4px;align-items:center">
                <button id="wapp-pending-btn" title="Conversas pendentes" style="position:relative">
                    <i class="ti ti-inbox"></i>
                    <span id="wapp-pending-badge"></span>
                </button>
                <button id="wapp-contacts-btn" title="Agenda de contatos"><i class="ti ti-address-book"></i></button>
            </div>
        </div>
        <div id="wapp-open-chats"></div>
        <div id="wapp-new-chat">
            <input type="text" id="wapp-number-input" placeholder="5511999999999">
            <button id="wapp-start-btn"><i class="ti ti-arrow-right"></i></button>
        </div>
    </div>
    <div id="wapp-main">
        <div id="wapp-empty-state">
            <i class="ti ti-message-circle"></i>
            <p>Selecione ou inicie uma conversa</p>
        </div>
        <div id="wapp-chat-area" style="display:none">
            <div id="wapp-chat-header">
                <span id="wapp-chat-title"></span>
                <div style="display:flex;gap:8px;align-items:center">
                    <button id="wapp-new-ticket-btn"><i class="ti ti-ticket"></i> Abrir chamado</button>
                    <button id="wapp-save-btn"><i class="ti ti-device-floppy"></i> Salvar no ticket</button>
                    <button id="wapp-close-chat"><i class="ti ti-x"></i></button>
                </div>
            </div>
            <div id="wapp-messages"></div>
            <div id="wapp-input-bar">
                <input type="text" id="wapp-msg-input" placeholder="Digite uma mensagem...">
                <button id="wapp-send-btn"><i class="ti ti-send"></i></button>
            </div>
        </div>
    </div>
</div>
<script>
window.WAPP_SERVER = <?= json_encode($serverUrl) ?>;
window.WAPP_TOKEN  = <?= json_encode($apiToken) ?>;
</script>
<script src="<?= Plugin::getWebDir('whatsappsimples') ?>/public/js/whatsappsimples.js"></script>
<?php Html::footer(); ?>