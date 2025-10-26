<?php
/**
 * Template para o widget do dashboard
 * 
 * @package WC_SGTM_Webhook_Pro
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wc-sgtm-webhook-dashboard-widget">
    <div class="wc-sgtm-webhook-widget-status">
        <div class="wc-sgtm-webhook-widget-status-indicator <?php echo $webhook_enabled ? 'active' : 'inactive'; ?>">
            <?php if ($webhook_enabled): ?>
                <span class="dashicons dashicons-yes-alt"></span> <?php _e('Ativo', 'wc-sgtm-webhook'); ?>
            <?php else: ?>
                <span class="dashicons dashicons-no-alt"></span> <?php _e('Inativo', 'wc-sgtm-webhook'); ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="wc-sgtm-webhook-widget-stats">
        <div class="wc-sgtm-webhook-widget-stat">
            <div class="wc-sgtm-webhook-widget-stat-label"><?php _e('Enviados', 'wc-sgtm-webhook'); ?></div>
            <div class="wc-sgtm-webhook-widget-stat-value"><?php echo number_format($stats['total_sent']); ?></div>
        </div>
        
        <div class="wc-sgtm-webhook-widget-stat">
            <div class="wc-sgtm-webhook-widget-stat-label"><?php _e('Erros Hoje', 'wc-sgtm-webhook'); ?></div>
            <div class="wc-sgtm-webhook-widget-stat-value"><?php echo $stats['errors_today']; ?></div>
        </div>
        
        <div class="wc-sgtm-webhook-widget-stat">
            <div class="wc-sgtm-webhook-widget-stat-label"><?php _e('Sucesso', 'wc-sgtm-webhook'); ?></div>
            <div class="wc-sgtm-webhook-widget-stat-value"><?php echo $stats['success_rate']; ?>%</div>
        </div>
        
        <div class="wc-sgtm-webhook-widget-stat">
            <div class="wc-sgtm-webhook-widget-stat-label"><?php _e('Total', 'wc-sgtm-webhook'); ?></div>
            <div class="wc-sgtm-webhook-widget-stat-value">R$ <?php echo number_format($stats['total_revenue'], 2, ',', '.'); ?></div>
        </div>
    </div>
    
    <?php if (!empty($stats['last_sent'])): ?>
    <div class="wc-sgtm-webhook-widget-last-sent">
        <strong><?php _e('Último envio:', 'wc-sgtm-webhook'); ?></strong> 
        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($stats['last_sent'])); ?>
        (<?php echo sprintf(__('Pedido #%s', 'wc-sgtm-webhook'), $stats['last_order_id']); ?>)
    </div>
    <?php endif; ?>
    
    <div class="wc-sgtm-webhook-widget-footer">
        <a href="<?php echo admin_url('admin.php?page=wc-sgtm-webhook'); ?>" class="button button-primary">
            <?php _e('Ver Painel Completo', 'wc-sgtm-webhook'); ?>
        </a>
    </div>
</div>