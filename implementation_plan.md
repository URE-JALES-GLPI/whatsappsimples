# Migração para Evolution API v2.2.1

Este plano detalha o processo de atualização da sua infraestrutura da Evolution API (da versão `1.8.2` para a `v2.2.1`). O objetivo principal desta migração é resolver o bug estrutural do motor Baileys que impede o envio de mensagens para identificadores criptografados da Meta (`@lid`), que foi corrigido na arquitetura da versão 2.

> [!WARNING]
> **Por que v2.2.1 e não a mais recente?**
> A partir da versão `v2.4.0+`, a Evolution API passou a exigir ativação de licença (mesmo gratuita) e migrações de banco de dados mais complexas. A versão `v2.2.1` é amplamente considerada a versão mais estável da V2, traz todas as correções para o `@lid` e funciona plug-and-play sem burocracias de licenciamento.

## User Review Required

> [!IMPORTANT]
> A Evolution API v2 foi reescrita e agora exige um banco de dados **PostgreSQL** e um sistema de cache **Redis** para funcionar de forma escalável e sem corrupção de sessões (o que ocorria muito na v1 com arquivos locais). 
> **Aprovação necessária:** O novo `docker-compose.yml` irá adicionar 2 novos containers (Postgres e Redis). Isso consumirá um pouco mais de memória RAM da sua VM Docker (recomendado ter pelo menos 2GB de RAM livre). Você concorda em prosseguirmos com essa arquitetura?

## Open Questions

1. Há algum histórico de mensagens ou sessões ativas na instância `atendimento` atual que você não pode perder sob hipótese alguma? (Na transição para v2, as sessões do WhatsApp geralmente precisam ser reconectadas lendo o QR Code novamente).

---

## Proposed Changes

### 1. Atualização da Infraestrutura Docker (VM Docker)

A estrutura atual será substituída para suportar o ecossistema da v2.

#### [MODIFY] [docker-compose.yml](file:///c:/Users/marco.masson/Desktop/Masson/UREGLPI/whatsappsimples/docker/docker-compose.yml)
- **Alterar imagem:** De `evoapicloud/evolution-api:v1.8.2` para `atendai/evolution-api:v2.2.1`.
- **Adicionar Serviços:** `evolution-postgres` (PostgreSQL 15) e `evolution-redis` (Redis).
- **Variáveis de Ambiente:** Adicionar as strings de conexão `DATABASE_CONNECTION_URI` e `CACHE_REDIS_URI`. 
- **Persistência:** Criar volumes para os dados do Postgres para não perder os contatos.

### 2. Adaptação do Código PHP (VM GLPI)

A V2 alterou o formato do pacote (JSON) que o Webhook envia para o GLPI. Precisamos atualizar o nosso "tradutor" para não quebrar a recepção de mensagens.

#### [MODIFY] [EvolutionApiService.php](file:///c:/Users/marco.masson/Desktop/Masson/UREGLPI/whatsappsimples/src/Service/EvolutionApiService.php)
- **Envio de Mídia:** A V2 alterou o payload de envio de arquivos, exigindo validações diferentes para `mediaMessage`.
- **Webhook Set:** A rota para registrar o Webhook mudou na v2 (antes `/webhook/set`, agora possui parâmetros globais diferentes, embora a v2 mantenha retrocompatibilidade parcial, revisaremos a rota exata).

#### [MODIFY] [WebhookController.php](file:///c:/Users/marco.masson/Desktop/Masson/UREGLPI/whatsappsimples/src/Controller/WebhookController.php)
- **Parse do Payload:** A V2 envia os eventos de mensagens dentro de um envelope maior. A leitura passará de `$payload['data']['message']` (v1) para a estrutura exata da v2.

#### [MODIFY] [IncomingMessageDTO.php](file:///c:/Users/marco.masson/Desktop/Masson/UREGLPI/whatsappsimples/src/DTO/IncomingMessageDTO.php)
- Adaptar o DTO para suportar a nova hierarquia de chaves JSON da V2.

---

## Verification Plan

### Testes Manuais (Passo a Passo)
1. Faremos o deploy do novo `docker-compose.yml`.
2. Você precisará escanear o QR Code novamente para conectar o WhatsApp na V2.
3. Simular o envio de um arquivo/mensagem a partir do GLPI para o número interno.
4. **O Teste de Ouro:** Mandar uma mensagem de um contato "Privado" (Via Anúncio/Cloud API), receber no GLPI e tentar **responder**. Se a mensagem sair do GLPI e chegar no WhatsApp do contato oculto, a missão estará cumprida!
