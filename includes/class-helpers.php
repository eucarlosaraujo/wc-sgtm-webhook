<?php
/**
 * Helper functions
 *
 * @package WC_SGTM_Webhook
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

class WC_SGTM_Helpers {
    
    /**
     * Get webhook URL
     */
    public static function get_webhook_url() {
        return get_option('wc_sgtm_webhook_url', '');
    }
    
    /**
     * Get container ID (GTM-XXXXX)
     */
    public static function get_container_id() {
        return get_option('wc_sgtm_container_id', '');
    }
    
    /**
     * Get webhook token
     */
    public static function get_webhook_token() {
        return get_option('wc_sgtm_webhook_token', '');
    }
    
    /**
     * Check if webhook is enabled
     */
    public static function is_webhook_enabled() {
        return get_option('wc_sgtm_webhook_enabled', 'no') === 'yes';
    }
    
    /**
     * Check if debug mode is active
     */
    public static function is_debug_mode() {
        return get_option('wc_sgtm_debug_mode', 'no') === 'yes';
    }
    
    /**
     * Build complete endpoint URL with container ID
     * Example: https://sgtm.example.com/data?id=GTM-XXXXX
     */
    public static function build_endpoint() {
        $base_url = trim(self::get_webhook_url());
        $container_id = trim(self::get_container_id());
        
        if (empty($base_url)) {
            return '';
        }
        
        // Ensure /data path exists
        if (stripos($base_url, '/data') === false) {
            $base_url = rtrim($base_url, '/') . '/data';
        }
        
        // Add container ID as query parameter if provided
        if (!empty($container_id)) {
            $separator = (strpos($base_url, '?') !== false) ? '&' : '?';
            
            // Check if id parameter already exists
            if (stripos($base_url, 'id=') === false) {
                $base_url .= $separator . 'id=' . rawurlencode($container_id);
            }
        }
        
        return $base_url;
    }
    
    /**
     * Sanitize and hash PII data
     */
    public static function hash_pii($value) {
        if (empty($value)) {
            return '';
        }
        return hash('sha256', strtolower(trim($value)));
    }
    
    /**
     * Format phone number (remove non-numeric characters)
     */
    public static function format_phone($phone) {
        return preg_replace('/[^0-9]/', '', $phone);
    }
    
    /**
     * Format ZIP code (remove non-numeric characters)
     */
    public static function format_zip($zip) {
        return preg_replace('/[^0-9]/', '', $zip);
    }
    
    /**
     * Log message with level
     */
    public static function log($message, $level = 'info') {
        if (!self::is_debug_mode() && $level === 'debug') {
            return;
        }
        
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
            case 'debug':
                $logger->debug($message, $context);
                break;
            case 'info':
            default:
                $logger->info($message, $context);
                break;
        }
    }
    
    /**
     * Convenience methods for logging
     */
    public static function log_info($message) {
        self::log($message, 'info');
    }
    
    public static function log_error($message) {
        self::log($message, 'error');
    }
    
    public static function log_warning($message) {
        self::log($message, 'warning');
    }
    
    public static function log_debug($message) {
        self::log($message, 'debug');
    }
    
    /**
     * Get recent logs
     */
    public static function get_recent_logs($limit = 20) {
        if (!function_exists('wc_get_logger')) {
            return array();
        }
        
        $log_files = glob(WC_LOG_DIR . 'wc-sgtm-webhook-*.log');
        
        if (empty($log_files)) {
            return array();
        }
        
        // Sort by modification time (newest first)
        array_multisort(array_map('filemtime', $log_files), SORT_DESC, $log_files);
        
        $logs = array();
        $latest_log = $log_files[0];
        
        if (is_readable($latest_log)) {
            $content = file_get_contents($latest_log);
            $lines = explode("\n", trim($content));
            $recent_lines = array_slice($lines, -$limit);
            
            foreach (array_reverse($recent_lines) as $line) {
                if (empty(trim($line))) continue;
                
                // Parse WooCommerce log format
                if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})\s+(\w+)\s+(.+)$/', $line, $matches)) {
                    $logs[] = array(
                        'timestamp' => date('d/m/Y H:i:s', strtotime($matches[1])),
                        'level' => $matches[2],
                        'message' => $matches[3]
                    );
                }
            }
        }
        
        return $logs;
    }
    
    /**
     * Clear old logs (keep last 7 days)
     */
    public static function clear_old_logs() {
        $log_files = glob(WC_LOG_DIR . 'wc-sgtm-webhook-*.log');
        $cleared = 0;
        $seven_days_ago = strtotime('-7 days');
        
        foreach ($log_files as $file) {
            if (is_file($file) && filemtime($file) < $seven_days_ago) {
                if (unlink($file)) {
                    $cleared++;
                }
            }
        }
        
        return $cleared;
    }
    
    /**
     * Test webhook connection
     */
    public static function test_connection() {
        $endpoint = self::build_endpoint();
        
        if (empty($endpoint)) {
            return array(
                'success' => false,
                'message' => __('URL do webhook não configurada', 'wc-sgtm-webhook')
            );
        }
        
        $test_data = array(
            'client_name' => 'Data Client',
            'event_name' => 'test_event',
            'event_time' => time(),
            'event_id' => 'test_' . time(),
            'action_source' => 'website',
            'metadata' => array(
                'test' => true,
                'source' => 'wc_sgtm_webhook',
                'version' => WC_SGTM_VERSION
            )
        );
        
        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'WooCommerce-SGTM-Webhook/' . WC_SGTM_VERSION,
            'Accept' => 'application/json'
        );
        
        $token = self::get_webhook_token();
        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        
        $args = array(
            'body' => json_encode($test_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'headers' => $headers,
            'timeout' => 15,
            'httpversion' => '1.1',
            'sslverify' => true,
            'blocking' => true
        );
        
        $response = wp_remote_post($endpoint, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code >= 200 && $code < 300) {
            return array(
                'success' => true,
                'message' => sprintf(__('Conexão OK (HTTP %d)', 'wc-sgtm-webhook'), $code),
                'code' => $code,
                'body' => $body
            );
        }
        
        return array(
            'success' => false,
            'message' => sprintf(__('Erro HTTP %d: %s', 'wc-sgtm-webhook'), $code, substr($body, 0, 100)),
            'code' => $code,
            'body' => $body
        );
    }
    
    /**
     * Get webhook statistics
     */
    public static function get_statistics() {
        global $wpdb;
        
        $stats = array(
            'total_sent' => 0,
            'total_errors' => 0,
            'sent_today' => 0,
            'errors_today' => 0,
            'last_sent' => null,
            'last_order_id' => null
        );
        
        $today = date('Y-m-d');
        
        // Total sent
        $stats['total_sent'] = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sgtm_webhook_sent'
            AND meta_value != ''
        ");
        
        // Total errors
        $stats['total_errors'] = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sgtm_webhook_error'
            AND meta_value != ''
        ");
        
        // Sent today
        $stats['sent_today'] = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND DATE(p.post_date) = %s
            AND pm.meta_key = '_sgtm_webhook_sent'
            AND pm.meta_value != ''
        ", $today));
        
        // Errors today
        $stats['errors_today'] = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND DATE(p.post_date) = %s
            AND pm.meta_key = '_sgtm_webhook_error'
            AND pm.meta_value != ''
        ", $today));
        
        // Last sent order
        $last_sent = $wpdb->get_row("
            SELECT post_id, meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sgtm_webhook_sent'
            AND meta_value != ''
            ORDER BY meta_value DESC
            LIMIT 1
        ");
        
        if ($last_sent) {
            $stats['last_sent'] = $last_sent->meta_value;
            $stats['last_order_id'] = $last_sent->post_id;
        }
        
        return $stats;
    }
}
