<?php
/**
 * Compatibilidade Whatsappsimples entre GLPI 11 e GLPI 12.
 *
 * Mesmo esquema de kanpro/inc/compat.php, protocolo/src/compat.php,
 * assetmgrstatus/src/compat.php, cadastroativos/src/compat.php e
 * floorplan/inc/compat.php. Helpers guardados por function_exists/
 * method_exists. No GLPI 12: Plugin::getWebDir() foi removido,
 * Session::getNewCSRFToken() e Session::checkCSRF() viraram no-ops (só
 * emitem deprecation) e o CSRF passou a ser validado pelo CheckCsrfListener
 * (Sec-Fetch-Site/Origin).
 */
if (!defined('GLPI_ROOT')) {
    // setup.php roda antes do GLPI_ROOT existir (carrega manifest); não morre aqui.
}

if (!function_exists('wa_glpi_version')) {
    function wa_glpi_version(): string
    {
        return defined('GLPI_VERSION') ? (string) GLPI_VERSION : '';
    }
}

if (!function_exists('wa_glpi_major')) {
    function wa_glpi_major(): int
    {
        $v = wa_glpi_version();
        if ($v === '') {
            return 0;
        }
        return (int) explode('.', $v)[0];
    }
}

if (!function_exists('wa_is_glpi12_plus')) {
    function wa_is_glpi12_plus(): bool
    {
        return wa_glpi_major() >= 12;
    }
}

if (!function_exists('wa_web_dir')) {
    /**
     * URL base do plugin (ex.: "/glpi/plugins/whatsappsimples").
     * GLPI <=11: usa Plugin::getWebDir(); GLPI 12 (método removido): monta via root_doc.
     */
    function wa_web_dir(string $plugin = 'whatsappsimples'): string
    {
        if (method_exists('Plugin', 'getWebDir')) {
            try {
                return (string) Plugin::getWebDir($plugin);
            } catch (\Throwable $e) {
                // cai para o fallback abaixo
            }
        }
        global $CFG_GLPI;
        $root = (is_array($CFG_GLPI) && isset($CFG_GLPI['root_doc'])) ? (string) $CFG_GLPI['root_doc'] : '';
        if (defined('GLPI_ROOT')) {
            $base = (string) GLPI_ROOT;
            if (@is_dir($base . '/marketplace/' . $plugin)) {
                return $root . '/marketplace/' . $plugin;
            }
        }
        return $root . '/plugins/' . $plugin;
    }
}

if (!function_exists('wa_csrf_token')) {
    /**
     * Token CSRF da página. No GLPI 12 não há token (retorna ''); o CSRF é
     * tratado pelo listener do core. No GLPI 11 mantém o comportamento atual.
     */
    function wa_csrf_token(): string
    {
        if (wa_is_glpi12_plus()) {
            return '';
        }
        if (method_exists('Session', 'getNewCSRFToken')) {
            try {
                return (string) Session::getNewCSRFToken();
            } catch (\Throwable $e) {
                return '';
            }
        }
        return '';
    }
}

if (!function_exists('wa_check_csrf')) {
    /**
     * Validação CSRF explícita. No GLPI 12 é no-op (o CheckCsrfListener cuida);
     * no GLPI 11 delega para Session::checkCSRF() preservando o token.
     */
    function wa_check_csrf(array $data): void
    {
        if (wa_is_glpi12_plus()) {
            return;
        }
        Session::checkCSRF($data, true);
    }
}
