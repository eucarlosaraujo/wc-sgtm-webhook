<?php
/**
 * Template para a página principal do plugin
 * 
 * @package WC_SGTM_Webhook_Pro
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wc-sgtm-webhook-wrap">
    <h1 class="wp-heading-inline"><?php _e('SGTM Webhook Pro', 'wc-sgtm-webhook'); ?></h1>
    
    <div class="wc-sgtm-webhook-header">
        <div class="wc-sgtm-webhook-status">
            <h2><?php _e('Status do Webhook', 'wc-sgtm-webhook'); ?></h2>
            <div class="wc-sgtm-webhook-status-indicator <?php echo $this->plugin->get_setting('webhook_enabled') ? 'active' : 'inactive'; ?>">
                <?php if ($this->plugin->get_setting('webhook_enabled')): ?>
                    <span class="dashicons dashicons-yes-alt"></span> <?php _e('Ativo', 'wc-sgtm-webhook'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-no-alt"></span> <?php _e('Inativo', 'wc-sgtm-webhook'); ?>
                <?php endif; ?>
            </div>
            <p>
                <a href="<?php echo admin_url('admin.php?page=wc-sgtm-webhook-settings'); ?>" class="button">
                    <?php _e('Configurações', 'wc-sgtm-webhook'); ?>
                </a>
                <button class="button" id="wc-sgtm-test-webhook">
                    <?php _e('Testar Webhook', 'wc-sgtm-webhook'); ?>
                </button>
            </p>
        </div>
        
        <div class="wc-sgtm-webhook-info">
            <h2><?php _e('Informações', 'wc-sgtm-webhook'); ?></h2>
            <ul>
                <li><strong><?php _e('URL:', 'wc-sgtm-webhook'); ?></strong> <?php echo esc_html($this->plugin->get_setting('webhook_url')); ?></li>
                <li><strong><?php _e('Versão:', 'wc-sgtm-webhook'); ?></strong> <?php echo WC_SGTM_WEBHOOK_VERSION; ?></li>
                <li><strong><?php _e('WooCommerce:', 'wc-sgtm-webhook'); ?></strong> <?php echo WC()->version; ?></li>
            </ul>
        </div>
    </div>
    
    <div class="wc-sgtm-webhook-statistics">
        <h2><?php _e('Estatísticas', 'wc-sgtm-webhook'); ?></h2>
        
        <div class="wc-sgtm-webhook-stats-grid">
            <div class="wc-sgtm-webhook-stat-card">
                <div class="wc-sgtm-webhook-stat-icon">
                    <span class="dashicons dashicons-upload"></span>
                </div>
                <div class="wc-sgtm-webhook-stat-content">
                    <h3><?php _e('Webhooks Enviados', 'wc-sgtm-webhook'); ?></h3>
                    <div class="wc-sgtm-webhook-stat-value"><?php echo number_format($stats['total_sent']); ?></div>
                    <div class="wc-sgtm-webhook-stat-period"><?php _e('Últimos 30 dias', 'wc-sgtm-webhook'); ?></div>
                </div>
            </div>
            
            <div class="wc-sgtm-webhook-stat-card">
                <div class="wc-sgtm-webhook-stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="wc-sgtm-webhook-stat-content">
                    <h3><?php _e('Erros Hoje', 'wc-sgtm-webhook'); ?></h3>
                    <div class="wc-sgtm-webhook-stat-value"><?php echo $stats['errors_today']; ?></div>
                    <div class="wc-sgtm-webhook-stat-period"><?php _e('Hoje', 'wc-sgtm-webhook'); ?></div>
                </div>
            </div>
            
            <div class="wc-sgtm-webhook-stat-card">
                <div class="wc-sgtm-webhook-stat-icon">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <div class="wc-sgtm-webhook-stat-content">
                    <h3><?php _e('Taxa de Sucesso', 'wc-sgtm-webhook'); ?></h3>
                    <div class="wc-sgtm-webhook-stat-value"><?php echo $stats['success_rate']; ?>%</div>
                    <div class="wc-sgtm-webhook-stat-period"><?php _e('Últimos 30 dias', 'wc-sgtm-webhook'); ?></div>
                </div>
            </div>
            
            <div class="wc-sgtm-webhook-stat-card">
                <div class="wc-sgtm-webhook-stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="wc-sgtm-webhook-stat-content">
                    <h3><?php _e('Total Processado', 'wc-sgtm-webhook'); ?></h3>
                    <div class="wc-sgtm-webhook-stat-value">R$ <?php echo number_format($stats['total_revenue'], 2, ',', '.'); ?></div>
                    <div class="wc-sgtm-webhook-stat-period"><?php _e('Últimos 30 dias', 'wc-sgtm-webhook'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="wc-sgtm-webhook-data-capture">
            <h3><?php _e('Captura de Dados', 'wc-sgtm-webhook'); ?></h3>
            
            <div class="wc-sgtm-webhook-data-capture-grid">
                <div class="wc-sgtm-webhook-data-capture-item">
                    <div class="wc-sgtm-webhook-data-capture-label"><?php _e('Dados do Navegador', 'wc-sgtm-webhook'); ?></div>
                    <div class="wc-sgtm-webhook-data-capture-value"><?php echo $stats['browser_data_rate']; ?>%</div>
                    <div class="wc-sgtm-webhook-data-capture-bar">
                        <div class="wc-sgtm-webhook-data-capture-progress" style="width: <?php echo $stats['browser_data_rate']; ?>%"></div>
                    </div>
                </div>
                
                <div class="wc-sgtm-webhook-data-capture-item">
                    <div class="wc-sgtm-webhook-data-capture-label"><?php _e('Dados do Facebook', 'wc-sgtm-webhook'); ?></div>
                    <div class="wc-sgtm-webhook-data-capture-value"><?php echo $stats['facebook_data_rate']; ?>%</div>
                    <div class="wc-sgtm-webhook-data-capture-bar">
                        <div class="wc-sgtm-webhook-data-capture-progress" style="width: <?php echo $stats['facebook_data_rate']; ?>%"></div>
                    </div>
                </div>
                
                <div class="wc-sgtm-webhook-data-capture-item">
                    <div class="wc-sgtm-webhook-data-capture-label"><?php _e('Dados do Google', 'wc-sgtm-webhook'); ?></div>
                    <div class="wc-sgtm-webhook-data-capture-value"><?php echo $stats['google_data_rate']; ?>%</div>
                    <div class="wc-sgtm-webhook-data-capture-bar">
                        <div class="wc-sgtm-webhook-data-capture-progress" style="width: <?php echo $stats['google_data_rate']; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="wc-sgtm-webhook-recent-activity">
        <h2><?php _e('Atividade Recente', 'wc-sgtm-webhook'); ?></h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Pedido', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Data', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Status', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Valor', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Dados', 'wc-sgtm-webhook'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_orders)): ?>
                    <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $order->ID . '&action=edit'); ?>">
                                    #<?php echo $order->ID; ?>
                                </a>
                            </td>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order->webhook_date)); ?></td>
                            <td>
                                <?php if ($order->webhook_status === 'success'): ?>
                                    <span class="wc-sgtm-webhook-status-success"><?php _e('Sucesso', 'wc-sgtm-webhook'); ?></span>
                                <?php else: ?>
                                    <span class="wc-sgtm-webhook-status-error"><?php _e('Erro', 'wc-sgtm-webhook'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?php echo number_format($order->order_total, 2, ',', '.'); ?></td>
                            <td>
                                <?php if ($order->has_browser_data): ?>
                                    <span class="dashicons dashicons-yes"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-no"></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5"><?php _e('Nenhum pedido recente encontrado.', 'wc-sgtm-webhook'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>