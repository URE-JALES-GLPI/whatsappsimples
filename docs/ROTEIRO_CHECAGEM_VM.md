# Roteiro de checagem nas VMs (MobaXterm)

> Criado em 2026-10-19. **Todos os comandos são só leitura** — nada altera as VMs.
> Objetivo: fechar as pendências 7, 8, 10 e 12 do `PLANO_ESTEIRA_IA.md`.
>
> **⚠️ Privacidade:** não cole chaves de API nem conteúdo de logs com dados de clientes no chat.
> Para os logs, baixe o arquivo via SFTP/MobaXterm e me passe o **caminho local**.

## VM .29 — Docker (Evolution API, Redis etc.)

SSH: `root@10.180.152.29`

### 1) Containers e versão da Evolution

```bash
docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}'
```

Se a imagem aparecer como `:latest`, veja a versão real pelos labels:

```bash
docker inspect --format '{{.Name}} -> {{.Config.Image}} {{index .Config.Labels "org.opencontainers.image.version"}}' $(docker ps -q)
```

### 2) Webhook configurado nas instâncias

```bash
# nomes dos containers
docker ps --format '{{.Names}}' | grep -i evolution

# quais variáveis de API key existem (sem mostrar o valor):
docker exec <CONTAINER_EVOLUTION> printenv | grep -i -E 'AUTHENTICATION|API_KEY' | sed 's/=.*/=<REDACTED>/'

# porta publicada (ajuste se não for 8080):
docker port <CONTAINER_EVOLUTION>

# liste as instâncias (use a chave localmente, NÃO cole aqui):
curl -s -H "apikey: <APIKEY>" http://127.0.0.1:8080/instance/fetchInstances | head -c 3000

# webhook de cada instância:
curl -s -H "apikey: <APIKEY>" http://127.0.0.1:8080/webhook/find/<NOME_DA_INSTANCIA>
```

**O que preciso na resposta:** a **imagem/tag da Evolution** e a **URL de webhook** que aparecer (sem a chave).

## VM .27 — GLPI (PHP 8.3.6)

SSH: `root@10.180.152.27`

### 3) O worker do plugin está rodando?

```bash
ps aux | grep -i 'bin/worker.php' | grep -v grep
systemctl list-units --type=service | grep -i -E 'whatsapp|worker' || true
crontab -l 2>/dev/null | grep -i -E 'whatsapp|worker' || true
```

Sem saída = worker não está rodando.

### 4) Localizar o log do plugin (para as fixtures)

```bash
find / -name 'whatsappsimples*.log' 2>/dev/null
```

Depois de achar o caminho:

```bash
ls -lh <CAMINHO_DO_LOG>
tail -n 5 <CAMINHO_DO_LOG>   # só para confirmar o formato; não cole aqui
```

**Baixe o arquivo via SFTP/MobaXterm** para a sua máquina Windows e me passe o caminho local.
A anonimização (nomes/telefones/`@lid`) é feita aqui antes de qualquer commit.

### 5) A página legada ainda é acessada?

```bash
# Apache:
ls /var/log/apache2/ 2>/dev/null | head
zgrep -h 'whatsappsimples/front/index.php' /var/log/apache2/access.log* 2>/dev/null | tail -n 20

# nginx (se for o caso):
zgrep -h 'whatsappsimples/front/index.php' /var/log/nginx/access.log* 2>/dev/null | tail -n 20
```

- Com acessos recentes → página legada viva: manter `media/` e fazer backup antes do pull.
- Sem saída → provavelmente morta: descontinuar e purgar `media/` (confirmar com a equipe).

### 6) Confirmar a versão do PHP do web server (bônus)

```bash
php -v
```

Já sabido: 8.3.6 na CLI — só confirmar que o web usa a mesma.

---

Quando tiver as saídas, é só colar aqui (as partes sem segredo). Com isso eu fecho as pendências 7, 8, 10 e 12.
