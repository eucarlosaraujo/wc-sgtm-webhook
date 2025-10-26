<?php
/**
 * Funções auxiliares para o plugin WooCommerce SGTM Webhook
 *
 * @package WC_SGTM_Webhook
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Registrar evento no log do WordPress
 *
 * @param string $message Mensagem a ser registrada
 * @param string $level Nível do log (info, warning, error)
 * @return void
 */
function wc_sgtm_webhook_log($message, $level = 'info') {
    if (!function_exists('wc_get_logger')) {
        return;
    }

    $logger = wc_get_logger();
    $context = array('source' => 'wc-sgtm-webhook');

    switch ($level) {
        case 'error':
            $logger->error($message, $context);
            break;
        case 'warning':
            $logger->warning($message, $context);
            break;
        case 'info':
        default:
            $logger->info($message, $context);
            break;
    }
}

/**
 * Verificar se um evento específico está ativado nas configurações
 *
 * @param string $event_key Chave do evento
 * @return bool Verdadeiro se o evento estiver ativado
 */
function wc_sgtm_webhook_is_event_enabled($event_key) {
    $events = get_option('wc_sgtm_webhook_events', array());
    return in_array($event_key, $events);
}

/**
 * Obter dados do cliente para rastreamento
 *
 * @return array Dados do cliente
 */
function wc_sgtm_webhook_get_client_data() {
    $client_data = array(
        'ip' => wc_sgtm_webhook_get_client_ip(),
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
        'referrer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
    );

    // Adicionar ID do cliente se disponível
    if (isset($_COOKIE['_ga'])) {
        $client_data['ga_client_id'] = sanitize_text_field($_COOKIE['_ga']);
    }

    return $client_data;
}

/**
 * Obter endereço IP do cliente
 *
 * @return string Endereço IP
 */
function wc_sgtm_webhook_get_client_ip() {
    $ip_keys = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    );

    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
            return sanitize_text_field($_SERVER[$key]);
        }
    }

    return '127.0.0.1'; // Fallback para localhost
}

/**
 * Formatar dados do produto para envio
 *
 * @param WC_Product $product Objeto do produto
 * @param int $quantity Quantidade
 * @param float $total Total
 * @return array Dados formatados
 */
function wc_sgtm_webhook_format_product_data($product, $quantity = 1, $total = null) {
    if (!$product) {
        return array();
    }

    $product_data = array(
        'id' => $product->get_id(),
        'name' => $product->get_name(),
        'price' => (float) $product->get_price(),
        'quantity' => (int) $quantity,
        'total' => is_null($total) ? ((float) $product->get_price() * (int) $quantity) : (float) $total,
        'sku' => $product->get_sku(),
        'categories' => array(),
    );

    // Adicionar categorias
    $terms = get_the_terms($product->get_id(), 'product_cat');
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $product_data['categories'][] = $term->name;
        }
    }

    return $product_data;
}

/**
 * Testar conexão com o endpoint SGTM
 *
 * @param string $endpoint URL do endpoint
 * @param string $auth_key Chave de autenticação (opcional)
 * @return array Resultado do teste
 */
function wc_sgtm_webhook_test_connection($endpoint, $auth_key = '') {
    $headers = array(
        'Content-Type' => 'application/json',
    );

    if (!empty($auth_key)) {
        $headers['Authorization'] = 'Bearer ' . $auth_key;
    }

    $test_data = array(
        'event' => 'test_connection',
        'timestamp' => time(),
        'source' => 'wc-sgtm-webhook',
        'version' => WC_SGTM_WEBHOOK_VERSION,
    );

    $response = wp_remote_post(
        $endpoint,
        array(
            'method' => 'POST',
            'timeout' => 15,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => $headers,
            'body' => wp_json_encode($test_data),
            'cookies' => array(),
        )
    );

    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => $response->get_error_message(),
        );
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code >= 200 && $response_code < 300) {
        return array(
            'success' => true,
            'message' => 'Conexão bem-sucedida',
            'response_code' => $response_code,
            'response_body' => $response_body,
        );
    } else {
        return array(
            'success' => false,
            'message' => 'Erro na conexão: HTTP ' . $response_code,
            'response_code' => $response_code,
            'response_body' => $response_body,
        );
    }
}

/**
 * Obter contagem de webhooks enviados nos últimos dias
 *
 * @param int $days Número de dias para filtrar
 * @return int Contagem de webhooks
 */
function wc_sgtm_webhook_get_sent_count($days = 30) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wc_sgtm_webhook_logs';
    $date_limit = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
    
    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE date_created >= %s",
        $date_limit
    );
    
    return (int) $wpdb->get_var($query);
}

/**
 * Obter contagem de erros de hoje
 *
 * @return int Contagem de erros
 */
function wc_sgtm_webhook_get_errors_today() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wc_sgtm_webhook_logs';
    $today_start = date('Y-m-d 00:00:00');
    
    $query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE status = 'error' AND date_created >= %s",
        $today_start
    );
    
    return (int) $wpdb->get_var($query);
}

/**
 * Obter taxa de sucesso de hoje
 *
 * @return float Taxa de sucesso (0-100)
 */
function wc_sgtm_webhook_get_success_rate() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wc_sgtm_webhook_logs';
    $today_start = date('Y-m-d 00:00:00');
    
    $total_query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE date_created >= %s",
        $today_start
    );
    
    $success_query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE status = 'success' AND date_created >= %s",
        $today_start
    );
    
    $total = (int) $wpdb->get_var($total_query);
    $success = (int) $wpdb->get_var($success_query);
    
    if ($total === 0) {
        return 100; // Se não houver envios, consideramos 100% de sucesso
    }
    
    return round(($success / $total) * 100, 2);
}

/**
 * Obter informações do último envio
 *
 * @return array|null Informações do último envio ou null se não houver
 */
function wc_sgtm_webhook_get_last_sent() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wc_sgtm_webhook_logs';
    
    $query = "SELECT * FROM $table_name ORDER BY date_created DESC LIMIT 1";
    
    return $wpdb->get_row($query, ARRAY_A);
}

/**
 * Obter total processado nos últimos dias
 *
 * @param int $days Número de dias para filtrar
 * @return float Total processado
 */
function wc_sgtm_webhook_get_total_processed($days = 30) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wc_sgtm_webhook_logs';
    $date_limit = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
    
    $query = $wpdb->prepare(
        "SELECT SUM(order_total) FROM $table_name WHERE date_created >= %s AND status = 'success'",
        $date_limit
    );
    
    $total = $wpdb->get_var($query);
    
    return $total ? (float) $total : 0;
}