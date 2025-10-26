<?php
/**
 * Plugin Name: WooCommerce SGTM Webhook
 * Plugin URI: https://github.com/yourusername/wc-sgtm-webhook
 * Description: Integração do WooCommerce com o Server-Side Google Tag Manager via webhooks
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://seusite.com
 * Text Domain: wc-sgtm-webhook
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package WC_SGTM_Webhook
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Definir constantes
define('WC_SGTM_WEBHOOK_VERSION', '1.0.0');
define('WC_SGTM_WEBHOOK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_SGTM_WEBHOOK_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Verificar se o WooCommerce está ativo
 */
function wc_sgtm_webhook_is_woocommerce_active() {
    $active_plugins = (array) get_option('active_plugins', array());
    
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
    }
    
    return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

/**
 * Inicializar o plugin
 */
function wc_sgtm_webhook_init() {
    // Carregar arquivos principais
    require_once WC_SGTM_WEBHOOK_PLUGIN_DIR . 'includes/helpers.php';
    require_once WC_SGTM_WEBHOOK_PLUGIN_DIR . 'includes/class-wc-sgtm-webhook.php';
    
    // Carregar arquivos de administração
    if (is_admin()) {
        require_once WC_SGTM_WEBHOOK_PLUGIN_DIR . 'admin/class-wc-sgtm-webhook-admin.php';
    }
    
    // Inicializar o plugin
    WC_SGTM_Webhook::instance();
}

/**
 * Verificar dependências e inicializar o plugin
 */
function wc_sgtm_webhook_plugins_loaded() {
    if (wc_sgtm_webhook_is_woocommerce_active()) {
        wc_sgtm_webhook_init();
    } else {
        add_action('admin_notices', 'wc_sgtm_webhook_woocommerce_missing_notice');
    }
}
add_action('plugins_loaded', 'wc_sgtm_webhook_plugins_loaded');

/**
 * Exibir aviso se o WooCommerce não estiver ativo
 */
function wc_sgtm_webhook_woocommerce_missing_notice() {
    echo '<div class="error"><p>' . sprintf(
        __('O plugin %1$s requer o WooCommerce para funcionar. Por favor, instale e ative o %2$s primeiro.', 'wc-sgtm-webhook'),
        '<strong>WooCommerce SGTM Webhook</strong>',
        '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
    ) . '</p></div>';
}

/**
 * Função de ativação do plugin
 */
function wc_sgtm_webhook_activate() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wc_sgtm_webhook_logs';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event_type varchar(50) NOT NULL,
        order_id bigint(20) NULL,
        order_total decimal(10,2) NULL,
        status varchar(20) NOT NULL,
        date_created datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    
    // Adicionar opção para controlar a versão da tabela
    add_option('wc_sgtm_webhook_db_version', WC_SGTM_WEBHOOK_VERSION);
}
register_activation_hook(__FILE__, 'wc_sgtm_webhook_activate');

/**
 * Função de desativação do plugin
 */
function wc_sgtm_webhook_deactivate() {
    // Código de desativação aqui
}
register_deactivation_hook(__FILE__, 'wc_sgtm_webhook_deactivate');