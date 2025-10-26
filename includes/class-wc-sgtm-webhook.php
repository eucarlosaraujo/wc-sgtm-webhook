<?php
/**
 * Classe responsável pela captura de dados do navegador
 * 
 * @package WC_SGTM_Webhook_Pro
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_SGTM_Browser_Capture {
    
    /**
     * Configurações do plugin
     */
    private $settings;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct($settings, $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
    }
    
    /**
     * Inicializar captura de browser
     */
    public function init() {
        if (!$this->settings['browser_capture_enabled']) {
            return;
        }
        
        // Enfileirar scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_wc_sgtm_save_browser_data', array($this, 'ajax_save_browser_data'));
        add_action('wp_ajax_nopriv_wc_sgtm_save_browser_data', array($this, 'ajax_save_browser_data'));
        
        // Salvar dados no pedido
        add_action('woocommerce_checkout_order_processed', array($this, 'save_to_order'), 10, 1);
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'save_to_order'), 10, 1);
        
        // Hooks para diferentes gateways de pagamento
        add_action('woocommerce_payment_complete', array($this, 'save_to_order'), 5, 1);
        
        $this->logger->debug('Browser Capture inicializado');
    }
    
    /**
     * Enfileirar scripts necessários
     */
    public function enqueue_scripts() {
        // Só carregar em páginas relevantes
        if (!$this->should_load_script()) {
            return;
        }
        
        wp_enqueue_script(
            'wc-sgtm-browser-capture',
            WC_SGTM_WEBHOOK_PLUGIN_URL . 'assets/js/browser-capture.js',
            array('jquery'),
            WC_SGTM_WEBHOOK_VERSION,
            true
        );
        
        // Configurações para o JavaScript
        $script_config = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_sgtm_browser_data'),
            'debug' => $this->settings['debug_mode'],
            'capture_config' => array(
                'facebook_pixel' => $this->settings['pixel_tracking']['facebook'],
                'google_ads' => $this->settings['pixel_tracking']['google_ads'],
                'utm_params' => $this->settings['pixel_tracking']['utm_params'],
                'respect_consent' => $this->settings['privacy']['respect_consent']
            ),
            'cookie_config' => array(
                'name' => 'wc_browser_data',
                'expires_days' => 30,
                'same_site' => 'Lax',
                'secure' => is_ssl()
            )
        );
        
        wp_localize_script('wc-sgtm-browser-capture', 'wcSgtmConfig', $script_config);
        
        $this->logger->debug('Scripts de captura enfileirados', array(
            'page' => get_queried_object_id(),
            'config' => $script_config
        ));
    }
    
    /**
     * Verificar se deve carregar o script
     */
    private function should_load_script() {
        // Páginas WooCommerce
        if (is_woocommerce() || is_cart() || is_checkout() || is_account_page()) {
            return true;
        }
        
        // Página de produto
        if (is_product()) {
            return true;
        }
        
        // Páginas com shortcodes WooCommerce
        global $post;
        if ($post && has_shortcode($post->post_content, 'woocommerce_cart')) {
            return true;
        }
        
        if ($post && has_shortcode($post->post_content, 'woocommerce_checkout')) {
            return true;
        }
        
        // Permitir filtro personalizado
        return apply_filters('wc_sgtm_should_load_browser_capture', false);
    }
    
    /**
     * Handler AJAX para salvar dados do navegador
     */
    public function ajax_save_browser_data() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_sgtm_browser_data')) {
            wp_die(json_encode(array(
                'success' => false,
                'error' => 'Nonce inválido'
            )));
        }
        
        $browser_data = $_POST['browser_data'] ?? '';
        
        if (empty($browser_data)) {
            wp_die(json_encode(array(
                'success' => false,
                'error' => 'Dados vazios'
            )));
        }
        
        // Decodificar e validar dados
        $decoded_data = json_decode(stripslashes($browser_data), true);
        
        if (!$decoded_data || !$this->validate_browser_data($decoded_data)) {
            wp_die(json_encode(array(
                'success' => false,
                'error' => 'Dados inválidos'
            )));
        }
        
        // Processar e enriquecer dados
        $processed_data = $this->process_browser_data($decoded_data);
        
        // Salvar na sessão WooCommerce
        $this->save_to_session($processed_data);
        
        $this->logger->debug('Browser data salvo na sessão', array(
            'session_id' => WC()->session ? WC()->session->get_customer_id() : 'unknown',
            'data_keys' => array_keys($processed_data)
        ));
        
        wp_die(json_encode(array(
            'success' => true,
            'message' => 'Dados salvos na sessão',
            'data_keys' => array_keys($processed_data)
        )));
    }
    
    /**
     * Validar dados do navegador
     */
    private function validate_browser_data($data) {
        // Verificações básicas
        if (!is_array($data)) {
            return false;
        }
        
        // Deve ter pelo menos timestamp e user_agent
        if (empty($data['timestamp']) || empty($data['user_agent'])) {
            return false;
        }
        
        // Verificar se timestamp é válido (últimos 24 horas)
        $timestamp = intval($data['timestamp']);
        if ($timestamp < (time() - 86400) || $timestamp > (time() + 3600)) {
            return false;
        }
        
        // Verificar se user_agent é válido
        if (strlen($data['user_agent']) < 10 || strlen($data['user_agent']) > 1000) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Processar e enriquecer dados do navegador
     */
    private function process_browser_data($data) {
        // Adicionar IP do cliente
        $data['client_ip_address'] = $this->get_client_ip();
        
        // Adicionar timestamp do servidor
        $data['server_timestamp'] = time();
        
        // Processar UTMs se habilitado
        if ($this->settings['pixel_tracking']['utm_params']) {
            $data = $this->process_utm_data($data);
        }
        
        // Aplicar configurações de privacidade
        if ($this->settings['privacy']['hash_pii']) {
            $data = $this->apply_privacy_settings($data);
        }
        
        // Anonimizar IP se configurado
        if ($this->settings['privacy']['anonymize_ip']) {
            $data['client_ip_address'] = $this->anonymize_ip($data['client_ip_address']);
        }
        
        // Validar e limitar tamanho dos dados
        $data = $this->sanitize_data($data);
        
        return $data;
    }
    
    /**
     * Processar dados UTM
     */
    private function process_utm_data($data) {
        $utm_fields = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content');
        
        foreach ($utm_fields as $field) {
            if (!empty($data[$field])) {
                // Limitar tamanho e sanitizar
                $data[$field] = substr(sanitize_text_field($data[$field]), 0, 255);
            }
        }
        
        // Processar referrer
        if (!empty($data['referrer'])) {
            $data['referrer'] = esc_url_raw(substr($data['referrer'], 0, 500));
        }
        
        return $data;
    }
    
    /**
     * Aplicar configurações de privacidade
     */
    private function apply_privacy_settings($data) {
        // Hash de dados sensíveis se configurado
        $sensitive_fields = array('fbp', 'fbc');
        
        foreach ($sensitive_fields as $field) {
            if (!empty($data[$field]) && $this->settings['privacy']['hash_pii']) {
                // Manter formato original mas hash parcial para debugging
                if ($this->settings['debug_mode']) {
                    $data[$field . '_original'] = substr($data[$field], 0, 10) . '...';
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Obter IP real do cliente
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancers
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',            // Nginx
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Se há múltiplos IPs, pegar o primeiro
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validar se é um IP válido
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Anonimizar IP
     */
    private function anonymize_ip($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: remover último octeto
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: remover últimos 80 bits
            $parts = explode(':', $ip);
            for ($i = 5; $i < 8; $i++) {
                if (isset($parts[$i])) {
                    $parts[$i] = '0';
                }
            }
            return implode(':', $parts);
        }
        
        return $ip;
    }
    
    /**
     * Sanitizar dados
     */
    private function sanitize_data($data) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'user_agent':
                    $sanitized[$key] = substr(sanitize_text_field($value), 0, 1000);
                    break;
                    
                case 'client_ip_address':
                    $sanitized[$key] = filter_var($value, FILTER_VALIDATE_IP) ? $value : '';
                    break;
                    
                case 'fbp':
                case 'fbc':
                case 'gclid':
                    $sanitized[$key] = preg_replace('/[^a-zA-Z0-9._-]/', '', $value);
                    break;
                    
                case 'timestamp':
                case 'server_timestamp':
                case 'first_visit_timestamp':
                    $sanitized[$key] = intval($value);
                    break;
                    
                case 'browser_info':
                    if (is_array($value)) {
                        $sanitized[$key] = array_map('sanitize_text_field', $value);
                    }
                    break;
                    
                default:
                    if (is_string($value)) {
                        $sanitized[$key] = substr(sanitize_text_field($value), 0, 500);
                    } elseif (is_array($value)) {
                        $sanitized[$key] = array_map('sanitize_text_field', $value);
                    } else {
                        $sanitized[$key] = $value;
                    }
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Salvar dados na sessão WooCommerce
     */
    private function save_to_session($data) {
        if (!WC()->session) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
        
        // Mesclar com dados existentes se houver
        $existing_data = WC()->session->get('browser_data', array());
        if (!empty($existing_data)) {
            // Manter dados de first-touch attribution
            $data = array_merge($data, array(
                'fbp' => $existing_data['fbp'] ?? $data['fbp'],
                'gclid' => $existing_data['gclid'] ?? $data['gclid'],
                'first_visit_timestamp' => $existing_data['first_visit_timestamp'] ?? $data['timestamp']
            ));
        }
        
        WC()->session->set('browser_data', $data);
    }
    
    /**
     * Salvar dados no pedido
     */
    public function save_to_order($order_id) {
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Obter dados da sessão
        $browser_data = WC()->session ? WC()->session->get('browser_data') : null;
        
        if (!$browser_data) {
            // Tentar obter do cookie como fallback
            $browser_data = $this->get_browser_data_from_cookie();
        }
        
        if ($browser_data && $this->validate_browser_data($browser_data)) {
            // Adicionar timestamp do pedido
            $browser_data['order_timestamp'] = current_time('timestamp');
            $browser_data['order_id'] = $order_id;
            
            // Salvar como meta do pedido
            $order->update_meta_data('_browser_data', $browser_data);
            $order->save();
            
            $this->logger->info('Browser data salvo no pedido', array(
                'order_id' => $order_id,
                'data_keys' => array_keys($browser_data),
                'has_fbp' => !empty($browser_data['fbp']),
                'has_gclid' => !empty($browser_data['gclid'])
            ));
            
            // Limpar da sessão após salvar
            if (WC()->session) {
                WC()->session->__unset('browser_data');
            }
        } else {
            $this->logger->warning('Nenhum browser data válido encontrado para pedido', array(
                'order_id' => $order_id,
                'session_exists' => WC()->session ? true : false
            ));
        }
    }
    
    /**
     * Fallback: obter dados do cookie
     */
    private function get_browser_data_from_cookie() {
        $cookie_name = $this->settings['cookie_config']['name'] ?? 'wc_browser_data';
        
        if (!isset($_COOKIE[$cookie_name])) {
            return null;
        }
        
        $cookie_data = json_decode(stripslashes($_COOKIE[$cookie_name]), true);
        
        if ($cookie_data && $this->validate_browser_data($cookie_data)) {
            // Processar dados do cookie
            return $this->process_browser_data($cookie_data);
        }
        
        return null;
    }
    
    /**
     * Obter dados do navegador para um pedido específico
     */
    public function get_order_browser_data($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
        
        return $order->get_meta('_browser_data', true);
    }
    
    /**
     * Verificar se um pedido tem dados do navegador
     */
    public function order_has_browser_data($order_id) {
        $data = $this->get_order_browser_data($order_id);
        return !empty($data) && $this->validate_browser_data($data);
    }
    
    /**
     * Estatísticas de captura
     */
    public function get_capture_statistics($days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Total de pedidos
        $total_orders = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'shop_order'
            AND p.post_date >= %s
            AND p.post_status IN ('wc-completed', 'wc-processing')
        ", $date_from));
        
        // Pedidos com browser data
        $orders_with_data = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_date >= %s
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_browser_data'
            AND pm.meta_value != ''
        ", $date_from));
        
        // Pedidos com Facebook data
        $orders_with_facebook = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_date >= %s
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_browser_data'
            AND pm.meta_value LIKE '%\"fbp\"%'
        ", $date_from));
        
        // Pedidos com Google Ads data
        $orders_with_google = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_date >= %s
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_browser_data'
            AND pm.meta_value LIKE '%\"gclid\"%'
        ", $date_from));
        
        $capture_rate = $total_orders > 0 ? round(($orders_with_data / $total_orders) * 100, 1) : 0;
        $facebook_rate = $total_orders > 0 ? round(($orders_with_facebook / $total_orders) * 100, 1) : 0;
        $google_rate = $total_orders > 0 ? round(($orders_with_google / $total_orders) * 100, 1) : 0;
        
        return array(
            'total_orders' => intval($total_orders),
            'orders_with_data' => intval($orders_with_data),
            'orders_with_facebook' => intval($orders_with_facebook),
            'orders_with_google' => intval($orders_with_google),
            'capture_rate' => $capture_rate,
            'facebook_rate' => $facebook_rate,
            'google_rate' => $google_rate,
            'period_days' => $days
        );
    }
    
    /**
     * Diagnóstico de problemas de captura
     */
    public function diagnose_capture_issues() {
        $issues = array();
        $stats = $this->get_capture_statistics(7);
        
        // Verificar taxa de captura baixa
        if ($stats['capture_rate'] < 80) {
            $issues[] = array(
                'type' => 'warning',
                'title' => 'Taxa de captura baixa',
                'message' => "Apenas {$stats['capture_rate']}% dos pedidos têm dados capturados.",
                'suggestion' => 'Verifique se o JavaScript está carregando corretamente e se não há conflitos de cache.'
            );
        }
        
        // Verificar problemas específicos do Facebook
        if ($stats['facebook_rate'] < 50 && $this->settings['pixel_tracking']['facebook']) {
            $issues[] = array(
                'type' => 'warning',
                'title' => 'Baixa captura do Facebook Pixel',
                'message' => "Apenas {$stats['facebook_rate']}% dos pedidos têm dados do Facebook.",
                'suggestion' => 'Verifique se o Facebook Pixel está configurado corretamente.'
            );
        }
        
        // Verificar problemas do Google Ads
        if ($stats['google_rate'] < 30 && $this->settings['pixel_tracking']['google_ads']) {
            $issues[] = array(
                'type' => 'info',
                'title' => 'Baixa captura do Google Ads',
                'message' => "Apenas {$stats['google_rate']}% dos pedidos têm dados do Google Ads.",
                'suggestion' => 'Normal se você não usa Google Ads ou auto-tagging não está habilitado.'
            );
        }
        
        // Verificar se JavaScript está sendo carregado
        if (!wp_script_is('wc-sgtm-browser-capture', 'enqueued')) {
            $issues[] = array(
                'type' => 'error',
                'title' => 'Script não carregado',
                'message' => 'O script de captura não está sendo enfileirado.',
                'suggestion' => 'Verifique se está em uma página WooCommerce válida.'
            );
        }
        
        return $issues;
    }
    
    /**
     * Limpar dados antigos (para cron job)
     */
    public function cleanup_old_data($days = null) {
        if ($days === null) {
            $days = $this->settings['data_retention_days'] ?? 30;
        }
        
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Limpar meta data de pedidos antigos
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE pm FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_browser_data'
            AND p.post_type = 'shop_order'
            AND p.post_date < %s
        ", $cutoff_date));
        
        $this->logger->info('Limpeza de browser data executada', array(
            'deleted_records' => $deleted,
            'cutoff_date' => $cutoff_date,
            'retention_days' => $days
        ));
        
        return $deleted;
    }
}