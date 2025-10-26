<?php
/**
 * Classe para gerenciar estatísticas do plugin
 *
 * @package WC_SGTM_Webhook_Pro
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe WC_SGTM_Statistics_Manager
 */
class WC_SGTM_Statistics_Manager {
    /**
     * Instância do plugin principal
     *
     * @var WC_SGTM_Webhook
     */
    private $plugin;

    /**
     * Instância do logger
     *
     * @var WC_SGTM_Logger
     */
    private $logger;

    /**
     * Construtor
     *
     * @param WC_SGTM_Webhook $plugin Instância do plugin principal
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->logger = $plugin->get_logger();

        // Inicializar hooks
        $this->init();
    }

    /**
     * Inicializar hooks
     */
    public function init() {
        // Registrar webhook enviado
        add_action('wc_sgtm_webhook_sent', array($this, 'register_webhook_sent'), 10, 2);
        
        // Registrar erro de webhook
        add_action('wc_sgtm_webhook_error', array($this, 'register_webhook_error'), 10, 3);
        
        // Limpar estatísticas antigas
        add_action('wc_sgtm_daily_maintenance', array($this, 'cleanup_old_stats'));
    }

    /**
     * Registrar webhook enviado
     *
     * @param int $order_id ID do pedido
     * @param array $response Resposta do webhook
     */
    public function register_webhook_sent($order_id, $response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_stats';
        
        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'event_type' => 'purchase',
                'status' => 'success',
                'response_code' => $response['response_code'] ?? 200,
                'message' => 'Webhook enviado com sucesso',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
        
        $this->logger->debug('Estatística registrada: webhook enviado', array(
            'order_id' => $order_id,
            'response_code' => $response['response_code'] ?? 200
        ));
    }

    /**
     * Registrar erro de webhook
     *
     * @param int $order_id ID do pedido
     * @param string $error Mensagem de erro
     * @param array $response Resposta do webhook (opcional)
     */
    public function register_webhook_error($order_id, $error, $response = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_stats';
        
        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'event_type' => 'purchase',
                'status' => 'error',
                'response_code' => $response['response_code'] ?? 0,
                'message' => $error,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
        
        $this->logger->debug('Estatística registrada: erro de webhook', array(
            'order_id' => $order_id,
            'error' => $error,
            'response_code' => $response['response_code'] ?? 0
        ));
    }

    /**
     * Obter estatísticas melhoradas
     *
     * @return array Estatísticas
     */
    public function get_enhanced_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_stats';
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        $today = date('Y-m-d 00:00:00');
        
        // Verificar se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            // Fallback para o método antigo se a tabela não existir
            return $this->get_legacy_statistics();
        }
        
        // Total de webhooks enviados (30 dias)
        $total_sent = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s", $thirty_days_ago));
        
        // Webhooks enviados com sucesso (30 dias)
        $successful_sent = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE status = 'success' AND created_at >= %s", $thirty_days_ago));
        
        // Webhooks enviados (7 dias)
        $sent_7_days = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s", $seven_days_ago));
        
        // Erros hoje
        $errors_today = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE status = 'error' AND created_at >= %s", $today));
        
        // Taxa de sucesso
        $success_rate = $total_sent > 0 ? round(($successful_sent / $total_sent) * 100) : 0;
        
        // Último webhook enviado
        $last_webhook = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 1"));
        
        // Receita total processada (30 dias)
        $total_revenue = $wpdb->get_var($wpdb->prepare("SELECT SUM(pm.meta_value) FROM {$wpdb->postmeta} pm INNER JOIN {$table_name} s ON pm.post_id = s.order_id WHERE pm.meta_key = '_order_total' AND s.created_at >= %s", $thirty_days_ago));
        
        // Pedidos com browser data (30 dias)
        $with_browser_data = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND p.post_date >= %s AND pm.meta_key = '_browser_data' AND pm.meta_value != ''", $thirty_days_ago));
        
        // Calcular taxa de captura de browser data
        $browser_capture_rate = $total_sent > 0 ? round(($with_browser_data / $total_sent) * 100) : 0;
        
        return array(
            'total_sent' => $total_sent,
            'sent_7_days' => $sent_7_days,
            'errors_today' => $errors_today,
            'success_rate' => $success_rate,
            'total_revenue' => $total_revenue,
            'last_webhook' => $last_webhook ? array(
                'order_id' => $last_webhook->order_id,
                'date' => $last_webhook->created_at,
                'status' => $last_webhook->status
            ) : null,
            'browser_capture_rate' => $browser_capture_rate,
            'with_browser_data' => $with_browser_data
        );
    }
    
    /**
     * Obter estatísticas usando o método legado (antes da tabela de estatísticas)
     * 
     * @return array Estatísticas
     */
    private function get_legacy_statistics() {
        global $wpdb;
        
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        $today = date('Y-m-d 00:00:00');
        
        // Webhooks enviados (30 dias)
        $total_sent = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND p.post_date >= %s AND pm.meta_key = '_sgtm_webhook_sent' AND pm.meta_value != ''", $thirty_days_ago));
        
        // Webhooks enviados (7 dias)
        $sent_7_days = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND p.post_date >= %s AND pm.meta_key = '_sgtm_webhook_sent' AND pm.meta_value != ''", $seven_days_ago));
        
        // Erros hoje
        $errors_today = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND p.post_date >= %s AND pm.meta_key = '_sgtm_webhook_error' AND pm.meta_value != ''", $today));
        
        // Pedidos com browser data (30 dias)
        $with_browser_data = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND p.post_date >= %s AND pm.meta_key = '_browser_data' AND pm.meta_value != ''", $thirty_days_ago));
        
        // Último webhook enviado
        $last_webhook = $wpdb->get_row("SELECT p.ID, pm.meta_value as sent_date FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND pm.meta_key = '_sgtm_webhook_sent' AND pm.meta_value != '' ORDER BY pm.meta_value DESC LIMIT 1");
        
        // Receita total processada (30 dias)
        $total_revenue = $wpdb->get_var($wpdb->prepare("SELECT SUM(CAST(pm2.meta_value AS DECIMAL(10,2))) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id WHERE p.post_type = 'shop_order' AND p.post_date >= %s AND pm.meta_key = '_sgtm_webhook_sent' AND pm.meta_value != '' AND pm2.meta_key = '_order_total'", $thirty_days_ago));
        
        // Calcular taxa de sucesso (estimativa)
        $success_rate = $total_sent > 0 ? round((($total_sent - $errors_today) / $total_sent) * 100) : 0;
        
        // Calcular taxa de captura de browser data
        $browser_capture_rate = $total_sent > 0 ? round(($with_browser_data / $total_sent) * 100) : 0;
        
        return array(
            'total_sent' => $total_sent,
            'sent_7_days' => $sent_7_days,
            'errors_today' => $errors_today,
            'success_rate' => $success_rate,
            'total_revenue' => $total_revenue,
            'last_webhook' => $last_webhook ? array(
                'order_id' => $last_webhook->ID,
                'date' => $last_webhook->sent_date,
                'status' => 'success' // Assumimos sucesso no método legado
            ) : null,
            'browser_capture_rate' => $browser_capture_rate,
            'with_browser_data' => $with_browser_data
        );
    }

    /**
     * Obter logs recentes
     *
     * @param int $limit Limite de logs
     * @return array Logs recentes
     */
    public function get_recent_logs($limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_stats';
        
        // Verificar se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return array(); // Retornar array vazio se a tabela não existir
        }
        
        $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d", $limit));
        
        return $logs;
    }

    /**
     * Limpar estatísticas antigas
     */
    public function cleanup_old_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_stats';
        $retention_days = $this->plugin->get_setting('data_retention_days', 90);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Verificar se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return; // Sair se a tabela não existir
        }
        
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE created_at < %s", $cutoff_date));
        
        if ($deleted) {
            $this->logger->info('Limpeza de estatísticas antigas concluída', array(
                'deleted_records' => $deleted,
                'retention_days' => $retention_days,
                'cutoff_date' => $cutoff_date
            ));
        }
    }

    /**
     * Criar tabela de estatísticas
     */
    public function create_stats_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_stats';
        
        // Verificar se a tabela já existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            return; // Sair se a tabela já existir
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            event_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            response_code int(11) NOT NULL DEFAULT 0,
            message text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->logger->info('Tabela de estatísticas criada', array(
            'table_name' => $table_name
        ));
    }
}