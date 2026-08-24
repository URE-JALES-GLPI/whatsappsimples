import makeWASocket, {
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion
} from '@whiskeysockets/baileys';
import qrcode from 'qrcode-terminal';
import http from 'http';

const PORT = 3001;
const SESSION_DIR = './auth_info';

const logger = {
    level: 'silent', trace:()=>{}, debug:()=>{}, info:()=>{},
    warn:()=>{}, error:()=>{}, fatal:()=>{}, child:()=>logger,
};

const _origError = console.error.bind(console);
console.error = (...args) => {
    const msg = args[0]?.toString() ?? '';
    if (msg.includes('Bad MAC') || msg.includes('Failed to decrypt') || msg.includes('Session error') || msg.includes('Closing session')) return;
    _origError(...args);
};

// Armazena mensagens em memória: { number: [{text, fromMe, timestamp}] }
const store = {};

function addMsg(number, text, fromMe, timestamp) {
    if (!store[number]) store[number] = [];
    store[number].push({ text, fromMe, timestamp });
    if (store[number].length > 200) store[number].shift();
}

let sock = null;

async function connect() {
    const { state, saveCreds } = await useMultiFileAuthState(SESSION_DIR);
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({ version, auth: state, logger });
    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
        if (qr) {
            console.clear();
            console.log('Escaneie o QR code:\n');
            qrcode.generate(qr, { small: true });
        }
        if (connection === 'open') {
            console.log('✔ WhatsApp conectado! Servidor HTTP na porta', PORT);
        } else if (connection === 'close') {
            const code = lastDisconnect?.error?.output?.statusCode;
            if (code !== DisconnectReason.loggedOut) connect();
        }
    });

    sock.ev.on('messages.upsert', ({ messages, type }) => {
        if (type !== 'notify') return;
        for (const msg of messages) {
            if (!msg.message) continue;
            const jid  = msg.key.remoteJid;
            const num  = jid.replace('@s.whatsapp.net', '').replace('@g.us', '');
            const fromMe = msg.key.fromMe;
            const text = msg.message.conversation
                || msg.message.extendedTextMessage?.text
                || '[mídia]';
            const timestamp = Number(msg.messageTimestamp) * 1000;
            addMsg(num, text, fromMe, timestamp);
        }
    });
}

// HTTP Server
const server = http.createServer((req, res) => {
    const url = new URL(req.url, `http://localhost`);
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
    res.setHeader('Content-Type', 'application/json');

    if (req.method === 'OPTIONS') { res.writeHead(204); res.end(); return; }

    // GET /messages?number=xxx&since=ts
    if (req.method === 'GET' && url.pathname === '/messages') {
        const num   = url.searchParams.get('number') || '';
        const since = parseInt(url.searchParams.get('since') || '0');
        const msgs  = (store[num] || []).filter(m => m.timestamp > since);
        res.writeHead(200);
        res.end(JSON.stringify(msgs));
        return;
    }

    // POST /send
    if (req.method === 'POST' && url.pathname === '/send') {
        let body = '';
        req.on('data', d => body += d);
        req.on('end', async () => {
            try {
                const { number, text } = JSON.parse(body);
                const jid = `${number}@s.whatsapp.net`;
                await sock.sendMessage(jid, { text });
                const now = Date.now();
                addMsg(number, text, true, now);
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

server.listen(PORT);
connect();
