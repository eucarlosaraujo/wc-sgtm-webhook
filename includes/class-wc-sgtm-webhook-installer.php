<?php
/**
 * Classe para instalação e atualização do plugin
 *
 * @package WC_SGTM_Webhook_Pro
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe WC_SGTM_Installer
 */
class WC_SGTM_Installer {
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
        if (!$this->logger && class_exists('WC_SGTM_Logger')) {
            $this->logger = new WC_SGTM_Logger();
        }
    }

    /**
     * Executar instalação
     */
    public function install() {
        if ($this->logger) {
            $this->logger->info('Iniciando instalação do plugin');
        }
        
        // Criar tabela de estatísticas
        $this->create_stats_table();
        
        // Atualizar versão do plugin
        update_option('wc_sgtm_webhook_version', WC_SGTM_WEBHOOK_VERSION);

        if ($this->logger) {
            $this->logger->info('Instalação do plugin concluída', array(
                'version' => WC_SGTM_WEBHOOK_VERSION
            ));
        }
    }

    /**
     * Verificar se é necessário atualizar
     */
    public function check_update() {
        $installed_version = get_option('wc_sgtm_webhook_version', '1.0.0');

        if (version_compare($installed_version, WC_SGTM_WEBHOOK_VERSION, '<')) {
            if ($this->logger) {
                $this->logger->info('Atualizando plugin', array(
                    'from' => $installed_version,
                    'to' => WC_SGTM_WEBHOOK_VERSION
                ));
            }

            $this->update($installed_version);
            update_option('wc_sgtm_webhook_version', WC_SGTM_WEBHOOK_VERSION);

            if ($this->logger) {
                $this->logger->info('Atualização do plugin concluída');
            }
        }
    }

    /**
     * Atualizar plugin
     *
     * @param string $from_version Versão anterior
     */
    private function update($from_version) {
        // Atualizar para versão 2.0.0
        if (version_compare($from_version, '2.0.0', '<')) {
            $this->update_to_2_0_0();
        }
    }

    /**
     * Atualizar para versão 2.0.0
     */
    private function update_to_2_0_0() {
        // Criar tabela de estatísticas
        $this->create_stats_table();
        
        // Migrar dados antigos para a nova tabela
        $this->migrate_legacy_data();
    }

    /**
     * Criar tabela de estatísticas
     */
    private function create_stats_table() {
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

        if ($this->logger) {
            $this->logger->info('Tabela de estatísticas criada', array(
                'table_name' => $table_name
            ));
        }
    }

    /**
     * Migrar dados antigos para a nova tabela
     */
    private function migrate_legacy_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_sgtm_webhook_stats';
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Verificar se a tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return; // Sair se a tabela não existir
        }
        
        // Obter pedidos com webhooks enviados nos útimos 30 dias
        $orders = $wpdb->get_results($wpdb->prepare("SELECT p.ID, pm.meta_value as sent_date FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'shop_order' AND p.post_date >= %s AND pm.meta_key = '_sgtm_webhook_sent' AND pm.meta_value != '' ORDER BY pm.meta_value DESC", $thirty_days_ago));
        
        if (empty($orders)) {
            if ($this->logger) {
                $this->logger->info('Nenhum dado legado para migrar');
            }
            return;
        }
        
        $migrated = 0;
        
        foreach ($orders as $order) {
            // Verificar se já existe registro para este pedido
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE order_id = %d", $order->ID));
            
            if ($exists) {
                continue; // Pular se já existe
            }
            
            // Verificar se houve erro
            $error = get_post_meta($order->ID, '_sgtm_webhook_error', true);
            $status = empty($error) ? 'success' : 'error';
            $message = empty($error) ? 'Webhook enviado com sucesso' : $error;
            
            // Inserir registro
            $wpdb->insert(
                $table_name,
                array(
                    'order_id' => $order->ID,
                    'event_type' => 'purchase',
                    'status' => $status,
                    'response_code' => empty($error) ? 200 : 0,
                    'message' => $message,
                    'created_at' => $order->sent_date
                ),
                array('%d', '%s', '%s', '%d', '%s', '%s')
            );
            
            $migrated++;
        }
        
        if ($this->logger) {
            $this->logger->info('Migração de dados legados concluída', array(
                'migrated' => $migrated,
                'total' => count($orders)
            ));
        }
    }
}