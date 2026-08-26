<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use GlpiPlugin\Whatsappsimples\Menu;
use GlpiPlugin\Whatsappsimples\Service\EvolutionApiService;
use Html;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConfigPageController extends AbstractController
{
    #[Route('/Config', name: 'whatsappsimples_config_page', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        Session::checkLoginUser();
        Session::checkRight('config', UPDATE);

        $msgSuccess = '';
        $msgError   = '';

        // Processa salvamento das configurações via POST
        if ($request->isMethod('POST')) {
            $serverUrl    = trim($request->request->getString('server_url'));
            $apiToken     = trim($request->request->getString('api_token'));
            $instanceName = trim($request->request->getString('instance_name'));

            EvolutionApiService::setConfig('server_url', $serverUrl);
            EvolutionApiService::setConfig('api_token', $apiToken);
            EvolutionApiService::setConfig('instance_name', $instanceName);

            $msgSuccess = 'Configurações salvas com sucesso!';
        }

        $serverUrl    = EvolutionApiService::getConfig('server_url');
        $apiToken     = EvolutionApiService::getConfig('api_token');
        $instanceName = EvolutionApiService::getConfig('instance_name');

        ob_start();
        Html::header('WhatsApp - Configurações', '/plugins/whatsappsimples/Config', 'config', Menu::class);

        ?>
        <style>
            .wa-cfg-container { max-width: 900px; margin: 20px auto; font-family: system-ui, -apple-system, sans-serif; display: flex; flex-direction: column; gap: 20px; }
            .wa-cfg-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
            .wa-cfg-title { font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
            .wa-cfg-sub { font-size: 0.85rem; color: #64748b; margin-bottom: 20px; }

            .wa-form-group { margin-bottom: 16px; display: flex; flex-direction: column; gap: 6px; }
            .wa-label { font-weight: 600; font-size: 0.88rem; color: #334155; }
            .wa-input { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; outline: none; }
            .wa-input:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1); }

            .wa-btn { padding: 10px 20px; background: #0d9488; color: #ffffff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
            .wa-btn:hover { background: #0f766e; }

            .wa-alert { padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 16px; }
            .wa-alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
            .wa-alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

            .wa-qr-box { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; min-height: 250px; text-align: center; }
            .wa-qr-img { width: 220px; height: 220px; border-radius: 8px; border: 1px solid #e2e8f0; margin: 12px 0; }
            .wa-status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; }
            .wa-status-badge.open { background: #dcfce7; color: #15803d; }
            .wa-status-badge.close { background: #fee2e2; color: #b91c1c; }
        </style>

        <div class="wa-cfg-container">
            <?php if ($msgSuccess): ?>
                <div class="wa-alert success"><?= htmlspecialchars($msgSuccess) ?></div>
            <?php endif; ?>

            <!-- CARD 1: FORMULÁRIO DE CREDENCIAIS EVOLUTIONAPI -->
            <div class="wa-cfg-card">
                <div class="wa-cfg-title">⚙️ Credenciais da EvolutionAPI</div>
                <div class="wa-cfg-sub">Informe as configurações do servidor EvolutionAPI rodando no seu ambiente</div>

                <form method="POST">
                    <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">
                    <div class="wa-form-group">
                        <label class="wa-label">URL do Servidor EvolutionAPI</label>
                        <input type="text" name="server_url" class="wa-input" value="<?= htmlspecialchars($serverUrl) ?>" placeholder="http://10.180.152.27:8080" required>
                    </div>

                    <div class="wa-form-group">
                        <label class="wa-label">API Key Global (Token)</label>
                        <input type="password" name="api_token" class="wa-input" value="<?= htmlspecialchars($apiToken) ?>" placeholder="Sua chave secreta global" required>
                    </div>

                    <div class="wa-form-group">
                        <label class="wa-label">Nome da Instância</label>
                        <input type="text" name="instance_name" class="wa-input" value="<?= htmlspecialchars($instanceName) ?>" placeholder="atendimento" required>
                    </div>

                    <button type="submit" class="wa-btn">Salvar Configurações</button>
                </form>
            </div>

            <!-- CARD 2: PAINEL DE CONEXÃO & QR CODE AO VIVO -->
            <div class="wa-cfg-card">
                <div class="wa-cfg-title">📱 Conexão do WhatsApp (QR Code)</div>
                <div class="wa-cfg-sub">Escaneie o QR Code abaixo com a câmera do seu WhatsApp para conectar o número</div>

                <div class="wa-qr-box" id="qr-box">
                    <div style="color:#64748b;">Carregando status da conexão...</div>
                </div>

                <div style="margin-top: 16px; text-align: center;">
                    <button class="wa-btn" onclick="checkStatusAndQrCode()">🔄 Atualizar Status / QR Code</button>
                </div>
            </div>

            <!-- CARD 3: URL DO WEBHOOK PARA A EVOLUTIONAPI -->
            <div class="wa-cfg-card">
                <div class="wa-cfg-title">🔗 URL do Webhook do GLPI</div>
                <div class="wa-cfg-sub">Cadastre a URL abaixo na sua EvolutionAPI para que o GLPI receba as mensagens do WhatsApp</div>

                <div class="wa-form-group">
                    <input type="text" class="wa-input" id="webhook-url-input" readonly value="">
                </div>
            </div>
        </div>

        <script>
            const rootDoc = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
            const webhookFullUrl = window.location.origin + rootDoc + '/plugins/whatsappsimples/webhook?token=ure_jales_evolution_token_2026';

            document.getElementById('webhook-url-input').value = webhookFullUrl;

            async function checkStatusAndQrCode() {
                const box = document.getElementById('qr-box');
                box.innerHTML = '<div style="color:#64748b;">Consultando EvolutionAPI...</div>';

                try {
                    const csrfToken = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.csrf_token) ? CFG_GLPI.csrf_token : '';
                    const res = await fetch(`${rootDoc}/plugins/whatsappsimples/ajax/qrcode`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Glpi-Csrf-Token': csrfToken
                        }
                    });
                    const data = await res.json();

                    if (data.state === 'open') {
                        box.innerHTML = `
                            <div class="wa-status-badge open">🟢 WhatsApp Conectado com Sucesso!</div>
                            <p style="margin-top:12px; color:#475569; font-size:0.9rem;">Sua instância está pronta para enviar e receber mensagens no GLPI.</p>
                        `;
                    } else if (data.base64) {
                        box.innerHTML = `
                            <div class="wa-status-badge close">🔴 Aguardando Conexão</div>
                            <p style="margin:10px 0 4px; color:#334155; font-weight:600;">Abra o WhatsApp no Celular ➔ Aparelhos Conectados ➔ Conectar Aparelho</p>
                            <img src="${data.base64}" class="wa-qr-img" alt="QR Code WhatsApp">
                        `;
                    } else {
                        box.innerHTML = `
                            <div class="wa-status-badge close">🔴 Desconectado</div>
                            <p style="margin-top:12px; color:#64748b; font-size:0.9rem;">${data.error || 'Não foi possível gerar o QR Code. Verifique as credenciais acima.'}</p>
                        `;
                    }
                } catch (e) {
                    box.innerHTML = '<div style="color:#ef4444;">Erro ao consultar a EvolutionAPI.</div>';
                }
            }

            // Consulta o status ao carregar a página
            checkStatusAndQrCode();
        </script>
        <?php

        Html::footer();
        return new Response(ob_get_clean() ?: '');
    }
}
