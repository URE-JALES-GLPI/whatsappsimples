import makeWASocket, {
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion
} from '@whiskeysockets/baileys';
import pino from 'pino';
import readline from 'readline';
import qrcode from 'qrcode-terminal';

const SESSION_DIR = './auth_info';
let sock = null;
let currentChat = null;
let rl = null;
let inputMode = 'menu'; // 'menu' | 'chat'

function clearLine() {
  process.stdout.write('\r\x1b[K');
}

function printMsg(from, text, isMe) {
  const tag = isMe ? '\x1b[32m[VOCÊ]\x1b[0m' : `\x1b[36m[${from}]\x1b[0m`;
  clearLine();
  console.log(`${tag} ${text}`);
  reprompt();
}

function reprompt() {
  if (inputMode === 'menu') {
    rl.setPrompt('\nDigite o número (ex: 5511999999999): ');
  } else {
    rl.setPrompt('\x1b[33m[MSG ou :sair]\x1b[0m > ');
  }
  rl.prompt(true);
}

async function connectToWhatsApp() {
  const { state, saveCreds } = await useMultiFileAuthState(SESSION_DIR);
  const { version } = await fetchLatestBaileysVersion();

  sock = makeWASocket({
    version,
    auth: state,
    logger: pino({ level: 'silent' }),
  });

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
    if (qr) {
      console.clear();
      console.log('Escaneie o QR code abaixo com o WhatsApp:\n');
      qrcode.generate(qr, { small: true });
    }
    if (connection === 'open') {
      console.clear();
      console.log('\x1b[32m? WhatsApp conectado!\x1b[0m\n');
      startMenu();
    } else if (connection === 'close') {
      const code = lastDisconnect?.error?.output?.statusCode;
      const shouldReconnect = code !== DisconnectReason.loggedOut;
      console.log('\x1b[31mDesconectado.\x1b[0m Reconectando:', shouldReconnect);
      if (shouldReconnect) connectToWhatsApp();
    }
  });

  sock.ev.on('messages.upsert', ({ messages, type }) => {
    if (type !== 'notify') return;
    for (const msg of messages) {
      if (!msg.message) continue;
      const from = msg.key.remoteJid;
      const isMe = msg.key.fromMe;
      const text =
        msg.message.conversation ||
        msg.message.extendedTextMessage?.text ||
        '[mídia]';

      const fromNumber = from.replace('@s.whatsapp.net', '');
      if (currentChat && fromNumber === currentChat) {
        printMsg(fromNumber, text, isMe);
      }
    }
  });
}

function startMenu() {
  rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
    terminal: true,
  });

  inputMode = 'menu';
  console.log('Digite o número para iniciar conversa (com DDI, ex: 5511999999999)');
  console.log('Ctrl+C para sair\n');
  rl.setPrompt('Número: ');
  rl.prompt();

  rl.on('line', async (line) => {
    line = line.trim();
    if (!line) { reprompt(); return; }

    if (inputMode === 'menu') {
      const jid = `${line}@s.whatsapp.net`;
      currentChat = line;
      inputMode = 'chat';
      console.clear();
      console.log(`\x1b[35m── Conversa com ${line} ──\x1b[0m`);
      console.log('\x1b[90mDigite :sair para trocar de número\x1b[0m\n');
      reprompt();

    } else if (inputMode === 'chat') {
      if (line === ':sair') {
        currentChat = null;
        inputMode = 'menu';
        console.clear();
        console.log('\x1b[32m✔ WhatsApp conectado!\x1b[0m\n');
        console.log('Digite o número para iniciar conversa:');
        reprompt();
        return;
      }
      try {
        const jid = `${currentChat}@s.whatsapp.net`;
        await sock.sendMessage(jid, { text: line });
        printMsg(currentChat, line, true);
      } catch (e) {
        console.error('\x1b[31mErro ao enviar:\x1b[0m', e.message);
        reprompt();
      }
    }
  });

  rl.on('close', () => {
    console.log('\nSaindo...');
    process.exit(0);
  });
}

connectToWhatsApp();
