<?php
/**
 * Admin interface
 *
 * @package WC_SGTM_Webhook
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

class WC_SGTM_Admin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('SGTM Webhook', 'wc-sgtm-webhook'),
            __('SGTM Webhook', 'wc-sgtm-webhook'),
            'manage_woocommerce',
            'wc-sgtm-webhook',
            array($this, 'render_admin_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'woocommerce_page_wc-sgtm-webhook') {
            return;
        }
        
        wp_enqueue_style('wc-sgtm-admin', WC_SGTM_PLUGIN_URL . 'assets/css/admin.css', array(), WC_SGTM_VERSION);
        wp_enqueue_script('wc-sgtm-admin', WC_SGTM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WC_SGTM_VERSION, true);
        
        wp_localize_script('wc-sgtm-admin', 'wcSgtmAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_sgtm_ajax')
        ));
    }
    
    public function handle_form_submissions() {
        if (!isset($_POST['wc_sgtm_action']) || !current_user_can('manage_woocommerce')) {
            return;
        }
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wc_sgtm_action')) {
            return;
        }
        
        $action = sanitize_key($_POST['wc_sgtm_action']);
        
        switch ($action) {
            case 'save_settings':
                $this->save_settings();
                break;
            case 'test_connection':
                $this->test_connection();
                break;
            case 'clear_logs':
                $this->clear_logs();
                break;
        }
    }
    
    private function save_settings() {
        $webhook_url = isset($_POST['webhook_url']) ? esc_url_raw($_POST['webhook_url']) : '';
        $container_id = isset($_POST['container_id']) ? sanitize_text_field($_POST['container_id']) : '';
        $webhook_token = isset($_POST['webhook_token']) ? sanitize_text_field($_POST['webhook_token']) : '';
        $webhook_enabled = isset($_POST['webhook_enabled']) ? 'yes' : 'no';
        $debug_mode = isset($_POST['debug_mode']) ? 'yes' : 'no';
        
        update_option('wc_sgtm_webhook_url', $webhook_url);
        update_option('wc_sgtm_container_id', $container_id);
        update_option('wc_sgtm_webhook_token', $webhook_token);
        update_option('wc_sgtm_webhook_enabled', $webhook_enabled);
        update_option('wc_sgtm_debug_mode', $debug_mode);
        
        WC_SGTM_Helpers::log_info('Configurações salvas');
        
        add_settings_error('wc_sgtm_admin', 'settings_saved', 
            __('✅ Configurações salvas com sucesso!', 'wc-sgtm-webhook'), 'success');
    }
    
    private function test_connection() {
        $result = WC_SGTM_Helpers::test_connection();
        
        if ($result['success']) {
            add_settings_error('wc_sgtm_admin', 'test_success', 
                '✅ ' . $result['message'], 'success');
        } else {
            add_settings_error('wc_sgtm_admin', 'test_error', 
                '❌ ' . $result['message'], 'error');
        }
    }
    
    private function clear_logs() {
        $cleared = WC_SGTM_Helpers::clear_old_logs();
        add_settings_error('wc_sgtm_admin', 'logs_cleared', 
            sprintf(__('🗑️ %d arquivo(s) de log removido(s)', 'wc-sgtm-webhook'), $cleared), 'success');
    }
    
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        ?>
        <div class="wrap wc-sgtm-admin">
            <h1><?php _e('🚀 WC SGTM Webhook Pro', 'wc-sgtm-webhook'); ?></h1>
            
            <?php settings_errors('wc_sgtm_admin'); ?>
            
            <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                <a href="?page=wc-sgtm-webhook&tab=dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    📊 <?php _e('Dashboard', 'wc-sgtm-webhook'); ?>
                </a>
                <a href="?page=wc-sgtm-webhook&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    ⚙️ <?php _e('Configurações', 'wc-sgtm-webhook'); ?>
                </a>
                <a href="?page=wc-sgtm-webhook&tab=orders" class="nav-tab <?php echo $active_tab === 'orders' ? 'nav-tab-active' : ''; ?>">
                    📦 <?php _e('Pedidos', 'wc-sgtm-webhook'); ?>
                </a>
                <a href="?page=wc-sgtm-webhook&tab=tools" class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
                    🔧 <?php _e('Ferramentas', 'wc-sgtm-webhook'); ?>
                </a>
            </nav>
            
            <div class="wc-sgtm-tab-content">
                <?php
                switch ($active_tab) {
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'orders':
                        $this->render_orders_tab();
                        break;
                    case 'tools':
                        $this->render_tools_tab();
                        break;
                    case 'dashboard':
                    default:
                        $this->render_dashboard_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_dashboard_tab() {
        $stats = WC_SGTM_Helpers::get_statistics();
        $is_enabled = WC_SGTM_Helpers::is_webhook_enabled();
        $is_debug = WC_SGTM_Helpers::is_debug_mode();
        $endpoint = WC_SGTM_Helpers::build_endpoint();
        ?>
        <div class="wc-sgtm-dashboard">
            <div class="wc-sgtm-cards">
                <div class="wc-sgtm-card <?php echo $is_enabled ? 'success' : 'error'; ?>">
                    <h3><?php _e('Status do Webhook', 'wc-sgtm-webhook'); ?></h3>
                    <div class="card-value">
                        <?php echo $is_enabled ? '✅ Ativo' : '❌ Inativo'; ?>
                    </div>
                    <div class="card-meta">
                        <?php echo !empty($endpoint) ? esc_html($endpoint) : __('Não configurado', 'wc-sgtm-webhook'); ?>
                    </div>
                </div>
                
                <div class="wc-sgtm-card">
                    <h3><?php _e('Envios Hoje', 'wc-sgtm-webhook'); ?></h3>
                    <div class="card-value"><?php echo number_format($stats['sent_today']); ?></div>
                    <div class="card-meta"><?php printf(__('%d erros', 'wc-sgtm-webhook'), $stats['errors_today']); ?></div>
                </div>
                
                <div class="wc-sgtm-card">
                    <h3><?php _e('Total Enviado', 'wc-sgtm-webhook'); ?></h3>
                    <div class="card-value"><?php echo number_format($stats['total_sent']); ?></div>
                    <div class="card-meta"><?php printf(__('%d erros totais', 'wc-sgtm-webhook'), $stats['total_errors']); ?></div>
                </div>
                
                <div class="wc-sgtm-card <?php echo $is_debug ? 'warning' : ''; ?>">
                    <h3><?php _e('Modo Debug', 'wc-sgtm-webhook'); ?></h3>
                    <div class="card-value">
                        <?php echo $is_debug ? '🔍 Ativo' : '✅ Desativo'; ?>
                    </div>
                    <div class="card-meta">
                        <?php echo $is_debug ? __('Desative em produção', 'wc-sgtm-webhook') : __('Tudo certo', 'wc-sgtm-webhook'); ?>
                    </div>
                </div>
            </div>
            
            <?php if ($stats['last_sent']): ?>
            <div class="wc-sgtm-info-box">
                <h3><?php _e('Último Envio', 'wc-sgtm-webhook'); ?></h3>
                <p>
                    <?php printf(
                        __('Pedido #%d em %s', 'wc-sgtm-webhook'),
                        $stats['last_order_id'],
                        date('d/m/Y H:i:s', strtotime($stats['last_sent']))
                    ); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="wc-sgtm-info-box">
                <h3><?php _e('Links Rápidos', 'wc-sgtm-webhook'); ?></h3>
                <p>
                    <a href="https://tagmanager.google.com" target="_blank" class="button">🏷️ Google Tag Manager</a>
                    <a href="https://app.stape.io" target="_blank" class="button">🚀 Stape.io</a>
                    <a href="https://business.facebook.com/events_manager" target="_blank" class="button">📘 Meta Events Manager</a>
                </p>
            </div>
        </div>
        <?php
    }
    
    private function render_settings_tab() {
        $webhook_url = WC_SGTM_Helpers::get_webhook_url();
        $container_id = WC_SGTM_Helpers::get_container_id();
        $webhook_token = WC_SGTM_Helpers::get_webhook_token();
        $webhook_enabled = WC_SGTM_Helpers::is_webhook_enabled();
        $debug_mode = WC_SGTM_Helpers::is_debug_mode();
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('wc_sgtm_action'); ?>
            <input type="hidden" name="wc_sgtm_action" value="save_settings">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="webhook_url"><?php _e('URL do Webhook', 'wc-sgtm-webhook'); ?></label>
                    </th>
                    <td>
                        <input type="url" 
                               id="webhook_url" 
                               name="webhook_url" 
                               value="<?php echo esc_attr($webhook_url); ?>" 
                               class="regular-text"
                               placeholder="https://sgtm.seudominio.com"
                               style="min-width: 400px;">
                        <p class="description">
                            <?php _e('URL base do seu servidor SGTM (Stape.io ou self-hosted). Não inclua /data no final.', 'wc-sgtm-webhook'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="container_id"><?php _e('Container ID', 'wc-sgtm-webhook'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="container_id" 
                               name="container_id" 
                               value="<?php echo esc_attr($container_id); ?>" 
                               class="regular-text"
                               placeholder="GTM-XXXXXXX"
                               pattern="GTM-[A-Z0-9]+"
                               maxlength="20">
                        <p class="description">
                            <?php _e('ID do container GTM Server-Side (ex: GTM-XXXXXXX)', 'wc-sgtm-webhook'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="webhook_token"><?php _e('Token de Autorização', 'wc-sgtm-webhook'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="webhook_token" 
                               name="webhook_token" 
                               value="<?php echo esc_attr($webhook_token); ?>" 
                               class="regular-text"
                               placeholder="token_secreto (opcional)">
                        <p class="description">
                            <?php _e('Token Bearer para autenticação (opcional). Será enviado no header Authorization.', 'wc-sgtm-webhook'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Ativar Webhook', 'wc-sgtm-webhook'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="webhook_enabled" 
                                   value="yes"
                                   <?php checked($webhook_enabled, true); ?>>
                            <?php _e('Enviar dados automaticamente para o webhook quando um pedido for pago', 'wc-sgtm-webhook'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Modo Debug', 'wc-sgtm-webhook'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="debug_mode" 
                                   value="yes"
                                   <?php checked($debug_mode, true); ?>>
                            <?php _e('Ativar logs detalhados (⚠️ desative em produção)', 'wc-sgtm-webhook'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary button-large">
                    💾 <?php _e('Salvar Configurações', 'wc-sgtm-webhook'); ?>
                </button>
            </p>
        </form>
        
        <div class="wc-sgtm-info-box">
            <h3><?php _e('ℹ️ Como Configurar', 'wc-sgtm-webhook'); ?></h3>
            <ol>
                <li><?php _e('Insira a URL base do seu servidor SGTM (sem /data)', 'wc-sgtm-webhook'); ?></li>
                <li><?php _e('Adicione o Container ID do GTM Server-Side', 'wc-sgtm-webhook'); ?></li>
                <li><?php _e('(Opcional) Configure um token de autorização para segurança extra', 'wc-sgtm-webhook'); ?></li>
                <li><?php _e('Ative o webhook e salve as configurações', 'wc-sgtm-webhook'); ?></li>
                <li><?php _e('Vá para a aba "Ferramentas" e teste a conexão', 'wc-sgtm-webhook'); ?></li>
            </ol>
            <p>
                <strong><?php _e('Endpoint final gerado:', 'wc-sgtm-webhook'); ?></strong>
                <code><?php echo esc_html(WC_SGTM_Helpers::build_endpoint() ?: __('Configure a URL primeiro', 'wc-sgtm-webhook')); ?></code>
            </p>
        </div>
        <?php
    }
    
    private function render_orders_tab() {
        $orders = wc_get_orders(array(
            'limit' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array('completed', 'processing')
        ));
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Pedido', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Data', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Status', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Total', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Webhook', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Resposta', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Ações', 'wc-sgtm-webhook'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="7"><?php _e('Nenhum pedido encontrado.', 'wc-sgtm-webhook'); ?></td>
                </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): 
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
                                <span style="color: red;" title="<?php echo esc_attr(is_array($webhook_error) && isset($webhook_error['error']) ? $webhook_error['error'] : ''); ?>">❌ Erro</span>
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
                            ?>
                        </td>
                        <td>
                            <button class="button button-small wc-sgtm-resend" data-order-id="<?php echo $order_id; ?>">
                                🔄 <?php _e('Reenviar', 'wc-sgtm-webhook'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function render_tools_tab() {
        $logs = WC_SGTM_Helpers::get_recent_logs(15);
        ?>
        <div class="wc-sgtm-tools">
            <h3><?php _e('⚡ Ações Rápidas', 'wc-sgtm-webhook'); ?></h3>
            
            <form method="post" style="display: inline-block;">
                <?php wp_nonce_field('wc_sgtm_action'); ?>
                <input type="hidden" name="wc_sgtm_action" value="test_connection">
                <button type="submit" class="button button-primary">
                    🧪 <?php _e('Testar Conexão', 'wc-sgtm-webhook'); ?>
                </button>
            </form>
            
            <form method="post" style="display: inline-block;">
                <?php wp_nonce_field('wc_sgtm_action'); ?>
                <input type="hidden" name="wc_sgtm_action" value="clear_logs">
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php _e('Limpar todos os logs?', 'wc-sgtm-webhook'); ?>')">
                    🗑️ <?php _e('Limpar Logs', 'wc-sgtm-webhook'); ?>
                </button>
            </form>
            
            <a href="<?php echo admin_url('admin.php?page=wc-status&tab=logs'); ?>" class="button button-secondary">
                📋 <?php _e('Ver Todos os Logs', 'wc-sgtm-webhook'); ?>
            </a>
            
            <h3 style="margin-top: 30px;"><?php _e('📝 Logs Recentes', 'wc-sgtm-webhook'); ?></h3>
            
            <?php if (empty($logs)): ?>
                <p><?php _e('Nenhum log encontrado. Ative o modo debug nas configurações.', 'wc-sgtm-webhook'); ?></p>
            <?php else: ?>
                <div class="wc-sgtm-logs">
                    <?php foreach ($logs as $log): ?>
                        <div class="wc-sgtm-log-entry <?php echo strtolower($log['level']); ?>">
                            <strong><?php echo esc_html($log['timestamp']); ?></strong>
                            <span class="log-level">[<?php echo esc_html($log['level']); ?>]</span>
                            <?php echo esc_html($log['message']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <h3 style="margin-top: 30px;"><?php _e('🔍 Informações do Sistema', 'wc-sgtm-webhook'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><?php _e('Versão do Plugin:', 'wc-sgtm-webhook'); ?></th>
                    <td><code><?php echo WC_SGTM_VERSION; ?></code></td>
                </tr>
                <tr>
                    <th><?php _e('WordPress:', 'wc-sgtm-webhook'); ?></th>
                    <td><code><?php echo get_bloginfo('version'); ?></code></td>
                </tr>
                <tr>
                    <th><?php _e('WooCommerce:', 'wc-sgtm-webhook'); ?></th>
                    <td><code><?php echo defined('WC_VERSION') ? WC_VERSION : 'N/A'; ?></code></td>
                </tr>
                <tr>
                    <th><?php _e('PHP:', 'wc-sgtm-webhook'); ?></th>
                    <td><code><?php echo PHP_VERSION; ?></code></td>
                </tr>
                <tr>
                    <th><?php _e('Endpoint Configurado:', 'wc-sgtm-webhook'); ?></th>
                    <td><code><?php echo esc_html(WC_SGTM_Helpers::build_endpoint() ?: __('Não configurado', 'wc-sgtm-webhook')); ?></code></td>
                </tr>
            </table>
        </div>
        <?php
    }
}
