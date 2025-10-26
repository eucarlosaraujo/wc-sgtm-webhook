<?php
/**
 * Classe de administração do plugin WooCommerce SGTM Webhook
 *
 * @package WC_SGTM_Webhook
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Classe de administração do plugin
 */
class WC_SGTM_Webhook_Admin {

    /**
     * Instância única da classe
     *
     * @var WC_SGTM_Webhook_Admin
     */
    private static $instance = null;

    /**
     * Construtor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Obter instância única da classe (Singleton)
     *
     * @return WC_SGTM_Webhook_Admin
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
        // Adicionar menu de administração
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Registrar configurações
        add_action('admin_init', array($this, 'register_settings'));
        
        // Adicionar link de configurações na página de plugins
        add_filter('plugin_action_links_wc-sgtm-webhook/wc-sgtm-webhook.php', array($this, 'add_settings_link'));
        
        // Carregar scripts e estilos de administração
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Adicionar endpoint AJAX para teste de conexão
        add_action('wp_ajax_wc_sgtm_webhook_test_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * Adicionar menu de administração
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('SGTM Webhook', 'wc-sgtm-webhook'),
            __('SGTM Webhook', 'wc-sgtm-webhook'),
            'manage_woocommerce',
            'wc-sgtm-webhook',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Registrar configurações
     */
    public function register_settings() {
        register_setting('wc_sgtm_webhook_settings', 'wc_sgtm_webhook_endpoint');
        register_setting('wc_sgtm_webhook_settings', 'wc_sgtm_webhook_auth_key');
        register_setting('wc_sgtm_webhook_settings', 'wc_sgtm_webhook_events', array($this, 'sanitize_events'));
        register_setting('wc_sgtm_webhook_settings', 'wc_sgtm_webhook_debug_mode');

        add_settings_section(
            'wc_sgtm_webhook_section',
            __('Configurações do SGTM Webhook', 'wc-sgtm-webhook'),
            array($this, 'settings_section_callback'),
            'wc_sgtm_webhook_settings'
        );

        add_settings_field(
            'wc_sgtm_webhook_endpoint',
            __('URL do Endpoint SGTM', 'wc-sgtm-webhook'),
            array($this, 'endpoint_field_callback'),
            'wc_sgtm_webhook_settings',
            'wc_sgtm_webhook_section'
        );

        add_settings_field(
            'wc_sgtm_webhook_auth_key',
            __('Chave de Autenticação', 'wc-sgtm-webhook'),
            array($this, 'auth_key_field_callback'),
            'wc_sgtm_webhook_settings',
            'wc_sgtm_webhook_section'
        );

        add_settings_field(
            'wc_sgtm_webhook_events',
            __('Eventos para Monitorar', 'wc-sgtm-webhook'),
            array($this, 'events_field_callback'),
            'wc_sgtm_webhook_settings',
            'wc_sgtm_webhook_section'
        );

        add_settings_field(
            'wc_sgtm_webhook_debug_mode',
            __('Modo de Depuração', 'wc-sgtm-webhook'),
            array($this, 'debug_mode_field_callback'),
            'wc_sgtm_webhook_settings',
            'wc_sgtm_webhook_section'
        );
    }

    /**
     * Sanitizar eventos selecionados
     *
     * @param array $input Eventos selecionados
     * @return array Eventos sanitizados
     */
    public function sanitize_events($input) {
        $valid_events = $this->get_available_events();
        $sanitized = array();
        
        if (is_array($input)) {
            foreach ($input as $event) {
                if (array_key_exists($event, $valid_events)) {
                    $sanitized[] = $event;
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Callback para a seção de configurações
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure a integração do WooCommerce com o Server-Side Google Tag Manager.', 'wc-sgtm-webhook') . '</p>';
    }

    /**
     * Callback para o campo de endpoint
     */
    public function endpoint_field_callback() {
        $endpoint = get_option('wc_sgtm_webhook_endpoint', '');
        echo '<input type="url" id="wc_sgtm_webhook_endpoint" name="wc_sgtm_webhook_endpoint" value="' . esc_attr($endpoint) . '" class="regular-text" />';
        echo '<p class="description">' . __('URL do endpoint do Server-Side GTM para onde os dados serão enviados.', 'wc-sgtm-webhook') . '</p>';
    }

    /**
     * Callback para o campo de chave de autenticação
     */
    public function auth_key_field_callback() {
        $auth_key = get_option('wc_sgtm_webhook_auth_key', '');
        echo '<input type="text" id="wc_sgtm_webhook_auth_key" name="wc_sgtm_webhook_auth_key" value="' . esc_attr($auth_key) . '" class="regular-text" />';
        echo '<p class="description">' . __('Chave de autenticação para o endpoint SGTM (opcional).', 'wc-sgtm-webhook') . '</p>';
    }

    /**
     * Callback para o campo de eventos
     */
    public function events_field_callback() {
        $events = get_option('wc_sgtm_webhook_events', array());
        $available_events = $this->get_available_events();
        
        foreach ($available_events as $event_key => $event_label) {
            $checked = in_array($event_key, $events) ? 'checked="checked"' : '';
            echo '<label><input type="checkbox" name="wc_sgtm_webhook_events[]" value="' . esc_attr($event_key) . '" ' . $checked . ' /> ' . esc_html($event_label) . '</label><br />';
        }
    }

    /**
     * Callback para o campo de modo de depuração
     */
    public function debug_mode_field_callback() {
        $debug_mode = get_option('wc_sgtm_webhook_debug_mode', false);
        echo '<label><input type="checkbox" id="wc_sgtm_webhook_debug_mode" name="wc_sgtm_webhook_debug_mode" value="1" ' . checked(1, $debug_mode, false) . ' /> ' . __('Ativar modo de depuração', 'wc-sgtm-webhook') . '</label>';
        echo '<p class="description">' . __('Registra informações adicionais no log do WordPress para depuração.', 'wc-sgtm-webhook') . '</p>';
    }

    /**
     * Obter eventos disponíveis para monitoramento
     *
     * @return array Eventos disponíveis
     */
    private function get_available_events() {
        return array(
            'new_order' => __('Novo Pedido', 'wc-sgtm-webhook'),
            'order_status_changed' => __('Mudança de Status do Pedido', 'wc-sgtm-webhook'),
            'payment_complete' => __('Pagamento Concluído', 'wc-sgtm-webhook'),
            'add_to_cart' => __('Adicionar ao Carrinho', 'wc-sgtm-webhook'),
            'remove_from_cart' => __('Remover do Carrinho', 'wc-sgtm-webhook'),
            'checkout_update' => __('Atualização do Checkout', 'wc-sgtm-webhook'),
        );
    }

    /**
     * Exibir página de configurações
     */
    public function display_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . __('WooCommerce SGTM Webhook - Stape.io', 'wc-sgtm-webhook') . '</h1>';
        
        // Exibir painel de estatísticas
        $this->display_dashboard();
        
        echo '<form method="post" action="options.php" class="wc-sgtm-webhook-settings">';
        settings_fields('wc_sgtm_webhook_settings');
        do_settings_sections('wc_sgtm_webhook_settings');
        submit_button();
        echo '</form>';
        
        echo '</div>';
    }

    /**
     * Exibir painel de estatísticas
     */
    private function display_dashboard() {
        // Obter estatísticas
        $webhooks_sent = wc_sgtm_webhook_get_sent_count();
        $errors_today = wc_sgtm_webhook_get_errors_today();
        $success_rate = wc_sgtm_webhook_get_success_rate();
        $last_sent = wc_sgtm_webhook_get_last_sent();
        $total_processed = wc_sgtm_webhook_get_total_processed();
        
        // Verificar status do webhook
        $endpoint = get_option('wc_sgtm_webhook_endpoint', '');
        $webhook_status = !empty($endpoint) ? 'ativo' : 'inativo';
        
        // Verificar modo de depuração
        $debug_mode = get_option('wc_sgtm_webhook_debug_mode', false) ? 'ativo' : 'inativo';
        
        // Verificar conectividade
        $connectivity_status = 'conectado';
        if (empty($endpoint)) {
            $connectivity_status = 'desconectado';
        }
        
        // Obter informações do sistema
        $wc_version = function_exists('WC') ? WC()->version : 'N/A';
        $wp_version = get_bloginfo('version');
        $php_version = phpversion();
        
        // Exibir painel
        ?>
        <div class="wc-sgtm-webhook-dashboard">
            <h2 class="wc-sgtm-dashboard-title"><?php _e('Status da Configuração', 'wc-sgtm-webhook'); ?></h2>
            
            <div class="wc-sgtm-dashboard-grid">
                <!-- Status do Webhook -->
                <div class="wc-sgtm-dashboard-card">
                    <h3><?php _e('Status do Webhook', 'wc-sgtm-webhook'); ?></h3>
                    <p class="wc-sgtm-webhook-url"><?php _e('URL:', 'wc-sgtm-webhook'); ?> <?php echo esc_html($endpoint); ?></p>
                    <p class="wc-sgtm-webhook-status"><?php _e('Status:', 'wc-sgtm-webhook'); ?> <span class="status-badge <?php echo $webhook_status === 'ativo' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($webhook_status); ?></span></p>
                </div>
                
                <!-- Modo Debug -->
                <div class="wc-sgtm-dashboard-card">
                    <h3><?php _e('Modo Debug', 'wc-sgtm-webhook'); ?></h3>
                    <p class="wc-sgtm-debug-status"><?php _e('Status:', 'wc-sgtm-webhook'); ?> <span class="status-badge <?php echo $debug_mode === 'ativo' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($debug_mode); ?></span></p>
                    <p class="wc-sgtm-debug-note"><?php _e('Lembre-se de desativar em produção', 'wc-sgtm-webhook'); ?></p>
                </div>
                
                <!-- Conectividade Stape.io -->
                <div class="wc-sgtm-dashboard-card">
                    <h3><?php _e('Conectividade Stape.io', 'wc-sgtm-webhook'); ?></h3>
                    <p class="wc-sgtm-connectivity-status"><?php _e('Status:', 'wc-sgtm-webhook'); ?> <span class="status-badge <?php echo $connectivity_status === 'conectado' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($connectivity_status); ?></span></p>
                    <p class="wc-sgtm-connectivity-note"><?php _e('Conectividade OK', 'wc-sgtm-webhook'); ?></p>
                </div>
                
                <!-- Informações do Sistema -->
                <div class="wc-sgtm-dashboard-card">
                    <h3><?php _e('Informações do Sistema', 'wc-sgtm-webhook'); ?></h3>
                    <p><?php _e('Versão WooCommerce:', 'wc-sgtm-webhook'); ?> <?php echo esc_html($wc_version); ?></p>
                    <p><?php _e('Versão WordPress:', 'wc-sgtm-webhook'); ?> <?php echo esc_html($wp_version); ?></p>
                    <p><?php _e('PHP:', 'wc-sgtm-webhook'); ?> <?php echo esc_html($php_version); ?></p>
                </div>
            </div>
            
            <h2 class="wc-sgtm-dashboard-title"><?php _e('Estatísticas', 'wc-sgtm-webhook'); ?></h2>
            
            <div class="wc-sgtm-dashboard-grid">
                <!-- Webhooks Enviados -->
                <div class="wc-sgtm-dashboard-card">
                    <h3><?php _e('Webhooks Enviados', 'wc-sgtm-webhook'); ?></h3>
                    <div class="wc-sgtm-stat-value"><?php echo number_format($webhooks_sent); ?></div>
                    <p class="wc-sgtm-stat-note"><?php _e('Últimos 30 dias', 'wc-sgtm-webhook'); ?></p>
                </div>
                
                <!-- Erros Hoje -->
                <div class="wc-sgtm-dashboard-card">
                    <h3><?php _e('Erros Hoje', 'wc-sgtm-webhook'); ?></h3>
                    <div class="wc-sgtm-stat-value"><?php echo number_format($errors_today); ?></div>
                    <p class="wc-sgtm-stat-note"><?php _e('Taxa de sucesso:', 'wc-sgtm-webhook'); ?> <?php echo $success_rate; ?>%</p>
                </div>
                
                <!-- Último Envio -->
                <div class="wc-sgtm-dashboard-card">
                    <h3><?php _e('Último Envio', 'wc-sgtm-webhook'); ?></h3>
                    <?php if ($last_sent): ?>
                        <div class="wc-sgtm-stat-value"><?php echo date_i18n('d/m/Y H:i', strtotime($last_sent['date_created'])); ?></div>
                        <p class="wc-sgtm-stat-note"><?php _e('Pedido #', 'wc-sgtm-webhook'); ?><?php echo $last_sent['order_id']; ?></p>
                    <?php else: ?>
                        <div class="wc-sgtm-stat-value">-</div>
                        <p class="wc-sgtm-stat-note"><?php _e('Nenhum envio registrado', 'wc-sgtm-webhook'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Total Processado -->
                <div class="wc-sgtm-dashboard-card">
                    <h3><?php _e('Total Processado', 'wc-sgtm-webhook'); ?></h3>
                    <div class="wc-sgtm-stat-value"><?php echo wc_price($total_processed); ?></div>
                    <p class="wc-sgtm-stat-note"><?php _e('Últimos 30 dias', 'wc-sgtm-webhook'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Adicionar link de configurações na página de plugins
     *
     * @param array $links Links existentes
     * @return array Links modificados
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-sgtm-webhook') . '">' . __('Configurações', 'wc-sgtm-webhook') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Carregar scripts e estilos de administração
     *
     * @param string $hook Hook atual
     */
    public function enqueue_admin_scripts($hook) {
        // Carregar apenas na página de configurações do plugin
        if ('woocommerce_page_wc-sgtm-webhook' !== $hook) {
            return;
        }
        
        // Registrar e enfileirar CSS
        wp_register_style(
            'wc-sgtm-webhook-admin',
            WC_SGTM_WEBHOOK_PLUGIN_URL . 'assets/admin.css',
            array(),
            WC_SGTM_WEBHOOK_VERSION
        );
        wp_enqueue_style('wc-sgtm-webhook-admin');
        
        // Registrar e enfileirar JavaScript
        wp_register_script(
            'wc-sgtm-webhook-admin',
            WC_SGTM_WEBHOOK_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            WC_SGTM_WEBHOOK_VERSION,
            true
        );
        
        // Adicionar variáveis para o script
        wp_localize_script(
            'wc-sgtm-webhook-admin',
            'wc_sgtm_webhook_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_sgtm_webhook_nonce'),
            )
        );
        
        wp_enqueue_script('wc-sgtm-webhook-admin');
    }
    
    /**
     * Manipular requisição AJAX para teste de conexão
     */
    public function ajax_test_connection() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_sgtm_webhook_nonce')) {
            wp_send_json_error(array('message' => 'Erro de segurança. Por favor, recarregue a página.'));
        }
        
        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Você não tem permissão para realizar esta ação.'));
        }
        
        // Obter endpoint
        $endpoint = isset($_POST['endpoint']) ? esc_url_raw($_POST['endpoint']) : '';
        if (empty($endpoint)) {
            wp_send_json_error(array('message' => 'URL do endpoint não fornecida.'));
        }
        
        // Obter chave de autenticação
        $auth_key = get_option('wc_sgtm_webhook_auth_key', '');
        
        // Testar conexão
        $result = wc_sgtm_webhook_test_connection($endpoint, $auth_key);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}

// Inicializar a classe de administração
WC_SGTM_Webhook_Admin::instance();