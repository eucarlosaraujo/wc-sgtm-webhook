<?php
/**
 * Classe responsável pelo painel administrativo
 * 
 * @package WC_SGTM_Webhook_Pro
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_SGTM_Admin_Panel {
    
    /**
     * Configurações do plugin
     */
    private $settings;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Plugin main instance
     */
    private $plugin;
    
    /**
     * Constructor
     */
    public function __construct($settings, $logger, $plugin = null) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->plugin = $plugin ?: wc_sgtm_webhook_pro();
    }
    
    /**
     * Inicializar painel admin
     */
    public function init() {
        // Menu do admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Scripts e estilos do admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_wc_sgtm_test_webhook', array($this, 'ajax_test_webhook'));
        add_action('wp_ajax_wc_sgtm_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_wc_sgtm_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_wc_sgtm_export_logs', array($this, 'ajax_export_logs'));
        
        // Metabox no pedido
        add_action('add_meta_boxes', array($this, 'add_order_metabox'));
        
        // Widget do dashboard
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        
        // Notificações admin
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Links de ação do plugin
        add_filter('plugin_action_links_' . WC_SGTM_WEBHOOK_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
    }
    
    /**
     * Adicionar menu no admin
     */
    public function add_admin_menu() {
        // Menu principal
        add_submenu_page(
            'woocommerce',
            __('SGTM Webhook Pro', 'wc-sgtm-webhook'),
            __('SGTM Webhook', 'wc-sgtm-webhook'),
            'manage_woocommerce',
            'wc-sgtm-webhook',
            array($this, 'render_main_page')
        );
        
        // Submenu de configurações
        add_submenu_page(
            'woocommerce',
            __('SGTM Webhook - Configurações', 'wc-sgtm-webhook'),
            __('SGTM Configurações', 'wc-sgtm-webhook'),
            'manage_woocommerce',
            'wc-sgtm-webhook-settings',
            array($this, 'render_settings_page')
        );
        
        // Submenu de logs
        add_submenu_page(
            'woocommerce',
            __('SGTM Webhook - Logs', 'wc-sgtm-webhook'),
            __('SGTM Logs', 'wc-sgtm-webhook'),
            'manage_woocommerce',
            'wc-sgtm-webhook-logs',
            array($this, 'render_logs_page')
        );
    }
    
    /**
     * Enfileirar scripts do admin
     */
    public function enqueue_admin_scripts($hook) {
        // Só carregar nas páginas do plugin
        if (strpos($hook, 'wc-sgtm-webhook') === false && $hook !== 'shop_order') {
            return;
        }

        wp_enqueue_script(
            'wc-sgtm-admin',
            WC_SGTM_WEBHOOK_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'wp-api'),
            WC_SGTM_WEBHOOK_VERSION,
            true
        );

        wp_enqueue_style(
            'wc-sgtm-admin',
            WC_SGTM_WEBHOOK_PLUGIN_URL . 'assets/admin.css',
            array(),
            WC_SGTM_WEBHOOK_VERSION
        );

        // Configurações para JavaScript
        wp_localize_script('wc-sgtm-admin', 'wcSgtmAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_sgtm_admin'),
            'strings' => array(
                'confirm_test' => __('Enviar webhook de teste?', 'wc-sgtm-webhook'),
                'confirm_clear_logs' => __('Limpar todos os logs?', 'wc-sgtm-webhook'),
                'confirm_reset_settings' => __('Resetar todas as configurações?', 'wc-sgtm-webhook'),
                'test_success' => __('Teste enviado com sucesso!', 'wc-sgtm-webhook'),
                'test_error' => __('Erro no teste. Verifique os logs.', 'wc-sgtm-webhook'),
                'settings_saved' => __('Configurações salvas!', 'wc-sgtm-webhook'),
                'settings_error' => __('Erro ao salvar configurações.', 'wc-sgtm-webhook')
            )
        ));
    }
    
    /**
     * Renderizar página principal
     */
    public function render_main_page() {
        $stats = $this->plugin->get_statistics_manager()->get_enhanced_statistics();
        $recent_orders = $this->get_recent_orders();
        $system_status = $this->get_system_status();
        
        include WC_SGTM_WEBHOOK_PLUGIN_PATH . 'templates/admin/main-page.php';
    }
    
    /**
     * Renderizar página de configurações
     */
    public function render_settings_page() {
        // Processar salvamento se enviado
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'wc_sgtm_save_settings')) {
            $this->save_settings();
        }
        
        $settings = $this->plugin->get_settings();
        $webhook_url = $settings['webhook_url'];
        $webhook_enabled = $settings['webhook_enabled'];
        
        include WC_SGTM_WEBHOOK_PLUGIN_PATH . 'templates/admin/settings-page.php';
    }
    
    /**
     * Renderizar página de logs
     */
    public function render_logs_page() {
        $logs = $this->plugin->get_statistics_manager()->get_recent_logs(50);
        $log_files = $this->get_log_files();
        
        include WC_SGTM_WEBHOOK_PLUGIN_PATH . 'templates/admin/logs-page.php';
    }
    
    /**
     * Salvar configurações
     */
    private function save_settings() {
        $new_settings = array();
        
        // Configurações básicas
        $new_settings['webhook_url'] = sanitize_url($_POST['webhook_url'] ?? '');
        $new_settings['webhook_enabled'] = isset($_POST['webhook_enabled']);
        $new_settings['debug_mode'] = isset($_POST['debug_mode']);
        $new_settings['browser_capture_enabled'] = isset($_POST['browser_capture_enabled']);
        
        // Configurações avançadas
        $new_settings['data_retention_days'] = intval($_POST['data_retention_days'] ?? 30);
        $new_settings['timeout'] = intval($_POST['timeout'] ?? 30);
        $new_settings['retry_attempts'] = intval($_POST['retry_attempts'] ?? 3);
        $new_settings['validate_ssl'] = isset($_POST['validate_ssl']);
        
        // Configurações de tracking
        $new_settings['pixel_tracking'] = array(
            'facebook' => isset($_POST['pixel_tracking_facebook']),
            'google_ads' => isset($_POST['pixel_tracking_google_ads']),
            'utm_params' => isset($_POST['pixel_tracking_utm'])
        );
        
        // Configurações de privacidade
        $new_settings['privacy'] = array(
            'hash_pii' => isset($_POST['privacy_hash_pii']),
            'respect_consent' => isset($_POST['privacy_respect_consent']),
            'anonymize_ip' => isset($_POST['privacy_anonymize_ip'])
        );
        
        // Atualizar configurações
        $this->plugin->update_settings($new_settings);
        
        // Log da atualização
        $this->logger->info('Configurações atualizadas via admin', array(
            'user_id' => get_current_user_id(),
            'changes' => array_keys($new_settings)
        ));
        
        add_settings_error('wc_sgtm_admin', 'settings_saved', 
            __('Configurações salvas com sucesso!', 'wc-sgtm-webhook'), 'success');
    }
    
    /**
     * AJAX: Testar webhook
     */
    public function ajax_test_webhook() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_sgtm_admin') || 
            !current_user_can('manage_woocommerce')) {
            wp_die('Acesso negado');
        }
        
        $webhook_sender = $this->plugin->get_webhook_sender();
        
        if (!$webhook_sender) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Webhook sender não disponível'
            )));
        }
        
        // Dados de teste
        $test_data = array(
            'client_name' => 'Data Client',
            'event_name' => 'purchase',
            'event_time' => time(),
            'event_id' => 'test_' . time(),
            'action_source' => 'website',
            'event_source_url' => get_home_url(),
            'user_data' => array(
                'em' => array(hash('sha256', 'test@example.com')),
                'fn' => array(hash('sha256', 'test')),
                'ln' => array(hash('sha256', 'user'))
            ),
            'custom_data' => array(
                'currency' => get_woocommerce_currency(),
                'value' => 99.99,
                'order_id' => 'test_order_' . time(),
                'content_type' => 'product'
            ),
            'metadata' => array(
                'source' => 'admin_test',
                'version' => WC_SGTM_WEBHOOK_VERSION,
                'test_mode' => true,
                'timestamp' => current_time('mysql')
            )
        );
        
        try {
            $response = $webhook_sender->send_webhook($test_data);
            
            if ($response['success']) {
                wp_die(json_encode(array(
                    'success' => true,
                    'message' => 'Teste enviado com sucesso!',
                    'response_code' => $response['response_code'],
                    'data' => $test_data
                )));
            } else {
                wp_die(json_encode(array(
                    'success' => false,
                    'message' => $response['error'],
                    'response_code' => $response['response_code'] ?? 0
                )));
            }
            
        } catch (Exception $e) {
            $this->logger->error('Erro no teste de webhook via admin', array(
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ));
            
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage()
            )));
        }
    }
    
    /**
     * AJAX: Obter estatísticas
     */
    public function ajax_get_stats() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Acesso negado');
        }
        
        $stats = $this->plugin->get_statistics_manager()->get_enhanced_statistics();
        $browser_stats = $this->plugin->get_browser_capture()->get_capture_statistics();
        
        wp_die(json_encode(array(
            'success' => true,
            'stats' => $stats,
            'browser_stats' => $browser_stats,
            'timestamp' => current_time('mysql')
        )));
    }
    
    /**
     * AJAX: Exportar logs
     */
    public function ajax_export_logs() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_sgtm_admin') || 
            !current_user_can('manage_woocommerce')) {
            wp_die('Acesso negado');
        }
        
        $logs = $this->get_recent_logs(1000);
        $filename = 'wc-sgtm-logs-' . date('Y-m-d-H-i-s') . '.json';
        
        $export_data = array(
            'plugin_version' => WC_SGTM_WEBHOOK_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => get_site_url(),
            'settings' => $this->plugin->get_settings(),
            'logs' => $logs
        );
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen(json_encode($export_data)));
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Adicionar metabox no pedido
     */
    public function add_order_metabox() {
        add_meta_box(
            'wc_sgtm_webhook_status',
            __('🚀 SGTM Webhook Status', 'wc-sgtm-webhook'),
            array($this, 'render_order_metabox'),
            'shop_order',
            'side',
            'default'
        );
        
        // Compatibilidade com HPOS
        if (class_exists('\Automattic\WooCommerce\Admin\Overrides\Order')) {
            add_meta_box(
                'wc_sgtm_webhook_status',
                __('🚀 SGTM Webhook Status', 'wc-sgtm-webhook'),
                array($this, 'render_order_metabox'),
                'woocommerce_page_wc-orders',
                'side',
                'default'
            );
        }
    }
    
    /**
     * Renderizar metabox do pedido
     */
    public function render_order_metabox($post_or_order) {
        $order_id = is_object($post_or_order) ? $post_or_order->get_id() : $post_or_order->ID;
        $order = wc_get_order($order_id);
        
        if (!$order) {
            echo '<p>' . __('Pedido não encontrado.', 'wc-sgtm-webhook') . '</p>';
            return;
        }
        
        // Status do webhook
        $webhook_sent = $order->get_meta('_sgtm_webhook_sent');
        $webhook_error = $order->get_meta('_sgtm_webhook_error');
        $webhook_response = $order->get_meta('_sgtm_webhook_response');
        
        // Dados do navegador
        $browser_data = $order->get_meta('_browser_data');
        
        include WC_SGTM_WEBHOOK_PLUGIN_PATH . 'templates/admin/order-metabox.php';
    }
    
    /**
     * Adicionar widget ao dashboard
     */
    public function add_dashboard_widget() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'wc_sgtm_dashboard_widget',
            __('🚀 SGTM Webhook Status', 'wc-sgtm-webhook'),
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * Renderizar widget do dashboard
     */
    public function render_dashboard_widget() {
        $stats = $this->get_enhanced_statistics();
        $webhook_enabled = $this->plugin->get_setting('webhook_enabled', false);
        
        include WC_SGTM_WEBHOOK_PLUGIN_PATH . 'templates/admin/dashboard-widget.php';
    }
    
    /**
     * Mostrar notificações do admin
     */
    public function show_admin_notices() {
        $screen = get_current_screen();
        
        // Só mostrar em páginas relevantes
        if (!$screen || (strpos($screen->id, 'woocommerce') === false && strpos($screen->id, 'sgtm') === false)) {
            return;
        }
        
        // Verificar problemas comuns
        $issues = $this->check_common_issues();
        
        foreach ($issues as $issue) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p><strong>%s:</strong> %s</p></div>',
                esc_attr($issue['type']),
                esc_html($issue['title']),
                wp_kses_post($issue['message'])
            );
        }
        
        // Mostrar notificações de configuração
        settings_errors('wc_sgtm_admin');
    }
    
    /**
     * Verificar problemas comuns
     */
    private function check_common_issues() {
        $issues = array();
        $settings = $this->plugin->get_settings();
        
        // Webhook desabilitado
        if (!$settings['webhook_enabled']) {
            $issues[] = array(
                'type' => 'info',
                'title' => __('SGTM Webhook', 'wc-sgtm-webhook'),
                'message' => sprintf(
                    __('O webhook está desabilitado. %s para ativá-lo.', 'wc-sgtm-webhook'),
                    '<a href="' . admin_url('admin.php?page=wc-sgtm-webhook-settings') . '">' . __('Clique aqui', 'wc-sgtm-webhook') . '</a>'
                )
            );
        }
        
        // URL não configurada
        if (empty($settings['webhook_url']) && $settings['webhook_enabled']) {
            $issues[] = array(
                'type' => 'error',
                'title' => __('SGTM Webhook', 'wc-sgtm-webhook'),
                'message' => __('URL do webhook não configurada. Configure nas configurações do plugin.', 'wc-sgtm-webhook')
            );
        }
        
        // Taxa de captura baixa
        $browser_capture = $this->plugin->get_browser_capture();
        if ($browser_capture) {
            $capture_stats = $browser_capture->get_capture_statistics(7);
            if ($capture_stats['capture_rate'] < 70) {
                $issues[] = array(
                    'type' => 'warning',
                    'title' => __('SGTM Webhook', 'wc-sgtm-webhook'),
                    'message' => sprintf(
                        __('Taxa de captura de dados baixa: %s%%. Verifique se há conflitos de JavaScript.', 'wc-sgtm-webhook'),
                        $capture_stats['capture_rate']
                    )
                );
            }
        }
        
        // Muitos erros recentes
        $recent_errors = $this->get_recent_error_count();
        if ($recent_errors > 10) {
            $issues[] = array(
                'type' => 'error',
                'title' => __('SGTM Webhook', 'wc-sgtm-webhook'),
                'message' => sprintf(
                    __('%d erros detectados nas últimas 24 horas. %s para investigar.', 'wc-sgtm-webhook'),
                    $recent_errors,
                    '<a href="' . admin_url('admin.php?page=wc-sgtm-webhook-logs') . '">' . __('Ver logs', 'wc-sgtm-webhook') . '</a>'
                )
            );
        }
        
        return $issues;
    }
    
    /**
     * Links de ação do plugin
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-sgtm-webhook-settings'),
            __('Configurações', 'wc-sgtm-webhook')
        );
        
        $dashboard_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-sgtm-webhook'),
            __('Dashboard', 'wc-sgtm-webhook')
        );
        
        array_unshift($links, $settings_link, $dashboard_link);
        
        return $links;
    }
    
    /**
     * Obter estatísticas melhoradas com fallback
     */
    public function get_enhanced_statistics() {
        try {
            $manager = $this->plugin ? $this->plugin->get_statistics_manager() : null;
            if ($manager && method_exists($manager, 'get_enhanced_statistics')) {
                $stats = $manager->get_enhanced_statistics();
                if (is_array($stats) && !empty($stats)) {
                    return $stats;
                }
            }
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->error('Falha ao obter estatísticas melhoradas', array(
                    'error' => $e->getMessage()
                ));
            }
        }

        // Fallback seguro (valores padrão)
        return array(
            'total_sent' => 0,
            'errors_today' => 0,
            'success_rate' => 100,
            'last_order_id' => null,
            'last_sent' => null,
            'total_revenue' => 0.0,
            'with_browser_data' => 0,
            'with_facebook_data' => 0,
            'with_google_data' => 0,
            'browser_data_rate' => 0,
            'facebook_data_rate' => 0,
            'google_data_rate' => 0,
            'period_days' => 30
        );
    }
    

    
    /**
     * Calcular tendência
     */
    private function calculate_trend($recent, $previous) {
        if ($previous == 0) return 'neutral';
        
        $change = (($recent - $previous) / $previous) * 100;
        
        if ($change > 10) return 'up';
        if ($change < -10) return 'down';
        return 'neutral';
    }
    
    /**
     * Obter pedidos recentes
     */
    private function get_recent_orders($limit = 20) {
        $orders = wc_get_orders(array(
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array('completed', 'processing', 'pending')
        ));
        
        $processed_orders = array();
        
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            
            $processed_orders[] = array(
                'id' => $order_id,
                'date' => $order->get_date_created(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'webhook_sent' => $order->get_meta('_sgtm_webhook_sent'),
                'webhook_error' => $order->get_meta('_sgtm_webhook_error'),
                'webhook_response' => $order->get_meta('_sgtm_webhook_response'),
                'browser_data' => $order->get_meta('_browser_data'),
                'edit_url' => admin_url('post.php?post=' . $order_id . '&action=edit')
            );
        }
        
        return $processed_orders;
    }
    
    /**
     * Obter status do sistema
     */
    private function get_system_status() {
        $settings = $this->plugin->get_settings();
        
        return array(
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'wc_version' => WC()->version,
            'plugin_version' => WC_SGTM_WEBHOOK_VERSION,
            'webhook_url' => $settings['webhook_url'],
            'webhook_enabled' => $settings['webhook_enabled'],
            'debug_mode' => $settings['debug_mode'],
            'browser_capture_enabled' => $settings['browser_capture_enabled'],
            'ssl_enabled' => is_ssl(),
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'curl_available' => function_exists('curl_init'),
            'openssl_available' => extension_loaded('openssl')
        );
    }
    
    /**
     * Obter logs recentes
     */
    private function get_recent_logs($limit = 50) {
        global $wpdb;
        
        // Tentar obter da tabela customizada primeiro
        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $logs = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM $table_name 
                ORDER BY created_at DESC 
                LIMIT %d
            ", $limit), ARRAY_A);
            
            return $logs;
        }
        
        // Fallback para logs do WooCommerce
        return $this->get_wc_logs($limit);
    }
    
    /**
     * Obter logs do WooCommerce
     */
    private function get_wc_logs($limit = 50) {
        if (!function_exists('wc_get_logger')) {
            return array();
        }
        
        $log_files = glob(WC_LOG_DIR . '*sgtm-webhook*.log');
        $logs = array();
        
        foreach ($log_files as $file) {
            if (!is_file($file)) continue;
            
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            
            foreach (array_reverse($lines) as $line) {
                if (empty(trim($line)) || count($logs) >= $limit) {
                    continue;
                }
                
                if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})\s+(\w+)\s+(.+)$/', $line, $matches)) {
                    $logs[] = array(
                        'created_at' => $matches[1],
                        'status' => strtolower($matches[2]),
                        'message' => $matches[3],
                        'source' => 'wc_log'
                    );
                }
            }
        }
        
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * Obter arquivos de log
     */
    private function get_log_files() {
        $files = array();
        
        // Logs do WooCommerce
        if (defined('WC_LOG_DIR')) {
            $wc_files = glob(WC_LOG_DIR . '*sgtm-webhook*.log');
            foreach ($wc_files as $file) {
                $files[] = array(
                    'name' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                    'type' => 'woocommerce'
                );
            }
        }
        
        // Logs customizados
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $files[] = array(
                'name' => 'Database Logs',
                'path' => 'database',
                'size' => $count,
                'modified' => time(),
                'type' => 'database'
            );
        }
        
        return $files;
    }
    
    /**
     * Obter contagem de erros recentes
     */
    private function get_recent_error_count() {
        global $wpdb;
        
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_date >= %s
            AND pm.meta_key = '_sgtm_webhook_error'
            AND pm.meta_value != ''
        ", $yesterday));
        
        return intval($count);
    }
    
    /**
     * Limpar logs antigos
     */
    public function cleanup_old_logs($days = 30) {
        global $wpdb;
        
        // Limpar logs da tabela customizada
        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE FROM $table_name 
                WHERE created_at < %s
            ", $cutoff_date));
            
            $this->logger->info('Logs antigos limpos', array(
                'deleted_count' => $deleted,
                'cutoff_date' => $cutoff_date
            ));
        }
        
        // Limpar arquivos de log do WooCommerce
        if (defined('WC_LOG_DIR')) {
            $log_files = glob(WC_LOG_DIR . '*sgtm-webhook*.log');
            $cutoff_time = time() - ($days * 24 * 60 * 60);
            
            foreach ($log_files as $file) {
                if (is_file($file) && filemtime($file) < $cutoff_time) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Gerar relatório de performance
     */
    public function generate_performance_report($days = 7) {
        $stats = $this->get_enhanced_statistics();
        $browser_capture = $this->plugin->get_browser_capture();
        $capture_stats = $browser_capture ? $browser_capture->get_capture_statistics($days) : array();
        
        $report = array(
            'period' => array(
                'days' => $days,
                'start_date' => date('Y-m-d', strtotime("-{$days} days")),
                'end_date' => date('Y-m-d')
            ),
            'webhook_performance' => array(
                'total_sent' => $stats['total_sent'],
                'success_rate' => $stats['success_rate'],
                'errors_today' => $stats['errors_today'],
                'total_revenue' => $stats['total_revenue']
            ),
            'browser_capture_performance' => $capture_stats,
            'system_status' => $this->get_system_status(),
            'issues' => $this->check_common_issues(),
            'generated_at' => current_time('mysql'),
            'generated_by' => get_current_user_id()
        );
        
        return $report;
    }
}