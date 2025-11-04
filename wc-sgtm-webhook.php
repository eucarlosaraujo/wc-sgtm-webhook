<?php
/**
 * Plugin Name: WC SGTM Webhook Pro
 * Plugin URI: https://github.com/elevelife/wc-sgtm-webhook
 * Description: Envia dados de pedidos pagos para Server-Side Google Tag Manager (Stape.io) via Data Client com Event Match Quality otimizado para Meta Ads.
 * Version: 3.0.0
 * Author: Carlos Araújo - Alta Cúpula / Elevelife
 * Author URI: https://elevelife.com
 * Text Domain: wc-sgtm-webhook
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WC_SGTM_Webhook
 * @version 3.0.0
 */

if (!defined('ABSPATH')) exit;

define('WC_SGTM_VERSION', '3.0.0');
define('WC_SGTM_PLUGIN_FILE', __FILE__);
define('WC_SGTM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_SGTM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_SGTM_PLUGIN_BASENAME', plugin_basename(__FILE__));

final class WC_SGTM_Webhook {
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
        add_action('plugins_loaded', array($this, 'check_dependencies'), 10);
        add_action('plugins_loaded', array($this, 'init'), 20);
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_filter('plugin_action_links_' . WC_SGTM_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }
    
    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return false;
        }
        return true;
    }
    
    public function init() {
        if (!$this->check_dependencies()) return;
        
        load_plugin_textdomain('wc-sgtm-webhook', false, dirname(WC_SGTM_PLUGIN_BASENAME) . '/languages');
        
        $this->includes();
        $this->init_components();
        
        do_action('wc_sgtm_webhook_loaded');
    }
    
    private function includes() {
        require_once WC_SGTM_PLUGIN_DIR . 'includes/class-helpers.php';
        require_once WC_SGTM_PLUGIN_DIR . 'includes/class-core.php';
        require_once WC_SGTM_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WC_SGTM_PLUGIN_DIR . 'includes/class-ajax.php';
    }
    
    private function init_components() {
        WC_SGTM_Core::instance();
        
        if (is_admin()) {
            WC_SGTM_Admin::instance();
            WC_SGTM_Ajax::instance();
        }
    }
    
    public function activate() {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(WC_SGTM_PLUGIN_BASENAME);
            wp_die(
                __('WC SGTM Webhook Pro requer WooCommerce. Instale o WooCommerce primeiro.', 'wc-sgtm-webhook'),
                __('Dependência Necessária', 'wc-sgtm-webhook'),
                array('back_link' => true)
            );
        }
        
        $defaults = array(
            'wc_sgtm_webhook_enabled' => 'no',
            'wc_sgtm_debug_mode' => 'no',
            'wc_sgtm_webhook_url' => '',
            'wc_sgtm_container_id' => '',
            'wc_sgtm_webhook_token' => '',
            'wc_sgtm_version' => WC_SGTM_VERSION
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('WC SGTM Webhook Pro', 'wc-sgtm-webhook'); ?></strong> 
                <?php _e('requer o WooCommerce. Instale e ative o WooCommerce.', 'wc-sgtm-webhook'); ?>
            </p>
        </div>
        <?php
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-sgtm-webhook') . '">' . __('Configurações', 'wc-sgtm-webhook') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

function wc_sgtm_webhook() {
    return WC_SGTM_Webhook::instance();
}

wc_sgtm_webhook();
