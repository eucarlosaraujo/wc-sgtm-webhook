<?php
/**
 * Core webhook functionality
 *
 * @package WC_SGTM_Webhook
 * @version 3.0.0
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
        
        // Log rotation (daily)
        if (!wp_next_scheduled('wc_sgtm_clear_old_logs')) {
            wp_schedule_event(time(), 'daily', 'wc_sgtm_clear_old_logs');
        }
        add_action('wc_sgtm_clear_old_logs', array('WC_SGTM_Helpers', 'clear_old_logs'));
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
            
            // Prepare order data
            $order_data = $this->prepare_order_data($order);
            
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
     * Prepare order data payload
     */
    private function prepare_order_data($order) {
        if (!$order) {
            return false;
        }
        
        $order_id = $order->get_id();
        
        return array(
            'client_name' => 'Data Client',
            'event_name' => 'purchase',
            'event_time' => $order->get_date_created()->getTimestamp(),
            'event_id' => 'wc_' . $order_id . '_' . time(),
            'action_source' => 'website',
            'event_source_url' => $order->get_checkout_order_received_url(),
            'user_data' => $this->prepare_user_data($order),
            'custom_data' => $this->prepare_custom_data($order),
            'metadata' => array(
                'source' => 'woocommerce',
                'plugin_version' => WC_SGTM_VERSION,
                'site_url' => get_site_url(),
                'order_status' => $order->get_status(),
                'payment_method' => $order->get_payment_method_title(),
                'order_date' => $order->get_date_created()->format('c')
            )
        );
    }
    
    /**
     * Prepare user data (with hashing for PII)
     */
    private function prepare_user_data($order) {
        $user_data = array();
        
        // Email (hashed and plain)
        if ($email = $order->get_billing_email()) {
            $clean_email = strtolower(trim($email));
            $user_data['em'] = array(WC_SGTM_Helpers::hash_pii($clean_email));
            $user_data['email_address'] = $clean_email;
        }
        
        // Phone (hashed and plain)
        if ($phone = $order->get_billing_phone()) {
            $clean_phone = WC_SGTM_Helpers::format_phone($phone);
            if (strlen($clean_phone) >= 10) {
                $user_data['ph'] = array(WC_SGTM_Helpers::hash_pii($clean_phone));
                $user_data['phone_number'] = $clean_phone;
            }
        }
        
        // First name
        if ($first_name = $order->get_billing_first_name()) {
            $user_data['fn'] = array(WC_SGTM_Helpers::hash_pii($first_name));
            $user_data['first_name'] = trim($first_name);
        }
        
        // Last name
        if ($last_name = $order->get_billing_last_name()) {
            $user_data['ln'] = array(WC_SGTM_Helpers::hash_pii($last_name));
            $user_data['last_name'] = trim($last_name);
        }
        
        // City
        if ($city = $order->get_billing_city()) {
            $user_data['ct'] = array(WC_SGTM_Helpers::hash_pii($city));
            $user_data['city'] = trim($city);
        }
        
        // State
        if ($state = $order->get_billing_state()) {
            $user_data['st'] = array(WC_SGTM_Helpers::hash_pii($state));
            $user_data['state'] = trim($state);
        }
        
        // ZIP code
        if ($postcode = $order->get_billing_postcode()) {
            $clean_zip = WC_SGTM_Helpers::format_zip($postcode);
            if (strlen($clean_zip) === 8) {
                $user_data['zp'] = array(WC_SGTM_Helpers::hash_pii($clean_zip));
                $user_data['zip_code'] = $clean_zip;
            }
        }
        
        // Country
        if ($country = $order->get_billing_country()) {
            $user_data['country'] = array(WC_SGTM_Helpers::hash_pii($country));
            $user_data['country_code'] = trim($country);
        }
        
        // External ID (user ID if logged in)
        if ($user_id = $order->get_user_id()) {
            $user_data['external_id'] = array(WC_SGTM_Helpers::hash_pii(strval($user_id)));
            $user_data['user_id'] = intval($user_id);
            
            $user = get_user_by('ID', $user_id);
            if ($user) {
                $user_data['username'] = $user->user_login;
                $user_data['user_registered'] = $user->user_registered;
            }
        }
        
        // Customer type
        $user_data['user_type'] = $user_id ? 'registered' : 'guest';
        
        // Company (if provided)
        $user_data['billing_company'] = $order->get_billing_company() ?: '';
        
        return array_filter($user_data);
    }
    
    /**
     * Prepare custom data (order details)
     */
    private function prepare_custom_data($order) {
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
        
        // Coupons
        $coupons = $order->get_coupon_codes();
        if (!empty($coupons)) {
            $custom_data['coupon'] = implode(', ', $coupons);
        }
        
        // Process order items
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if (!$product) continue;
            
            $product_id = $product->get_id();
            $quantity = intval($item->get_quantity());
            $price = floatval($order->get_item_subtotal($item, false, false));
            
            // Product IDs
            $custom_data['content_ids'][] = strval($product_id);
            
            // Product names
            $custom_data['content_names'][] = $product->get_name();
            
            // Categories
            $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
            $category_name = !empty($categories) ? $categories[0] : '';
            if (!empty($category_name)) {
                $custom_data['content_category'][] = $category_name;
            }
            
            // Detailed item data
            $custom_data['contents'][] = array(
                'id' => strval($product_id),
                'name' => $product->get_name(),
                'category' => $category_name,
                'quantity' => $quantity,
                'item_price' => $price,
                'brand' => $this->get_product_brand($product),
                'sku' => $product->get_sku() ?: strval($product_id)
            );
        }
        
        // Ensure unique categories
        $custom_data['content_category'] = array_unique(array_filter($custom_data['content_category']));
        
        return $custom_data;
    }
    
    /**
     * Get product brand from various sources
     */
    private function get_product_brand($product) {
        $brand = '';
        
        // Check for brand attribute
        if (method_exists($product, 'get_attribute')) {
            $brand = $product->get_attribute('pa_marca');
            if (empty($brand)) {
                $brand = $product->get_attribute('marca');
            }
        }
        
        // Check for brand taxonomy
        if (empty($brand) && $product->get_id()) {
            $taxonomies = array('product_brand', 'pa_brand', 'yith_product_brand');
            foreach ($taxonomies as $tax) {
                $terms = wp_get_post_terms($product->get_id(), $tax, array('fields' => 'names'));
                if (!empty($terms) && !is_wp_error($terms)) {
                    $brand = $terms[0];
                    break;
                }
            }
        }
        
        return $brand ?: '';
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
