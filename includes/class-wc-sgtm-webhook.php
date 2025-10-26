<?php
/**
 * Classe principal do plugin WooCommerce SGTM Webhook
 *
 * @package WC_SGTM_Webhook
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Classe principal do plugin
 */
class WC_SGTM_Webhook {

    /**
     * Instância única da classe
     *
     * @var WC_SGTM_Webhook
     */
    private static $instance = null;

    /**
     * URL do endpoint SGTM
     *
     * @var string
     */
    private $sgtm_endpoint = '';

    /**
     * Chave de autenticação para o SGTM
     *
     * @var string
     */
    private $sgtm_auth_key = '';

    /**
     * Construtor
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_settings();
    }

    /**
     * Obter instância única da classe (Singleton)
     *
     * @return WC_SGTM_Webhook
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Hooks para eventos do WooCommerce
        add_action('woocommerce_new_order', array($this, 'handle_new_order'), 10, 2);
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_changed'), 10, 4);
        add_action('woocommerce_payment_complete', array($this, 'handle_payment_complete'), 10, 1);
        
        // Hooks para carrinho
        add_action('woocommerce_add_to_cart', array($this, 'handle_add_to_cart'), 10, 6);
        add_action('woocommerce_cart_item_removed', array($this, 'handle_remove_from_cart'), 10, 2);
        
        // Hooks para checkout
        add_action('woocommerce_checkout_update_order_meta', array($this, 'handle_checkout_update'), 10, 2);
    }

    /**
     * Carregar configurações do plugin
     */
    private function load_settings() {
        $this->sgtm_endpoint = get_option('wc_sgtm_webhook_endpoint', '');
        $this->sgtm_auth_key = get_option('wc_sgtm_webhook_auth_key', '');
    }

    /**
     * Manipular evento de novo pedido
     *
     * @param int $order_id ID do pedido
     * @param WC_Order $order Objeto do pedido
     */
    public function handle_new_order($order_id, $order = null) {
        if (is_null($order)) {
            $order = wc_get_order($order_id);
        }

        if (!$order) {
            return;
        }

        $data = $this->prepare_order_data($order, 'new_order');
        $this->send_webhook($data);
    }

    /**
     * Manipular evento de mudança de status do pedido
     *
     * @param int $order_id ID do pedido
     * @param string $status_from Status anterior
     * @param string $status_to Novo status
     * @param WC_Order $order Objeto do pedido
     */
    public function handle_order_status_changed($order_id, $status_from, $status_to, $order) {
        $data = $this->prepare_order_data($order, 'order_status_changed');
        $data['status_from'] = $status_from;
        $data['status_to'] = $status_to;
        
        $this->send_webhook($data);
    }

    /**
     * Manipular evento de pagamento completo
     *
     * @param int $order_id ID do pedido
     */
    public function handle_payment_complete($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        $data = $this->prepare_order_data($order, 'payment_complete');
        $this->send_webhook($data);
    }

    /**
     * Manipular evento de adição ao carrinho
     *
     * @param string $cart_item_key Chave do item no carrinho
     * @param int $product_id ID do produto
     * @param int $quantity Quantidade
     * @param int $variation_id ID da variação
     * @param array $variation Atributos da variação
     * @param array $cart_item_data Dados do item no carrinho
     */
    public function handle_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return;
        }

        $data = array(
            'event' => 'add_to_cart',
            'timestamp' => time(),
            'client_id' => $this->get_client_id(),
            'user_id' => get_current_user_id(),
            'product' => array(
                'id' => $product_id,
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'quantity' => $quantity,
                'variation_id' => $variation_id,
            ),
            'cart_item_key' => $cart_item_key,
        );

        $this->send_webhook($data);
    }

    /**
     * Manipular evento de remoção do carrinho
     *
     * @param string $cart_item_key Chave do item no carrinho
     * @param WC_Cart $cart Objeto do carrinho
     */
    public function handle_remove_from_cart($cart_item_key, $cart) {
        $data = array(
            'event' => 'remove_from_cart',
            'timestamp' => time(),
            'client_id' => $this->get_client_id(),
            'user_id' => get_current_user_id(),
            'cart_item_key' => $cart_item_key,
        );

        $this->send_webhook($data);
    }

    /**
     * Manipular evento de atualização do checkout
     *
     * @param int $order_id ID do pedido
     * @param array $posted_data Dados do formulário
     */
    public function handle_checkout_update($order_id, $posted_data) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        $data = $this->prepare_order_data($order, 'checkout_update');
        $this->send_webhook($data);
    }

    /**
     * Preparar dados do pedido para envio
     *
     * @param WC_Order $order Objeto do pedido
     * @param string $event_type Tipo de evento
     * @return array Dados formatados
     */
    private function prepare_order_data($order, $event_type) {
        $items = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'id' => $item->get_product_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $product ? $product->get_price() : 0,
                'total' => $item->get_total(),
            );
        }

        return array(
            'event' => $event_type,
            'timestamp' => time(),
            'client_id' => $this->get_client_id(),
            'order' => array(
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'currency' => $order->get_currency(),
                'total' => $order->get_total(),
                'subtotal' => $order->get_subtotal(),
                'shipping_total' => $order->get_shipping_total(),
                'tax_total' => $order->get_total_tax(),
                'discount_total' => $order->get_discount_total(),
                'payment_method' => $order->get_payment_method(),
                'payment_method_title' => $order->get_payment_method_title(),
                'customer' => array(
                    'id' => $order->get_customer_id(),
                    'email' => $order->get_billing_email(),
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                ),
                'items' => $items,
            ),
        );
    }

    /**
     * Enviar dados para o webhook SGTM
     *
     * @param array $data Dados a serem enviados
     * @return bool Sucesso ou falha
     */
    private function send_webhook($data) {
        // Verificar se o endpoint está configurado
        if (empty($this->sgtm_endpoint)) {
            return false;
        }
    
        // Adicionar informações de autenticação
        $headers = array(
            'Content-Type' => 'application/json',
        );
    
        if (!empty($this->sgtm_auth_key)) {
            $headers['Authorization'] = 'Bearer ' . $this->sgtm_auth_key;
        }
    
        // Enviar requisição
        $response = wp_remote_post(
            $this->sgtm_endpoint,
            array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => false,
                'headers' => $headers,
                'body' => wp_json_encode($data),
                'cookies' => array(),
            )
        );
    
        // Registrar erro se houver
        if (is_wp_error($response)) {
            error_log('WC SGTM Webhook Error: ' . $response->get_error_message());
            $this->log_webhook_event($data, 'error');
            return false;
        }
    
        // Registrar evento bem-sucedido
        $this->log_webhook_event($data, 'success');
        return true;
    }

    /**
     * Registrar evento de webhook no banco de dados
     *
     * @param array $data Dados enviados
     * @param string $status Status do envio (success/error)
     * @return void
     */
    private function log_webhook_event($data, $status = 'success') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_logs';
        
        $event_type = isset($data['event']) ? $data['event'] : 'unknown';
        $order_id = isset($data['order']['id']) ? $data['order']['id'] : null;
        $order_total = isset($data['order']['total']) ? $data['order']['total'] : null;
        
        $wpdb->insert(
            $table_name,
            array(
                'event_type' => $event_type,
                'order_id' => $order_id,
                'order_total' => $order_total,
                'status' => $status,
                'date_created' => current_time('mysql'),
            ),
            array('%s', '%d', '%f', '%s', '%s')
        );
    }

    /**
     * Obter ID do cliente (para rastreamento)
     *
     * @return string ID do cliente
     */
    private function get_client_id() {
        // Tentar obter do cookie _ga
        if (isset($_COOKIE['_ga'])) {
            return sanitize_text_field($_COOKIE['_ga']);
        }
        
        // Gerar ID de sessão se não houver cookie
        if (!session_id()) {
            session_start();
        }
        
        return session_id();
    }
}