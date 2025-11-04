<?php
/**
 * Core webhook functionality - Meta Ads Format
 *
 * @package WC_SGTM_Webhook
 * @version 3.1.0
 */

if (!defined('ABSPATH')) exit;

class WC_SGTM_Core {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // WooCommerce order status hooks
        add_action('woocommerce_order_status_completed', array($this, 'send_webhook'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'send_webhook'), 10, 1);
        add_action('woocommerce_payment_complete', array($this, 'send_webhook'), 10, 1);
        
        // Capture user agent at checkout
        add_action('woocommerce_checkout_create_order', array($this, 'save_customer_user_agent'), 10, 2);
        
        // Log rotation (daily)
        if (!wp_next_scheduled('wc_sgtm_clear_old_logs')) {
            wp_schedule_event(time(), 'daily', 'wc_sgtm_clear_old_logs');
        }
        add_action('wc_sgtm_clear_old_logs', array('WC_SGTM_Helpers', 'clear_old_logs'));
    }
    
    /**
     * Save customer user agent when order is created
     */
    public function save_customer_user_agent($order, $data) {
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $order->update_meta_data('_customer_user_agent', sanitize_text_field($_SERVER['HTTP_USER_AGENT']));
        }
    }
    
    /**
     * Send webhook for order
     */
    public function send_webhook($order_id) {
        if (!WC_SGTM_Helpers::is_webhook_enabled()) {
            WC_SGTM_Helpers::log_debug('Webhook desabilitado');
            return;
        }
        
        $endpoint = WC_SGTM_Helpers::build_endpoint();
        if (empty($endpoint)) {
            WC_SGTM_Helpers::log_error('Endpoint não configurado');
            return;
        }
        
        try {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                WC_SGTM_Helpers::log_error('Pedido não encontrado: ' . $order_id);
                return;
            }
            
            // Check if already sent
            $webhook_sent = get_post_meta($order_id, '_sgtm_webhook_sent', true);
            if ($webhook_sent) {
                WC_SGTM_Helpers::log_debug('Webhook já enviado para pedido ' . $order_id . ' em ' . $webhook_sent);
                return;
            }
            
            // Check if order is paid
            if (!$order->is_paid()) {
                WC_SGTM_Helpers::log_debug('Pedido ' . $order_id . ' não está pago ainda (status: ' . $order->get_status() . ')');
                return;
            }
            
            // Prepare order data in Meta Ads format
            $order_data = $this->prepare_order_data_metaads($order);
            
            if (empty($order_data)) {
                WC_SGTM_Helpers::log_error('Erro ao preparar dados do pedido ' . $order_id);
                return;
            }
            
            WC_SGTM_Helpers::log_info('Enviando webhook para pedido ' . $order_id);
            
            // Send webhook
            $response = $this->send_data($order_data);
            
            // Process response
            $this->process_response($response, $order_id);
            
        } catch (Exception $e) {
            WC_SGTM_Helpers::log_error('Exceção ao processar pedido ' . $order_id . ': ' . $e->getMessage());
            
            update_post_meta($order_id, '_sgtm_webhook_error', array(
                'timestamp' => current_time('mysql'),
                'type' => 'exception',
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Prepare order data in Meta Ads format
     */
    private function prepare_order_data_metaads($order) {
        if (!$order) {
            return false;
        }
        
        return array(
            'event_name' => 'Purchase', // PascalCase
            'event_time' => $order->get_date_created()->getTimestamp(),
            'action_source' => 'website',
            'event_source_url' => $order->get_checkout_order_received_url(),
            'user_data' => $this->prepare_user_data_metaads($order),
            'custom_data' => $this->prepare_custom_data_metaads($order)
        );
    }
    
    /**
     * Prepare user data in Meta Ads format
     */
    private function prepare_user_data_metaads($order) {
        $user_data = array();
        
        // Email - lowercase
        if ($email = $order->get_billing_email()) {
            $user_data['em'] = strtolower(trim($email));
        }
        
        // Phone - adicionar 55 se não existir
        if ($phone = $order->get_billing_phone()) {
            $clean_phone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($clean_phone) >= 10) {
                // Adicionar 55 se não começar com ele
                if (!preg_match('/^55/', $clean_phone)) {
                    $clean_phone = '55' . $clean_phone;
                }
                $user_data['ph'] = $clean_phone;
            }
        }
        
        // First name - apenas primeiro nome, lowercase
        if ($first_name = $order->get_billing_first_name()) {
            $names = explode(' ', trim($first_name));
            $user_data['fn'] = strtolower($names[0]);
        }
        
        // Last name - apenas último nome, lowercase
        if ($last_name = $order->get_billing_last_name()) {
            $names = explode(' ', trim($last_name));
            $user_data['ln'] = strtolower(end($names));
        }
        
        // City - sem espaços, sem acentos, lowercase
        if ($city = $order->get_billing_city()) {
            $user_data['ct'] = $this->normalize_city($city);
        }
        
        // State - lowercase
        if ($state = $order->get_billing_state()) {
            $user_data['st'] = strtolower(trim($state));
        }
        
        // Country - lowercase
        if ($country = $order->get_billing_country()) {
            $user_data['country'] = strtolower(trim($country));
        }
        
        // ZIP - apenas 5 primeiros dígitos
        if ($postcode = $order->get_billing_postcode()) {
            $clean_zip = preg_replace('/[^0-9]/', '', $postcode);
            $user_data['zp'] = substr($clean_zip, 0, 5);
        }
        
        // Gender - padrão "m"
        $user_data['ge'] = $this->guess_gender($order->get_billing_first_name());
        
        // Client IP Address - do WooCommerce order
        $user_data['client_ip_address'] = $order->get_customer_ip_address() ?: '';
        
        // Client User Agent - do meta do pedido
        $user_data['client_user_agent'] = $order->get_meta('_customer_user_agent', true) ?: '';
        
        return array_filter($user_data);
    }
    
    /**
     * Normalize city name (remove accents, spaces, lowercase)
     */
    private function normalize_city($city_name) {
        $result = '';
        $length = mb_strlen($city_name, 'UTF-8');
        
        for ($i = 0; $i < $length; $i++) {
            $current = mb_substr($city_name, $i, 1, 'UTF-8');
            $next = ($i + 1 < $length) ? mb_substr($city_name, $i + 1, 1, 'UTF-8') : '';
            $pair = $current . $next;
            
            // Mapa de substituições para acentos UTF-8
            if ($pair === 'ã' || $pair === 'á' || $pair === 'à' || $pair === 'â') {
                $result .= 'a';
                $i++;
            } elseif ($pair === 'é' || $pair === 'ê') {
                $result .= 'e';
                $i++;
            } elseif ($pair === 'í') {
                $result .= 'i';
                $i++;
            } elseif ($pair === 'ó' || $pair === 'ô' || $pair === 'õ') {
                $result .= 'o';
                $i++;
            } elseif ($pair === 'ú' || $pair === 'ü') {
                $result .= 'u';
                $i++;
            } elseif ($pair === 'ç') {
                $result .= 'c';
                $i++;
            } elseif ($current !== ' ') {
                // Não é espaço, adiciona normalmente
                $result .= $current;
            }
            // Se for espaço, não adiciona nada (remove)
        }
        
        return strtolower($result);
    }
    
    /**
     * Guess gender from first name (default: "m")
     */
    private function guess_gender($first_name) {
        if (empty($first_name)) {
            return 'm';
        }
        
        // Lista de nomes femininos comuns
        $female_names = array(
            'maria', 'ana', 'julia', 'juliana', 'fernanda', 'patricia',
            'carla', 'mariana', 'camila', 'amanda', 'jessica', 'bruna',
            'leticia', 'rafaela', 'gabriela', 'adriana', 'luciana', 'renata',
            'paula', 'sandra', 'monica', 'daniela', 'vanessa', 'tatiana'
        );
        
        $name_lower = strtolower(trim($first_name));
        $first_word = explode(' ', $name_lower)[0];
        
        return in_array($first_word, $female_names) ? 'f' : 'm';
    }
    
    /**
     * Prepare custom data in Meta Ads format
     */
    private function prepare_custom_data_metaads($order) {
        $contents = array();
        $content_names = array();
        $total_items = 0;
        
        // Processar itens do pedido
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if (!$product) continue;
            
            $product_id = $product->get_id();
            $quantity = intval($item->get_quantity());
            $price = floatval($order->get_item_subtotal($item, false, false));
            
            // Buscar categoria do produto
            $category = $this->get_product_category($product_id);
            
            // Adicionar ao contents
            $contents[] = array(
                'id' => strval($product_id),
                'title' => $product->get_name(),
                'quantity' => $quantity,
                'item_price' => $price,
                'category' => $category
            );
            
            // Adicionar nome ao content_name
            $content_names[] = $product->get_name();
            
            // Somar quantidade
            $total_items += $quantity;
        }
        
        $custom_data = array(
            'content_type' => 'product_group', // Fixo
            'content_ids' => wp_list_pluck($contents, 'id'),
            'contents' => $contents,
            'content_name' => implode(', ', $content_names),
            'value' => floatval($order->get_total()),
            'currency' => 'BRL',
            'transaction_id' => strval($order->get_id()), // String
            'num_items' => $total_items // Soma das quantidades
        );
        
        return $custom_data;
    }
    
    /**
     * Get product category from WooCommerce
     */
    private function get_product_category($product_id) {
        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
        
        if (!empty($categories) && !is_wp_error($categories)) {
            return $categories[0]; // Primeira categoria
        }
        
        return '';
    }
    
    /**
     * Send data to webhook endpoint
     */
    private function send_data($data) {
        $endpoint = WC_SGTM_Helpers::build_endpoint();
        
        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'WooCommerce-SGTM-Webhook/' . WC_SGTM_VERSION,
            'Accept' => 'application/json'
        );
        
        // Add authorization header if token is configured
        $token = WC_SGTM_Helpers::get_webhook_token();
        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        
        $args = array(
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'headers' => $headers,
            'timeout' => 30,
            'httpversion' => '1.1',
            'sslverify' => true,
            'blocking' => true
        );
        
        if (WC_SGTM_Helpers::is_debug_mode()) {
            WC_SGTM_Helpers::log_debug('Enviando POST para: ' . $endpoint);
            WC_SGTM_Helpers::log_debug('Tamanho payload: ' . strlen($args['body']) . ' bytes');
            WC_SGTM_Helpers::log_debug('Payload: ' . $args['body']);
        }
        
        return wp_remote_post($endpoint, $args);
    }
    
    /**
     * Process webhook response
     */
    private function process_response($response, $order_id) {
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            WC_SGTM_Helpers::log_error('Erro de conexão no pedido ' . $order_id . ': ' . $error_message);
            
            update_post_meta($order_id, '_sgtm_webhook_error', array(
                'timestamp' => current_time('mysql'),
                'type' => 'connection_error',
                'error' => $error_message
            ));
            
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        WC_SGTM_Helpers::log_info('Resposta webhook pedido ' . $order_id . ' - HTTP ' . $code);
        
        // Save response
        update_post_meta($order_id, '_sgtm_webhook_response', array(
            'code' => $code,
            'body' => substr($body, 0, 500)
        ));
        
        if ($code >= 200 && $code < 300) {
            // Success
            update_post_meta($order_id, '_sgtm_webhook_sent', current_time('mysql'));
            delete_post_meta($order_id, '_sgtm_webhook_error');
            
            WC_SGTM_Helpers::log_info('Webhook enviado com sucesso para pedido ' . $order_id);
            return true;
        } else {
            // Error
            WC_SGTM_Helpers::log_error('Erro HTTP ' . $code . ' no pedido ' . $order_id);
            
            update_post_meta($order_id, '_sgtm_webhook_error', array(
                'timestamp' => current_time('mysql'),
                'type' => 'http_error',
                'code' => $code,
                'body' => substr($body, 0, 500)
            ));
            
            return false;
        }
    }
}