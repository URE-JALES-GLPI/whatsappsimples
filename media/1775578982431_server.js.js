import makeWASocket, {
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    jidNormalizedUser
} from '@whiskeysockets/baileys';

import qrcode from 'qrcode-terminal';
import http from 'http';

const PORT      = 3001;
const API_TOKEN = 'glpi_whatsapp_token_2025';

const GLPI_BASE       = 'http://10.180.152.86/glpi';
const GLPI_APP_TOKEN  = 'Qy0ocv7BYvG363PY6O4CVSJvlHGAWI34t9Ex93BH';
const GLPI_USER_TOKEN = 'RYRtYjeUqLMLzlm3DqhMYxDzC64j97DQz79Oxmq9';

const SESSION_DIR = './auth_info';

const logger = {
    level: 'silent', trace:()=>{}, debug:()=>{}, info:()=>{},
    warn:()=>{}, error:()=>{}, fatal:()=>{}, child:()=>logger,
};

process.stderr.write = (chunk, ...args) => {
    const str = typeof chunk === 'string' ? chunk : chunk.toString();
    if (str.includes('Bad MAC') || str.includes('Failed to decrypt') ||
        str.includes('Session error') || str.includes('Closing session') ||
        str.includes('Closing open session') ||
        str.includes('SessionEntry') || str.includes('_chains') ||
        str.includes('registrationId') || str.includes('currentRatchet') ||
        str.includes('ephemeralKeyPair') || str.includes('indexInfo') ||
        str.includes('baseKey') || str.includes('rootKey') ||
        str.includes('chainKey') || str.includes('Buffer')) return true;
    return process.stderr._write ? process.stderr._write(chunk, ...args) : true;
};

const store             = {};
const validationPending = {};
const numberToLid       = {};
const lidToNumber       = {};

function extractText(msg) {
    const m = msg.message;
    if (!m) return '[mensagem vazia]';
    if (m.conversation)               return m.conversation;
    if (m.extendedTextMessage?.text)  return m.extendedTextMessage.text;
    if (m.imageMessage)               return `[Imagem${m.imageMessage.caption ? ': ' + m.imageMessage.caption : ''}]`;
    if (m.videoMessage)               return `[Video${m.videoMessage.caption ? ': ' + m.videoMessage.caption : ''}]`;
    if (m.audioMessage)               return m.audioMessage.ptt ? '[Audio de voz]' : '[Audio]';
    if (m.documentMessage)            return `[Documento: ${m.documentMessage.fileName || 'arquivo'}]`;
    if (m.documentWithCaptionMessage) return `[Documento: ${m.documentWithCaptionMessage.message?.documentMessage?.fileName || 'arquivo'}]`;
    if (m.stickerMessage)             return '[Figurinha]';
    if (m.locationMessage)            return `[Localizacao: ${m.locationMessage.degreesLatitude}, ${m.locationMessage.degreesLongitude}]`;
    if (m.contactMessage)             return `[Contato: ${m.contactMessage.displayName}]`;
    if (m.contactsArrayMessage)       return '[Contatos]';
    if (m.reactionMessage)            return `[Reacao: ${m.reactionMessage.text}]`;
    if (m.pollCreationMessage)        return `[Enquete: ${m.pollCreationMessage.name}]`;
    if (m.ephemeralMessage)           return extractText({ message: m.ephemeralMessage.message });
    if (m.viewOnceMessage)            return '[Mensagem de visualizacao unica]';
    if (m.viewOnceMessageV2)          return '[Mensagem de visualizacao unica]';
    return '[midia]';
}

function mapLid(lid, number) {
    if (!lid || !number) return;
    lidToNumber[lid] = number;
    numberToLid[number] = lid;
    console.log('LID mapeado:', lid, '->', number);

    // Migra msgs salvas com LID para o numero real
    if (store[lid]) {
        if (!store[number]) store[number] = [];
        store[number] = [...store[number], ...store[lid]]
            .sort((a, b) => a.timestamp - b.timestamp);
        delete store[lid];
        console.log('Msgs migradas do LID para', number);
    }
}

function resolveNumFromJid(jid) {
    if (!jid) return null;
    if (jid.includes('@s.whatsapp.net')) return jid.replace('@s.whatsapp.net', '');
    if (jid.includes('@lid')) {
        const lid = jid.replace('@lid', '');
        return lidToNumber[lid] || null;
    }
    return null;
}

function addMsg(number, text, fromMe, timestamp) {
    if (!number) return;
    if (!store[number]) store[number] = [];
    store[number].push({ text, fromMe, timestamp });
    if (store[number].length > 500) store[number].shift();
}

async function handleValidationReply(mobile, text) {
    const pending = validationPending[mobile];
    if (!pending) return;
    if (!text.match(/^[12]$/)) return;
    const approve = text === '1';
    const status  = approve ? 3 : 4;
    try {
        const sessionRes = await fetch(`${GLPI_BASE}/apirest.php/initSession`, {
            headers: {
                'App-Token':     GLPI_APP_TOKEN,
                'Authorization': `user_token ${GLPI_USER_TOKEN}`,
                'Content-Type':  'application/json',
            }
        });
        const { session_token } = await sessionRes.json();
        const valRes = await fetch(
            `${GLPI_BASE}/apirest.php/TicketValidation?searchText[items_id]=${pending.ticket_id}&searchText[users_id_validate]=${pending.validator_id}`,
            { headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session_token, 'Content-Type': 'application/json' } }
        );
        const validations = await valRes.json();
        if (!Array.isArray(validations) || !validations.length) return;
        const valId = validations[0].id;
        await fetch(`${GLPI_BASE}/apirest.php/TicketValidation/${valId}`, {
            method: 'PUT',
            headers: { 'App-Token': GLPI_APP_TOKEN, 'Session-Token': session_token, 'Content-Type': 'application/json' },
            body: JSON.stringify({ input: { id: valId, status, comment_validation: approve ? 'Aprovado via WhatsApp' : 'Recusado via WhatsApp' } })
        });
        const reply = approve ? `Ticket #${pending.ticket_id} aprovado!` : `Ticket #${pending.ticket_id} recusado.`;
        await sock.sendMessage(jidNormalizedUser(mobile + '@s.whatsapp.net'), { text: reply });
        addMsg(mobile, reply, true, Date.now());
        delete validationPending[mobile];
    } catch(e) {
        console.error('Erro validacao:', e.message);
    }
}

let sock = null;

async function connect() {
    const { state, saveCreds } = await useMultiFileAuthState(SESSION_DIR);
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({ version, auth: state, logger });
    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
        if (qr) { console.clear(); console.log('Escaneie o QR code:\n'); qrcode.generate(qr, { small: true }); }
        if (connection === 'open') console.log('WhatsApp conectado! Porta', PORT);
        else if (connection === 'close') {
            const code = lastDisconnect?.error?.output?.statusCode;
            if (code !== DisconnectReason.loggedOut) connect();
        }
    });

    sock.ev.on('contacts.upsert', (contacts) => {
        for (const c of contacts) {
            if (c.id && c.lid) {
                const num = c.id.replace('@s.whatsapp.net', '');
                const lid = c.lid.replace('@lid', '');
                mapLid(lid, num);
            }
        }
    });

    sock.ev.on('contacts.update', (contacts) => {
        for (const c of contacts) {
            if (c.id && c.lid) {
                const num = c.id.replace('@s.whatsapp.net', '');
                const lid = c.lid.replace('@lid', '');
                mapLid(lid, num);
            }
        }
    });

    sock.ev.on('messages.upsert', ({ messages, type }) => {
        if (type !== 'notify') return;

        for (const msg of messages) {
            if (!msg.message) continue;

            const jid    = msg.key.remoteJid;
            const fromMe = msg.key.fromMe;
            const text   = extractText(msg);
            const ts     = Number(msg.messageTimestamp) * 1000;

            let num = resolveNumFromJid(jid);

            if (!num && jid.includes('@lid')) {
                const lid = jid.replace('@lid', '');
                console.log('LID sem mapeamento:', lid, '| texto:', text);
                addMsg(lid, text, fromMe, ts);
                continue;
            }

            if (!num) continue;

            console.log(fromMe ? 'ENVIADA' : 'RECEBIDA', num, '|', text);
            addMsg(num, text, fromMe, ts);

            if (!fromMe && validationPending[num]) handleValidationReply(num, text);
        }
    });
}

function checkToken(req) {
    return req.headers['x-api-token'] === API_TOKEN;
}

const server = http.createServer((req, res) => {
    const url = new URL(req.url, `http://localhost`);
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, x-api-token');
    res.setHeader('Content-Type', 'application/json');

    if (req.method === 'OPTIONS') { res.writeHead(204); res.end(); return; }
    if (!checkToken(req)) { res.writeHead(401); res.end(JSON.stringify({ error: 'unauthorized' })); return; }

    if (req.method === 'GET' && url.pathname === '/api/status') {
        res.writeHead(200);
        res.end(JSON.stringify({ connected: sock?.user != null, user: sock?.user }));
        return;
    }

    if (req.method === 'GET' && url.pathname === '/api/messages') {
        const num   = url.searchParams.get('number') || '';
        const since = parseInt(url.searchParams.get('since') || '0');

        let msgs = (store[num] || []).filter(m => m.timestamp > since);

        const lid = numberToLid[num];
        if (lid && store[lid]) {
            const lidMsgs = store[lid].filter(m => m.timestamp > since);
            msgs = [...msgs, ...lidMsgs].sort((a, b) => a.timestamp - b.timestamp);
            store[num] = [...(store[num] || []), ...store[lid]].sort((a, b) => a.timestamp - b.timestamp);
            delete store[lid];
        }

        res.writeHead(200);
        res.end(JSON.stringify(msgs));
        return;
    }

    if (req.method === 'GET' && url.pathname === '/api/conversations') {
        res.writeHead(200);
        res.end(JSON.stringify(Object.keys(store)));
        return;
    }

    if (req.method === 'POST' && url.pathname === '/api/send') {
        let body = '';
        req.on('data', d => body += d);
        req.on('end', async () => {
            try {
                const body_parsed = JSON.parse(body);
                const { number, text } = body_parsed;

                // Tenta descobrir o LID antes de enviar
                try {
                    const result = await sock.onWhatsApp(`${number}@s.whatsapp.net`);
                    if (result && result[0]?.lid) {
                        const lid = result[0].lid.replace('@lid', '');
                        mapLid(lid, number);
                    }
                } catch(e) {}

                const sent = await sock.sendMessage(`${number}@s.whatsapp.net`, { text });

                // Tenta pegar LID da resposta do sendMessage
                if (sent?.key?.remoteJid?.includes('@lid')) {
                    const lid = sent.key.remoteJid.replace('@lid', '');
                    mapLid(lid, number);
                }

                addMsg(number, text, true, Date.now());

                if (body_parsed.validation) {
                    validationPending[body_parsed.validation.mobile] = {
                        ticket_id:    body_parsed.validation.ticket_id,
                        validator_id: body_parsed.validation.validator_id,
                    };
                }

                res.writeHead(200);
                res.end(JSON.stringify({ ok: true }));
            } catch(e) {
                res.writeHead(500);
                res.end(JSON.stringify({ ok: false, error: e.message }));
            }
        });
        return;
    }

    res.writeHead(404);
    res.end(JSON.stringify({ error: 'not found' }));
});

server.listen(PORT, () => console.log(`Servidor HTTP na porta ${PORT}`));

connect();