<?php
/**
 * Plugin Name: WooCommerce SGTM Webhook
 * Plugin URI: https://wordpress.org/plugins/wc-sgtm-webhook/
 * Description: Integração profissional do WooCommerce com Server-Side Google Tag Manager via webhooks para rastreamento avançado de e-commerce.
 * Version: 1.2.2
 * Author: Seu Nome
 * Author URI: https://profiles.wordpress.org/seuusuario/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-sgtm-webhook
 * Domain Path: /languages
 * Requires at least: 5.6
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * Requires PHP: 7.4
 * Network: false
 */

// Evitar acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Verificar se WooCommerce está ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * ========================================
 * DEFINIÇÕES E CONSTANTES
 * ========================================
 */
if (!defined('WC_SGTM_WEBHOOK_VERSION')) {
    define('WC_SGTM_WEBHOOK_VERSION', '1.0.0');
}
if (!defined('WC_SGTM_WEBHOOK_PLUGIN_URL')) {
    define('WC_SGTM_WEBHOOK_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('WC_SGTM_WEBHOOK_PLUGIN_PATH')) {
    define('WC_SGTM_WEBHOOK_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('WC_SGTM_WEBHOOK_PLUGIN_BASENAME')) {
    define('WC_SGTM_WEBHOOK_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * ========================================
 * CLASSE PRINCIPAL DO PLUGIN
 * ========================================
 */
class WC_SGTM_Webhook_Pro {
    
    /**
     * Instância única do plugin (Singleton)
     */
    private static $instance = null;
    
    /**
     * Configurações do plugin
     */
    private $settings = array();
    
    /**
     * Componentes do plugin
     */
    private $browser_capture;
    private $webhook_sender;
    private $admin_panel;
    private $statistics_manager;
    private $logger;
    
    /**
     * Construtor privado (Singleton)
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Obter instância única (Singleton)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializar o plugin
     */
    private function init() {
        // Carregar textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Hooks de ativação/desativação
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Carregar configurações
        $this->load_settings();
        
        // Incluir arquivos necessários
        $this->includes();
        
        // Inicializar componentes
        $this->init_components();
        
        // Hooks de inicialização
        add_action('init', array($this, 'init_plugin'));
        add_action('wp_loaded', array($this, 'init_after_wp_loaded'));
        
        // Hook para verificar dependências
        add_action('admin_notices', array($this, 'check_dependencies'));
    }
    
    /**
     * Carregar configurações do plugin
     */
    private function load_settings() {
        $default_settings = array(
            'webhook_url' => '',
            'webhook_enabled' => false,
            'debug_mode' => false,
            'browser_capture_enabled' => true,
            'data_retention_days' => 30,
            'fallback_mode' => true,
            'validate_ssl' => true,
            'timeout' => 30,
            'retry_attempts' => 3,
            'pixel_tracking' => array(
                'facebook' => true,
                'google_ads' => true,
                'utm_params' => true
            ),
            'privacy' => array(
                'hash_pii' => true,
                'respect_consent' => true,
                'anonymize_ip' => false
            )
        );
        
        $this->settings = wp_parse_args(
            get_option('wc_sgtm_webhook_settings', array()),
            $default_settings
        );
    }
    
    /**
     * Incluir arquivos necessários
     */
    private function includes() {
        // Classes principais (apenas arquivos que existem)
        require_once WC_SGTM_WEBHOOK_PLUGIN_PATH . 'includes/helpers.php';
        require_once WC_SGTM_WEBHOOK_PLUGIN_PATH . 'includes/class-wc-sgtm-webhook-logger.php';
        require_once WC_SGTM_WEBHOOK_PLUGIN_PATH . 'includes/class-wc-sgtm-webhook.php';
        require_once WC_SGTM_WEBHOOK_PLUGIN_PATH . 'includes/class-wc-sgtm-webhook-sender.php';
        require_once WC_SGTM_WEBHOOK_PLUGIN_PATH . 'includes/class-wc-sgtm-webhook-statistics-manager.php';
        require_once WC_SGTM_WEBHOOK_PLUGIN_PATH . 'includes/class-wc-sgtm-webhook-installer.php';
    
        // Admin
        require_once WC_SGTM_WEBHOOK_PLUGIN_PATH . 'admin/class-wc-sgtm-webhook-admin.php';
    }
    
    /**
     * Inicializar componentes
     */
    private function init_components() {
        // Inicializar logger
        $this->logger = new WC_SGTM_Logger();
    
        // Inicializar componentes principais
        $this->browser_capture = new WC_SGTM_Browser_Capture($this->settings, $this->logger);
        $this->webhook_sender = new WC_SGTM_Webhook_Sender($this->settings, $this->logger);
        $this->statistics_manager = new WC_SGTM_Statistics_Manager($this);
    
        // Inicializar painel de administração
        $this->admin_panel = new WC_SGTM_Admin_Panel($this->settings, $this->logger, $this);
    
        // Inicializar instalador
        $installer = new WC_SGTM_Installer($this);
        $installer->check_update();
    }
    
    /**
     * Inicializar após WordPress carregar
     */
    public function init_plugin() {
        // Inicializar componentes que dependem do WordPress
        if ($this->browser_capture) {
            $this->browser_capture->init();
        }
        
        if ($this->webhook_sender) {
            $this->webhook_sender->init();
        }
        
        if ($this->statistics_manager) {
            $this->statistics_manager->init();
        }
        
        if (is_admin() && $this->admin_panel) {
            $this->admin_panel->init();
        }
        
        // Agendar tarefas de manutenção
        if (!wp_next_scheduled('wc_sgtm_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'wc_sgtm_daily_maintenance');
        }
        
        // Log de inicialização
        if ($this->is_debug_enabled()) {
            $this->logger->info('Plugin inicializado com sucesso', array(
                'version' => WC_SGTM_WEBHOOK_VERSION,
                'settings' => $this->settings
            ));
        }
    }
    
    /**
     * Inicializar após WP carregado completamente
     */
    public function init_after_wp_loaded() {
        // Verificar atualizações de configuração
        $this->maybe_update_settings();
        
        // Agendar eventos cron
        $this->schedule_cron_events();
    }
    
    /**
     * Verificar dependências
     */
    public function check_dependencies() {
        // Verificar WooCommerce
        if (!class_exists('WooCommerce')) {
            $this->show_admin_notice(
                __('WooCommerce SGTM Webhook Pro requer WooCommerce para funcionar.', 'wc-sgtm-webhook'),
                'error'
            );
        }
        
        // Verificar versão PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $this->show_admin_notice(
                __('WooCommerce SGTM Webhook Pro requer PHP 7.4 ou superior.', 'wc-sgtm-webhook'),
                'error'
            );
        }
        
        // Verificar configuração básica
        if (empty($this->settings['webhook_url']) && $this->settings['webhook_enabled']) {
            $this->show_admin_notice(
                sprintf(
                    __('Configure a URL do webhook nas %s para começar a usar o plugin.', 'wc-sgtm-webhook'),
                    '<a href="' . admin_url('admin.php?page=wc-sgtm-webhook-settings') . '">' . __('configurações', 'wc-sgtm-webhook') . '</a>'
                ),
                'warning'
            );
        }
    }
    
    /**
     * Carregar textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wc-sgtm-webhook',
            false,
            dirname(WC_SGTM_WEBHOOK_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Ativação do plugin
     */
    public function activate() {
        // Incluir classe do instalador se ainda não foi incluída
        if (!class_exists('WC_SGTM_Installer')) {
            require_once WC_SGTM_WEBHOOK_PLUGIN_PATH . 'includes/class-wc-sgtm-webhook-installer.php';
        }

        // Garantir que o logger exista durante a ativação
        if (!$this->logger && class_exists('WC_SGTM_Logger')) {
            $this->logger = new WC_SGTM_Logger();
        }

        // Inicializar instalador com logger garantido
        $installer = new WC_SGTM_Installer($this);
        $installer->install();
        
        // Configurações padrão
        if (!get_option('wc_sgtm_webhook_settings')) {
            update_option('wc_sgtm_webhook_settings', $this->settings);
        }
        
        // Agendar eventos cron
        if (!wp_next_scheduled('wc_sgtm_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'wc_sgtm_daily_maintenance');
        }
        
        // Log de ativação
        if (class_exists('WC_SGTM_Logger')) {
            $logger = new WC_SGTM_Logger();
            $logger->info('Plugin ativado', array('version' => WC_SGTM_WEBHOOK_VERSION));
        }
        
        // Flush rewrite rules se necessário
        flush_rewrite_rules();
    }
    
    /**
     * Desativação do plugin
     */
    public function deactivate() {
        // Limpar eventos cron
        wp_clear_scheduled_hook('wc_sgtm_daily_maintenance');
        wp_clear_scheduled_hook('wc_sgtm_webhook_cleanup');
        wp_clear_scheduled_hook('wc_sgtm_webhook_report');
        wp_clear_scheduled_hook('wc_sgtm_webhook_retry_failed');
        // Limpar eventos cron adicionais definidos em helpers
        wp_clear_scheduled_hook('wc_sgtm_weekly_report');
        wp_clear_scheduled_hook('wc_sgtm_cleanup_logs');

        // Log de desativação
        if ($this->logger) {
            $this->logger->info('Plugin desativado');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Obter instância do logger
     *
     * @return WC_SGTM_Logger
     */
    public function get_logger() {
        return $this->logger;
    }
    
    /**
     * Obter instância do webhook sender
     *
     * @return WC_SGTM_Webhook_Sender
     */
    public function get_webhook_sender() {
        return $this->webhook_sender;
    }
    
    /**
     * Obter instância do browser capture
     *
     * @return WC_SGTM_Browser_Capture
     */
    public function get_browser_capture() {
        return $this->browser_capture;
    }
    
    /**
     * Obter instância do statistics manager
     *
     * @return WC_SGTM_Statistics_Manager
     */
    public function get_statistics_manager() {
        return $this->statistics_manager;
    }
    /**
     * Agendar eventos cron
     */
    private function schedule_cron_events() {
        // Limpeza automática de logs antigos
        if (!wp_next_scheduled('wc_sgtm_webhook_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wc_sgtm_webhook_cleanup');
        }
        
        // Relatório semanal
        if (!wp_next_scheduled('wc_sgtm_webhook_report') && $this->settings['debug_mode']) {
            wp_schedule_event(time(), 'weekly', 'wc_sgtm_webhook_report');
        }
        
        // Retry de webhooks falhados
        if (!wp_next_scheduled('wc_sgtm_webhook_retry_failed')) {
            wp_schedule_event(time(), 'hourly', 'wc_sgtm_webhook_retry_failed');
        }
    }
    
    /**
     * Verificar se debug está habilitado
     */
    public function is_debug_enabled() {
        return !empty($this->settings['debug_mode']);
    }
    
    /**
     * Obter configuração específica
     */
    public function get_setting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Atualizar configuração
     */
    public function update_setting($key, $value) {
        $this->settings[$key] = $value;
        update_option('wc_sgtm_webhook_settings', $this->settings);
    }
    
    /**
     * Obter todas as configurações
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Atualizar todas as configurações
     */
    public function update_settings($new_settings) {
        $this->settings = wp_parse_args($new_settings, $this->settings);
        update_option('wc_sgtm_webhook_settings', $this->settings);
    }
    
    /**
     * Verificar se precisa atualizar configurações
     */
    private function maybe_update_settings() {
        $current_version = get_option('wc_sgtm_webhook_settings_version', '1.0.0');
        
        if (version_compare($current_version, WC_SGTM_WEBHOOK_VERSION, '<')) {
            $this->upgrade_settings($current_version);
            update_option('wc_sgtm_webhook_settings_version', WC_SGTM_WEBHOOK_VERSION);
        }
    }
    
    /**
     * Atualizar configurações para nova versão
     */
    private function upgrade_settings($from_version) {
        // Aqui você pode adicionar lógica de migração
        if (version_compare($from_version, '2.0.0', '<')) {
            // Migração para v2.0.0
            $old_settings = get_option('wc_sgtm_webhook_settings', array());
            
            // Migrar configurações antigas se existirem
            if (defined('SGTM_WEBHOOK_URL')) {
                $old_settings['webhook_url'] = SGTM_WEBHOOK_URL;
            }
            
            if (defined('SGTM_WEBHOOK_ENABLED')) {
                $old_settings['webhook_enabled'] = SGTM_WEBHOOK_ENABLED;
            }
            
            if (defined('SGTM_DEBUG_MODE')) {
                $old_settings['debug_mode'] = SGTM_DEBUG_MODE;
            }
            
            $this->update_settings($old_settings);
            
            $this->logger->info('Configurações migradas para v2.0.0', array(
                'from_version' => $from_version,
                'migrated_settings' => array_keys($old_settings)
            ));
        }
    }
    
    /**
     * Mostrar aviso no admin
     */
    private function show_admin_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                wp_kses_post($message)
            );
        });
    }
    
    /**
     * Obter instância do logger
     */
    public function get_logger() {
        return $this->logger;
    }
    
    /**
     * Obter instância do capturador de browser
     */
    public function get_browser_capture() {
        return $this->browser_capture;
    }
    
    /**
     * Obter instância do enviador de webhook
     */
    public function get_webhook_sender() {
        return $this->webhook_sender;
    }
    
    /**
     * Obter instância do painel admin
     */
    public function get_admin_panel() {
        return $this->admin_panel;
    }
}

/**
 * ========================================
 * FUNÇÃO GLOBAL PARA ACESSAR O PLUGIN
 * ========================================
 */
function wc_sgtm_webhook_pro() {
    return WC_SGTM_Webhook_Pro::get_instance();
}

/**
 * ========================================
 * INICIALIZAR O PLUGIN
 * ========================================
 */
add_action('plugins_loaded', function() {
    wc_sgtm_webhook_pro();
}, 10);

/**
 * ========================================
 * HOOKS DE LIMPEZA
 * ========================================
 */
add_action('wc_sgtm_webhook_cleanup', function() {
    $plugin = wc_sgtm_webhook_pro();
    $retention_days = $plugin->get_setting('data_retention_days', 30);
    
    // Limpar logs antigos
    global $wpdb;
    $table = $wpdb->prefix . 'wc_sgtm_webhook_logs';
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
    
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE created_at < %s",
        $cutoff_date
    ));
    
    if ($plugin->is_debug_enabled()) {
        $plugin->get_logger()->info("Limpeza automática executada", array(
            'deleted_logs' => $deleted,
            'cutoff_date' => $cutoff_date
        ));
    }
});

/**
 * ========================================
 * COMPATIBILIDADE COM HPOS
 * ========================================
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * ========================================
 * LINKS DE AÇÃO DO PLUGIN
 * ========================================
 */
add_filter('plugin_action_links_' . WC_SGTM_WEBHOOK_PLUGIN_BASENAME, function($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=wc-sgtm-webhook-settings'),
        __('Configurações', 'wc-sgtm-webhook')
    );
    
    $docs_link = sprintf(
        '<a href="%s" target="_blank">%s</a>',
        'https://github.com/seu-usuario/wc-sgtm-webhook-pro/wiki',
        __('Documentação', 'wc-sgtm-webhook')
    );
    
    array_unshift($links, $settings_link);
    array_push($links, $docs_link);
    
    return $links;
});

/**
 * ========================================
 * INFORMAÇÕES DO PLUGIN
 * ========================================
 */
if (WC_SGTM_Webhook_Pro::get_instance()->is_debug_enabled()) {
    add_action('wp_footer', function() {
        if (current_user_can('manage_options')) {
            echo '<!-- WC SGTM Webhook Pro v' . WC_SGTM_WEBHOOK_VERSION . ' - Debug Mode Ativo -->';
        }
    });
}

// Requisitos mínimos
if (!defined('WC_SGTM_MIN_PHP')) {
    define('WC_SGTM_MIN_PHP', '7.4');
}
if (!defined('WC_SGTM_MIN_WC')) {
    define('WC_SGTM_MIN_WC', '5.8');
}

function wc_sgtm_requirements_met() {
    $php_ok = version_compare(PHP_VERSION, WC_SGTM_MIN_PHP, '>=');
    $wc_ok  = false;

    if (class_exists('WooCommerce') && function_exists('WC')) {
        $wc_version = defined('WC_VERSION') ? WC_VERSION : (WC()->version ?? get_option('woocommerce_version'));
        if (is_string($wc_version)) {
            $wc_ok = version_compare($wc_version, WC_SGTM_MIN_WC, '>=');
        }
    }

    return ($php_ok && $wc_ok);
}

function wc_sgtm_admin_notice_requirements() {
    if (wc_sgtm_requirements_met()) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    $msg = sprintf(
        'WC-SGTM-Webhook requer PHP %s+ e WooCommerce %s+. Atualize seu ambiente para utilizar o plugin.',
        esc_html(WC_SGTM_MIN_PHP),
        esc_html(WC_SGTM_MIN_WC)
    );
    echo '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>';
}
add_action('admin_notices', 'wc_sgtm_admin_notice_requirements');

// Impede inicialização se requisitos não atendidos
add_action('plugins_loaded', function () {
    if (!wc_sgtm_requirements_met()) {
        return;
    }
    // ... existing code ...
});
}
}