(() => {
    const SERVER = window.WAPP_SERVER;
    const TOKEN  = window.WAPP_TOKEN;
    const headers = { 'Content-Type': 'application/json', 'x-api-token': TOKEN };

    const GLPI_URL        = 'http://10.180.152.110';
    const GLPI_APP_TOKEN  = 'Qy0ocv7BYvG363PY6O4CVSJvlHGAWI34t9Ex93BH';
    const GLPI_USER_TOKEN = 'RYRtYjeUqLMLzlm3DqhMYxDzC64j97DQz79Oxmq9';

    let chats        = {};
    let current      = null;
    let pollTimer    = null;
    let lastTs       = {};
    let contactNames = {};
    let unread       = {};
    let clearedAt    = {};
    let pending      = {}; // fila de conversas pendentes (nao abertas ainda)

    const $ = id => document.getElementById(id);

    // Nome do usuario logado   pega do dropdown do menu do GLPI
    const GLPI_USER_NAME = (() => {
        const el = document.querySelector('.user-menu .dropdown-header');
        if (el) return el.textContent.trim();
        return 'Atendente';
    })();

    function renderMsgContent(text) {
        if (!text) return '';
        if (text.startsWith('__MEDIA__')) {
            try {
                const info = JSON.parse(text.replace('__MEDIA__', ''));
                const url  = `${SERVER}/api/media/${encodeURIComponent(info.fname)}`;
                if (info.mediaType === 'image') {
                    return `<div class="wapp-media-wrap">
                        <img src="${url}" class="wapp-media-img" onclick="window.open('${url}','_blank')" title="Clique para abrir">
                        <a href="${url}" download="${info.name}" class="wapp-media-dl"><i class="ti ti-download"></i> Baixar</a>
                    </div>`;
                }
                if (info.mediaType === 'audio') {
                    return `<div class="wapp-media-wrap">
                        <audio controls class="wapp-media-audio"><source src="${url}"></audio>
                        <a href="${url}" download="${info.name}" class="wapp-media-dl"><i class="ti ti-download"></i> ${info.name}</a>
                    </div>`;
                }
                if (info.mediaType === 'document') {
                    const icon = info.name.endsWith('.pdf') ? 'ti-file-type-pdf' : 'ti-file';
                    return `<div class="wapp-media-wrap wapp-media-doc">
                        <i class="ti ${icon}"></i>
                        <span title="${info.name}">${info.name}</span>
                        <a href="${url}" target="_blank" class="wapp-media-dl"><i class="ti ti-external-link"></i> Abrir</a>
                        <a href="${url}" download="${info.name}" class="wapp-media-dl"><i class="ti ti-download"></i> Baixar</a>
                    </div>`;
                }
            } catch(e) {}
        }
        if (text === '[Figurinha]') return '<span class="wapp-sticker">??</span>';
        return text;
    }

    function msgPreview(text) {
        if (!text) return '';
        if (text.startsWith('__MEDIA__')) {
            try {
                const info = JSON.parse(text.replace('__MEDIA__', ''));
                if (info.mediaType === 'image')    return `[Imagem: ${info.name}]`;
                if (info.mediaType === 'audio')    return `[Audio: ${info.name}]`;
                if (info.mediaType === 'document') return `[Doc: ${info.name}]`;
            } catch(e) {}
            return '[Midia]';
        }
        return text.length > 60 ? text.substring(0, 60) + '...' : text;
    }

    function isMedia(text) {
        return text && text.startsWith('__MEDIA__');
    }

    function getMediaInfo(text) {
        try { return JSON.parse(text.replace('__MEDIA__', '')); } catch(e) { return null; }
    }

    // -- Sess o GLPI --------------------------------------------------------
    async function glpiSession() {
        const r = await fetch(`${GLPI_URL}/glpi/apirest.php/initSession`, {
            headers: {
                'App-Token':     GLPI_APP_TOKEN,
                'Authorization': `user_token ${GLPI_USER_TOKEN}`,
                'Content-Type':  'application/json',
            }
        });
        const d = await r.json();
        return d.session_token;
    }

    // Upload via PHP proxy com token CSRF do GLPI 11
    async function uploadMediaToGlpi(session, fname, name, ticketId) {
        try {
            const mediaUrl   = `${SERVER}/api/media/${encodeURIComponent(fname)}`;
            const csrfToken  = document.querySelector('meta[property="glpi:csrf_token"]')?.content || '';
            const r = await fetch(`${GLPI_URL}/glpi/plugins/whatsappsimples/front/ajax/upload_media.php`, {
                method: 'POST',
                headers: {
                    'Content-Type':      'application/x-www-form-urlencoded',
                    'X-GLPI-CSRF-Token': csrfToken,
                },
                body: new URLSearchParams({
                    media_url:        mediaUrl,
                    media_name:       name,
                    ticket_id:        ticketId,
                    _glpi_csrf_token: csrfToken,
                })
            });
            const result = await r.json();
            return result.ok ? result.document_id : null;
        } catch(e) {
            console.error('Erro upload midia:', e);
            return null;
        }
    }

    function buildMsgSelectList(containerId) {
        const msgs = chats[current] || [];
        const list = $(containerId);
        list.innerHTML = '';
        msgs.forEach((m, i) => {
            const time  = new Date(m.timestamp).toLocaleString('pt-BR');
            const who   = m.fromMe ? 'Eu' : (contactNames[current] || current);
            const prev  = msgPreview(m.text);
            const media = isMedia(m.text);
            const row   = document.createElement('label');
            row.className = 'wapp-msg-select-row';
            row.innerHTML = `
                <input type="checkbox" class="wapp-msg-chk" data-idx="${i}" checked>
                <div class="wapp-msg-select-info">
                    <span class="wapp-msg-select-who ${m.fromMe ? 'me' : 'them'}">${who}</span>
                    <span class="wapp-msg-select-text">${media ? '<i class="ti ti-paperclip" style="font-size:.75rem"></i> ' : ''}${prev}</span>
                    <span class="wapp-msg-select-time">${time}</span>
                </div>
            `;
            list.appendChild(row);
        });
        return msgs.length;
    }

    function getSelectedIdxs(containerId) {
        return [...document.querySelectorAll(`#${containerId} .wapp-msg-chk:checked`)]
            .map(c => parseInt(c.dataset.idx));
    }

    function getSelectedMsgs(containerId) {
        const msgs = chats[current] || [];
        return getSelectedIdxs(containerId).map(i => {
            const m    = msgs[i];
            const time = new Date(m.timestamp).toLocaleString('pt-BR');
            const who  = m.fromMe ? 'Eu' : (contactNames[current] || current);
            const txt  = isMedia(m.text) ? msgPreview(m.text) : m.text;
            return `[${time}] ${who}: ${txt}`;
        });
    }

    // -- Modal Salvar Ticket ------------------------------------------------
    function createSaveModal() {
        if ($('wapp-modal')) return;
        const m = document.createElement('div');
        m.id = 'wapp-modal';
        m.innerHTML = `
            <div id="wapp-modal-box">
                <div id="wapp-modal-header">
                    <span><i class="ti ti-device-floppy"></i> Salvar no ticket</span>
                    <button id="wapp-modal-close"><i class="ti ti-x"></i></button>
                </div>
                <div id="wapp-modal-body">
                    <label>Numero do Ticket</label>
                    <input type="number" id="wapp-ticket-id" placeholder="Ex: 123" min="1">
                    <div id="wapp-ticket-info"></div>
                    <div id="wapp-msg-select-wrap" style="display:none">
                        <div id="wapp-msg-select-header">
                            <label>Selecione as mensagens</label>
                            <div style="display:flex;gap:8px">
                                <button id="wapp-sel-all" class="wapp-sel-btn">Todas</button>
                                <button id="wapp-sel-none" class="wapp-sel-btn">Nenhuma</button>
                            </div>
                        </div>
                        <div id="wapp-msg-select-list"></div>
                    </div>
                </div>
                <div id="wapp-modal-footer">
                    <button id="wapp-modal-cancel">Cancelar</button>
                    <button id="wapp-modal-save"><i class="ti ti-check"></i> Salvar</button>
                </div>
            </div>
        `;
        document.body.appendChild(m);
        $('wapp-modal-close').onclick  = closeSaveModal;
        $('wapp-modal-cancel').onclick = closeSaveModal;
        $('wapp-modal').onclick = e => { if (e.target === $('wapp-modal')) closeSaveModal(); };
        let searchTimer = null;
        $('wapp-ticket-id').oninput = () => {
            clearTimeout(searchTimer);
            const id = $('wapp-ticket-id').value.trim();
            if (!id) { $('wapp-ticket-info').innerHTML = ''; return; }
            searchTimer = setTimeout(() => fetchTicketInfo(id), 500);
        };
        $('wapp-modal-save').onclick = saveToTicket;
        $('wapp-sel-all').onclick  = () => document.querySelectorAll('#wapp-msg-select-list .wapp-msg-chk').forEach(c => c.checked = true);
        $('wapp-sel-none').onclick = () => document.querySelectorAll('#wapp-msg-select-list .wapp-msg-chk').forEach(c => c.checked = false);
    }

    function openSaveModal() {
        createSaveModal();
        $('wapp-ticket-id').value = '';
        $('wapp-ticket-info').innerHTML = '';
        const count = buildMsgSelectList('wapp-msg-select-list');
        $('wapp-msg-select-wrap').style.display = count ? 'flex' : 'none';
        $('wapp-modal').style.display = 'flex';
        setTimeout(() => $('wapp-ticket-id').focus(), 100);
    }

    function closeSaveModal() {
        const m = $('wapp-modal');
        if (m) m.style.display = 'none';
    }

    async function fetchTicketInfo(id) {
        const info = $('wapp-ticket-info');
        info.innerHTML = '<span style="opacity:.5">Buscando...</span>';
        try {
            const session = await glpiSession();
            const r = await fetch(`${GLPI_URL}/glpi/apirest.php/Ticket/${id}`, {
                headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session, 'Content-Type': 'application/json' }
            });
            const t = await r.json();
            if (t.id) {
                info.innerHTML = `<div class="wapp-ticket-found"><i class="ti ti-ticket"></i> <strong>#${t.id}</strong> - ${t.name}</div>`;
            } else {
                info.innerHTML = '<span class="wapp-ticket-notfound">Ticket nao encontrado</span>';
            }
        } catch(e) {
            info.innerHTML = '<span class="wapp-ticket-notfound">Erro ao buscar ticket</span>';
        }
    }

    async function saveToTicket() {
        const ticketId = $('wapp-ticket-id').value.trim();
        if (!ticketId) {
            $('wapp-ticket-info').innerHTML = '<span class="wapp-ticket-notfound">Informe o numero do ticket</span>';
            return;
        }
        const idxs = getSelectedIdxs('wapp-msg-select-list');
        if (!idxs.length) {
            $('wapp-ticket-info').innerHTML = '<span class="wapp-ticket-notfound">Selecione ao menos uma mensagem</span>';
            return;
        }
        const btn = $('wapp-modal-save');
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader"></i> Salvando...';
        try {
            const session = await glpiSession();
            const msgs    = chats[current] || [];
            const label   = contactNames[current] || current;
            const lines   = getSelectedMsgs('wapp-msg-select-list');
            const content = `<b>Conversa WhatsApp - ${label} (${current})</b><br><pre style="font-size:12px;line-height:1.5">${lines.join('\n')}</pre>`;

            const r = await fetch(`${GLPI_URL}/glpi/apirest.php/ITILFollowup`, {
                method: 'POST',
                headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session, 'Content-Type': 'application/json' },
                body: JSON.stringify({ input: { itemtype: 'Ticket', items_id: parseInt(ticketId), content, is_private: 0 } })
            });
            const result = await r.json();
            if (!result.id) throw new Error('Erro followup');

            for (const i of idxs) {
                const m = msgs[i];
                if (!isMedia(m.text)) continue;
                const info = getMediaInfo(m.text);
                if (info && info.fname) {
                    await uploadMediaToGlpi(session, info.fname, info.name, parseInt(ticketId));
                }
            }

            $('wapp-ticket-info').innerHTML = `<div class="wapp-ticket-found"><i class="ti ti-circle-check"></i> Salvo no ticket #${ticketId} com sucesso!</div>`;
            btn.innerHTML = '<i class="ti ti-check"></i> Salvo!';
            setTimeout(closeSaveModal, 1500);
        } catch(e) {
            $('wapp-ticket-info').innerHTML = '<span class="wapp-ticket-notfound">Erro ao salvar. Verifique o ticket.</span>';
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-check"></i> Salvar';
        }
    }

    // -- Modal Novo Chamado -------------------------------------------------
    let glpiCategories = [];
    let glpiUsers      = [];

    function createNewTicketModal() {
        if ($('wapp-new-ticket-modal')) return;
        const m = document.createElement('div');
        m.id = 'wapp-new-ticket-modal';
        m.innerHTML = `
            <div id="wapp-new-ticket-box">
                <div id="wapp-new-ticket-header">
                    <span><i class="ti ti-ticket"></i> Abrir Chamado</span>
                    <button id="wapp-new-ticket-close"><i class="ti ti-x"></i></button>
                </div>
                <div id="wapp-new-ticket-body">
                    <div class="wapp-form-group">
                        <label>Titulo *</label>
                        <input type="text" id="wnt-title" placeholder="Titulo do chamado">
                    </div>
                    <div class="wapp-form-row">
                        <div class="wapp-form-group">
                            <label>Urgencia</label>
                            <select id="wnt-urgency">
                                <option value="1">Muito Baixa</option>
                                <option value="2">Baixa</option>
                                <option value="3" selected>Media</option>
                                <option value="4">Alta</option>
                                <option value="5">Muito Alta</option>
                            </select>
                        </div>
                        <div class="wapp-form-group">
                            <label>Impacto</label>
                            <select id="wnt-impact">
                                <option value="1">Muito Baixo</option>
                                <option value="2">Baixo</option>
                                <option value="3" selected>Medio</option>
                                <option value="4">Alto</option>
                                <option value="5">Muito Alto</option>
                            </select>
                        </div>
                        <div class="wapp-form-group">
                            <label>Prioridade</label>
                            <select id="wnt-priority">
                                <option value="1">Muito Baixa</option>
                                <option value="2">Baixa</option>
                                <option value="3" selected>Media</option>
                                <option value="4">Alta</option>
                                <option value="5">Muito Alta</option>
                            </select>
                        </div>
                    </div>
                    <div class="wapp-form-group">
                        <label>Categoria</label>
                        <select id="wnt-category"><option value="0">Sem categoria</option></select>
                    </div>
                    <div class="wapp-form-group">
                        <label>Solicitante</label>
                        <select id="wnt-requester"><option value="0">Selecione...</option></select>
                    </div>
                    <div class="wapp-form-group">
                        <label>Descricao</label>
                        <textarea id="wnt-description" rows="3" placeholder="Descricao do chamado..."></textarea>
                    </div>
                    <div class="wapp-form-group">
                        <div id="wapp-new-ticket-msg-header">
                            <label>Incluir mensagens no chamado</label>
                            <div style="display:flex;gap:8px">
                                <button id="wnt-sel-all" class="wapp-sel-btn">Todas</button>
                                <button id="wnt-sel-none" class="wapp-sel-btn">Nenhuma</button>
                            </div>
                        </div>
                        <div id="wnt-msg-list"></div>
                    </div>
                    <div id="wnt-result"></div>
                </div>
                <div id="wapp-new-ticket-footer">
                    <button id="wnt-cancel">Cancelar</button>
                    <button id="wnt-submit"><i class="ti ti-check"></i> Abrir Chamado</button>
                </div>
            </div>
        `;
        document.body.appendChild(m);
        $('wapp-new-ticket-close').onclick = closeNewTicketModal;
        $('wnt-cancel').onclick            = closeNewTicketModal;
        $('wapp-new-ticket-modal').onclick = e => { if (e.target === $('wapp-new-ticket-modal')) closeNewTicketModal(); };
        $('wnt-submit').onclick            = submitNewTicket;
        $('wnt-sel-all').onclick  = () => document.querySelectorAll('#wnt-msg-list .wapp-msg-chk').forEach(c => c.checked = true);
        $('wnt-sel-none').onclick = () => document.querySelectorAll('#wnt-msg-list .wapp-msg-chk').forEach(c => c.checked = false);
    }

    async function openNewTicketModal() {
        createNewTicketModal();
        $('wnt-title').value       = contactNames[current] ? `Atendimento - ${contactNames[current]}` : `Atendimento - ${current}`;
        $('wnt-description').value = '';
        $('wnt-urgency').value     = '3';
        $('wnt-impact').value      = '3';
        $('wnt-priority').value    = '3';
        $('wnt-result').innerHTML  = '';

        try {
            const session = await glpiSession();
            if (glpiCategories.length === 0) {
                const rc   = await fetch(`${GLPI_URL}/glpi/apirest.php/ITILCategory?range=0-500`, {
                    headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session, 'Content-Type': 'application/json' }
                });
                const cats = await rc.json();
                glpiCategories = Array.isArray(cats) ? cats : [];
                const sel = $('wnt-category');
                sel.innerHTML = '<option value="0">Sem categoria</option>';
                glpiCategories.forEach(c => {
                    const o = document.createElement('option');
                    o.value = c.id;
                    o.textContent = c.completename || c.name;
                    sel.appendChild(o);
                });
            }
            if (glpiUsers.length === 0) {
                const ru = await fetch(`${GLPI_URL}/glpi/apirest.php/User?range=0-500`, {
                    headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session, 'Content-Type': 'application/json' }
                });
                const us = await ru.json();
                glpiUsers = Array.isArray(us) ? us.filter(u => u.is_active) : [];
                const sel = $('wnt-requester');
                sel.innerHTML = '<option value="0">Selecione...</option>';
                glpiUsers.forEach(u => {
                    const o = document.createElement('option');
                    o.value = u.id;
                    o.textContent = `${u.firstname || ''} ${u.realname || ''}`.trim() || u.name;
                    sel.appendChild(o);
                });
            }
        } catch(e) {}

        buildMsgSelectList('wnt-msg-list');
        $('wapp-new-ticket-modal').style.display = 'flex';
        setTimeout(() => $('wnt-title').focus(), 100);
    }

    function closeNewTicketModal() {
        const m = $('wapp-new-ticket-modal');
        if (m) m.style.display = 'none';
    }

    async function submitNewTicket() {
        const title = $('wnt-title').value.trim();
        if (!title) {
            $('wnt-result').innerHTML = '<span class="wapp-ticket-notfound">Informe o titulo do chamado</span>';
            return;
        }
        const btn = $('wnt-submit');
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader"></i> Abrindo...';
        try {
            const session     = await glpiSession();
            const label       = contactNames[current] || current;
            const idxs        = getSelectedIdxs('wnt-msg-list');
            const msgs        = chats[current] || [];
            const lines       = idxs.map(i => {
                const m    = msgs[i];
                const time = new Date(m.timestamp).toLocaleString('pt-BR');
                const who  = m.fromMe ? 'Eu' : label;
                const txt  = isMedia(m.text) ? msgPreview(m.text) : m.text;
                return `[${time}] ${who}: ${txt}`;
            });
            const description = $('wnt-description').value.trim();
            const urgency     = parseInt($('wnt-urgency').value);
            const impact      = parseInt($('wnt-impact').value);
            const priority    = parseInt($('wnt-priority').value);
            const categoryId  = parseInt($('wnt-category').value) || 0;
            const requesterId = parseInt($('wnt-requester').value) || 0;

            let content = description ? `<p>${description}</p>` : '';
            if (lines.length) {
                content += `<br><b>Mensagens WhatsApp - ${label} (${current})</b><br><pre style="font-size:12px;line-height:1.5">${lines.join('\n')}</pre>`;
            }
            if (!content) content = `Chamado aberto via WhatsApp Simples - ${label} (${current})`;

            const input = { name: title, content, urgency, impact, priority, type: 1, status: 1 };
            if (categoryId) input.itilcategories_id = categoryId;

            const r = await fetch(`${GLPI_URL}/glpi/apirest.php/Ticket`, {
                method: 'POST',
                headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session, 'Content-Type': 'application/json' },
                body: JSON.stringify({ input })
            });
            const result = await r.json();

            if (result.id) {
                if (requesterId) {
                    await fetch(`${GLPI_URL}/glpi/apirest.php/Ticket_User`, {
                        method: 'POST',
                        headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ input: { tickets_id: result.id, users_id: requesterId, type: 1 } })
                    });
                }

                // Upload de midias selecionadas
                let uploadCount = 0;
                for (const i of idxs) {
                    const m = msgs[i];
                    if (!isMedia(m.text)) continue;
                    const info = getMediaInfo(m.text);
                    if (info && info.fname) {
                        const docId = await uploadMediaToGlpi(session, info.fname, info.name, result.id);
                        if (docId) uploadCount++;
                    }
                }

                const extra = uploadCount ? ` + ${uploadCount} arquivo(s) anexado(s)` : '';
                $('wnt-result').innerHTML = `<div class="wapp-ticket-found"><i class="ti ti-circle-check"></i> Chamado <strong>#${result.id}</strong> aberto com sucesso!${extra}</div>`;
                btn.innerHTML = '<i class="ti ti-check"></i> Aberto!';
                setTimeout(closeNewTicketModal, 2000);
            } else {
                throw new Error('Erro ao abrir chamado');
            }
        } catch(e) {
            $('wnt-result').innerHTML = '<span class="wapp-ticket-notfound">Erro ao abrir chamado. Tente novamente.</span>';
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-check"></i> Abrir Chamado';
        }
    }

    // -- Modal Contatos -----------------------------------------------------
    let allContacts = [];

    function createContactsModal() {
        if ($('wapp-contacts-modal')) return;
        const m = document.createElement('div');
        m.id = 'wapp-contacts-modal';
        m.innerHTML = `
            <div id="wapp-contacts-box">
                <div id="wapp-contacts-header">
                    <span><i class="ti ti-address-book"></i> Agenda de Contatos</span>
                    <button id="wapp-contacts-close"><i class="ti ti-x"></i></button>
                </div>
                <div id="wapp-contacts-search-wrap">
                    <input type="text" id="wapp-contacts-search" placeholder="Buscar por nome ou numero...">
                </div>
                <div id="wapp-contacts-list"><div class="wapp-contacts-loading"><i class="ti ti-loader"></i> Carregando...</div></div>
            </div>
        `;
        document.body.appendChild(m);
        $('wapp-contacts-close').onclick = closeContactsModal;
        $('wapp-contacts-modal').onclick = e => { if (e.target === $('wapp-contacts-modal')) closeContactsModal(); };
        $('wapp-contacts-search').oninput = () => {
            renderContacts($('wapp-contacts-search').value.trim().toLowerCase());
        };
    }

    async function openContactsModal() {
        createContactsModal();
        $('wapp-contacts-modal').style.display = 'flex';
        $('wapp-contacts-search').value = '';
        $('wapp-contacts-list').innerHTML = '<div class="wapp-contacts-loading"><i class="ti ti-loader"></i> Carregando...</div>';
        await loadContacts();
        renderContacts('');
        setTimeout(() => $('wapp-contacts-search').focus(), 100);
    }

    function closeContactsModal() {
        const m = $('wapp-contacts-modal');
        if (m) m.style.display = 'none';
    }

    async function loadContacts() {
        const list = $('wapp-contacts-list');
        try {
            const session = await glpiSession();
            const rUsers = await fetch(`${GLPI_URL}/glpi/apirest.php/User?range=0-2000`, {
                headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session, 'Content-Type': 'application/json' }
            });
            const dataUsers = await rUsers.json();
            const users = Array.isArray(dataUsers) ? dataUsers : [];
            const userContacts = users
                .filter(u => u.mobile !== null && u.mobile !== undefined && String(u.mobile).trim() !== '')
                .map(u => ({
                    name:   `${u.firstname || ''} ${u.realname || ''}`.trim() || u.name || 'Sem nome',
                    login:  u.name || '',
                    mobile: String(u.mobile).replace(/\D/g, ''),
                    raw:    String(u.mobile).trim(),
                    kind:   'usuario',
                }))
                .filter(c => c.mobile.length >= 8);

            const rContacts = await fetch(`${GLPI_URL}/glpi/apirest.php/Contact?range=0-2000`, {
                headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session, 'Content-Type': 'application/json' }
            });
            const dataContacts = await rContacts.json();
            const contacts = Array.isArray(dataContacts) ? dataContacts : [];
            const glpiContacts = contacts
                .filter(c => {
                    const mob = c.mobile || c.phone_mobile || c.cell_phone || '';
                    return String(mob).trim() !== '';
                })
                .map(c => {
                    const mobRaw = String(c.mobile || c.phone_mobile || c.cell_phone || '').trim();
                    return {
                        name:   `${c.firstname || ''} ${c.name || ''}`.trim() || 'Sem nome',
                        login:  '',
                        mobile: mobRaw.replace(/\D/g, ''),
                        raw:    mobRaw,
                        kind:   'contato',
                    };
                })
                .filter(c => c.mobile.length >= 8);

            allContacts = [...userContacts, ...glpiContacts]
                .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));

            if (allContacts.length === 0 && list) {
                list.innerHTML = '<div class="wapp-contacts-empty">Nenhum contato com celular cadastrado</div>';
            }
        } catch(e) {
            if (list) list.innerHTML = '<div class="wapp-contacts-empty">Erro ao carregar contatos</div>';
        }
    }

    function renderContacts(filter) {
        const list = $('wapp-contacts-list');
        if (!list) return;
        const filtered = filter
            ? allContacts.filter(c =>
                c.name.toLowerCase().includes(filter) ||
                c.mobile.includes(filter) ||
                c.login.toLowerCase().includes(filter)
              )
            : allContacts;
        if (filtered.length === 0) {
            list.innerHTML = '<div class="wapp-contacts-empty">Nenhum contato encontrado</div>';
            return;
        }
        list.innerHTML = '';
        filtered.forEach(c => {
            const d = document.createElement('div');
            d.className = 'wapp-contact-item';
            const badgeClass = c.kind === 'usuario' ? 'wapp-badge-usuario' : 'wapp-badge-contato';
            const badgeLabel = c.kind === 'usuario' ? 'Usuario' : 'Contato';
            d.innerHTML = `
                <div class="wapp-contact-avatar"><i class="ti ti-user"></i></div>
                <div class="wapp-contact-info">
                    <div class="wapp-contact-name">
                        <span class="wapp-type-badge ${badgeClass}">${badgeLabel}</span>${c.name}
                    </div>
                    <div class="wapp-contact-number">${c.raw}</div>
                </div>
                <button class="wapp-contact-start" data-number="${c.mobile}">
                    <i class="ti ti-message"></i>
                </button>
            `;
            d.querySelector('.wapp-contact-start').onclick = () => {
                closeContactsModal();
                contactNames[c.mobile] = c.name;
                if (!chats[c.mobile]) chats[c.mobile] = [];
                openChat(c.mobile);
            };
            list.appendChild(d);
        });
    }

    // -- Fila de Pendentes --------------------------------------------------
    let pendingPollTimer = null;

    function updatePendingBadge() {
        const btn   = $('wapp-pending-btn');
        const badge = $('wapp-pending-badge');
        if (!btn || !badge) return;
        const count = Object.keys(pending).length;
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    function openPendingModal() {
        let modal = $('wapp-pending-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'wapp-pending-modal';
            modal.innerHTML = `
                <div id="wapp-pending-box">
                    <div id="wapp-pending-header">
                        <span><i class="ti ti-inbox"></i> Conversas Pendentes</span>
                        <button id="wapp-pending-close"><i class="ti ti-x"></i></button>
                    </div>
                    <div id="wapp-pending-list"></div>
                </div>
            `;
            document.body.appendChild(modal);
            $('wapp-pending-close').onclick = () => { modal.style.display = 'none'; };
            modal.onclick = e => { if (e.target === modal) modal.style.display = 'none'; };
        }
        renderPendingList();
        modal.style.display = 'flex';
    }

    function renderPendingList() {
        const list = $('wapp-pending-list');
        if (!list) return;
        const nums = Object.keys(pending);
        if (nums.length === 0) {
            list.innerHTML = '<div class="wapp-pending-empty"><i class="ti ti-check"></i> Nenhuma conversa pendente</div>';
            return;
        }
        list.innerHTML = '';
        nums.forEach(num => {
            const p     = pending[num];
            const label = contactNames[num] || num;
            const prev  = msgPreview(p.lastMsg);
            const time  = new Date(p.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const d     = document.createElement('div');
            d.className = 'wapp-pending-item';
            d.innerHTML = `
                <div class="wapp-pending-avatar"><i class="ti ti-user"></i></div>
                <div class="wapp-pending-info">
                    <div class="wapp-pending-name">${label}</div>
                    <div class="wapp-pending-preview">${prev}</div>
                </div>
                <div class="wapp-pending-meta">
                    <span class="wapp-pending-time">${time}</span>
                    <span class="wapp-pending-count">${p.count}</span>
                </div>
            `;
            d.onclick = () => {
                // Move da fila pendente para o chat ativo
                if (!chats[num]) chats[num] = [];
                delete pending[num];
                updatePendingBadge();
                $('wapp-pending-modal').style.display = 'none';
                openChat(num);
            };
            list.appendChild(d);
        });
    }

    // Poll global para conversas pendentes (numeros que nao estao no sidebar)
    async function pollPending() {
        try {
            const r = await fetch(`${SERVER}/api/conversations`, { headers });
            const convs = await r.json();
            if (!Array.isArray(convs)) return;
            convs.forEach(conv => {
                const num = conv.number || conv.id;
                if (!num) return;
                // Se ja esta no sidebar, ignora
                if (chats[num] !== undefined) return;
                // Se tem mensagens nao lidas do cliente
                if (conv.unread && conv.unread > 0) {
                    pending[num] = {
                        lastMsg:   conv.lastMessage || '',
                        timestamp: conv.lastTimestamp || Date.now(),
                        count:     conv.unread,
                    };
                }
            });
            updatePendingBadge();
        } catch(e) {}
    }

    // -- Chat ---------------------------------------------------------------
    function renderSidebar() {
        const el = $('wapp-open-chats');
        el.innerHTML = '';
        Object.keys(chats).forEach(num => {
            const d = document.createElement('div');
            d.className = 'wapp-chat-item' + (num === current ? ' active' : '');
            const label = contactNames[num] || num;
            const badge = unread[num] ? `<span class="wapp-unread-badge">${unread[num]}</span>` : '';
            d.innerHTML = `
                <i class="ti ti-user"></i>
                <span class="wapp-chat-item-label">${label}</span>
                ${badge}
                <button class="wapp-chat-item-clear" title="Limpar conversa"><i class="ti ti-trash"></i></button>
                <button class="wapp-chat-item-close" title="Fechar conversa"><i class="ti ti-x"></i></button>
            `;
            d.querySelector('.wapp-chat-item-label').onclick = () => openChat(num);
            d.querySelector('i.ti-user').onclick = () => openChat(num);
            d.querySelector('.wapp-chat-item-clear').onclick = e => { e.stopPropagation(); clearChat(num); };
            d.querySelector('.wapp-chat-item-close').onclick = e => { e.stopPropagation(); removeChat(num); };
            el.appendChild(d);
        });
    }

    function clearChat(num) {
        const label = contactNames[num] || num;
        if (!confirm(`Limpar toda a conversa com "${label}"?\n\nEsta acao nao pode ser desfeita.`)) return;
        chats[num]     = [];
        clearedAt[num] = Date.now();
        lastTs[num]    = clearedAt[num];
        unread[num]    = 0;
        if (current === num) renderMessages();
        renderSidebar();
    }

    function removeChat(num) {
        if (current === num) {
            current = null;
            $('wapp-chat-area').style.display   = 'none';
            $('wapp-empty-state').style.display = 'flex';
            clearInterval(pollTimer);
        }
        delete chats[num];
        delete lastTs[num];
        delete unread[num];
        delete clearedAt[num];
        renderSidebar();
    }

    function renderMessages() {
        if (!current) return;
        const el = $('wapp-messages');
        el.innerHTML = '';
        (chats[current] || []).forEach(m => {
            const d = document.createElement('div');
            d.className = 'wapp-msg ' + (m.fromMe ? 'me' : 'them');
            const t      = new Date(m.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const body   = renderMsgContent(m.text);
            const sender = m.fromMe ? `<div class="wapp-msg-sender me">${GLPI_USER_NAME}</div>` : '';
            d.innerHTML = `${sender}${body}<div class="wapp-msg-time">${t}</div>`;
            el.appendChild(d);
        });
        el.scrollTop = el.scrollHeight;
    }

    function openChat(number) {
        current = number;
        unread[number] = 0;
        if (!chats[number]) chats[number] = [];
        $('wapp-chat-title').textContent = contactNames[number] || number;
        $('wapp-empty-state').style.display = 'none';
        $('wapp-chat-area').style.display   = 'flex';
        renderSidebar();
        renderMessages();
        startPoll();
    }

    function closeChat() {
        current = null;
        $('wapp-chat-area').style.display   = 'none';
        $('wapp-empty-state').style.display = 'flex';
        clearInterval(pollTimer);
        renderSidebar();
    }

    async function poll() {
        if (!current) return;
        const since = lastTs[current] || 0;
        try {
            const r = await fetch(`${SERVER}/api/messages?number=${current}&since=${since}`, { headers });
            const msgs = await r.json();
            if (Array.isArray(msgs) && msgs.length) {
                const cutoff  = clearedAt[current] || 0;
                const newMsgs = msgs.filter(m => m.timestamp > cutoff);
                if (newMsgs.length) {
                    chats[current] = [...(chats[current] || []), ...newMsgs];
                    lastTs[current] = newMsgs[newMsgs.length - 1].timestamp;
                    renderMessages();
                } else {
                    lastTs[current] = msgs[msgs.length - 1].timestamp;
                }
            }
        } catch(e) {}

        for (const num of Object.keys(chats)) {
            if (num === current) continue;
            const since2 = lastTs[num] || 0;
            try {
                const r2 = await fetch(`${SERVER}/api/messages?number=${num}&since=${since2}`, { headers });
                const msgs2 = await r2.json();
                if (Array.isArray(msgs2) && msgs2.length) {
                    const cutoff2  = clearedAt[num] || 0;
                    const newMsgs2 = msgs2.filter(m => m.timestamp > cutoff2);
                    if (newMsgs2.length) {
                        const incoming = newMsgs2.filter(m => !m.fromMe);
                        if (incoming.length) {
                            unread[num] = (unread[num] || 0) + incoming.length;
                            renderSidebar();
                        }
                        chats[num] = [...(chats[num] || []), ...newMsgs2];
                        lastTs[num] = newMsgs2[newMsgs2.length - 1].timestamp;
                    } else {
                        lastTs[num] = msgs2[msgs2.length - 1].timestamp;
                    }
                }
            } catch(e) {}
        }
    }

    function startPoll() {
        clearInterval(pollTimer);
        poll();
        pollTimer = setInterval(poll, 2000);
        // Poll de pendentes a cada 5s
        if (!pendingPollTimer) {
            pollPending();
            pendingPollTimer = setInterval(pollPending, 5000);
        }
    }

    async function sendMessage() {
        const text = $('wapp-msg-input').value.trim();
        if (!text || !current) return;
        $('wapp-msg-input').value = '';
        const now = Date.now();
        // Salva localmente sem prefixo (tela limpa)
        chats[current].push({ text, fromMe: true, timestamp: now });
        lastTs[current] = now;
        renderMessages();
        try {
            // Envia ao cliente COM o nome prefixado
            const textWithName = `*[${GLPI_USER_NAME}]*\n${text}`;
            await fetch(`${SERVER}/api/send`, {
                method: 'POST',
                headers,
                body: JSON.stringify({ number: current, text: textWithName }),
            });
        } catch(e) {}
    }

    // -- Eventos ------------------------------------------------------------
    $('wapp-start-btn').onclick = () => {
        const num = $('wapp-number-input').value.replace(/\D/g, '');
        if (!num) return;
        $('wapp-number-input').value = '';
        if (!chats[num]) chats[num] = [];
        openChat(num);
    };

    $('wapp-number-input').onkeydown = e => { if (e.key === 'Enter') $('wapp-start-btn').click(); };
    $('wapp-close-chat').onclick     = closeChat;
    $('wapp-send-btn').onclick       = sendMessage;
    $('wapp-msg-input').onkeydown    = e => { if (e.key === 'Enter') sendMessage(); };
    $('wapp-save-btn').onclick       = openSaveModal;
    $('wapp-contacts-btn').onclick   = openContactsModal;
    $('wapp-new-ticket-btn').onclick = openNewTicketModal;
    $('wapp-pending-btn').onclick    = openPendingModal;

    // Inicia poll de pendentes imediatamente
    pollPending();
    pendingPollTimer = setInterval(pollPending, 5000);

    // Carrega contatos do GLPI ao iniciar para vincular nomes aos numeros
    (async () => {
        try {
            const session = await glpiSession();
            const rUsers = await fetch(`${GLPI_URL}/glpi/apirest.php/User?range=0-2000`, {
                headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session, 'Content-Type': 'application/json' }
            });
            const users = await rUsers.json();
            if (Array.isArray(users)) {
                users.filter(u => u.mobile && String(u.mobile).trim()).forEach(u => {
                    const num  = String(u.mobile).replace(/\D/g, '');
                    const name = `${u.firstname || ''} ${u.realname || ''}`.trim() || u.name;
                    if (num.length >= 8 && name) contactNames[num] = name;
                });
            }
            const rContacts = await fetch(`${GLPI_URL}/glpi/apirest.php/Contact?range=0-2000`, {
                headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session, 'Content-Type': 'application/json' }
            });
            const contacts = await rContacts.json();
            if (Array.isArray(contacts)) {
                contacts.forEach(c => {
                    const mob = c.mobile || c.phone_mobile || c.cell_phone || '';
                    const num = String(mob).replace(/\D/g, '');
                    const name = `${c.firstname || ''} ${c.name || ''}`.trim();
                    if (num.length >= 8 && name) contactNames[num] = name;
                });
            }
            renderSidebar();
            updatePendingBadge();
        } catch(e) {}
    })();
})();