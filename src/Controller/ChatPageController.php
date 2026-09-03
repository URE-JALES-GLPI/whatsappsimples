<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use Session;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChatPageController extends AbstractController
{
    #[Route('/Chat', name: 'whatsappsimples_chat', methods: ['GET'])]
    public function __invoke(): Response
    {
        Session::checkLoginUser();
        Session::checkRight('plugin_whatsappsimples', READ);
        global $CFG_GLPI;

        ob_start();
        include_once GLPI_ROOT . '/inc/includes.php';
        \Html::header('WhatsApp', $_SERVER['PHP_SELF'], 'tools', 'whatsappsimples');

        $canTransfer = Session::haveRight('plugin_whatsappsimples_transfer', READ);
        ?>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

            .omni-app {
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
                display: flex;
                flex-direction: column;
                height: calc(100vh - 100px);
                background: #f1f5f9;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
                border: 1px solid #cbd5e1;
                margin-top: -5px;
                position: relative;
            }

            /* TOP NAVBAR OMNICHANNEL */
            .omni-navbar {
                height: 48px;
                background: #1e293b;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 16px;
                color: #ffffff;
                user-select: none;
            }

            .omni-brand {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 1.1rem;
                font-weight: 700;
                color: #ffffff;
                letter-spacing: -0.3px;
            }
            .omni-brand span { color: #38bdf8; }

            .omni-nav-tools {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .omni-nav-icon {
                width: 34px;
                height: 34px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                color: #cbd5e1;
                cursor: pointer;
                font-size: 1.1rem;
                transition: all 0.15s;
            }
            .omni-nav-icon:hover { background: rgba(255,255,255,0.1); color: #ffffff; }
            .omni-nav-icon.active {
                background: #0f172a;
                color: #ffffff;
                border: 2px solid #ef4444;
            }

            .omni-user-badge {
                width: 28px;
                height: 28px;
                border-radius: 50%;
                background: #ec4899;
                color: #ffffff;
                font-weight: 700;
                font-size: 0.75rem;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-left: 8px;
            }

            /* BODY LAYOUT */
            .omni-body {
                flex: 1;
                display: flex;
                overflow: hidden;
            }

            /* SIDEBAR ESQUERDA */
            .omni-sidebar {
                width: 360px;
                background: #ffffff;
                border-right: 1px solid #e2e8f0;
                display: flex;
                flex-direction: column;
            }

            .omni-sidebar-header {
                padding: 12px 16px 8px 16px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 1.05rem;
                font-weight: 700;
                color: #1e293b;
            }

            .omni-search-box {
                padding: 0 16px 8px 16px;
                display: flex;
                gap: 8px;
            }

            .omni-search-input-wrap {
                flex: 1;
                position: relative;
                display: flex;
                align-items: center;
            }

            .omni-search-input-wrap span {
                position: absolute;
                left: 10px;
                color: #94a3b8;
                font-size: 0.9rem;
            }

            .omni-search-input {
                width: 100%;
                padding: 7px 10px 7px 32px;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                font-size: 0.85rem;
                outline: none;
                background: #f8fafc;
            }
            .omni-search-input:focus { border-color: #0284c7; background: #ffffff; }

            .omni-filter-btn {
                width: 32px;
                height: 32px;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                background: #ffffff;
                color: #64748b;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
            }

            /* ABAS (CHATS, FILA, CONTATOS) */
            .omni-tabs {
                display: flex;
                border-bottom: 1px solid #e2e8f0;
                padding: 0 8px;
            }

            .omni-tab-btn {
                flex: 1;
                padding: 10px 4px;
                border: none;
                background: transparent;
                font-size: 0.85rem;
                font-weight: 600;
                color: #64748b;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                border-bottom: 2px solid transparent;
                transition: all 0.15s;
            }
            .omni-tab-btn.active {
                color: #0284c7;
                border-bottom-color: #0284c7;
            }

            .omni-tab-badge {
                padding: 1px 6px;
                border-radius: 10px;
                font-size: 0.7rem;
                font-weight: 700;
                background: #e2e8f0;
                color: #475569;
            }
            .omni-tab-btn.active .omni-tab-badge {
                background: #0284c7;
                color: #ffffff;
            }

            .omni-subsort {
                padding: 6px 16px;
                font-size: 0.75rem;
                color: #64748b;
                background: #f8fafc;
                border-bottom: 1px solid #f1f5f9;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .omni-subsort span { color: #0284c7; font-weight: 600; cursor: pointer; }

            /* LISTA DE CHATS */
            .omni-chat-list {
                flex: 1;
                overflow-y: auto;
            }

            .omni-chat-card {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                border-bottom: 1px solid #f1f5f9;
                cursor: pointer;
                transition: background 0.15s;
            }
            .omni-chat-card:hover { background: #f8fafc; }
            .omni-chat-card.selected {
                background: #e0f2fe;
                border-left: 4px solid #0284c7;
            }

            .omni-avatar-wrap {
                position: relative;
            }

            .omni-avatar {
                width: 42px;
                height: 42px;
                border-radius: 50%;
                background: #0284c7;
                color: #ffffff;
                font-weight: 700;
                font-size: 1rem;
                display: flex;
                align-items: center;
                justify-content: center;
                text-transform: uppercase;
            }

            .omni-avatar-icon {
                position: absolute;
                bottom: -2px;
                right: -2px;
                width: 18px;
                height: 18px;
                border-radius: 50%;
                background: #22c55e;
                color: #ffffff;
                font-size: 0.65rem;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 2px solid #ffffff;
            }

            .omni-card-info {
                flex: 1;
                min-width: 0;
            }

            .omni-card-row1 {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 2px;
            }

            .omni-card-name {
                font-weight: 600;
                font-size: 0.9rem;
                color: #1e293b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .omni-card-time {
                font-size: 0.72rem;
                color: #94a3b8;
            }

            .omni-card-row2 {
                display: flex;
                align-items: center;
                gap: 4px;
                font-size: 0.8rem;
                color: #64748b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* MAIN CHAT WINDOW */
            .omni-main {
                flex: 1;
                display: flex;
                flex-direction: column;
                background: #efeae2;
                position: relative;
            }

            /* CHAT HEADER BAR */
            .omni-chat-header {
                height: 54px;
                background: #ffffff;
                border-bottom: 1px solid #cbd5e1;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 16px;
                z-index: 10;
            }

            .omni-header-contact {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .omni-header-tags {
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .omni-tag-badge {
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 0.72rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .omni-tag-whatsapp { background: #22c55e; color: #ffffff; }
            .omni-tag-dept { background: #64748b; color: #ffffff; }

            .omni-finish-btn {
                padding: 6px 14px;
                background: #ef4444;
                color: #ffffff;
                border: none;
                border-radius: 6px;
                font-size: 0.82rem;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 6px;
                transition: background 0.15s;
            }
            .omni-finish-btn:hover { background: #dc2626; }

            .omni-transfer-btn {
                padding: 6px 14px;
                background: #3b82f6;
                color: #ffffff;
                border: none;
                border-radius: 6px;
                font-size: 0.82rem;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 6px;
                transition: background 0.15s;
            }
            .omni-transfer-btn:hover { background: #2563eb; }

            /* MESSAGES AREA */
            .omni-messages-area {
                flex: 1;
                padding: 20px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 12px;
                background-color: #efeae2;
                background-image: radial-gradient(#cbd5e1 0.75px, transparent 0.75px);
                background-size: 16px 16px;
            }

            .omni-divider-badge {
                align-self: center;
                background: #e2e8f0;
                color: #475569;
                padding: 4px 14px;
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 500;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                margin: 8px 0;
            }

            .omni-bubble {
                max-width: 65%;
                padding: 8px 12px 6px 12px;
                border-radius: 8px;
                font-size: 0.88rem;
                line-height: 1.45;
                position: relative;
                word-wrap: break-word;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            .omni-bubble.user, .omni-bubble.contact {
                align-self: flex-start;
                background: #f1f5f9;
                color: #0f172a;
                border: 1px solid #e2e8f0;
                border-top-left-radius: 0;
            }
            .omni-bubble.attendant {
                align-self: flex-end;
                background: #dcf8c6;
                color: #0f172a;
                border: 1px solid #bce2a4;
                border-top-right-radius: 0;
            }
            .omni-bubble.omni-msg-internal {
                background: #fef08a !important;
                color: #854d0e !important;
                border: 1px solid #fde047 !important;
            }

            .omni-bubble-sender {
                font-size: 0.75rem;
                font-weight: 700;
                margin-bottom: 2px;
            }
            .omni-bubble.user .omni-bubble-sender, .omni-bubble.contact .omni-bubble-sender { color: #0284c7; }
            .omni-bubble.attendant .omni-bubble-sender { color: #047857; }

            .omni-bubble-time {
                font-size: 0.65rem;
                color: #94a3b8;
                float: right;
                margin-top: 4px;
                margin-left: 8px;
            }

            /* BOTTOM INPUT FOOTER */
            .omni-input-footer {
                background: #ffffff;
                border-top: 1px solid #cbd5e1;
                padding: 10px 16px;
                display: flex;
                align-items: center;
                gap: 10px;
                position: relative;
            }

            .omni-footer-tools {
                display: flex;
                align-items: center;
                gap: 10px;
                color: #64748b;
                font-size: 1.2rem;
            }
            .omni-footer-tool-btn {
                cursor: pointer;
                transition: transform 0.1s, color 0.15s;
                user-select: none;
            }
            .omni-footer-tool-btn:hover { color: #0284c7; transform: scale(1.1); }

            .omni-message-input {
                flex: 1;
                padding: 9px 16px;
                border: 1px solid #cbd5e1;
                border-radius: 20px;
                font-size: 0.88rem;
                outline: none;
                resize: none;
                font-family: inherit;
                line-height: 1.4;
                height: 38px;
                max-height: 114px;
                overflow-y: auto;
                box-sizing: border-box;
            }
            .omni-message-input:focus { border-color: #0284c7; }

            .omni-send-btn {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                background: #0284c7;
                color: #ffffff;
                border: none;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                font-size: 1.05rem;
                transition: background 0.15s;
            }
            .omni-send-btn:hover { background: #0369a1; }
            .omni-send-btn:disabled { background: #cbd5e1; cursor: not-allowed; }

            /* POP-OVERS DE EMOJI E RESPOSTAS RÁPIDAS */
            .omni-popover {
                position: absolute;
                bottom: 60px;
                left: 16px;
                background: #ffffff;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15);
                padding: 12px;
                z-index: 100;
                display: none;
            }

            .omni-emoji-grid {
                display: grid;
                grid-template-columns: repeat(6, 1fr);
                gap: 8px;
                width: 240px;
            }
            .omni-emoji-item {
                font-size: 1.3rem;
                text-align: center;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
                transition: background 0.15s;
            }
            .omni-emoji-item:hover { background: #f1f5f9; }

            .omni-canned-list {
                display: flex;
                flex-direction: column;
                gap: 6px;
                width: 320px;
                max-height: 250px;
                overflow-y: auto;
            }
            .omni-canned-item {
                padding: 8px 12px;
                font-size: 0.83rem;
                color: #334155;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.15s;
            }
            .omni-canned-item:hover {
                background: #e0f2fe;
                border-color: #0284c7;
                color: #0369a1;
            }
        </style>

        <div class="omni-app">
            <!-- TOP NAVBAR -->
            <div class="omni-navbar">
                <div class="omni-brand">
                    <span>URE Omnichannel</span>
                </div>
                <div class="omni-nav-tools">
                    <div class="omni-nav-icon active" title="Conversas / Chat">💬</div>
                    <div class="omni-nav-icon" title="Contatos">👥</div>
                    <div class="omni-nav-icon" title="Tags / Etiquetas">🏷️</div>
                    <div class="omni-nav-icon" title="Configurações" style="cursor:pointer;" onclick="openSettingsModal()">⚙️</div>
                    <div class="omni-user-badge">TI</div>
                </div>
            </div>

            <!-- BODY LAYOUT -->
            <div class="omni-body">
                <!-- SIDEBAR ESQUERDA -->
                <div class="omni-sidebar">
                    <div class="omni-sidebar-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Conversas</span>
                        <div style="display: flex; gap: 8px;">
                            <span style="font-size:0.9rem; color:#94a3b8; cursor:pointer;" onclick="loadChats()">🔄</span>
                        </div>
                    </div>

                    <div class="omni-search-box">
                        <div class="omni-search-input-wrap">
                            <span>🔍</span>
                            <input type="text" class="omni-search-input" id="search-input" placeholder="Pesquisar por nome ou número..." oninput="filterChatList()">
                        </div>
                        <div class="omni-filter-btn" title="Filtros">🔻</div>
                    </div>

                    <!-- 3 ABAS -->
                    <div class="omni-tabs">
                        <button class="omni-tab-btn active" onclick="switchTab('mine', this)">
                            💬 Chats <span class="omni-tab-badge" id="badge-mine">0</span>
                        </button>
                        <button class="omni-tab-btn" onclick="switchTab('queue', this)">
                            📥 Fila <span class="omni-tab-badge" id="badge-queue">0</span>
                        </button>
                        <button class="omni-tab-btn" onclick="switchTab('all', this)">
                            👥 Contatos
                        </button>
                    </div>

                    <div class="omni-subsort">
                        <span>Ordenar chamados por:</span>
                        <span>Opções de ordenação ↕</span>
                    </div>

                    <!-- LISTA DE CHATS -->
                    <div class="omni-chat-list" id="chat-list">
                        <div style="padding:20px; text-align:center; color:#94a3b8; font-size:0.85rem;">Carregando atendimentos...</div>
                    </div>
                </div>

                <!-- MAIN CHAT WINDOW -->
                <div class="omni-main">
                    <div class="omni-chat-header" id="main-chat-header" style="display:none;">
                        <div class="omni-header-contact">
                            <div class="omni-avatar" id="header-avatar">?</div>
                            <div>
                                <div style="font-weight:700; font-size:0.95rem; color:#0f172a;" id="header-title">Contato</div>
                                <div style="font-size:0.78rem; color:#64748b;" id="header-sub">Telefone</div>
                            </div>
                        </div>

                        <div class="omni-header-tags">
                            <span class="omni-tag-badge omni-tag-whatsapp">🟢 Central WhatsApp</span>
                            <span class="omni-tag-badge omni-tag-dept">🏷️ Suporte URE TI</span>
                            <div id="header-actions" style="display:flex; gap:8px; margin-left:12px;"></div>
                        </div>
                    </div>

                    <div class="omni-messages-area" id="messages-box">
                        <div style="margin:auto; text-align:center; color:#94a3b8; font-size:0.9rem;">
                            <div style="font-size:3rem; margin-bottom:10px;">💬</div>
                            <div>Selecione uma conversa ao lado para iniciar o atendimento</div>
                        </div>
                    </div>

                    <!-- PREVIEW DE ARQUIVOS -->
                    <div id="file-preview-container" style="display:none; background: #f8fafc; padding: 8px 16px; border-top: 1px solid #cbd5e1;">
                        <div id="file-preview-list"></div>
                    </div>

                    <div class="omni-input-footer">
                        <!-- POP-OVER EMOJIS -->
                        <div class="omni-popover" id="emoji-popover">
                            <div style="font-weight:700; font-size:0.8rem; color:#64748b; margin-bottom:8px;">SELECIONE UM EMOJI</div>
                            <div class="omni-emoji-grid">
                                <span class="omni-emoji-item" onclick="insertEmoji('😊')">😊</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('👍')">👍</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('🚀')">🚀</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('📋')">📋</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('🙏')">🙏</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('✅')">✅</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('📞')">📞</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('⏳')">⏳</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('🤝')">🤝</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('💡')">💡</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('📍')">📍</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('📄')">📄</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('⚠️')">⚠️</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('❌')">❌</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('🤖')">🤖</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('👋')">👋</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('🎯')">🎯</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('💻')">💻</span>
                            </div>
                        </div>

                        <!-- POP-OVER RESPOSTAS RÁPIDAS -->
                        <div class="omni-popover" id="canned-popover">
                            <div style="font-weight:700; font-size:0.8rem; color:#64748b; margin-bottom:8px;">RESPOSTAS RÁPIDAS</div>
                            <div class="omni-canned-list">
                                <div class="omni-canned-item" onclick="insertCanned('Olá! Como posso te ajudar hoje?')">
                                    💬 <strong>Saudação:</strong> Olá! Como posso te ajudar hoje?
                                </div>
                                <div class="omni-canned-item" onclick="insertCanned('Seu chamado de suporte técnico está em andamento pela nossa equipe de TI.')">
                                    🛠️ <strong>Em Andamento:</strong> Seu chamado de suporte está em andamento.
                                </div>
                                <div class="omni-canned-item" onclick="insertCanned('Aguarde um momento enquanto realizamos a verificação no sistema.')">
                                    ⏳ <strong>Aguarde:</strong> Aguarde um momento enquanto verificamos no sistema.
                                </div>
                                <div class="omni-canned-item" onclick="insertCanned('Poderia nos enviar uma foto ou print do erro, por gentileza?')">
                                    📷 <strong>Solicitar Print:</strong> Poderia nos enviar um print do erro?
                                </div>
                                <div class="omni-canned-item" onclick="insertCanned('Atendimento concluído com sucesso. Qualquer dúvida estamos à disposição!')">
                                    ✅ <strong>Conclusão:</strong> Atendimento concluído com sucesso.
                                </div>
                            </div>
                        </div>

                        <!-- INPUT INVISÍVEL DE UPLOAD DE ARQUIVOS -->
                        <input type="file" id="file-input" style="display:none;" onchange="uploadSelectedFile(this)" multiple>

                        <div class="omni-footer-tools">
                            <span class="omni-footer-tool-btn" title="Anexar Arquivo/Foto" onclick="document.getElementById('file-input').click()">+</span>
                            <span class="omni-footer-tool-btn" title="Emojis" onclick="toggleOmniPopover('emoji-popover')">😊</span>
                            <span class="omni-footer-tool-btn" title="Respostas Rápidas" onclick="toggleOmniPopover('canned-popover')">⚡</span>
                            <span class="omni-footer-tool-btn" title="Nota Interna" onclick="insertCanned('[NOTA INTERNA] ')">📝</span>
                        </div>

                        <textarea class="omni-message-input" id="message-input" placeholder="Digite uma mensagem (Shift + Enter quebra linha)..." onkeydown="handleKeyPress(event)" oninput="autoResizeInput(this)" disabled rows="1"></textarea>
                        <button class="omni-send-btn" id="send-btn" onclick="sendCurrentMessage()" disabled title="Enviar">✈️</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE TRANSFERÊNCIA -->
        <div id="transfer-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:20px; border-radius:8px; width:400px; max-width:90%; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
                    <h3 style="margin:0; font-size:1.1rem; color:#1e293b;">🔄 Transferir Chat</h3>
                    <button onclick="closeTransferModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:#64748b;">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-size:0.9rem; font-weight:600; color:#475569;">Selecione o destino:</label>
                    <select id="transfer-user-select" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">
                        <option value="0">📥 Fila (Desvincular)</option>
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button onclick="closeTransferModal()" style="padding:8px 16px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer; font-size:0.9rem;">Cancelar</button>
                    <button id="transfer-submit-btn" onclick="submitTransfer()" style="padding:8px 16px; background:#0284c7; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:0.9rem; font-weight:600;">Confirmar</button>
                </div>
            </div>
        </div>

        <!-- MODAL DE CONFIGURAÇÕES -->
        <div id="settings-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
            <div style="background:#fff; border-radius:8px; width:800px; max-width:95%; height:600px; max-height:90vh; box-shadow:0 10px 25px rgba(0,0,0,0.2); display:flex; flex-direction:column; overflow:hidden;">
                <!-- Header -->
                <div style="display:flex; justify-content:space-between; align-items:center; padding:15px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                    <h3 style="margin:0; font-size:1.1rem; color:#1e293b; display:flex; align-items:center; gap:8px;">⚙️ Configurações</h3>
                    <button onclick="closeSettingsModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
                </div>
                <!-- Body -->
                <div style="display:flex; flex:1; overflow:hidden;">
                    <!-- Menu lateral -->
                    <div style="width:220px; background:#f1f5f9; border-right:1px solid #e2e8f0; display:flex; flex-direction:column; padding:15px 0;">
                        <button class="settings-tab-btn active" onclick="switchSettingsTab('settings-tab-newchat')" style="padding:10px 20px; text-align:left; background:#e0f2fe; border:none; border-right:3px solid #0284c7; color:#0369a1; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:8px;">
                            ➕ Novo Contato
                        </button>
                        <button class="settings-tab-btn" style="padding:10px 20px; text-align:left; background:transparent; border:none; border-right:3px solid transparent; color:#475569; font-weight:500; cursor:pointer; display:flex; align-items:center; gap:8px;">
                            🤖 Respostas Rápidas
                        </button>
                    </div>
                    <!-- Conteúdo -->
                    <div style="flex:1; padding:25px; overflow-y:auto; background:#fff;">
                        <!-- ABA NOVO CONTATO -->
                        <div id="settings-tab-newchat" class="settings-content-pane">
                            <h4 style="margin-top:0; margin-bottom:20px; color:#1e293b; font-size:1.1rem;">Iniciar Nova Conversa</h4>
                            <p style="color:#64748b; font-size:0.9rem; margin-bottom:20px;">Envie uma mensagem ativa para um contato que ainda não está no sistema.</p>
                            
                            <div style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:5px; font-size:0.9rem; font-weight:600; color:#475569;">Número do WhatsApp (com DDI e DDD):</label>
                                <input type="text" id="new-chat-phone" placeholder="Ex: 5517999999999" style="width:100%; max-width:400px; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.95rem;">
                                <small style="display:block; color:#64748b; font-size:0.8rem; margin-top:5px;">Apenas números. Ex: 55 para Brasil.</small>
                            </div>
                            <div style="margin-bottom:25px;">
                                <label style="display:block; margin-bottom:5px; font-size:0.9rem; font-weight:600; color:#475569;">Nome do Contato (Opcional):</label>
                                <input type="text" id="new-chat-name" placeholder="Ex: João Silva" style="width:100%; max-width:400px; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.95rem;">
                            </div>
                            <div id="new-chat-error" style="color:#ef4444; font-size:0.85rem; margin-bottom:15px; display:none; background:#fef2f2; padding:10px; border-radius:4px; border:1px solid #fecaca; max-width:400px;"></div>
                            
                            <button id="new-chat-submit-btn" onclick="submitNewChat()" style="padding:10px 24px; background:#0284c7; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:0.95rem; font-weight:600; transition:background 0.2s;">Iniciar Conversa</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            let currentTab = 'mine';
            let activeChatId = 0;
            let activePhoneNumber = '';
            let isContactTabActive = false;
            let allLoadedChats = [];
            let stagedFiles = [];
            const rootDoc = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
            const CAN_TRANSFER = <?= json_encode($canTransfer) ?>;
            let transferUsersLoaded = false;

            function switchTab(tab, btn) {
                currentTab = tab;
                isContactTabActive = (tab === 'all');
                document.querySelectorAll('.omni-tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                loadChats();
            }

            async function safeFetchJson(url, options = {}) {
                try {
                    const csrfMeta = document.querySelector('meta[property="glpi:csrf_token"]');
                    if (csrfMeta) {
                        const token = csrfMeta.getAttribute('content');
                        
                        // 1. Sempre adiciona na URL
                        if (url.includes('?')) {
                            url += '&_glpi_csrf_token=' + token;
                        } else {
                            url += '?_glpi_csrf_token=' + token;
                        }

                        // 2. Sempre adiciona no Header (O GLPI as vezes depende exclusivamente dele)
                        options.headers = options.headers || {};
                        options.headers['X-Glpi-Csrf-Token'] = token;
                        options.headers['X-Requested-With'] = 'XMLHttpRequest'; // Garante que o GLPI saiba que é AJAX

                        // 3. Sempre adiciona no FormData se aplicável
                        if (options.body && typeof options.body.append === 'function') {
                            options.body.append('_glpi_csrf_token', token);
                        }
                    }
                    const res = await fetch(url, options);
                    const text = await res.text();
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        console.error('Resposta nao-JSON do servidor:', text);
                        return { success: false, error: 'Servidor retornou formato invalido. Verifique o log whatsappsimples.log' };
                    }
                } catch(e) {
                    console.error('Erro na chamada AJAX:', e);
                    return { success: false, error: 'Falha na conexao de rede' };
                }
            }

            async function loadChats() {
                const data = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/chats.php?tab=${currentTab}`);
                allLoadedChats = data.chats || [];

                if (currentTab === 'mine') {
                    document.getElementById('badge-mine').innerText = allLoadedChats.length;
                } else if (currentTab === 'queue') {
                    document.getElementById('badge-queue').innerText = allLoadedChats.length;
                }

                renderChatList(allLoadedChats);

                // Auto-atualiza a janela principal se houver um chat aberto
                if (activeChatId || activePhoneNumber) {
                    // Verifica se o chat ativo recebeu mensagens novas no poll para zerar o badge silenciosamente
                    const activeObj = allLoadedChats.find(c => c.id === activeChatId || c.phone_number === activePhoneNumber);
                    if (activeObj && activeObj.unread_count > 0) {
                        activeObj.unread_count = 0;
                        resetUnreadCount(activeObj.id);
                    }
                    loadMessages(isContactTabActive);
                }
            }

            function filterChatList() {
                const query = document.getElementById('search-input').value.toLowerCase().trim();
                if (!query) {
                    renderChatList(allLoadedChats);
                    return;
                }

                const filtered = allLoadedChats.filter(c => 
                    (c.contact_name && c.contact_name.toLowerCase().includes(query)) ||
                    (c.phone_number && c.phone_number.includes(query))
                );
                renderChatList(filtered);
            }

            function renderChatList(chats) {
                const listEl = document.getElementById('chat-list');

                if (!chats || chats.length === 0) {
                    listEl.innerHTML = '<div style="padding:20px; text-align:center; color:#94a3b8; font-size:0.85rem;">Nenhum registro encontrado</div>';
                    return;
                }

                listEl.innerHTML = chats.map(c => {
                    const initials = getInitials(c.contact_name || c.phone_number);
                    const isSelected = (c.id === activeChatId || c.phone_number === activePhoneNumber);
                    
                    let displayPhone = c.phone_number;
                    if (displayPhone && displayPhone.includes('@lid')) {
                        displayPhone = 'Número Privado (Meta)';
                    }

                    return `
                        <div class="omni-chat-card ${isSelected ? 'selected' : ''}" onclick="openChat(${c.id}, '${escapeJs(c.contact_name)}', '${escapeJs(c.phone_number)}', ${isContactTabActive}, '${escapeJs(c.technician_name || 'Sem Atendente')}')">
                            <div class="omni-avatar-wrap">
                                <div class="omni-avatar">${initials}</div>
                                <div class="omni-avatar-icon">💬</div>
                            </div>
                            <div class="omni-card-info">
                                <div class="omni-card-row1">
                                    <div class="omni-card-name">${c.contact_name}</div>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        ${(!isSelected && c.unread_count > 0) ? `<span style="background:#ef4444; color:#fff; font-size:0.7rem; font-weight:700; padding:1px 6px; border-radius:10px;">${c.unread_count}</span>` : ''}
                                        <div class="omni-card-time">${formatTime(c.date_mod)}</div>
                                    </div>
                                </div>
                                <div class="omni-card-row2">
                                    <span>✓✓</span> ${displayPhone}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            async function resetUnreadCount(chatId) {
                if (!chatId || chatId <= 0) return;
                const metaCsrf = document.querySelector('meta[property="glpi:csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
                const csrfToken = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.csrf_token) ? CFG_GLPI.csrf_token : (metaCsrf ? metaCsrf.content : '');
                const formData = new FormData();
                formData.append('chat_id', chatId);
                await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/reset-unread.php`, {
                    method: 'POST',
                    body: formData
                });
            }

            async function openChat(chatId, name, phone, isContactTab = false, technicianName = 'Sem Atendente') {
                activeChatId = chatId;
                activePhoneNumber = phone;

                // Zera o contador visualmente e no banco
                const chatObj = allLoadedChats.find(c => c.id === chatId);
                if (chatObj && chatObj.unread_count > 0) {
                    chatObj.unread_count = 0;
                    resetUnreadCount(chatId);
                }

                document.getElementById('main-chat-header').style.display = 'flex';
                let displayPhone = phone;
                if (displayPhone && displayPhone.includes('@lid')) {
                    displayPhone = 'Número Privado (Meta)';
                }
                
                document.getElementById('header-title').innerText = name || displayPhone;
                document.getElementById('header-sub').innerText = displayPhone;
                document.getElementById('header-avatar').innerText = getInitials(name);

                let transferBtnHtml = '';
                if (CAN_TRANSFER) {
                    transferBtnHtml = `
                    <button class="omni-transfer-btn" onclick="openTransferModal()" title="Transferir Atendimento">
                        <span>🔄</span> Transferir
                    </button>`;
                }

                const actionsBox = document.getElementById('header-actions');
                if (!isContactTab && chatId > 0) {
                    actionsBox.innerHTML = `
                    <button class="omni-finish-btn" onclick="closeActiveChat(${chatId})" title="Encerrar Atendimento">
                        <span style="font-size: 1.1em;">📵</span> Encerrar
                    </button>
                    ${transferBtnHtml}
                    `;
                } else if (isContactTab) {
                    actionsBox.innerHTML = `
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:0.78rem; color:#64748b; font-weight:600;">👤 Resp: ${escapeHtml(technicianName)}</span>
                            ${transferBtnHtml}
                        </div>
                    `;
                } else {
                    actionsBox.innerHTML = '';
                }

                document.getElementById('message-input').disabled = false;
                document.getElementById('send-btn').disabled = false;

                renderChatList(allLoadedChats);
                loadMessages(isContactTab);
            }

            async function closeActiveChat(chatId) {
                if (!confirm('Deseja realmente encerrar este atendimento?')) return;

                const metaCsrf = document.querySelector('meta[property="glpi:csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
                const csrfToken = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.csrf_token) ? CFG_GLPI.csrf_token : (metaCsrf ? metaCsrf.content : '');
                
                const formData = new FormData();
                formData.append('chat_id', chatId);
                const data = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/close.php`, {
                    method: 'POST',
                    body: formData
                });

                if (data.success) {
                    activeChatId = 0;
                    document.getElementById('main-chat-header').style.display = 'none';
                    document.getElementById('messages-box').innerHTML = `
                        <div class="omni-divider-badge">Atendimento encerrado com sucesso</div>
                    `;
                    document.getElementById('message-input').disabled = true;
                    document.getElementById('send-btn').disabled = true;
                    loadChats();
                } else {
                    alert(data.error || 'Erro ao encerrar atendimento');
                }
            }

            async function loadMessages(isContactTab = false) {
                if (!activeChatId && !activePhoneNumber) return;
                const url = isContactTab 
                    ? `${rootDoc}/plugins/whatsappsimples/ajax/messages.php?phone_number=${encodeURIComponent(activePhoneNumber)}`
                    : `${rootDoc}/plugins/whatsappsimples/ajax/messages.php?chat_id=${activeChatId}`;

                const data = await safeFetchJson(url);
                const box = document.getElementById('messages-box');

                if (!data.messages || data.messages.length === 0) {
                    box.innerHTML = '<div style="margin:auto; color:#94a3b8; font-size:0.85rem;">Sem mensagens registradas</div>';
                    return;
                }

                box.innerHTML = `
                    <div class="omni-divider-badge">Atendimento Iniciado</div>
                    ${data.messages.map(m => {
                        let mediaHtml = '';
                        if (m.media_url && m.media_url.startsWith('data:')) {
                            if (m.media_url.startsWith('data:image/')) {
                                mediaHtml = `<img src="${m.media_url}" style="max-width: 100%; max-height: 250px; border-radius: 8px; margin-bottom: 8px;" alt="Imagem" /><br>`;
                            } else if (m.media_url.startsWith('data:video/')) {
                                mediaHtml = `<video src="${m.media_url}" controls style="max-width: 100%; max-height: 250px; border-radius: 8px; margin-bottom: 8px;"></video><br>`;
                            } else if (m.media_url.startsWith('data:audio/')) {
                                mediaHtml = `<audio src="${m.media_url}" controls style="max-width: 250px; margin-bottom: 8px;"></audio><br>`;
                            } else {
                                mediaHtml = `<a href="${m.media_url}" download="arquivo" style="display:inline-block; padding:8px 12px; background:rgba(0,0,0,0.05); border:1px solid rgba(0,0,0,0.1); border-radius:6px; text-decoration:none; color:inherit; margin-bottom: 8px; font-weight:600;">📎 Baixar Arquivo</a><br>`;
                            }
                        }
                        return `
                            <div class="omni-bubble ${m.sender_type} ${m.is_internal ? 'omni-msg-internal' : ''}">
                                <div class="omni-bubble-sender">
                                    <span>${m.sender_name || ''}</span>
                                </div>
                                <div>${mediaHtml}${formatMessageHtml(m.message_text)}</div>
                                <div class="omni-bubble-time">${formatTime(m.date_creation)} ✓✓</div>
                            </div>
                        `;
                    }).join('')}
                `;

                box.scrollTop = box.scrollHeight;
            }

            async function sendCurrentMessage() {
                const input = document.getElementById('message-input');
                const text = input.value.trim();

                if ((!text && stagedFiles.length === 0) || (!activeChatId && !activePhoneNumber)) return;

                if (isContactTabActive) {
                    alert('Você está visualizando o Histórico deste Contato.\n\nPara poder enviar mensagens, você precisa assumir a propriedade deste chat clicando no botão "Transferir" no cabeçalho e transferindo para o seu nome!');
                    openTransferModal();
                    return;
                }

                // Bloqueia o input durante envio para evitar duplicação
                document.getElementById('send-btn').disabled = true;
                input.disabled = true;

                input.value = '';
                input.style.height = '38px'; // Reset height after send

                const metaCsrf = document.querySelector('meta[property="glpi:csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
                const csrfToken = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.csrf_token) ? CFG_GLPI.csrf_token : (metaCsrf ? metaCsrf.content : '');
                
                let hasError = false;

                if (stagedFiles.length > 0) {
                    for (let i = 0; i < stagedFiles.length; i++) {
                        const formData = new FormData();
                        formData.append('chat_id', activeChatId || 0);
                        formData.append('phone_number', activePhoneNumber || '');
                        // A legenda vai apenas no primeiro arquivo, se houver
                        if (i === 0 && text) {
                            formData.append('text', text);
                        }
                        formData.append('file', stagedFiles[i]);
                        const data = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/send.php`, {
                            method: 'POST',
                            body: formData
                        });

                        if (!data.success) {
                            alert('Erro ao enviar o arquivo ' + stagedFiles[i].name + ': ' + (data.error || 'Falha desconhecida'));
                            hasError = true;
                        }
                    }
                } else if (text) {
                    const formData = new FormData();
                    formData.append('chat_id', activeChatId || 0);
                    formData.append('phone_number', activePhoneNumber || '');
                    formData.append('text', text);
                    const data = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/send.php`, {
                        method: 'POST',
                        body: formData
                    });

                    if (!data.success) {
                        alert('Erro ao enviar: ' + (data.error || 'Falha desconhecida'));
                        hasError = true;
                    }
                }

                document.getElementById('send-btn').disabled = false;
                input.disabled = false;
                input.focus();

                if (!hasError) {
                    clearSelectedFile();
                    loadMessages(isContactTabActive);
                    loadChats();
                } else {
                    // Recarrega para mostrar as que passaram
                    loadMessages(isContactTabActive);
                    loadChats();
                }
            }

            function clearSelectedFile() {
                stagedFiles = [];
                document.getElementById('file-input').value = '';
                renderStagedFiles();
            }

            function removeStagedFile(index) {
                stagedFiles.splice(index, 1);
                renderStagedFiles();
            }

            function renderStagedFiles() {
                const container = document.getElementById('file-preview-container');
                const list = document.getElementById('file-preview-list');
                
                if (stagedFiles.length === 0) {
                    container.style.display = 'none';
                    list.innerHTML = '';
                    return;
                }
                
                container.style.display = 'block';
                list.innerHTML = stagedFiles.map((f, i) => `
                    <div style="display: flex; align-items: center; justify-content: space-between; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; margin-bottom: 4px;">
                        <span style="font-size: 0.8rem; font-weight: 600; color: #334155; max-width: 90%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">📎 ${f.name}</span>
                        <span style="cursor: pointer; color: #ef4444; font-weight: bold; font-size: 1.1rem; line-height: 1;" onclick="removeStagedFile(${i})" title="Remover">&times;</span>
                    </div>
                `).join('');
            }

            function uploadSelectedFile(inputEl) {
                if (!inputEl.files || inputEl.files.length === 0) return;
                if (!activeChatId && !activePhoneNumber) {
                    alert('Selecione uma conversa antes de anexar arquivos.');
                    inputEl.value = '';
                    return;
                }
                
                for(let i = 0; i < inputEl.files.length; i++) {
                    stagedFiles.push(inputEl.files[i]);
                }
                
                renderStagedFiles();
                inputEl.value = ''; // Limpa o input para poder selecionar mais depois
            }

            async function toggleOmniPopover(id) {
                const pop = document.getElementById(id);
                if (pop.style.display === 'block') {
                    pop.style.display = 'none';
                } else {
                    document.querySelectorAll('.omni-popover').forEach(p => p.style.display = 'none');
                    pop.style.display = 'block';
                }
            }

            function closeAllPopovers() {
                document.querySelectorAll('.omni-popover').forEach(p => p.style.display = 'none');
            }

            function insertEmoji(emoji) {
                const input = document.getElementById('message-input');
                input.value += emoji;
                input.focus();
                closeAllPopovers();
            }

            function insertCanned(text) {
                const input = document.getElementById('message-input');
                input.value = text;
                input.focus();
                closeAllPopovers();
            }

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.omni-input-footer')) {
                    closeAllPopovers();
                }
            });

            function handleKeyPress(e) {
                if (e.key === 'Enter') {
                    if (e.shiftKey) {
                        // Não faz nada, o comportamento padrão do navegador vai inserir a quebra de linha
                        return;
                    } else {
                        e.preventDefault();
                        sendCurrentMessage();
                    }
                }
            }

            function autoResizeInput(el) {
                el.style.height = '38px';
                if (el.scrollHeight > 38) {
                    el.style.height = Math.min(el.scrollHeight, 114) + 'px';
                }
            }

            function getInitials(name) {
                if (!name) return 'C';
                const parts = name.trim().split(' ');
                if (parts.length >= 2) {
                    return (parts[0][0] + parts[1][0]).toUpperCase();
                }
                return name.substring(0, 2).toUpperCase();
            }

            function formatTime(dateStr) {
                if (!dateStr) return '';
                const parts = dateStr.split(' ');
                if (parts.length === 2) {
                    return parts[1].substring(0, 5);
                }
                return dateStr;
            }

            function escapeHtml(str) {
                if (!str) return '';
                return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br>");
            }

            function formatMessageHtml(str) {
                if (!str) return '';
                let escaped = escapeHtml(str);
                // Bold: *text*
                escaped = escaped.replace(/\*([^\*]+)\*/g, "<strong>$1</strong>");
                // Italic: _text_
                escaped = escaped.replace(/_([^_]+)_/g, "<em>$1</em>");
                // Strikethrough: ~text~
                escaped = escaped.replace(/~([^~]+)~/g, "<del>$1</del>");
                return escaped;
            }

            function escapeJs(str) {
                if (!str) return '';
                return str.replace(/'/g, "\\'");
            }

            // Transfer Logic
            async function openTransferModal() {
                if (!activeChatId && !activePhoneNumber) return;
                const modal = document.getElementById('transfer-modal');
                modal.style.display = 'flex';
                
                if (!transferUsersLoaded) {
                    const select = document.getElementById('transfer-user-select');
                    select.innerHTML = '<option value="0">📥 Fila (Desvincular)</option><option disabled>Carregando técnicos...</option>';
                    
                    const res = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/users.php`);
                    select.innerHTML = '<option value="0">📥 Fila (Desvincular)</option>';
                    
                    if (res && res.users) {
                        res.users.forEach(u => {
                            const opt = document.createElement('option');
                            opt.value = u.id;
                            opt.textContent = `👤 ${u.name}`;
                            select.appendChild(opt);
                        });
                        transferUsersLoaded = true;
                    } else {
                        select.innerHTML = '<option value="0">📥 Fila (Desvincular)</option><option disabled>Erro ao carregar técnicos</option>';
                    }
                }
            }

            function closeTransferModal() {
                document.getElementById('transfer-modal').style.display = 'none';
            }

            async function submitTransfer() {
                if (!activeChatId && !activePhoneNumber) return;
                
                // If chat isn't initialized yet (just clicking in Contacts before any message), activeChatId might be 0, but we need it.
                // However, in contacts, we fetch history by phone, but usually there's a chat ID if it exists.
                if (activeChatId === 0) {
                    alert('Este chat ainda não existe no banco de dados. Envie uma mensagem primeiro.');
                    return;
                }

                const select = document.getElementById('transfer-user-select');
                const newUserId = select.value;
                const btn = document.getElementById('transfer-submit-btn');
                
                btn.innerText = 'Transferindo...';
                btn.disabled = true;
                
                const formData = new FormData();
                formData.append('chat_id', activeChatId);
                formData.append('user_id', newUserId);
                
                const res = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/transfer.php`, {
                    method: 'POST',
                    body: formData
                });
                
                btn.innerText = 'Confirmar';
                btn.disabled = false;
                
                if (res && res.success) {
                    closeTransferModal();
                    // Limpa chat ativo da tela
                    activeChatId = 0;
                    activePhoneNumber = '';
                    document.getElementById('main-chat-header').style.display = 'none';
                    document.getElementById('messages-box').innerHTML = `
                        <div style="margin:auto; text-align:center; color:#94a3b8; font-size:0.9rem;">
                            <div style="font-size:3rem; margin-bottom:10px;">💬</div>
                            <div>Chat transferido com sucesso!</div>
                        </div>
                    `;
                    document.getElementById('message-input').disabled = true;
                    document.getElementById('send-btn').disabled = true;
                    
                    loadChats();
                } else {
                    alert(res.error || 'Erro ao transferir chat.');
                }
            }

            function openSettingsModal() {
                document.getElementById('new-chat-phone').value = '';
                document.getElementById('new-chat-name').value = '';
                document.getElementById('new-chat-error').style.display = 'none';
                
                document.getElementById('settings-modal').style.display = 'flex';
                setTimeout(() => document.getElementById('new-chat-phone').focus(), 100);
            }

            function closeSettingsModal() {
                document.getElementById('settings-modal').style.display = 'none';
            }
            
            function switchSettingsTab(tabId) {
                // Future use when we have more tabs
            }

            async function submitNewChat() {
                const phoneInput = document.getElementById('new-chat-phone').value.trim();
                const nameInput = document.getElementById('new-chat-name').value.trim();
                const errorEl = document.getElementById('new-chat-error');
                const btn = document.getElementById('new-chat-submit-btn');

                if (!phoneInput) {
                    errorEl.innerText = 'O número de telefone é obrigatório.';
                    errorEl.style.display = 'block';
                    return;
                }

                errorEl.style.display = 'none';
                btn.innerText = 'Iniciando...';
                btn.disabled = true;

                const formData = new FormData();
                formData.append('phone_number', phoneInput);
                formData.append('contact_name', nameInput);

                const res = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/new-chat.php`, {
                    method: 'POST',
                    body: formData
                });

                btn.innerText = 'Iniciar';
                btn.disabled = false;

                if (res && res.success) {
                    closeSettingsModal();
                    // Muda para a aba "Meus Chats" para ver a conversa recém adicionada
                    document.querySelector('.omni-tab-btn[onclick="switchTab(\'mine\', this)"]').click();
                    await loadChats();
                    
                    // Abre o chat recém criado
                    openChat(res.chat_id, res.contact_name, res.phone_number, false, 'Você');
                } else {
                    errorEl.innerText = res.error || 'Erro desconhecido ao iniciar conversa.';
                    errorEl.style.display = 'block';
                }
            }

            // Inicia o app
            setInterval(loadChats, 5000);
            loadChats();
        </script>
        <?php

        \Html::footer();
        return new Response(ob_get_clean());
    }
}
