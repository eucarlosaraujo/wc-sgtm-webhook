<?php
/**
 * Funções auxiliares e Lógica Principal do plugin WooCommerce SGTM Webhook
 *
 * Este arquivo foi atualizado com a lógica procedural completa dos snippets de código
 * (Configuração Principal Corrigida e Painel Admin Stape.io), substituindo o uso
 * das classes WC_SGTM_Webhook e WC_SGTM_Webhook_Admin.
 *
 * @package WC_SGTM_Webhook
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Helper para ler configurações do plugin com fallback para constantes
function wc_sgtm_get_setting($key, $default = null) {
    if (function_exists('wc_sgtm_webhook_pro')) {
        $plugin = wc_sgtm_webhook_pro();
        if ($plugin && method_exists($plugin, 'get_setting')) {
            $val = $plugin->get_setting($key, null);
            if ($val !== null) {
                return $val;
            }
        }
    }
    // Fallback para constantes antigas
    switch ($key) {
        case 'webhook_enabled':
            return defined('SGTM_WEBHOOK_ENABLED') ? SGTM_WEBHOOK_ENABLED : ($default ?? false);
        case 'webhook_url':
            return defined('SGTM_WEBHOOK_URL') ? SGTM_WEBHOOK_URL : ($default ?? '');
        case 'debug_mode':
            return defined('SGTM_DEBUG_MODE') ? SGTM_DEBUG_MODE : ($default ?? false);
        case 'timeout':
            return $default ?? 30;
        case 'validate_ssl':
            return $default ?? true;
        case 'auth_token':
            return defined('SGTM_AUTH_TOKEN') ? SGTM_AUTH_TOKEN : ($default ?? '');
        case 'auth_key':
            return defined('SGTM_AUTH_KEY') ? SGTM_AUTH_KEY : ($default ?? '');
        case 'rate_limit_seconds':
            return $default ?? 60;
        default:
            return $default;
    }
}

// O código abaixo é a migração literal dos snippets para o plugin.

/**
 * ========================================
 * FUNÇÃO PRINCIPAL - ENVIAR WEBHOOK
 * ========================================
 */
function wc_sgtm_enviar_webhook_pedido_pago($order_id) {

    $enabled = (bool) wc_sgtm_get_setting('webhook_enabled', false);
    if (!$enabled) {
        wc_sgtm_log_debug('Webhook desabilitado via configuração');
        return;
    }

    try {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_sgtm_log_debug('Pedido não encontrado: ' . $order_id);
            return;
        }

        if (!$order->is_paid()) {
            wc_sgtm_log_debug('Pedido não pago ainda: ' . $order_id . ' - Status: ' . $order->get_status());
            return;
        }

        // Evitar envios duplicados
        $webhook_sent = get_post_meta($order_id, '_sgtm_webhook_sent', true);
        if ($webhook_sent) {
            wc_sgtm_log_debug('Webhook já enviado para pedido: ' . $order_id . ' em ' . $webhook_sent);
            return;
        }

        // Rate limiting para evitar reenvios rápidos
        $rate_limit = (int) wc_sgtm_get_setting('rate_limit_seconds', 60);
        $last_attempt = get_post_meta($order_id, '_sgtm_webhook_last_attempt', true);
        if (!empty($last_attempt)) {
            $elapsed = time() - strtotime($last_attempt);
            if ($elapsed < $rate_limit) {
                wc_sgtm_log_debug('Rate limit ativo para pedido ' . $order_id . ' (última tentativa: ' . $last_attempt . ')');
                return;
            }
        }

        // Preparar dados do pedido
        $order_data = wc_sgtm_preparar_dados_pedido($order);

        if (empty($order_data)) {
            wc_sgtm_log_debug('Erro ao preparar dados do pedido: ' . $order_id);
            return;
        }

        wc_sgtm_log_debug('Enviando webhook para pedido: ' . $order_id);

        // Marcar tentativa para rate limiting
        update_post_meta($order_id, '_sgtm_webhook_last_attempt', current_time('mysql'));

        // Enviar webhook
        $response = wc_sgtm_enviar_dados($order_data);

        // Processar resposta
        wc_sgtm_processar_resposta_webhook($response, $order_id);

    } catch (Exception $e) {
        wc_sgtm_log_debug('Exceção ao processar pedido ' . $order_id . ': ' . $e->getMessage());

        update_post_meta($order_id, '_sgtm_webhook_error', array(
            'timestamp' => current_time('mysql'),
            'error' => $e->getMessage()
        ));
    }
}

/**
 * ========================================
 * PREPARAR DADOS DO PEDIDO
 * ========================================
 */
function wc_sgtm_preparar_dados_pedido($order) {

    if (!$order) {
        return false;
    }

    $order_id = $order->get_id();

    $order_data = array(
        'client_name' => 'Data Client',
        'event_name' => 'purchase',
        'event_time' => time(),
        'event_id' => 'wc_' . $order_id . '_' . time(),
        'action_source' => 'website',
        'event_source_url' => get_home_url(),
        'user_data' => wc_sgtm_preparar_user_data($order),
        'custom_data' => wc_sgtm_preparar_custom_data($order),
        'metadata' => array(
            'source' => 'woocommerce',
            'version' => '1.0',
            'site_url' => get_site_url(),
            'order_status' => $order->get_status(),
            'payment_method' => $order->get_payment_method(),
            'order_date' => $order->get_date_created()->format('c')
        )
    );

    return $order_data;
}

/**
 * ========================================
 * PREPARAR DADOS DO USUÁRIO - VERSÃO ATUALIZADA
 * ========================================
 */
function wc_sgtm_preparar_user_data($order) {

    $user_data = array();

    // Email - AMBOS: hash e sem hash
    if ($email = $order->get_billing_email()) {
        $clean_email = strtolower(trim($email));
        $user_data['em'] = array(hash('sha256', $clean_email)); // Hash para Facebook
        $user_data['email_address'] = $clean_email; // Sem hash para outras plataformas
    }

    // Telefone
    if ($phone = $order->get_billing_phone()) {
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($clean_phone) >= 10) {
            $user_data['ph'] = array(hash('sha256', $clean_phone));
            // Opcional: telefone sem hash para algumas plataformas
            $user_data['phone_number'] = $clean_phone;
        }
    }

    // Nome
    if ($first_name = $order->get_billing_first_name()) {
        $user_data['fn'] = array(hash('sha256', strtolower(trim($first_name))));
        $user_data['first_name'] = trim($first_name); // Sem hash
    }

    if ($last_name = $order->get_billing_last_name()) {
        $user_data['ln'] = array(hash('sha256', strtolower(trim($last_name))));
        $user_data['last_name'] = trim($last_name); // Sem hash
    }

    // Endereço
    if ($city = $order->get_billing_city()) {
        $user_data['ct'] = array(hash('sha256', strtolower(trim($city))));
        $user_data['city'] = trim($city); // Sem hash
    }

    if ($state = $order->get_billing_state()) {
        $user_data['st'] = array(hash('sha256', strtolower(trim($state))));
        $user_data['state'] = trim($state); // Sem hash
    }

    // CEP
    if ($postcode = $order->get_billing_postcode()) {
        $clean_zip = preg_replace('/[^0-9]/', '', $postcode);
        if (strlen($clean_zip) === 8) {
            $user_data['zp'] = array(hash('sha256', $clean_zip));
            $user_data['zip_code'] = $clean_zip; // Sem hash
        }
    }

    // País
    if ($country = $order->get_billing_country()) {
        $user_data['country'] = array(hash('sha256', strtolower(trim($country))));
        $user_data['country_code'] = trim($country); // Sem hash
    }

    // Usuário logado - AMBOS: hash e sem hash
    if ($user_id = $order->get_user_id()) {
        $user_data['external_id'] = array(hash('sha256', strval($user_id))); // Hash para Facebook
        $user_data['user_id'] = intval($user_id); // Sem hash para outras plataformas

        // Dados adicionais do usuário WordPress
        $user = get_user_by('ID', $user_id);
        if ($user) {
            $user_data['username'] = $user->user_login;
            $user_data['user_registered'] = $user->user_registered;
        }
    }

    // Cliente guest (sem login)
    if (!$user_id) {
        $user_data['user_type'] = 'guest';
    } else {
        $user_data['user_type'] = 'registered';
    }

    // Dados adicionais úteis para segmentação
    $user_data['billing_company'] = $order->get_billing_company() ?: '';

    return array_filter($user_data);
}
/**
 * ========================================
 * PREPARAR DADOS CUSTOMIZADOS - VERSÃO CORRIGIDA
 * ========================================
 */
function wc_sgtm_preparar_custom_data($order) {

    $custom_data = array(
        'currency' => $order->get_currency(),
        'value' => floatval($order->get_total()),
        'order_id' => strval($order->get_id()),
        'num_items' => intval($order->get_item_count()),
        'content_type' => 'product',
        'content_ids' => array(),
        'content_names' => array(),
        'content_category' => array(),
        'contents' => array(),
        'subtotal' => floatval($order->get_subtotal()),
        'tax' => floatval($order->get_total_tax()),
        'shipping' => floatval($order->get_shipping_total()),
        'discount' => floatval($order->get_discount_total()),
        'order_key' => $order->get_order_key()
    );

    // Processar itens do pedido - VERSÃO CORRIGIDA
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();

        if (!$product) {
            continue;
        }

        $product_id = $product->get_id();
        $quantity = intval($item->get_quantity());
        $price = floatval($product->get_price());

        // IDs dos produtos
        $custom_data['content_ids'][] = strval($product_id);

        // Nomes dos produtos
        $custom_data['content_names'][] = $product->get_name();

        // Categorias dos produtos
        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
        if (!empty($categories)) {
            $custom_data['content_category'][] = $categories[0];
        }

        // Dados detalhados de cada produto - SEM VARIAÇÃO POR ENQUANTO
        $custom_data['contents'][] = array(
            'id' => strval($product_id),
            'name' => $product->get_name(),
            'category' => !empty($categories) ? $categories[0] : '',
            'quantity' => $quantity,
            'item_price' => $price,
            'brand' => wc_sgtm_get_product_brand($product),
            'sku' => $product->get_sku() ?: ''
        );
    }

    return $custom_data;
}

/**
 * ========================================
 * FUNÇÕES AUXILIARES CORRIGIDAS
 * ========================================
 */

// Obter marca do produto
function wc_sgtm_get_product_brand($product) {
    $brand = '';

    // Verificar atributo 'marca'
    if (method_exists($product, 'get_attribute')) {
        $brand = $product->get_attribute('pa_marca');
        if (empty($brand)) {
            $brand = $product->get_attribute('marca');
        }
    }

    // Verificar taxonomia de marca
    if (empty($brand) && $product->get_id()) {
        $terms = wp_get_post_terms($product->get_id(), 'product_brand', array('fields' => 'names'));
        if (!empty($terms) && !is_wp_error($terms)) {
            $brand = $terms[0];
        }
    }

    return $brand ?: '';
}

/**
 * ========================================
 * ENVIO DE DADOS VIA HTTP
 * ========================================
 */
function wc_sgtm_enviar_dados($data) {

    $url = wc_sgtm_get_setting('webhook_url', '');
    if (empty($url)) {
        wc_sgtm_log_debug('Webhook URL não configurada.');
        return new WP_Error('no_url', 'Webhook URL não configurada.');
    }

    $args = array(
        'body' => json_encode($data, JSON_UNESCAPED_UNICODE),
        'headers' => array(
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'WooCommerce-SGTM-Webhook/' . (defined('WC_SGTM_WEBHOOK_VERSION') ? WC_SGTM_WEBHOOK_VERSION : '1.x'),
            'Accept' => 'application/json'
        ),
        'timeout' => (int) wc_sgtm_get_setting('timeout', 30),
        'httpversion' => '1.1',
        'sslverify' => (bool) wc_sgtm_get_setting('validate_ssl', true),
        'blocking' => true
    );

    // Optional auth headers
    $auth_token = wc_sgtm_get_setting('auth_token', '');
    if (!empty($auth_token)) {
        $args['headers']['Authorization'] = 'Bearer ' . $auth_token;
        wc_sgtm_log_debug('Auth token header habilitado');
    }
    $auth_key = wc_sgtm_get_setting('auth_key', '');
    if (!empty($auth_key)) {
        $args['headers']['X-Webhook-Key'] = $auth_key;
        wc_sgtm_log_debug('Webhook key header habilitado');
    }

    // SGTM-specific headers via options
    $opt_auth_token = get_option('sgtm_auth_token', '');
    if (!empty($opt_auth_token)) {
        $args['headers']['X-Auth-Token'] = $opt_auth_token;
    }
    $opt_client_id = get_option('sgtm_client_id', '');
    if (!empty($opt_client_id)) {
        $args['headers']['X-Client-ID'] = $opt_client_id;
    }

    if (wc_sgtm_get_setting('debug_mode', false)) {
        wc_sgtm_log_debug('Enviando POST para: ' . $url);
        wc_sgtm_log_debug('Tamanho dos dados: ' . strlen($args['body']) . ' bytes');
    }

    return wp_remote_post($url, $args);
}

/**
 * ========================================
 * PROCESSAR RESPOSTA DO WEBHOOK
 * ========================================
 */
function wc_sgtm_processar_resposta_webhook($response, $order_id) {

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wc_sgtm_log_debug('Erro de conexão no webhook - Pedido ' . $order_id . ': ' . $error_message);

        update_post_meta($order_id, '_sgtm_webhook_error', array(
            'timestamp' => current_time('mysql'),
            'type' => 'connection_error',
            'error' => $error_message
        ));

        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    wc_sgtm_log_debug('Resposta webhook - Pedido ' . $order_id . ' - Código: ' . $response_code);

    if ($response_code >= 200 && $response_code < 300) {
        if ($response_code === 204) {
            wc_sgtm_log_debug('SGTM respondeu 204 (No Content) - sucesso sem corpo');
        }
        update_post_meta($order_id, '_sgtm_webhook_sent', current_time('mysql'));
        update_post_meta($order_id, '_sgtm_webhook_response', array(
            'code' => $response_code,
            'body' => substr($response_body, 0, 500) // Limitar tamanho
        ));

        wc_sgtm_log_debug('Webhook enviado com sucesso para pedido: ' . $order_id);
        return true;

    } else {
        wc_sgtm_log_debug('Erro HTTP no webhook - Pedido ' . $order_id . ' - Código: ' . $response_code);

        update_post_meta($order_id, '_sgtm_webhook_error', array(
            'timestamp' => current_time('mysql'),
            'type' => 'http_error',
            'code' => $response_code,
            'body' => substr($response_body, 0, 500)
        ));

        return false;
    }
}

/**
 * ========================================
 * SISTEMA DE LOGS
 * ========================================
 */
function wc_sgtm_log_debug($message) {
    if (!wc_sgtm_get_setting('debug_mode', false)) {
        return;
    }

    // Redação de dados sensíveis
    $redacted = $message;
    // Emails
    $redacted = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email_redacted]', $redacted);
    // Telefones (10+ dígitos)
    $redacted = preg_replace('/\b\d{10,}\b/', '[phone_redacted]', $redacted);

    $iso_timestamp = date('c');
    $log_line = $iso_timestamp . ' ' . $redacted;

    if (function_exists('wc_get_logger')) {
        // Log para WooCommerce
        $logger = wc_get_logger();
        $logger->info($redacted, array('source' => 'sgtm-webhook'));
    }

    // Log dedicado no diretório do WooCommerce com limite de 10MB
    if (defined('WC_LOG_DIR')) {
        $log_file = rtrim(WC_LOG_DIR, '\\/') . DIRECTORY_SEPARATOR . 'sgtm-webhook-' . date('Y-m-d') . '.log';
        if (file_exists($log_file) && filesize($log_file) > 10485760) { // 10MB
            @unlink($log_file);
        }
        @file_put_contents($log_file, $log_line . PHP_EOL, FILE_APPEND);
    }

    // Também enviar para o error_log padrão
    error_log('[SGTM] ' . $log_line);
}

/**
 * ========================================
 * HOOKS DO WOOCOMMERCE
 * ========================================
 */

// Hooks principais
add_action('woocommerce_order_status_completed', 'wc_sgtm_enviar_webhook_pedido_pago', 10, 1);
add_action('woocommerce_order_status_processing', 'wc_sgtm_enviar_webhook_pedido_pago', 10, 1);
add_action('woocommerce_payment_complete', 'wc_sgtm_enviar_webhook_pedido_pago', 10, 1);

/**
 * ========================================
 * FUNÇÃO DE REENVIO MANUAL
 * ========================================
 */
function wc_sgtm_reenviar_webhook_manual($order_id) {
    delete_post_meta($order_id, '_sgtm_webhook_sent');
    delete_post_meta($order_id, '_sgtm_webhook_error');
    delete_post_meta($order_id, '_sgtm_webhook_response');
    delete_post_meta($order_id, '_sgtm_webhook_last_attempt');

    wc_sgtm_enviar_webhook_pedido_pago($order_id);
}

// Ajax handler para reenvio
add_action('wp_ajax_wc_sgtm_reenviar_webhook', function() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_sgtm_reenviar') || !current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => 'Acesso negado'), 403);
    }

    $order_id = intval($_POST['order_id']);
    if ($order_id) {
        wc_sgtm_reenviar_webhook_manual($order_id);
        wp_send_json_success(array('message' => 'Webhook reenviado com sucesso!'));
    }

    wp_send_json_error(array('message' => 'ID do pedido inválido'));
});

/**
 * ========================================
 * INFORMAÇÕES DE DEBUG
 * ========================================
 */
if (wc_sgtm_get_setting('debug_mode', false)) {
    wc_sgtm_log_debug('SGTM Webhook Plugin carregado - Versão: ' . (defined('WC_SGTM_WEBHOOK_VERSION') ? WC_SGTM_WEBHOOK_VERSION : '1.x'));
    $url = wc_sgtm_get_setting('webhook_url', '');
    if (!empty($url)) {
        wc_sgtm_log_debug('URL configurada: ' . $url);
    }
    $enabled = (bool) wc_sgtm_get_setting('webhook_enabled', false);
    wc_sgtm_log_debug('Status: ' . ($enabled ? 'Ativo' : 'Inativo'));
}


// Nome do Snippet: WooCommerce SGTM Webhook - Painel Admin Stape.io
// Descrição: Painel administrativo completo para gerenciar webhooks SGTM via stape.io

/**
 * ========================================
 * ADICIONAR MENU NO ADMIN WORDPRESS
 * ========================================
 */
function wc_sgtm_add_admin_menu() {
    add_submenu_page(
        'woocommerce',
        'SGTM Webhook Stape.io',
        'SGTM Webhook',
        'manage_woocommerce',
        'wc-sgtm-webhook',
        'wc_sgtm_admin_page'
    );
}
add_action('admin_menu', 'wc_sgtm_add_admin_menu');

/**
 * ========================================
 * PÁGINA PRINCIPAL DO ADMIN
 * ========================================
 */
function wc_sgtm_admin_page() {

    // Processar ações do formulário
    if (isset($_POST['action'], $_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'wc_sgtm_admin_action')) {

        switch ($_POST['action']) {
            case 'test_webhook':
                wc_sgtm_test_webhook_admin();
                break;

            case 'clear_logs':
                wc_sgtm_clear_logs_admin();
                break;

            case 'reprocess_orders':
                wc_sgtm_reprocess_recent_orders();
                break;

            case 'export_logs':
                wc_sgtm_export_logs();
                break;
        }
    }

    ?>
    <div class="wrap">
        <h1>🚀 WooCommerce SGTM Webhook - Stape.io</h1>

                <div class="postbox" style="margin-top: 20px;">
            <h2 class="hndle">📊 Status da Configuração</h2>
            <div class="inside">
                <?php wc_sgtm_render_status_section(); ?>
            </div>
        </div>

                <div class="postbox">
            <h2 class="hndle">📈 Estatísticas</h2>
            <div class="inside">
                <?php wc_sgtm_render_statistics_section(); ?>
            </div>
        </div>

                <div class="postbox">
            <h2 class="hndle">⚡ Ações Rápidas</h2>
            <div class="inside">
                <?php wc_sgtm_render_actions_section(); ?>
            </div>
        </div>

                <div class="postbox">
            <h2 class="hndle">📦 Últimos Pedidos Processados</h2>
            <div class="inside">
                <?php wc_sgtm_render_recent_orders(); ?>
            </div>
        </div>

                <div class="postbox">
            <h2 class="hndle">📝 Logs Recentes</h2>
            <div class="inside">
                <?php wc_sgtm_render_recent_logs(); ?>
            </div>
        </div>

                <div class="postbox">
            <h2 class="hndle">⚙️ Configurações e Troubleshooting</h2>
            <div class="inside">
                <?php wc_sgtm_render_advanced_section(); ?>
            </div>
        </div>
    </div>

    <style>
        .wc-sgtm-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .wc-sgtm-status-card {
            background: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #0073aa;
            border-radius: 3px;
        }
        .wc-sgtm-status-card.success { border-left-color: #46b450; }
        .wc-sgtm-status-card.warning { border-left-color: #ffb900; }
        .wc-sgtm-status-card.error { border-left-color: #dc3232; }
        .wc-sgtm-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .wc-sgtm-stat {
            display: inline-block;
            margin-right: 20px;
            font-weight: bold;
        }
        .wc-sgtm-log-entry {
            background: #f1f1f1;
            padding: 10px;
            margin: 5px 0;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
    <?php
}

/**
 * ========================================
 * SEÇÃO DE STATUS DA CONFIGURAÇÃO
 * ========================================
 */
function wc_sgtm_render_status_section() {
    $webhook_enabled = defined('SGTM_WEBHOOK_ENABLED') ? SGTM_WEBHOOK_ENABLED : false;
    $debug_mode = defined('SGTM_DEBUG_MODE') ? SGTM_DEBUG_MODE : false;
    $webhook_url = defined('SGTM_WEBHOOK_URL') ? SGTM_WEBHOOK_URL : 'Não configurado';

    // Testar conectividade
    $connectivity_status = wc_sgtm_test_connectivity();

    ?>
    <div class="wc-sgtm-status-grid">
        <div class="wc-sgtm-status-card <?php echo $webhook_enabled ? 'success' : 'error'; ?>">
            <h4>🔗 Status do Webhook</h4>
            <p><strong>URL:</strong> <?php echo esc_html($webhook_url); ?></p>
            <p><strong>Status:</strong> 
                <?php if ($webhook_enabled): ?>
                    <span style="color: green;">✅ Ativo</span>
                <?php else: ?>
                    <span style="color: red;">❌ Inativo</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="wc-sgtm-status-card <?php echo $debug_mode ? 'warning' : 'success'; ?>">
            <h4>🐛 Modo Debug</h4>
            <p><strong>Status:</strong> 
                <?php if ($debug_mode): ?>
                    <span style="color: orange;">🔍 Ativo</span><br>
                    <small>Lembre-se de desativar em produção</small>
                <?php else: ?>
                    <span style="color: green;">✅ Desativo</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="wc-sgtm-status-card <?php echo $connectivity_status['status'] === 'success' ? 'success' : 'error'; ?>">
            <h4>🌐 Conectividade Stape.io</h4>
            <p><strong>Status:</strong> 
                <span style="color: <?php echo $connectivity_status['status'] === 'success' ? 'green' : 'red'; ?>;">
                    <?php echo $connectivity_status['status'] === 'success' ? '✅ Conectado' : '❌ Erro de Conexão'; ?>
                </span>
            </p>
            <p><small><?php echo esc_html($connectivity_status['message']); ?></small></p>
        </div>

        <div class="wc-sgtm-status-card">
            <h4>📅 Informações do Sistema</h4>
            <p><strong>Versão WooCommerce:</strong> <?php echo function_exists('WC') ? WC()->version : 'N/A'; ?></p>
            <p><strong>Versão WordPress:</strong> <?php echo get_bloginfo('version'); ?></p>
            <p><strong>PHP:</strong> <?php echo PHP_VERSION; ?></p>
        </div>
    </div>
    <?php
}

/**
 * ========================================
 * SEÇÃO DE ESTATÍSTICAS
 * ========================================
 */
function wc_sgtm_render_statistics_section() {
    $stats = wc_sgtm_get_webhook_statistics();
    ?>
    <div class="wc-sgtm-status-grid">
        <div class="wc-sgtm-status-card success">
            <h4>📊 Webhooks Enviados</h4>
            <p style="font-size: 24px; margin: 0;"><strong><?php echo number_format($stats['total_sent']); ?></strong></p>
            <small>Últimos 30 dias</small>
        </div>

        <div class="wc-sgtm-status-card <?php echo $stats['errors_today'] > 0 ? 'warning' : 'success'; ?>">
            <h4>⚠️ Erros Hoje</h4>
            <p style="font-size: 24px; margin: 0;"><strong><?php echo $stats['errors_today']; ?></strong></p>
            <small>Taxa de sucesso: <?php echo $stats['success_rate']; ?>%</small>
        </div>

        <div class="wc-sgtm-status-card">
            <h4>🕒 Último Envio</h4>
            <p><strong><?php echo $stats['last_sent'] ? $stats['last_sent'] : 'Nunca'; ?></strong></p>
            <small>Pedido #<?php echo $stats['last_order_id'] ? $stats['last_order_id'] : 'N/A'; ?></small>
        </div>

        <div class="wc-sgtm-status-card">
            <h4>💰 Total Processado</h4>
            <p style="font-size: 20px; margin: 0;"><strong>R$ <?php echo number_format($stats['total_revenue'], 2, ',', '.'); ?></strong></p>
            <small>Últimos 30 dias</small>
        </div>
    </div>
    <?php
}

/**
 * ========================================
 * SEÇÃO DE AÇÕES RÁPIDAS
 * ========================================
 */
function wc_sgtm_render_actions_section() {
    ?>
    <div class="wc-sgtm-actions">
        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field('wc_sgtm_admin_action'); ?>
            <input type="hidden" name="action" value="test_webhook">
            <button type="submit" class="button button-primary">🧪 Testar Webhook</button>
        </form>

        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field('wc_sgtm_admin_action'); ?>
            <input type="hidden" name="action" value="reprocess_orders">
            <button type="submit" class="button button-secondary" onclick="return confirm('Reprocessar últimos 10 pedidos?')">🔄 Reprocessar Pedidos</button>
        </form>

        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field('wc_sgtm_admin_action'); ?>
            <input type="hidden" name="action" value="clear_logs">
            <button type="submit" class="button button-secondary" onclick="return confirm('Limpar todos os logs?')">🗑️ Limpar Logs</button>
        </form>

        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field('wc_sgtm_admin_action'); ?>
            <input type="hidden" name="action" value="export_logs">
            <button type="submit" class="button button-secondary">📥 Exportar Logs</button>
        </form>

        <a href="<?php echo admin_url('admin.php?page=wc-status&tab=logs'); ?>" class="button button-secondary">📋 Ver Todos os Logs</a>
    </div>

    <div style="margin-top: 15px;">
        <h4>🔧 Links Úteis:</h4>
        <a href="https://tagmanager.google.com" target="_blank" class="button button-small">🏷️ Google Tag Manager</a>
        <a href="https://app.stape.io" target="_blank" class="button button-small">🚀 Stape.io Dashboard</a>
        <a href="https://business.facebook.com/events_manager" target="_blank" class="button button-small">📘 Facebook Events Manager</a>
    </div>
    <?php
}

/**
 * ========================================
 * SEÇÃO DE PEDIDOS RECENTES
 * ========================================
 */
function wc_sgtm_render_recent_orders() {
    $recent_orders = wc_get_orders(array(
        'limit' => 15,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => array('completed', 'processing')
    ));

    if (empty($recent_orders)) {
        echo '<p>Nenhum pedido encontrado.</p>';
        return;
    }

    ?>
    <div style="overflow-x: auto;">
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 80px;">Pedido</th>
                    <th style="width: 120px;">Data</th>
                    <th style="width: 100px;">Status</th>
                    <th style="width: 100px;">Total</th>
                    <th style="width: 120px;">Webhook</th>
                    <th style="width: 80px;">Resposta</th>
                    <th style="width: 100px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $order): ?>
                    <?php
                    $order_id = $order->get_id();
                    $webhook_sent = get_post_meta($order_id, '_sgtm_webhook_sent', true);
                    $webhook_error = get_post_meta($order_id, '_sgtm_webhook_error', true);
                    $webhook_response = get_post_meta($order_id, '_sgtm_webhook_response', true);
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" target="_blank">
                                #<?php echo $order_id; ?>
                            </a>
                        </td>
                        <td><?php echo $order->get_date_created()->format('d/m/Y H:i'); ?></td>
                        <td>
                            <span class="wc-order-status status-<?php echo $order->get_status(); ?>">
                                <?php echo wc_get_order_status_name($order->get_status()); ?>
                            </span>
                        </td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                        <td>
                            <?php if ($webhook_sent): ?>
                                <span style="color: green;">✅ <?php echo date('d/m H:i', strtotime($webhook_sent)); ?></span>
                            <?php elseif ($webhook_error): ?>
                                <span style="color: red;" title="<?php echo esc_attr($webhook_error['error']); ?>">❌ Erro</span>
                            <?php else: ?>
                                <span style="color: orange;">⏳ Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($webhook_response && isset($webhook_response['code'])) {
                                $code = $webhook_response['code'];
                                $color = ($code >= 200 && $code < 300) ? 'green' : 'red';
                                echo '<span style="color: ' . $color . ';">' . $code . '</span>';
                            } else {
                                echo '-';
                            }
                        </td>
                        <td>
                            <button class="button button-small" onclick="wc_sgtm_reenviar_webhook(<?php echo $order_id; ?>)">
                                🔄 Reenviar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    function wc_sgtm_reenviar_webhook(order_id) {
        if (!confirm('Reenviar webhook para o pedido #' + order_id + '?')) {
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'wc_sgtm_reenviar_webhook',
            order_id: order_id,
            nonce: '<?php echo wp_create_nonce('wc_sgtm_reenviar'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Webhook reenviado!');
            } else {
                alert('Erro ao reenviar webhook: ' + response.data.message);
            }
            location.reload();
        }).fail(function() {
            alert('Erro ao reenviar webhook!');
        });
    }
    </script>
    <?php
}

/**
 * ========================================
 * SEÇÃO DE LOGS RECENTES
 * ========================================
 */
function wc_sgtm_render_recent_logs() {
    if (!function_exists('wc_get_logger') || !defined('WC_LOG_DIR')) {
        echo '<p>Nenhum log encontrado. Ative o modo debug e verifique se o WC_LOG_DIR está definido.</p>';
        return;
    }
    
    $logs = wc_sgtm_get_recent_logs(10);

    if (empty($logs)) {
        echo '<p>Nenhum log encontrado. Ative o modo debug para ver os logs.</p>';
        return;
    }

    ?>
    <div style="max-height: 400px; overflow-y: auto;">
        <?php foreach ($logs as $log): ?>
            <div class="wc-sgtm-log-entry">
                <strong><?php echo esc_html($log['timestamp']); ?></strong> - 
                <?php echo esc_html($log['message']); ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * ========================================
 * SEÇÃO AVANÇADA
 * ========================================
 */
function wc_sgtm_render_advanced_section() {
    ?>
    <h4>🔍 Informações de Debug:</h4>
    <table class="form-table">
        <tr>
            <th>URL Configurada:</th>
            <td><code><?php echo defined('SGTM_WEBHOOK_URL') ? SGTM_WEBHOOK_URL : 'Não definida'; ?></code></td>
        </tr>
        <tr>
            <th>Hooks Ativos:</th>
            <td>
                <code>woocommerce_order_status_completed</code><br>
                <code>woocommerce_order_status_processing</code><br>
                <code>woocommerce_payment_complete</code>
            </td>
        </tr>
        <tr>
            <th>Logs do WooCommerce:</th>
            <td>
                <?php if (function_exists('wc_get_logger')): ?>
                    ✅ Disponível - <a href="<?php echo admin_url('admin.php?page=wc-status&tab=logs'); ?>">Ver logs</a>
                <?php else: ?>
                    ❌ Indisponível
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Memória PHP:</th>
            <td><?php echo ini_get('memory_limit'); ?> (Livre: <?php echo size_format(memory_get_usage(true)); ?>)</td>
        </tr>
    </table>

    <h4>🚨 Troubleshooting:</h4>
    <ul>
        <li><strong>Webhook não dispara:</strong> Verifique se SGTM_WEBHOOK_ENABLED está como true</li>
        <li><strong>Erro 404:</strong> Verifique se a URL do stape.io está correta e o path existe</li>
        <li><strong>Erro SSL:</strong> Verifique se o certificado SSL do stape.io está válido</li>
        <li><strong>Dados não chegam no Facebook:</strong> Verifique a configuração da tag no SGTM</li>
        <li><strong>Duplicatas:</strong> Sistema automático previne, mas logs mostram tentativas</li>
    </ul>
    <?php
}

/**
 * ========================================
 * FUNÇÕES AUXILIARES PARA O ADMIN
 * ========================================
 */

// Testar webhook com dados fictícios
function wc_sgtm_test_webhook_admin() {
    $test_data = array(
        'client_name' => 'Data Client',
        'event_name' => 'purchase',
        'event_time' => time(),
        'event_id' => 'test_' . time(),
        'action_source' => 'website',
        'event_source_url' => get_home_url(),
        'user_data' => array(
            'em' => array(hash('sha256', 'teste@example.com'))
        ),
        'custom_data' => array(
            'currency' => 'BRL',
            'value' => 199.90,
            'order_id' => 'test_order_' . time()
        ),
        'metadata' => array(
            'source' => 'admin_test',
            'test_mode' => true
        )
    );

    $response = wc_sgtm_enviar_dados($test_data);

    if (is_wp_error($response)) {
        $message = 'Erro no teste: ' . $response->get_error_message();
        $type = 'error';
    } else {
        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            $message = 'Teste enviado com sucesso! Código de resposta: ' . $code;
            $type = 'success';
        } else {
            $message = 'Teste enviado mas retornou código HTTP: ' . $code;
            $type = 'warning';
        }
    }

    add_settings_error('wc_sgtm_admin', 'test_result', $message, $type);
}

// Limpar logs
function wc_sgtm_clear_logs_admin() {
    // Limpar logs do WooCommerce
    if (function_exists('wc_get_logger') && defined('WC_LOG_DIR')) {
        $log_files = glob(WC_LOG_DIR . 'sgtm-webhook-*.log');
        $cleared = 0;
        foreach ($log_files as $file) {
            if (is_file($file) && unlink($file)) {
                $cleared++;
            }
        }
        add_settings_error('wc_sgtm_admin', 'logs_cleared',
            "Logs limpos com sucesso! {$cleared} arquivo(s) removido(s).", 'success');
    } else {
        add_settings_error('wc_sgtm_admin', 'logs_error',
            'Sistema de logs não disponível.', 'error');
    }
}

// Reprocessar pedidos recentes
function wc_sgtm_reprocess_recent_orders() {
    $orders = wc_get_orders(array(
        'limit' => 10,
        'status' => array('completed', 'processing'),
        'orderby' => 'date',
        'order' => 'DESC'
    ));

    $processed = 0;
    foreach ($orders as $order) {
        // Remover flag de webhook enviado
        delete_post_meta($order->get_id(), '_sgtm_webhook_sent');
        delete_post_meta($order->get_id(), '_sgtm_webhook_error');

        // Reenviar
        wc_sgtm_enviar_webhook_pedido_pago($order->get_id());
        $processed++;
    }

    add_settings_error('wc_sgtm_admin', 'reprocess_done',
        "Reprocessados {$processed} pedidos!", 'success');
}

// Obter estatísticas
function wc_sgtm_get_webhook_statistics() {
    $cache_key = 'wc_sgtm_stats_' . date('YmdH');
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    global $wpdb;

    // Pedidos com webhook enviado (últimos 30 dias)
    $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

    $total_sent = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s
        AND pm.meta_key = '_sgtm_webhook_sent'
        AND pm.meta_value != ''
    ", $thirty_days_ago));

    // Erros hoje
    $today = date('Y-m-d');
    $start_today = $today . ' 00:00:00';
    $end_today = $today . ' 23:59:59';
    $errors_today = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s
        AND p.post_date < %s
        AND pm.meta_key = '_sgtm_webhook_error'
        AND pm.meta_value != ''
    ", $start_today, $end_today));

    // Último envio
    $last_sent_order = $wpdb->get_row("
        SELECT p.ID, pm.meta_value as sent_date
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND pm.meta_key = '_sgtm_webhook_sent'
        AND pm.meta_value != ''
        ORDER BY pm.meta_value DESC
        LIMIT 1
    ");

    // Total de receita processada
    $total_revenue = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(pm2.meta_value)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s
        AND pm.meta_key = '_sgtm_webhook_sent'
        AND pm.meta_value != ''
        AND pm2.meta_key = '_order_total'
    ", $thirty_days_ago));

    // Cálculo da taxa de sucesso de hoje
    $success_today = (int)$wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s
        AND p.post_date < %s
        AND pm.meta_key = '_sgtm_webhook_sent'
        AND pm.meta_value != ''
    ", $start_today, $end_today));

    $total_webhooks_today = $success_today + (int)$errors_today;
    $success_rate = $total_webhooks_today > 0 ? round(($success_today / $total_webhooks_today) * 100, 1) : 100;

    $stats = array(
        'total_sent' => (int)$total_sent,
        'errors_today' => (int)$errors_today,
        'success_rate' => $success_rate,
        'last_sent' => $last_sent_order ? date_i18n('d/m/Y H:i', strtotime($last_sent_order->sent_date)) : null,
        'last_order_id' => $last_sent_order ? $last_sent_order->ID : null,
        'total_revenue' => (float)$total_revenue ?: 0
    );

    set_transient($cache_key, $stats, HOUR_IN_SECONDS);
    return $stats;
}

// Testar conectividade
function wc_sgtm_test_connectivity() {
    $url = wc_sgtm_get_setting('webhook_url', '');
    if (empty($url)) {
        return array('status' => 'error', 'message' => 'URL não configurada');
    }

    $response = wp_remote_get($url, array('timeout' => 10));

    if (is_wp_error($response)) {
        return array('status' => 'error', 'message' => $response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code == 200 || $code == 405) { // 405 é normal para GET em endpoint POST
        return array('status' => 'success', 'message' => 'Conectividade OK');
    }

    return array('status' => 'error', 'message' => 'Código HTTP: ' . $code);
}

// Obter logs recentes
function wc_sgtm_get_recent_logs($limit = 10) {
    if (!function_exists('wc_get_logger') || !defined('WC_LOG_DIR')) {
        return array();
    }

    $log_files = glob(WC_LOG_DIR . 'sgtm-webhook-*.log');
    $logs = array();

    foreach ($log_files as $file) {
        if (is_file($file)) {
            $content = @file_get_contents($file);
            if ($content === false) continue;
            
            $lines = explode("\n", $content);

            foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
                if (empty(trim($line))) continue;

                if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})\s+(.+)$/', $line, $matches)) {
                    $logs[] = array(
                        'timestamp' => date_i18n('d/m/Y H:i:s', strtotime($matches[1])),
                        'message' => $matches[2]
                    );
                }
            }
        }
    }

    return array_slice($logs, 0, $limit);
}

// Mostrar notificações admin
add_action('admin_notices', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'wc-sgtm-webhook') {
        settings_errors('wc_sgtm_admin');
    }
});

// Adicionar link nas configurações do plugin
add_filter('plugin_action_links_wc-sgtm-webhook/wc-sgtm-webhook.php', function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-sgtm-webhook') . '">SGTM Webhook</a>';
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * ========================================
 * EXPORTAR LOGS PARA DOWNLOAD
 * ========================================
 */
function wc_sgtm_export_logs() {
    if (!function_exists('wc_get_logger') || !defined('WC_LOG_DIR')) {
        add_settings_error('wc_sgtm_admin', 'export_error',
            'Sistema de logs não disponível para exportação.', 'error');
        return;
    }

    $log_files = glob(WC_LOG_DIR . 'sgtm-webhook-*.log');
    $export_content = "SGTM Webhook Logs Export - " . date_i18n('d/m/Y H:i:s') . "\n";
    $export_content .= str_repeat("=", 60) . "\n\n";

    foreach ($log_files as $file) {
        if (is_file($file)) {
            $export_content .= "ARQUIVO: " . basename($file) . "\n";
            $export_content .= str_repeat("-", 30) . "\n";
            $export_content .= @file_get_contents($file);
            $export_content .= "\n\n";
        }
    }

    // Headers para download
    $filename = 'sgtm-webhook-logs-' . date('Y-m-d-H-i-s') . '.txt';

    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($export_content));

    echo $export_content;
    exit;
}

/**
 * ========================================
 * WIDGET NO DASHBOARD WORDPRESS
 * ========================================
 */
add_action('wp_dashboard_setup', 'wc_sgtm_add_dashboard_widget');

function wc_sgtm_add_dashboard_widget() {
    if (current_user_can('manage_woocommerce')) {
        wp_add_dashboard_widget(
            'wc_sgtm_dashboard_widget',
            '🚀 SGTM Webhook Status',
            'wc_sgtm_dashboard_widget_content'
        );
    }
}

function wc_sgtm_dashboard_widget_content() {
    $stats = wc_sgtm_get_webhook_statistics();
    $webhook_enabled = defined('SGTM_WEBHOOK_ENABLED') ? SGTM_WEBHOOK_ENABLED : false;

    ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
        <div>
            <strong>Status:</strong>
            <?php if ($webhook_enabled): ?>
                <span style="color: green;">✅ Ativo</span>
            <?php else: ?>
                <span style="color: red;">❌ Inativo</span>
            <?php endif; ?>
        </div>
        <div>
            <strong>Sucesso:</strong> <?php echo $stats['success_rate']; ?>%
        </div>
        <div>
            <strong>Enviados (30d):</strong> <?php echo number_format($stats['total_sent']); ?>
        </div>
        <div>
            <strong>Erros hoje:</strong> <?php echo $stats['errors_today']; ?>
        </div>
    </div>

    <?php if ($stats['last_sent']): ?>
        <p><strong>Último envio:</strong> <?php echo $stats['last_sent']; ?> (Pedido #<?php echo $stats['last_order_id']; ?>)</p>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 15px;">
        <a href="<?php echo admin_url('admin.php?page=wc-sgtm-webhook'); ?>" class="button button-primary">
            Ver Painel Completo
        </a>
    </div>
    <?php
}

/**
 * ========================================
 * NOTIFICAÇÕES AUTOMÁTICAS
 * ========================================
 */
add_action('admin_init', 'wc_sgtm_check_webhook_health');

function wc_sgtm_check_webhook_health() {
    // Verificar apenas para admins e na página correta
    if (!current_user_can('manage_woocommerce') || !is_admin()) {
        return;
    }

    // Verificar se há muitos erros recentes
    $error_count = wc_sgtm_get_recent_error_count();

    if ($error_count > 5) {
        add_action('admin_notices', function() use ($error_count) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>⚠️ SGTM Webhook:</strong>
                Detectados <?php echo $error_count; ?> erros recentes.
                <a href="<?php echo admin_url('admin.php?page=wc-sgtm-webhook'); ?>">Verificar logs</a>
                </p>
            </div>
            <?php
        });
    }

    // Verificar se webhook está desabilitado
    if (!defined('SGTM_WEBHOOK_ENABLED') || !SGTM_WEBHOOK_ENABLED) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-info is-dismissible">
                <p><strong>ℹ️ SGTM Webhook:</strong>
                Webhook está desabilitado.
                <a href="<?php echo admin_url('admin.php?page=wc-sgtm-webhook'); ?>">Configurar</a>
                </p>
            </div>
            <?php
        });
    }
}

function wc_sgtm_get_recent_error_count() {
    global $wpdb;

    $last_24h = date('Y-m-d H:i:s', strtotime('-24 hours'));

    $error_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s
        AND pm.meta_key = '_sgtm_webhook_error'
        AND pm.meta_value != ''
    ", $last_24h));

    return (int)$error_count;
}

/**
 * ========================================
 * AJAX HANDLERS PARA AÇÕES RÁPIDAS
 * ========================================
 */
// O handler AJAX já está definido em cima: add_action('wp_ajax_wc_sgtm_reenviar_webhook', 'wc_sgtm_ajax_reenviar_webhook');

function wc_sgtm_ajax_reenviar_webhook() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_sgtm_reenviar') || !current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => 'Acesso negado'), 403);
    }

    $order_id = intval($_POST['order_id']);

    if (!$order_id) {
        wp_send_json_error(array('message' => 'ID do pedido inválido'), 400);
    }

    // Remover flags de webhook anterior
    delete_post_meta($order_id, '_sgtm_webhook_sent');
    delete_post_meta($order_id, '_sgtm_webhook_error');
    delete_post_meta($order_id, '_sgtm_webhook_response');

    // Reenviar webhook
    wc_sgtm_enviar_webhook_pedido_pago($order_id);

    wp_send_json_success(array(
        'success' => true,
        'message' => 'Webhook reenviado com sucesso!'
    ));
}

// AJAX para obter status em tempo real
add_action('wp_ajax_wc_sgtm_get_status', 'wc_sgtm_ajax_get_status');

function wc_sgtm_ajax_get_status() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => 'Acesso negado'), 403);
    }

    $stats = wc_sgtm_get_webhook_statistics();
    $webhook_enabled = defined('SGTM_WEBHOOK_ENABLED') ? SGTM_WEBHOOK_ENABLED : false;

    wp_send_json_success(array(
        'enabled' => $webhook_enabled,
        'stats' => $stats,
        'connectivity' => wc_sgtm_test_connectivity()
    ));
}

/**
 * ========================================
 * METABOX NO PEDIDO INDIVIDUAL
 * ========================================
 */
add_action('add_meta_boxes', 'wc_sgtm_add_order_metabox');

function wc_sgtm_add_order_metabox() {
    add_meta_box(
        'wc_sgtm_order_webhook',
        '🚀 SGTM Webhook Status',
        'wc_sgtm_order_metabox_content',
        'shop_order',
        'side',
        'default'
    );
}

function wc_sgtm_order_metabox_content($post) {
    $order_id = $post->ID;
    $webhook_sent = get_post_meta($order_id, '_sgtm_webhook_sent', true);
    $webhook_error = get_post_meta($order_id, '_sgtm_webhook_error', true);
    $webhook_response = get_post_meta($order_id, '_sgtm_webhook_response', true);
    $reenviar_nonce = wp_create_nonce('wc_sgtm_reenviar');
    ?>
    <div class="wc-sgtm-order-status">
        <?php if ($webhook_sent): ?>
            <p><strong>Status:</strong> <span style="color: green;">✅ Enviado</span></p>
            <p><strong>Data:</strong> <?php echo date_i18n('d/m/Y H:i:s', strtotime($webhook_sent)); ?></p>

            <?php if ($webhook_response): ?>
                <p><strong>Resposta:</strong>
                    <span style="color: <?php echo $webhook_response['code'] < 300 ? 'green' : 'red'; ?>;">
                        HTTP <?php echo $webhook_response['code']; ?>
                    </span>
                </p>
            <?php endif; ?>

        <?php elseif ($webhook_error): ?>
            <p><strong>Status:</strong> <span style="color: red;">❌ Erro</span></p>
            <p><strong>Erro:</strong> <?php echo esc_html($webhook_error['error']); ?></p>
            <p><strong>Data:</strong> <?php echo date_i18n('d/m/Y H:i:s', strtotime($webhook_error['timestamp'])); ?></p>

        <?php else: ?>
            <p><strong>Status:</strong> <span style="color: orange;">⏳ Pendente</span></p>

        <?php endif; ?>

        <div style="margin-top: 10px;">
            <button type="button" class="button button-secondary" onclick="wc_sgtm_reenviar_pedido_webhook(<?php echo $order_id; ?>)">
                🔄 Reenviar Webhook
            </button>
        </div>

        <div style="margin-top: 10px; font-size: 11px; color: #666;">
            <a href="<?php echo admin_url('admin.php?page=wc-sgtm-webhook'); ?>" target="_blank">
                Ver painel completo
            </a>
        </div>
    </div>

    <script>
    function wc_sgtm_reenviar_pedido_webhook(order_id) {
        if (!confirm('Reenviar webhook para este pedido?')) return;

        jQuery.post(ajaxurl, {
            action: 'wc_sgtm_reenviar_webhook',
            order_id: order_id,
            nonce: '<?php echo $reenviar_nonce; ?>'
        }, function(response) {
            if (response.success) {
                alert('Webhook reenviado!');
            } else {
                alert('Erro ao reenviar webhook: ' + response.data.message);
            }
            location.reload();
        }).fail(function() {
            alert('Erro ao reenviar webhook!');
        });
    }
    </script>

    <style>
    .wc-sgtm-order-status p {
        margin: 5px 0;
    }
    .wc-sgtm-order-status .button {
        width: 100%;
    }
    </style>
    <?php
}

/**
 * ========================================
 * RELATÓRIO DE PERFORMANCE SEMANAL
 * ========================================
 */
add_action('wp_loaded', 'wc_sgtm_schedule_weekly_report');

function wc_sgtm_schedule_weekly_report() {
    if (!wp_next_scheduled('wc_sgtm_weekly_report')) {
        wp_schedule_event(time(), 'weekly', 'wc_sgtm_weekly_report');
    }
}

add_action('wc_sgtm_weekly_report', 'wc_sgtm_send_weekly_report');

function wc_sgtm_send_weekly_report() {
    if (!defined('SGTM_WEBHOOK_ENABLED') || !SGTM_WEBHOOK_ENABLED) {
        return; // Não enviar relatório se webhook está desabilitado
    }

    $stats = wc_sgtm_get_weekly_statistics();
    $admin_email = get_option('admin_email');

    $subject = '[' . get_bloginfo('name') . '] Relatório Semanal SGTM Webhook';

    $message = "Relatório Semanal - SGTM Webhook\n";
    $message .= "=====================================\n\n";
    $message .= "Período: " . date_i18n('d/m/Y', strtotime('-7 days')) . " a " . date_i18n('d/m/Y') . "\n\n";
    $message .= "📊 ESTATÍSTICAS:\n";
    $message .= "• Webhooks enviados: " . number_format($stats['sent']) . "\n";
    $message .= "• Erros registrados: " . number_format($stats['errors']) . "\n";
    $message .= "• Taxa de sucesso: " . $stats['success_rate'] . "%\n";
    $message .= "• Total processado: R$ " . number_format($stats['revenue'], 2, ',', '.') . "\n\n";
    $message .= "🔗 Ver painel completo: " . admin_url('admin.php?page=wc-sgtm-webhook') . "\n";

    wp_mail($admin_email, $subject, $message);
}

function wc_sgtm_get_weekly_statistics() {
    global $wpdb;

    $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));

    $sent = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s
        AND pm.meta_key = '_sgtm_webhook_sent'
    ", $seven_days_ago));

    $errors = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s
        AND pm.meta_key = '_sgtm_webhook_error'
    ", $seven_days_ago));

    $revenue = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(pm2.meta_value)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s
        AND pm.meta_key = '_sgtm_webhook_sent'
        AND pm2.meta_key = '_order_total'
    ", $seven_days_ago));

    $total = (int)$sent + (int)$errors;
    $success_rate = $total > 0 ? round((($sent / $total) * 100), 1) : 100;

    return array(
        'sent' => (int)$sent,
        'errors' => (int)$errors,
        'success_rate' => $success_rate,
        'revenue' => (float)$revenue ?: 0
    );
}

/**
 * ========================================
 * LIMPEZA AUTOMÁTICA DE LOGS ANTIGOS
 * ========================================
 */
add_action('wp_loaded', 'wc_sgtm_schedule_log_cleanup');

function wc_sgtm_schedule_log_cleanup() {
    if (!wp_next_scheduled('wc_sgtm_cleanup_logs')) {
        wp_schedule_event(time(), 'daily', 'wc_sgtm_cleanup_logs');
    }
}

add_action('wc_sgtm_cleanup_logs', 'wc_sgtm_cleanup_old_logs');

function wc_sgtm_cleanup_old_logs() {
    if (!function_exists('wc_get_logger') || !defined('WC_LOG_DIR')) {
        return;
    }

    $log_files = glob(WC_LOG_DIR . 'sgtm-webhook-*.log');
    $thirty_days_ago = time() - (30 * 24 * 60 * 60);

    foreach ($log_files as $file) {
        if (is_file($file) && filemtime($file) < $thirty_days_ago) {
            unlink($file);
        }
    }
}

// Desagendar eventos ao desativar (função chamada no main file)
function wc_sgtm_deactivate_scheduled_events() {
    wp_clear_scheduled_hook('wc_sgtm_weekly_report');
    wp_clear_scheduled_hook('wc_sgtm_cleanup_logs');
}

/**
 * ========================================
 * INFORMAÇÕES FINAIS
 * ========================================
 */
if (defined('SGTM_DEBUG_MODE') && SGTM_DEBUG_MODE) {
    wc_sgtm_log_debug('Painel Admin SGTM Webhook carregado completamente');
}