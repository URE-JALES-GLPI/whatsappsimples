import makeWASocket, {
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    jidNormalizedUser,
    downloadMediaMessage
} from '@whiskeysockets/baileys';

import qrcode from 'qrcode-terminal';
import http from 'http';
import fs from 'fs';
import path from 'path';

const PORT      = 3001;
const API_TOKEN = 'glpi_whatsapp_token_2025';

const GLPI_BASE       = 'http://10.180.152.110/glpi';
const GLPI_APP_TOKEN  = 'Qy0ocv7BYvG363PY6O4CVSJvlHGAWI34t9Ex93BH';
const GLPI_USER_TOKEN = 'RYRtYjeUqLMLzlm3DqhMYxDzC64j97DQz79Oxmq9';

const SESSION_DIR = './auth_info';
const MEDIA_DIR   = './media';

if (!fs.existsSync(MEDIA_DIR)) fs.mkdirSync(MEDIA_DIR, { recursive: true });

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

function getMediaType(msg) {
    const m = msg.message;
    if (!m) return null;
    if (m.imageMessage)               return 'image';
    if (m.audioMessage)               return 'audio';
    if (m.documentMessage)            return 'document';
    if (m.documentWithCaptionMessage) return 'document';
    if (m.videoMessage)               return 'video';
    return null;
}

function getMediaExt(msg) {
    const m = msg.message;
    if (!m) return 'bin';
    if (m.imageMessage)               return m.imageMessage.mimetype?.split('/')[1]?.split(';')[0] || 'jpg';
    if (m.audioMessage)               return m.audioMessage.mimetype?.includes('ogg') ? 'ogg' : 'mp3';
    if (m.documentMessage)            return m.documentMessage.fileName?.split('.').pop() || 'bin';
    if (m.documentWithCaptionMessage) return m.documentWithCaptionMessage.message?.documentMessage?.fileName?.split('.').pop() || 'bin';
    return 'bin';
}

function getMediaName(msg) {
    const m = msg.message;
    if (!m) return 'arquivo';
    if (m.documentMessage?.fileName)            return m.documentMessage.fileName;
    if (m.documentWithCaptionMessage?.message?.documentMessage?.fileName)
        return m.documentWithCaptionMessage.message.documentMessage.fileName;
    if (m.imageMessage)  return 'imagem';
    if (m.audioMessage)  return 'audio';
    return 'arquivo';
}

async function saveMedia(msg) {
    const mediaType = getMediaType(msg);
    if (!mediaType) return null;

    try {
        const buffer = await downloadMediaMessage(msg, 'buffer', {});
        const ext    = getMediaExt(msg);
        const name   = getMediaName(msg);
        const ts     = Date.now();
        const fname  = `${ts}_${name.replace(/[^a-zA-Z0-9._-]/g, '_')}.${ext}`.replace(/\.+$/, '');
        const fpath  = path.join(MEDIA_DIR, fname);
        fs.writeFileSync(fpath, buffer);
        return { fname, mediaType, name };
    } catch(e) {
        console.error('Erro ao salvar midia:', e.message);
        return null;
    }
}

function extractText(msg, mediaInfo) {
    const m = msg.message;
    if (!m) return '[mensagem vazia]';
    if (m.conversation)               return m.conversation;
    if (m.extendedTextMessage?.text)  return m.extendedTextMessage.text;

    if (mediaInfo) {
        return `__MEDIA__${JSON.stringify(mediaInfo)}`;
    }

    if (m.stickerMessage)       return '[Figurinha]';
    if (m.locationMessage)      return `[Localizacao: ${m.locationMessage.degreesLatitude}, ${m.locationMessage.degreesLongitude}]`;
    if (m.contactMessage)       return `[Contato: ${m.contactMessage.displayName}]`;
    if (m.contactsArrayMessage) return '[Contatos]';
    if (m.reactionMessage)      return `[Reacao: ${m.reactionMessage.text}]`;
    if (m.pollCreationMessage)  return `[Enquete: ${m.pollCreationMessage.name}]`;
    if (m.ephemeralMessage)     return extractText({ message: m.ephemeralMessage.message }, null);
    if (m.viewOnceMessage)      return '[Mensagem de visualizacao unica]';
    if (m.viewOnceMessageV2)    return '[Mensagem de visualizacao unica]';
    return '[midia]';
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

function mergelidMsgs(number) {
    const lid = numberToLid[number];
    if (lid && store[lid] && store[lid].length > 0) {
        store[number] = [...(store[number] || []), ...store[lid]];
        store[number].sort((a, b) => a.timestamp - b.timestamp);
        delete store[lid];
        console.log('Migrado LID', lid, '->', number);
    }
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
                lidToNumber[lid] = num;
                numberToLid[num] = lid;
                mergelidMsgs(num);
            }
        }
    });

    sock.ev.on('contacts.update', (contacts) => {
        for (const c of contacts) {
            if (c.id && c.lid) {
                const num = c.id.replace('@s.whatsapp.net', '');
                const lid = c.lid.replace('@lid', '');
                lidToNumber[lid] = num;
                numberToLid[num] = lid;
                mergelidMsgs(num);
            }
        }
    });

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;

        for (const msg of messages) {
            if (!msg.message) continue;

            const jid    = msg.key.remoteJid;
            const fromMe = msg.key.fromMe;
            const ts     = Number(msg.messageTimestamp) * 1000;

            let num = resolveNumFromJid(jid);

            // Baixa midia se houver
            let mediaInfo = null;
            if (getMediaType(msg)) {
                mediaInfo = await saveMedia(msg);
            }

            const text = extractText(msg, mediaInfo);

            if (!num && jid.includes('@lid')) {
                const lid = jid.replace('@lid', '');
                console.log('LID sem mapeamento:', lid, '| texto:', mediaInfo ? '[midia]' : text);
                addMsg(lid, text, fromMe, ts);
                continue;
            }

            if (!num) continue;

            console.log(fromMe ? 'ENVIADA' : 'RECEBIDA', num, '|', mediaInfo ? `[${mediaInfo.mediaType}]` : text);
            addMsg(num, text, fromMe, ts);

            if (!fromMe && validationPending[num]) handleValidationReply(num, text);
        }
    });
}

function checkToken(req) {
    return req.headers['x-api-token'] === API_TOKEN;
}

const MIME_TYPES = {
    jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp',
    pdf: 'application/pdf', ogg: 'audio/ogg', mp3: 'audio/mpeg', mp4: 'video/mp4',
    doc: 'application/msword', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    xls: 'application/vnd.ms-excel', xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    zip: 'application/zip', txt: 'text/plain',
};

const server = http.createServer((req, res) => {
    const url = new URL(req.url, `http://localhost`);
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, x-api-token');

    if (req.method === 'OPTIONS') { res.writeHead(204); res.end(); return; }

    // Endpoint publico para servir midia (sem token para facilitar download no browser)
    if (req.method === 'GET' && url.pathname.startsWith('/api/media/')) {
        const fname = path.basename(url.pathname.replace('/api/media/', ''));
        const fpath = path.join(MEDIA_DIR, fname);
        if (!fs.existsSync(fpath)) {
            res.writeHead(404);
            res.end('Not found');
            return;
        }
        const ext  = fname.split('.').pop().toLowerCase();
        const mime = MIME_TYPES[ext] || 'application/octet-stream';
        res.setHeader('Content-Type', mime);
        res.setHeader('Content-Disposition', `inline; filename="${fname}"`);
        res.writeHead(200);
        fs.createReadStream(fpath).pipe(res);
        return;
    }

    res.setHeader('Content-Type', 'application/json');

    if (!checkToken(req)) { res.writeHead(401); res.end(JSON.stringify({ error: 'unauthorized' })); return; }

    if (req.method === 'GET' && url.pathname === '/api/status') {
        res.writeHead(200);
        res.end(JSON.stringify({ connected: sock?.user != null, user: sock?.user }));
        return;
    }

    if (req.method === 'GET' && url.pathname === '/api/messages') {
        const num   = url.searchParams.get('number') || '';
        const since = parseInt(url.searchParams.get('since') || '0');
        mergelidMsgs(num);
        const msgs = (store[num] || []).filter(m => m.timestamp > since);
        res.writeHead(200);
        res.end(JSON.stringify(msgs));
        return;
    }

    if (req.method === 'GET' && url.pathname === '/api/conversations') {
    // Retorna todas as conversas com detalhes para a fila de pendentes
    const convs = Object.entries(store).map(([number, msgs]) => {
        const lastMsg = msgs[msgs.length - 1];
        const unread  = msgs.filter(m => !m.fromMe).length > 0
            ? msgs.filter(m => !m.fromMe && m.timestamp > (Date.now() - 24 * 60 * 60 * 1000)).length
            : 0;
        return {
            number,
            lastMessage:   lastMsg ? lastMsg.text : '',
            lastTimestamp: lastMsg ? lastMsg.timestamp : 0,
            unread,
        };
    });
    res.writeHead(200);
    res.end(JSON.stringify(convs));
    return;

    }

    if (req.method === 'POST' && url.pathname === '/api/send') {
        let body = '';
        req.on('data', d => body += d);
        req.on('end', async () => {
            try {
                const body_parsed = JSON.parse(body);
                const { number, text } = body_parsed;

                try {
                    const result = await sock.onWhatsApp(`${number}@s.whatsapp.net`);
                    if (result && result[0]?.lid) {
                        const lid = result[0].lid.replace('@lid', '');
                        lidToNumber[lid] = number;
                        numberToLid[number] = lid;
                        mergelidMsgs(number);
                    }
                } catch(e) {}

                const sent = await sock.sendMessage(`${number}@s.whatsapp.net`, { text });

                if (sent?.key?.remoteJid?.includes('@lid')) {
                    const lid = sent.key.remoteJid.replace('@lid', '');
                    lidToNumber[lid] = number;
                    numberToLid[number] = lid;
                    mergelidMsgs(number);
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