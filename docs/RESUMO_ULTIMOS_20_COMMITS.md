# Resumo dos Últimos 20 Commits — whatsappsimples

Período: 10/09/2026 a 13/10/2026. Os 20 commits mais recentes do `git log`, agrupados por objetivo.

## Visão geral

| Tema | Objetivo | Commits |
|---|---|---|
| 1. Mídias | Armazenar mídia em disco e fazer streaming, evitando crashes do navegador e limite do MySQL | `54ae925`, `7f6887e`, `a2a4bca`, `30bfa8f` |
| 2. Fluxo de atendimento | Corrigir o comportamento de chats encerrados e da aba Contatos | `935007b`, `92cac19`, `7bbab17` |
| 3. Contatos (vCard) | Interpretar contatos compartilhados e exibi-los como cartão | `628203f`, `d37a330` |
| 4. Tempo real | Atualizar a tela automaticamente via Mercure/SSE | `22e69c7`, `ff56420` |
| 5. Fila assíncrona | Webhook rápido, com processamento via Redis e worker | `3197cfc`, `be28de3`, `1c16af8` |
| 6. Compatibilidade GLPI 11/12 | Rodar o plugin nas duas versões do GLPI | `8968a74`, `56d3a0a` |
| 7. Merges | Integração das branches `marco` e `main` | `137e722`, `92913db`, `be2a953`, `97784d1` |

---

## 1. Mídias: armazenamento e streaming

**Problema:** mídias salvas no banco (base64) estouravam o limite do MySQL e travavam o navegador. Áudios e vídeos não tocavam direito.

- `54ae925` (10/09): reverteu a gravação em arquivos físicos, que falhou por falta de permissão. Adicionou `sleep` de 4s para tratar uma *race condition* e criou `fix_db.php` para corrigir o banco.
- `7f6887e` (16/09): moveu o armazenamento para `GLPI_PLUGIN_DOC_DIR` e criou o proxy de stream em `front/media.php`. Também removeu fixtures de teste do `pino` que tinham sido versionadas por engano.
- `a2a4bca` (16/09): refatoração completa (Fases 0 a 3).
  - Novo `src/Service/MediaStorage.php`, com validação de integridade.
  - `front/media.php` passou a atender requisições `206 Range`, necessárias para seek em áudio e vídeo.
  - Ajustes em `hook.php`, `setup.php` e `MessageDispatcherService`.
- `30bfa8f` (16/09): corrigiu um erro fatal de sintaxe no `IncomingMessageDTO` que derrubava o webhook. Reescreveu a renderização do DOM para ser **incremental**, o que evita *layout shift* e scroll forçado.

## 2. Fluxo de atendimento e aba Contatos

**Problema:** chats encerrados continuavam com "dono", e o técnico ficava bloqueado de responder pela aba Contatos.

- `935007b` (17/09): removeu o bloqueio de envio na aba Contatos quando o técnico já é o dono do atendimento.
- `92cac19` (17/09): removeu o dono falso de chats encerrados. Enviar mensagem para um histórico fechado passou a **criar um novo atendimento**. Alterou `ChatPageController`, `GetChatsController` e `SendMessageController`.
- `7bbab17` (17/09): limpa a div de mensagens ao trocar de contato, evitando acúmulo visual causado pela renderização incremental.

## 3. Compartilhamento de contatos (vCard)

- `628203f` (18/09): `IncomingMessageDTO` passou a interpretar vCard. O `ChatPageController` renderiza um cartão interativo.
- `d37a330` (18/09): aplicou `trim` na string vCard, porque espaços em branco quebravam a criação do componente.

## 4. Tempo real (Mercure / SSE)

**Problema:** as abas Fila e Chats não atualizavam sozinhas.

- `22e69c7` (28/09): criou `MercurePublisherService` e fez o webhook publicar eventos no Mercure Hub.
- `ff56420` (28/09): o frontend (`public/js/whatsappsimples.js`) passou a assinar o stream SSE e a atualizar a tela em tempo real.

## 5. Fila assíncrona com Redis

**Problema:** o webhook processava tudo de forma síncrona, o que é lento e frágil.

- `3197cfc` (28/09): o `WebhookController` agora só enfileira a mensagem no Redis (redução de ~40 linhas). O novo `bin/worker.php` consome a fila e processa.
- `be28de3` (28/09): carrega o autoloader no worker, que roda em CLI fora do GLPI.
- `1c16af8` (28/09): timeout infinito no socket do Redis, para o worker não cair por inatividade.

**Operação:** o worker precisa estar rodando (`php bin/worker.php`). Se ele cair, as mensagens ficam na fila e não aparecem no GLPI.

## 6. Compatibilidade GLPI 11 e 12

- `8968a74` (13/10): adicionou a camada `src/compat.php` (101 linhas) com helpers de `web_dir` e CSRF. O `rightname` passou a ser string. Ajustou `setup.php`, `config`, `profile`, `validation`, `Menu` e `ConfigPageController`.
- `56d3a0a` (13/10): corrigiu bytes latin-1 em `config.class.php` e `validation.class.php`, porque uma edição anterior tinha normalizado os acentos e corrompido o encoding.

**Contexto:** a VM `.27` foi recriada com GLPI 12. Existem as branches `glpi11`, `glpi12`, `lab`, `main` e `marco`.

## 7. Merges

- `137e722` e `92913db` (28/09): merges de `marco` e `main` feitos por Leonardo.
- `be2a953` (PR #5, "Webhook messages") e `97784d1` (PR #6, "Redis queue implemented"): PRs de `marco` para `main`.

---

## Pendências e riscos

- Garantir que o `worker.php` seja iniciado após reinício do servidor (por exemplo com systemd ou supervisor).
- Redis e Mercure precisam estar acessíveis na VM nova.
- Validar qual branch usar no GLPI 12: `glpi12` ou `marco`.
- O Webhook da Evolution API deve apontar para o IP `.27`.
- Bugs listados anteriormente, que ainda precisam de validação:
  - Mensagem enviada do celular após encerrar o atendimento cai em Chat, e não em Fila.
  - A mensagem "0" enviada do celular não chega.
  - Falta mensagem automática ao encerrar ou transferir o atendimento.
  - A aba Chats deve mostrar só os atendimentos do próprio usuário.

> Nota: o resumo se baseia nas mensagens e nos arquivos alterados de cada commit. Não li o diff linha a linha.
