<?php
/**
 * AJAX handlers
 *
 * @package WC_SGTM_Webhook
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

class WC_SGTM_Ajax {
    
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
        add_action('wp_ajax_wc_sgtm_resend_webhook', array($this, 'resend_webhook'));
    }
    
    /**
     * Resend webhook for specific order
     */
    public function resend_webhook() {
        // Check nonce
        check_ajax_referer('wc_sgtm_ajax', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('Você não tem permissão para realizar esta ação.', 'wc-sgtm-webhook')
            ), 403);
        }
        
        // Get order ID
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array(
                'message' => __('ID do pedido inválido.', 'wc-sgtm-webhook')
            ), 400);
        }
        
        // Get order
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array(
                'message' => __('Pedido não encontrado.', 'wc-sgtm-webhook')
            ), 404);
        }
        
        // Clear previous webhook meta
        delete_post_meta($order_id, '_sgtm_webhook_sent');
        delete_post_meta($order_id, '_sgtm_webhook_error');
        delete_post_meta($order_id, '_sgtm_webhook_response');
        
        // Get core instance
        $core = WC_SGTM_Core::instance();
        
        // Send webhook
        $core->send_webhook($order_id);
        
        // Check result
        $webhook_sent = get_post_meta($order_id, '_sgtm_webhook_sent', true);
        $webhook_error = get_post_meta($order_id, '_sgtm_webhook_error', true);
        
        if ($webhook_sent) {
            wp_send_json_success(array(
                'message' => __('Webhook reenviado com sucesso!', 'wc-sgtm-webhook'),
                'sent_at' => $webhook_sent
            ));
        } else {
            $error_message = __('Falha ao reenviar webhook.', 'wc-sgtm-webhook');
            
            if (is_array($webhook_error)) {
                if (isset($webhook_error['error'])) {
                    $error_message .= ' ' . $webhook_error['error'];
                } elseif (isset($webhook_error['body'])) {
                    $error_message .= ' ' . substr($webhook_error['body'], 0, 100);
                }
            }
            
            wp_send_json_error(array(
                'message' => $error_message
            ), 500);
        }
    }
}
