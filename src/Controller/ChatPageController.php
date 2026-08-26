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
        global $CFG_GLPI;

        ob_start();
        include_once GLPI_ROOT . '/inc/includes.php';
        \Html::header('WhatsApp', $_SERVER['PHP_SELF'], 'tools', 'whatsappsimples');

        ?>
        <style>
            .wa-container { display: flex; height: calc(100vh - 120px); background: #f8fafc; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
            .wa-sidebar { width: 340px; background: #ffffff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; }
            .wa-tabs { display: flex; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; padding: 4px; }
            .wa-tab-btn { flex: 1; padding: 8px 4px; border: none; background: transparent; font-size: 0.85rem; font-weight: 600; color: #64748b; cursor: pointer; border-radius: 6px; transition: all 0.2s; }
            .wa-tab-btn.active { background: #ffffff; color: #0d9488; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            
            .wa-chat-list { flex: 1; overflow-y: auto; }
            .wa-chat-item { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.15s; }
            .wa-chat-item:hover { background: #f8fafc; }
            .wa-chat-item.selected { background: #ccfbf1; border-left: 4px solid #0d9488; }
            .wa-chat-header { display: flex; justify-content: space-between; font-weight: 600; font-size: 0.9rem; color: #1e293b; margin-bottom: 4px; }
            .wa-chat-time { font-size: 0.75rem; color: #94a3b8; font-weight: normal; }
            .wa-chat-sub { font-size: 0.8rem; color: #64748b; }

            .wa-main { flex: 1; display: flex; flex-direction: column; background: #efeae2; }
            .wa-main-header { padding: 14px 20px; background: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
            .wa-contact-title { font-weight: 700; font-size: 1rem; color: #0f172a; }
            .wa-contact-sub { font-size: 0.8rem; color: #64748b; }

            .wa-close-btn { padding: 6px 14px; background: #ef4444; color: #ffffff; border: none; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
            .wa-close-btn:hover { background: #dc2626; }

            .wa-messages-box { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
            .wa-bubble { max-width: 65%; padding: 10px 14px; border-radius: 10px; font-size: 0.9rem; line-height: 1.4; position: relative; word-wrap: break-word; }
            .wa-bubble.user { align-self: flex-start; background: #ffffff; color: #1e293b; border-bottom-left-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
            .wa-bubble.attendant { align-self: flex-end; background: #dcf8c6; color: #0f172a; border-bottom-right-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
            .wa-msg-sender { font-size: 0.75rem; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.2px; }
            .wa-bubble.user .wa-msg-sender { color: #0284c7; }
            .wa-bubble.attendant .wa-msg-sender { color: #047857; }
            .wa-msg-time { font-size: 0.68rem; color: #94a3b8; text-align: right; margin-top: 4px; }

            .wa-input-area { padding: 12px 16px; background: #ffffff; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; }
            .wa-input { flex: 1; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 20px; outline: none; font-size: 0.9rem; }
            .wa-input:focus { border-color: #0d9488; }
            .wa-send-btn { padding: 10px 20px; background: #0d9488; color: #ffffff; border: none; border-radius: 20px; font-weight: 600; cursor: pointer; }
            .wa-send-btn:hover { background: #0f766e; }
            .wa-send-btn:disabled { background: #cbd5e1; cursor: not-allowed; }
        </style>

        <div class="wa-container">
            <!-- COLUNA DA ESQUERDA: 3 ABAS -->
            <div class="wa-sidebar">
                <div class="wa-tabs">
                    <button class="wa-tab-btn active" onclick="switchTab('mine', this)">💬 Chats</button>
                    <button class="wa-tab-btn" onclick="switchTab('queue', this)">📥 Fila</button>
                    <button class="wa-tab-btn" onclick="switchTab('all', this)">👥 Contatos</button>
                </div>
                <div class="wa-chat-list" id="chat-list">
                    <div style="padding:20px; text-align:center; color:#94a3b8;">Carregando conversas...</div>
                </div>
            </div>

            <!-- COLUNA DA DIREITA: JANELA DO CHAT -->
            <div class="wa-main">
                <div class="wa-main-header">
                    <div>
                        <div class="wa-contact-title" id="chat-title">Selecione uma conversa</div>
                        <div class="wa-contact-sub" id="chat-sub">Escolha um atendimento na lista à esquerda</div>
                    </div>
                    <div id="header-actions"></div>
                </div>

                <div class="wa-messages-box" id="messages-box">
                    <div style="margin:auto; color:#94a3b8; font-size:0.9rem;">Nenhuma conversa selecionada</div>
                </div>

                <div class="wa-input-area">
                    <input type="text" class="wa-input" id="message-input" placeholder="Digite sua resposta..." onkeypress="handleKeyPress(event)" disabled>
                    <button class="wa-send-btn" id="send-btn" onclick="sendCurrentMessage()" disabled>Enviar</button>
                </div>
            </div>
        </div>

        <script>
            let currentTab = 'mine';
            let activeChatId = 0;
            let activePhoneNumber = '';
            let isContactTabActive = false;
            const rootDoc = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';

            function switchTab(tab, btn) {
                currentTab = tab;
                isContactTabActive = (tab === 'all');
                document.querySelectorAll('.wa-tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                loadChats();
            }

            async function loadChats() {
                try {
                    const res = await fetch(`${rootDoc}/plugins/whatsappsimples/ajax/chats?tab=${currentTab}`);
                    const data = await res.json();
                    const listEl = document.getElementById('chat-list');

                    if (!data.chats || data.chats.length === 0) {
                        listEl.innerHTML = '<div style="padding:20px; text-align:center; color:#94a3b8;">Nenhum registro nesta aba</div>';
                        return;
                    }

                    listEl.innerHTML = data.chats.map(c => `
                        <div class="wa-chat-item ${(c.id === activeChatId || c.phone_number === activePhoneNumber) ? 'selected' : ''}" onclick="openChat(${c.id}, '${c.contact_name}', '${c.phone_number}', ${isContactTabActive})">
                            <div class="wa-chat-header">
                                <span>${c.contact_name}</span>
                                <span class="wa-chat-time">${c.date_mod}</span>
                            </div>
                            <div class="wa-chat-sub">${c.phone_number}</div>
                        </div>
                    `).join('');
                } catch (e) {
                    console.error('Erro ao carregar chats:', e);
                }
            }

            async function openChat(chatId, name, phone, isContactTab = false) {
                activeChatId = chatId;
                activePhoneNumber = phone;
                document.getElementById('chat-title').innerText = name;
                document.getElementById('chat-sub').innerText = phone;

                const actionsBox = document.getElementById('header-actions');
                if (!isContactTab && chatId > 0) {
                    actionsBox.innerHTML = `
                        <button class="wa-close-btn" onclick="closeActiveChat(${chatId})">✅ Encerrar Atendimento</button>
                    `;
                } else if (isContactTab) {
                    actionsBox.innerHTML = `<span style="font-size:0.8rem; color:#64748b; font-weight:600;">📜 Histórico Completo do Contato</span>`;
                } else {
                    actionsBox.innerHTML = '';
                }

                document.getElementById('message-input').disabled = false;
                document.getElementById('send-btn').disabled = false;

                loadChats();
                loadMessages(isContactTab);
            }

            async function closeActiveChat(chatId) {
                if (!confirm('Deseja realmente encerrar este atendimento?')) return;

                try {
                    const metaCsrf = document.querySelector('meta[property="glpi:csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.csrf_token) ? CFG_GLPI.csrf_token : (metaCsrf ? metaCsrf.content : '');
                    
                    const formData = new FormData();
                    formData.append('chat_id', chatId);
                    if (csrfToken) {
                        formData.append('_glpi_csrf_token', csrfToken);
                    }

                    const res = await fetch(`${rootDoc}/plugins/whatsappsimples/ajax/close`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Glpi-Csrf-Token': csrfToken
                        },
                        body: formData
                    });

                    const data = await res.json();
                    if (data.success) {
                        activeChatId = 0;
                        document.getElementById('chat-title').innerText = 'Selecione uma conversa';
                        document.getElementById('chat-sub').innerText = 'Escolha um atendimento na lista à esquerda';
                        document.getElementById('header-actions').innerHTML = '';
                        document.getElementById('messages-box').innerHTML = '<div style="margin:auto; color:#94a3b8; font-size:0.9rem;">Atendimento encerrado com sucesso!</div>';
                        document.getElementById('message-input').disabled = true;
                        document.getElementById('send-btn').disabled = true;
                        loadChats();
                    } else {
                        alert(data.error || 'Erro ao encerrar atendimento');
                    }
                } catch (e) {
                    console.error('Erro ao encerrar chat:', e);
                }
            }

            async function loadMessages(isContactTab = false) {
                if (!activeChatId && !activePhoneNumber) return;
                try {
                    const url = isContactTab 
                        ? `${rootDoc}/plugins/whatsappsimples/ajax/messages?phone_number=${encodeURIComponent(activePhoneNumber)}`
                        : `${rootDoc}/plugins/whatsappsimples/ajax/messages?chat_id=${activeChatId}`;

                    const res = await fetch(url);
                    const data = await res.json();
                    const box = document.getElementById('messages-box');

                    if (!data.messages || data.messages.length === 0) {
                        box.innerHTML = '<div style="margin:auto; color:#94a3b8;">Sem mensagens registradas</div>';
                        return;
                    }

                    box.innerHTML = data.messages.map(m => `
                        <div class="wa-bubble ${m.sender_type}">
                            <div class="wa-msg-sender">${m.sender_name || ''}</div>
                            <div>${m.message_text}</div>
                            <div class="wa-msg-time">${m.date_creation}</div>
                        </div>
                    `).join('');

                    box.scrollTop = box.scrollHeight;
                } catch (e) {
                    console.error('Erro ao carregar mensagens:', e);
                }
            }

            async function sendCurrentMessage() {
                const input = document.getElementById('message-input');
                const text = input.value.trim();
                if (!text || (!activeChatId && !activePhoneNumber)) return;

                input.value = '';

                try {
                    const metaCsrf = document.querySelector('meta[property="glpi:csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
                    const csrfToken = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.csrf_token) ? CFG_GLPI.csrf_token : (metaCsrf ? metaCsrf.content : '');
                    const formData = new FormData();
                    formData.append('chat_id', activeChatId || 0);
                    formData.append('phone_number', activePhoneNumber || '');
                    formData.append('text', text);
                    if (csrfToken) {
                        formData.append('_glpi_csrf_token', csrfToken);
                    }

                    const res = await fetch(`${rootDoc}/plugins/whatsappsimples/ajax/send`, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Glpi-Csrf-Token': csrfToken
                        },
                        body: formData
                    });

                    const data = await res.json();
                    if (data.success) {
                        loadMessages(isContactTabActive);
                        loadChats();
                    } else {
                        alert('Erro ao enviar: ' + (data.error || 'Falha desconhecida'));
                    }
                } catch (e) {
                    alert('Erro na requisição de envio.');
                    console.error(e);
                }
            }

            function handleKeyPress(e) {
                if (e.key === 'Enter') {
                    sendCurrentMessage();
                }
            }

            loadChats();
            setInterval(loadChats, 5000);
        </script>
        <?php

        \Html::footer();
        return new Response(ob_get_clean());
    }
}
