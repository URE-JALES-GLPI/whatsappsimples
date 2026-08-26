# 📘 Plugin WhatsAppSimples (URE Omnichannel) para GLPI 11

Plugin Omnichannel nativo para **GLPI 11** integrado à **EvolutionAPI (v1.8.2)**.

---

## 🚀 Funcionalidades Principais

* **Interface Inspirada na Plataforma Digisac:** Barra superior `URE Omnichannel`, avatares com selo do WhatsApp, temas de mensagens e busca em tempo real.
* **Ciclo de Vida de Atendimento:** Separação por **Chats (Meus Atendimentos)**, **Fila (Aguardando)** e **Contatos (Lista Única e Histórico Unificado)**.
* **Encerrar e Reabrir:** Botão **"Encerrar Atendimento"** que arquiva a conversa. Ao receber nova mensagem do cliente, o chamado é reaberto na Fila preservando 100% do histórico prévio.
* **Identificação de Remetente:** Exibição do **Nome do Técnico Logado no GLPI** (ex: *Marco Masson*, *Aryan Ferrari*, *Leonardo Poiatti*) nos balões verdes e do nome do cliente nos balões brancos.
* **Ferramentas Funcionais de Mensagem:** Upload de arquivos/mídia (`+`), seletor interativo de emojis (`😊`), respostas rápidas padronizadas (`📄`) e notas internas (`⚡`).

---

## 📂 Estrutura de Arquivos e Controladores Symfony (GLPI 11)

```
whatsappsimples/
├── src/
│   ├── Controller/
│   │   ├── ChatPageController.php      # Rota /Chat (Interface URE Omnichannel)
│   │   ├── ConfigPageController.php    # Rota /front/config.php (Configurações / QR Code)
│   │   ├── GetChatsController.php      # Rota /ajax/chats (Listagem e Deduplicação)
│   │   ├── GetMessagesController.php   # Rota /ajax/messages (Histórico de Mensagens)
│   │   ├── SendMessageController.php   # Rota /ajax/send (Envio de Mensagens e Mídia)
│   │   ├── CloseChatController.php     # Rota /ajax/close (Encerramento de Atendimento)
│   │   └── WebhookController.php       # Rota /webhook (Webhook Público EvolutionAPI)
│   └── Service/
│       └── EvolutionApiService.php     # Serviço cURL com EvolutionAPI v1.8.2
├── hook.php                           # Schemas SQL e Instalação
├── setup.php                          # Registro de Hooks e Rotas do GLPI
└── README.md
```

---

## 🗄️ Tabelas no Banco de Dados GLPI

1. **`glpi_plugin_whatsappsimples_configs`**: Guardas de credenciais (`server_url`, `api_token`, `instance_name`).
2. **`glpi_plugin_whatsappsimples_chats`**: Atendimentos e contatos (`phone_number`, `contact_name`, `users_id`, `status`).
3. **`glpi_plugin_whatsappsimples_messages`**: Registro de mensagens e arquivos (`chats_id`, `users_id`, `sender_type`, `message_text`, `media_url`).

---

## ⚡ Comandos para Atualização e Deploy (Servidor GLPI)

```bash
cd /var/www/html/glpi/plugins/whatsappsimples
git pull origin marco
php /var/www/html/glpi/bin/console glpi:cache:clear --allow-superuser
```