# Plano: Esteira de Desenvolvimento com Agentes de IA (opencode + VSCode)

> **Status:** proposto em 2026-10-16; revisado em 2026-10-19 (respostas do Marco + viabilidade do ai-memory).
> **Nada deste plano foi executado nem commitado.** Aguardando aprovação do Marco antes do Bloco A.
>
> **Origem:** sessão de brainstorming sobre "o que incrementar no VSCode / na esteira de
> desenvolvimento com agentes de IA", à luz do documento *Otimização de IA com Akita*
> (engenharia agêntica: AGENTS.md, skills, oráculo determinístico, kaizen).

---

## 1. Por que

- O "cru" do plugin já funciona: envio/recebimento de mensagens para **contatos** (sem grupos).
  O trabalho daqui pra frente é **refino e robustez nos detalhes** — é isso que deixa o
  programa utilizável de verdade.
- O fluxo atual (contexto máximo em um prompt + várias rodadas de refino até ficar "decente")
  tem 3 custos estruturais:
  1. Sem memória/instruções persistentes → cada sessão re-explica stack, convenções e armadilhas.
  2. Sem verificação automática → **o humano é o oráculo** (valida clicando).
  3. Validação só pós-deploy (VM GLPI) → cada erro custa o ciclo inteiro: editar → push → pull → clicar.
- Princípios adotados do método (doc Akita):
  - Harness + **oráculo determinístico** (teste/lint headless) é o que destrava a autonomia do agente.
  - `AGENTS.md` enxuto como camada de contexto; skills com revelação progressiva e catálogo pequeno
    ("skill encostada é dívida").
  - **Teste de regressão antes da correção**; 1 item = 1 commit com teste manual descrito.
  - **Kaizen:** toda falha/retrabalho vira teste, regra no AGENTS.md ou ajuste de skill.
  - Não forçar multi-agente; não adotar camada/SaaS/memória externa antes de sentir a dor.

## 2. Diagnóstico (2026-10-16)

| # | Achado | Evidência |
|---|---|---|
| 1 | **Sem oráculo**: nenhum PHPUnit/PHPStan/lint/CI no repo; validação 100% manual pós-deploy | Sem `composer.json`/`phpunit.xml`/`phpstan.neon`; backlog manda "teste manual curto descrito no commit" |
| 2 | **Nada roda na máquina de dev**: Windows sem PHP/composer/docker (só Node v24) | `php -v`, `composer`, `docker` não encontrados; WSL2 não instalado |
| 3 | **Sem camada de instruções**: não existe AGENTS.md, .vscode, .editorconfig | conferido na raiz |
| 4 | **Higiene do repo**: sem `.gitignore`; **4.564 de 5.026 arquivos versionados são `node_modules` (91%)**; `auth_info/` versionado com `creds.json` e chaves de sessão (fase Baileys); `media/` com 289 arquivos reais de terceiros (fotos/editais — LGPD) | `git ls-files`; ambos desde o 1º commit `421e281` |
| 5 | **Monolitos**: `src/Controller/ChatPageController.php` com 2.209 linhas | fadiga de refino concentrada aqui |

## 3. Blocos de execução

### Bloco A — Higiene do repositório (primeiro; pequeno e urgente)

- Criar `.gitignore` na raiz: `node_modules/`, `vendor/`, `auth_info/`, `media/`, `*.log`,
  `.env` (exceto `*.env.example`), `whatsappterminal/node_modules/`.
- `git rm --cached -r` em `node_modules`, `auth_info` e `media` (raiz e
  `whatsappterminal/node_modules`) — os arquivos **permanecem no disco**, saem só do versionamento.
- **⚠️ Ordem operacional na VM GLPI:** antes de dar `git pull` no commit que destrackeia a mídia,
  decidir o destino da pasta `media/` **do repositório** na VM (revisão 2026-10-16, achado 6).
  Essa pasta **não** é a mídia do runtime — `front/media.php` serve `GLPI_PLUGIN_DOC_DIR/whatsappsimples/media`.
  A `media/` versionada só é lida pela **página legada** (`front/index.php` + `front/ajax/upload_media.php`,
  alimentada pelo antigo `server.js`).
  - Página legada morta → descontinuar e **purgar os 289 arquivos também do disco** (LGPD).
  - Página legada em uso → `cp -a media ~/media-backup-vm` antes do pull e restaurar depois.
- Confirmar que a sessão do `auth_info/` está morta (a infra migrou para Evolution API);
  se ainda estiver ativa em algum lugar, desconectar o dispositivo no WhatsApp.
- **Decisão futura (não fazer agora):** limpeza de histórico (`git filter-repo`/BFG) para tirar
  creds/mídia/node_modules dos commits antigos — exige coordenar a equipe; preparar procedimento depois.
- 1 commit local na `dev`, sem push.

### Bloco A2 — Segredos versionados (achado da revisão, 2026-10-16; **requer decisão**)

Tokens literais confirmados (mesmo par GLPI App-Token/User-Token) em: `server.js`,
`public/js/whatsappsimples.js` (**linhas 7–8 — arquivo servido ao navegador!**),
`front/ajax/upload_media.php`, e cópia em `media/1775578982431_server.js.js`.
Além disso: tokens da Evolution em `test_evo.php`/`dump_contacts.php`/`ConfigPageController` e
credenciais de DB em `docker/docker-compose.yml`. O critério "repo sem credenciais" do Bloco A
**não** é atingido só com o destrack de `auth_info/`.
Ações: inventariar → **rotacionar junto ao GLPI** → mover para config/ambiente (fora do código).
Marco acredita que os tokens ainda são válidos (2026-10-19); a rotação segue necessária. Se a página legada
for descontinuada (pend. 3), os arquivos que usam os tokens saem junto — e a rotação fica mais simples.

### Bloco B — Camada de instruções

- **`AGENTS.md` na raiz** (~1 página; economia de contexto é parte do método):
  - Visão e stack: plugin GLPI 11/12 ("URE Omnichannel"), Evolution API (**alvo v2.2.1**, atual v1.8.2),
    Mercure/SSE, MySQL, PHP 8.1+; camada de compatibilidade `src/compat.php`.
  - Verificação: comandos do Bloco C (`composer check`, `composer test`).
  - **Invariantes que nunca podem quebrar** (lista inicial, validar contra o código na execução):
    1. Rotas de webhook são stateless (`registerPluginStatelessPath` + `Firewall::STRATEGY_NO_CHECK`).
    2. `csrf_compliant` só para GLPI < 12.
    3. Prefixo `55` padrão no campo telefone do novo chat; modal aberto via JS não apaga o `55`.
    4. `date_mod` garantido na criação do chat; chats ativos prevalecem na query.
    5. Parser de quotes com `strpos`/substring (sem regex sensível a quebra de linha).
    6. Assinatura injetada **depois** do tratamento da citação.
    7. Mídia salva em disco (limite `max_allowed_packet` do MySQL); `MediaStorage` é o dono disso.
    8. Encerrar fecha todos os ativos do mesmo telefone; polling não reabre histórico encerrado.
  - Convenções de trabalho: 1 item do backlog = 1 commit na `dev` com teste manual descrito;
    **teste de regressão antes da correção**; nada de refactor "de passagem"; `docs/` é a memória
    do projeto (o BACKLOG é a fila oficial).
- **`~/.config/opencode/AGENTS.md` global** (fora do repo): preferências pessoais do Marco
  (idioma pt-BR, estilo de commit etc.) — definir juntos na execução.
- **`UREGLPI\AGENTS_RULES.md` (fora do repo) vira legado** (decisão de 2026-10-19): o novo `AGENTS.md` do repo
  é a fonte canônica — seguir o padrão Akita/estado da arte, não o documento antigo. Adicionar um aviso no
  arquivo antigo apontando para o `AGENTS.md` do repo (ação manual, fora do versionamento).

### Bloco C — Oráculo local (o pulo do gato)

- Instalar **PHP 8.3 portátil** em `%USERPROFILE%\tools\php` + **Composer** (phar) — **sem admin**
  (winget v1.29 e choco 2.7 existem na máquina como alternativas). **Pinar 8.3.x — a VM GLPI roda PHP 8.3.6.**
- `composer.json` novo na raiz, dev deps: `phpunit/phpunit` (^11), `friendsofphp/php-cs-fixer`
  (+ ruleset GLPI se disponível no Packagist). `phpstan/phpstan` fica para a fase 2.
- Scripts:
  - `composer check` — `php -l` em todos os `.php` de `src/ front/ ajax/ inc/ public/ bin/`
    + `hook.php setup.php` (fase 2: + phpstan).
  - `composer test` — PHPUnit (`vendor/bin/phpunit`).
  - `composer fix` — php-cs-fixer nos arquivos tocados (estilo GLPI).
- **Passo 0:** definir o webhook canônico antes de escrever qualquer teste de parser — há três receivers no
  repo aprovados pela revisão (`front/webhook.php` = fluxo síncrono registrado pelo QR; `public/webhook.php` =
  cópia obsoleta; `WebhookController` = rota removida, enfileira p/ Redis) e o `bin/worker.php` pode estar sem
  alimentação (pendências nº 7 e 8). Testar código morto seria retrabalho.
- `phpunit.xml` + `tests/` — alvos iniciais revisados (2026-10-16): **só funções genuinamente puras**
  (sem `$DB`, sem Redis, sem rede):
  - DTO de mensagem (campos de texto) e utilitários de telefone: `resolvePhoneNumber`,
    `formatNumberForSending`, `isValidBrazilianNumber` (prefixo `55`, `@lid`, `@c.us`);
  - parser de citação/quote **quando extraível como função pura** (hoje vive inline em método estático).
- **Fixtures:** payloads reais **exportados do log da VM pelo Marco**; anonimizados antes do commit
  (nomes/telefones trocados, dígitos de `@lid` alterados; estrutura e casos-limite preservados: `'0'`, mídia, caption).
- **Segurança dos testes:** o oráculo roda **offline por padrão** — nenhum caminho que alcance Redis/fila
  compartilhada, banco do GLPI ou Mercure (a fila é consumida pela VM; um teste mal comportado injetaria
  mensagens reais). Fixtures e fakes apenas.
- **Regra permanente:** todo bug novo ganha **primeiro um teste que falha**
  (ex.: "mensagem `0` não chega", "pós-encerrado cai em Chat em vez de Fila"), depois a correção.
  Quando não existir costura, a correção pode incluir **extração mínima congelada** (mover o trecho inline
  para função pura, sem mudar comportamento), aprovada caso a caso no diff.
- Fase 2 (não agora): stubs/fakes das classes GLPI (`ChatLifecycleService`, `WebhookController`, payloads de
  envio/mídia do `EvolutionApiService`) + PHPStan.

### Bloco D — Config do opencode no repo (`.opencode/`)

- `opencode.jsonc` (com `$schema`):
  - `permissions` (ordem importa: a última regra que casa vence):
    - allow: `php -l *`, `composer check`, `composer test`, `composer fix`,
      `vendor/bin/phpunit *`, `git status *`, `git diff *`, `git log *`, `git show *`;
    - ask: `shell *` (default), `git push *` (push sempre revisado por humano);
    - `read` de `auth_info/*` e `*.env` → deny/ask (credenciais e sessão WhatsApp);
    - `external_directory` → ask (agente fica dentro do repo).
  - `watcher.ignore`: `node_modules/**`, `auth_info/**`, `media/**`.
  - `formatter`: php-cs-fixer para `.php` (habilitar após o Bloco C).
  - (opcional, decidir na execução) `instructions`: incluir
    `docs/BACKLOG_MARCO_REIMPLEMENTAR.md` — injeta a fila em toda sessão; pode valer, pesa um pouco.
- `commands/`:
  - `/verificar` — roda `composer check` + `composer test`, resume falhas e propõe correções;
  - `/sessao` — handoff: resume feito/pendente, atualiza o BACKLOG e registra o próximo passo.
- `skills/` (máximo 2):
  - `item-backlog` — procedimento padrão: escolher item → teste que falha → implementar mínimo →
    verificar → atualizar docs → commit com teste manual descrito;
  - `kaizen` — após falha/retrabalho, cristalizar a lição em: teste de regressão, regra no
    AGENTS.md ou ajuste de skill.
- O agente **nunca** faz push; nunca toca em produção/VM.

### Bloco E — VSCode

- `.vscode/tasks.json`: tarefas "Verificar tudo", "Rodar testes", "Formatar" (chamam os scripts composer).
- `.vscode/settings.json`: `php.validate.executablePath` → PHP portátil (erro de sintaxe ao vivo);
  `files.exclude`/`search.exclude` para `node_modules`, `auth_info`, `media`, `whatsappterminal/node_modules`.
- `.vscode/extensions.json` (recomendações): `bmewburn.vscode-intelephense-client`,
  `EditorConfig.EditorConfig`.
- `.editorconfig` na raiz (padrão GLPI: 4 espaços, UTF-8; **conferir EOL atual do repo antes de impor**).
- Sem extensão especial do opencode no VSCode: fluxo principal continua opencode no terminal +
  estas tarefas para verificação rápida. (OpenCode expõe ACP para clientes compatíveis, ex. Zed;
  não é caminho necessário aqui.)

### Bloco F — ai-memory do Akita (piloto paralelo; não toca código do plugin)

- **Viabilidade confirmada na fonte oficial (README, 2026-10-19; versão atual **3.1.1**, release de 17/10/2026):**
  - Instalação **nativa no Windows sem admin**: `irm https://github.com/akitaonrails/ai-memory/releases/latest/download/install.ps1 | iex`
    + `ai-memory setup` (descompacta em `%LOCALAPPDATA%\Programs\ai-memory`, adiciona ao PATH, inicia no logon).
  - Matriz de suporte: **Windows nativo = Experimental**; **Windows via WSL2 = Supported**; Docker Desktop também
    serve como alternativa. **OpenCode V1/V2 = suporte oficial** (`--agent opencode`; aliases `opencode2`).
  - **Antigravity CLI = suporte oficial**; o **Antigravity IDE** não está na matriz → plugável via MCP genérico
    (testar; único ponto em aberto).
  - Padrão: **zero chamadas de LLM**; embeddings locais (`all-MiniLM-L6-v2`, ~87 MB); wiki em **Markdown
    versionado em Git (OKF)** + SQLite como índice derivado; servidor local em loopback.
  - Desinstalação limpa e reversível: `ai-memory uninstall --apply`.
- **Status 2026-10-19 — bloqueado pelo Defender ASR do TI (rota nativa):** o `install.ps1` baixou o zip
  (`ai-memory-windows-x86_64.zip`, v3.1.1), mas execução **e leitura** do `ai-memory.exe` são negadas:
  evento Defender **1121**, regra ASR `01443614-cd74-433a-b99e-2ecdc07bfc25` ("Block executable files from
  running unless they meet a prevalence, age, or trusted list criterion", ação Bloquear via política;
  `AttackSurfaceReductionRules_Actions = 1`). Binário Rust não assinado, baixa prevalência ⇒ bloqueado;
  máquina gerenciada (SCCM/Intune) — admin local não sobrepõe política.
  - **Pronto até aqui:** binário+hooks+docs extraídos em `%LOCALAPPDATA%\Programs\ai-memory` (inertes até a
    liberação) e **`.ai-memory.toml` criado no repo** (workspace `ure` / project `whatsappsimples`; ignora
    `auth_info/`, `media/`, `.env`, `node_modules`, arquivos com tokens). Nada foi conectado ao opencode.
  - **Rota A (recomendada):** TI adicionar exclusão da regra ASR (Intune/SCCM — `AttackSurfaceReductionOnlyExclusions`
    ou exclusão por regra) para `%LOCALAPPDATA%\Programs\ai-memory\ai-memory.exe` (ou a pasta). Dossiê pronto:
    ferramenta open-source (MIT, 10,9k★), release **v3.1.1**, sem instalador/serviço, escopo de usuário,
    checksum SHA-256 publicado no release.
  - **Rota B:** servidor ai-memory em container no VM `.29` (Docker) + opencode via MCP HTTP + artefatos
    TypeScript gerados no Linux (nenhum executável no Windows) — requer aval da infra e token/TLS na LAN.
    - **Custo financeiro: R$ 0** — OSS; modo zero-LLM padrão (sem chamadas pagas; embeddings locais ~90 MB no 1º boot).
    - **Infra no `.29`:** 1 container + 1 volume nomeado, porta **só loopback** (`-p 127.0.0.1:49374:49374`) com
      acesso por **túnel SSH** (perfil no MobaXterm) ⇒ sem exposição na LAN, sem regra de firewall, tráfego cifrado.
      `--restart unless-stopped`; pegada pequena (Rust+SQLite, dezenas–centenas de MB de RAM, CPU em rajadas ao
      indexar). Dados ficam na infra da empresa (LGPD ok) e as exclusões do `.ai-memory.toml` seguem valendo.
    - **Cliente Windows sem o `.exe` bloqueado (verificado):** o fixture oficial de teste executa o plugin V2 gerado
      e comprova captura por **HTTP puro** (`fetch`); servidor fora do ar ⇒ *spool* local (`hook-spool/`, idempotente,
      drenado pelo próprio plugin). Artefatos gerados por containers one-shot no `.29`
      (`install-mcp --client opencode2` → snippet p/ `mcp.servers`; `setup-agent --agent opencode --opencode-dialect v2`
      → plugin TS), trazidos por SFTP e instalados localmente com backup.
    - **Montagem (uma vez): ~45–90 min** — comandos na VM executados pelo Marco (regra: agente não toca VM); parte
      local (plugin/MCP com backup, dir de spool, túnel) por este agente, com revisão. **Recorrente:** túnel ligado
      ao trabalhar (sem túnel não se perde nada: spool) e `docker pull` ocasional.
    - **Limites honestos:** captura de texto do assistente (opt-in, desligada por padrão) e os hooks do
      **Antigravity CLI** seguem dependendo do binário nativo ⇒ pendentes da rota A; para o opencode, cobertura 100%.
  - **Rota C (não recomendada):** WSL2 — esbarra no opencode nativo do Windows.
  - **Decisão 2026-10-19 (Marco): manter o `opencode-mem` por ora e parar o ai-memory.** Rotas A/B ficam
    documentadas como plano de retomada; gatilhos para reabrir: necessidade real de memória cross-harness
    (ex.: Antigravity), dor concreta do `opencode-mem`, ou liberação do TI (rota A). Restos inertes no lugar:
    pasta `%LOCALAPPDATA%\Programs\ai-memory` (bloqueada pelo ASR) e `.ai-memory.toml` untracked no repo.
- **Passos do piloto:**
  1. `ai-memory setup --plan` (mostra o plano, não altera nada) → depois `--agent opencode`.
  2. **Antes da 1ª sessão no repo:** configurar `[capture] ignore_paths` para `auth_info/**`, `media/**`,
     `*.env` e arquivos de credenciais — segredos/LGPD não podem virar página de memória.
  3. `cd whatsappsimples && ai-memory bootstrap` (adota o histórico existente do projeto).
  4. Validar: sessão nova pergunta "o que você lembra deste projeto?"; `ai-memory status`; Web UI opcional.
  5. Depois: tentar **Antigravity IDE via MCP** e/ou **Gemini CLI** (`--agent gemini-cli`).
- **Convivência com o opencode-mem:** opencode-mem continua (perfil/sessão no opencode); o ai-memory agrega
  o handoff entre harnesses (opencode ↔ Antigravity) e a wiki auditável. Se houver ruído de dupla injeção,
  pausar um dos dois após 1–2 semanas de observação.
- **Critérios de sucesso (2 semanas):** recall útil em ≥ metade das sessões; zero segredo/PII nas páginas da wiki;
  custo zero de LLM; não atrapalhar o fluxo.
- **Fora de escopo agora:** servidor de time no `.29` (multi-user/TLS) — só quando houver time usando.
- Este bloco é **independente** dos demais: se o piloto não convencer, `ai-memory uninstall --apply` e segue a vida.

## 4. Execução e segurança

- Ordem: **A → B → C → D → E** (o **Bloco F** — piloto do ai-memory — roda em paralelo e não toca no código
  do plugin). Cada bloco = 1 commit local na `dev`; **sem push** — revisão com `git diff` antes de qualquer subida.
- Nesta fase **nada altera a lógica do plugin**: só arquivos novos (`AGENTS.md`, `composer.json`,
  `tests/`, `.opencode/`, `.vscode/`, `.editorconfig`) + destrack no Bloco A (a exceção futura de
  extração mínima está descrita no Bloco C).
- O oráculo roda **offline**: nenhum teste fala com Redis/fila compartilhada, GLPI/Mercure ou rede.
- Push, deploy e VM continuam 100% manuais/humanos.

## 5. Critérios de sucesso

- `composer check` roda localmente em segundos; o agente se autocorrige antes de o Marco olhar.
- Todo bug corrigido deixa um teste de regressão (ex.: "mensagem 0", "pós-encerrado vai pra Fila").
- Sessão nova do opencode não re-explica stack/invariantes (estão no `AGENTS.md`).
- Repo sem credenciais/mídia/node_modules versionados; pulls e clones leves.

## 6. Decisões pendentes (responder na retomada)

1. Aprovar instalação do PHP portátil + Composer na pasta de usuário (sem admin)? (winget/choco ok?)
2. `auth_info/` pode ser destrackeado? A sessão Baileys antiga está morta/desconectada?
3. Página legada ainda é usada? (comando no `docs/ROTEIRO_CHECAGEM_VM.md`) → morta: purgar `media/`; viva:
   backup antes do pull. O backup em si é feito pelo Marco no deploy.
4. Limpeza de histórico (filter-repo/BFG): agora, depois ou nunca?
5. Replicar este pacote nos outros plugins URE (o plano vira template)?
6. **Segredos (Bloco A2):** Marco acredita que os tokens ainda são válidos (confirmar); dúvida sobre limitação
   da Evolution. **Rotação continua necessária** — definir responsável na execução.
7. **Webhook canônico:** *pendente de checagem na VM* — `docs/ROTEIRO_CHECAGEM_VM.md` (VMs: GLPI `.27`,
   Docker/EvoAPI/Redis `.29`).
8. **Evolution por ambiente:** *pendente* — `docker ps` no `.29` (roteiro).
9. ~~`UREGLPI\AGENTS_RULES.md` ainda é autoritativo?~~ **DECIDIDO (2026-10-19):** o novo `AGENTS.md` do repo
   é canônico; seguir o padrão Akita/estado da arte. O arquivo antigo vira legado (aviso apontando para o repo).
10. **Fixtures:** *pendente* — localizar/exportar o log da VM `.27` (roteiro). **Não colar log no chat**
    (PII): baixar por SFTP/MobaXterm e passar o caminho local; a anonimização é feita aqui.
11. ~~PHP da VM GLPI~~ **DECIDIDO:** **PHP 8.3.6** (CLI, `root@glpi`). O oráculo local pina PHP 8.3.
12. **`implementation_plan.md`:** *pendente* — confirmar com `docker ps` no `.29` (roteiro).

## 7. Fora de escopo agora (consciente)

- Multi-agente forçado / orquestração planner+executor.
- Memória de agentes: agora no **Bloco F (piloto ai-memory)**; RAG/bancos vetoriais pesados seguem fora.
- CI no GitHub Actions (candidato à fase 2 — o `composer check` já é o passo).
- WSL2 + ai-jail (o sandbox do doc Akita exige WSL2; hoje não instalado).
- MCP servers (GitHub etc.) — só quando doer.
- Limpeza de histórico (item 4 das pendências).

## 8. Adendo A — Memória de agentes (2026-10-16)

- **Correção de atribuição:** o projeto de memória do Fábio Akita é o **ai-memory**
  (github.com/akitaonrails/ai-memory; aimemory.sh) — Rust/MIT, Markdown+Git (padrão OKF), SQLite como índice
  derivado, embeddings locais, handoff tipado entre fornecedores de agente. **"SuperMemory" não é dele**
  (supermemory.ai é SaaS de terceiros). Um texto gerado por IA colado nessa data misturou ai-memory +
  supermemory + o servidor oficial `@modelcontextprotocol/server-memory` (mantido pela Anthropic).
- **Estado verificado:** o opencode do Marco **já tem memória ativa** — plugin `opencode-mem@latest`
  (config `~/.config/opencode/opencode-mem.jsonc`): banco vetorial local (Turso/SQLite em `~/.opencode-mem/data`),
  embeddings ONNX locais (all-MiniLM-L6-v2), auto-captura, perfil de usuário, Web UI em http://localhost:4747,
  escopo por projeto. Não há nada a "implementar antes" para o opencode; o que falta é conteúdo durável
  (AGENTS.md, fila, kaizen) — Blocos B/C.
- **Fase 2/3 (quando doer):** trocar embedding para `Xenova/nomic-embed-text-v1` (melhor PT-BR; exige re-embed);
  memória compartilhada opencode ↔ Antigravity via **MCP** (opencode: `mcp.servers`; Antigravity:
  Settings → Customizations → Installed MCP Servers).
- **ai-memory (verificado em 2026-10-19):** instalador **nativo Windows sem admin** (`install.ps1`) — Windows
  nativo = Experimental, WSL2 = Supported, Docker Desktop também. **OpenCode e Antigravity CLI suportados**;
  Antigravity IDE via MCP genérico (testar). Piloto no **Bloco F**.
- **Soberania de dados:** manter memória local (o setup atual já é); evitar SaaS de memória para dados de clientes (LGPD).

## 9. Adendo B — Revisão builder (2026-10-16)

Revisão independente do plano contra o repo: 8 achados; os que dava para fechar eu já incorporei ao corpo
do plano (Blocos A/A2/C) e os que dependem de resposta viraram as pendências nº 6–12.

| # | Achado | Encaixe |
|---|---|---|
| 1 | Três receivers de webhook; `WebhookController` está sem rota e enfileira p/ Redis (`10.180.152.29:6380`); `bin/worker.php` pode estar sem alimentação | Passo 0 do Bloco C; pendências 7/8 |
| 2 | Migração p/ Evolution v2 parece já executada (commit 27/08; imagem `:latest` com fixes de 2.3.7) — "atual v1.8.2" pode estar defasado | Pendências 8/12 |
| 3 | Alvos "puros" do Bloco C não eram todos puros (`$DB`, Redis, cURL, `MediaStorage`+`sleep`) | Corrigido no Bloco C |
| 4 | Segredos versionados (mesmo par GLPI App/User-Token em `server.js`, `public/js/whatsappsimples.js` **linhas 7–8 — servido ao navegador**, `upload_media.php`, cópia em `media/`; DB no compose) | Novo Bloco A2; pendência 6 |
| 5 | `UREGLPI\AGENTS_RULES.md` (acima do repo) foi o arquivo usado p/ orientar IAs e contradiz o código atual | Pendência 9 |
| 6 | Justificativa do backup de `media/` estava errada (runtime usa `GLPI_PLUGIN_DOC_DIR`) | Corrigido no Bloco A |
| 7 | Fixtures conteriam PII de cidadãos (logs da VM) | Anonimização definida no Bloco C; pendência 10 |
| 8 | Versão de PHP da VM desconhecida (oráculo deve pinar) | Pendência 11 |

## 10. Adendo C — Respostas do Marco (2026-10-19)

Mapa das VMs: **GLPI = 10.180.152.27** (`root@glpi`, PHP 8.3.6) e **Docker da infra = 10.180.152.29**
(Evolution API, Redis `:6380`...). O Redis do `WebhookController` aponta para o `.29` — consistente.

| Pend. | Resposta | Status |
|---|---|---|
| 6 — Segredos | Tokens provavelmente válidos; dúvida sobre a Evolution | Rotação continua necessária |
| 7 — Webhook canônico | Não sabe; checar na VM | Roteiro (`docs/ROTEIRO_CHECAGEM_VM.md`) |
| 8 — Evolution | Não sabe; checar no MobaXterm | Roteiro |
| 9 — AGENTS_RULES | **Seguir padrão Akita/estado da arte** | **Resolvido** — `AGENTS.md` do repo é canônico |
| 10 — Fixtures | Não sabe como exportar | Roteiro (log no `.27`, sem colar PII no chat) |
| 11 — PHP da VM | **PHP 8.3.6** (CLI) | **Resolvido** — oráculo pina 8.3 |
| 12 — implementation_plan | Não sabe | `docker ps` no `.29` (roteiro) |

- **ai-memory:** viável — ver **Bloco F**. Instalação nativa Windows sem admin; OpenCode suportado oficialmente.
- **Roteiro:** `docs/ROTEIRO_CHECAGEM_VM.md` (comandos copy-paste, só leitura, para MobaXterm).

## 11. Referências

- Doc base: *Otimização de IA com Akita* (pasta `Desktop\Masson\Loucuras e Validações\IA`).
- Fila oficial do projeto: `docs/BACKLOG_MARCO_REIMPLEMENTAR.md`.
- Migração Evolution API v2.2.1: `implementation_plan.md`.
- ai-memory (Akita): https://github.com/akitaonrails/ai-memory — https://aimemory.sh
- Roteiro de checagem das VMs: `docs/ROTEIRO_CHECAGEM_VM.md`.
- Regras antigas para agentes (legado): `../AGENTS_RULES.md`.
- OpenCode V2 (config, permissions, commands, skills, instructions, formatters):
  https://opencode.ai/v2/docs/config
