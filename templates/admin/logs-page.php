<?php
/**
 * Template para a página de logs
 * 
 * @package WC_SGTM_Webhook_Pro
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wc-sgtm-webhook-wrap">
    <h1 class="wp-heading-inline"><?php _e('Logs do SGTM Webhook', 'wc-sgtm-webhook'); ?></h1>
    
    <div class="wc-sgtm-webhook-logs-actions">
        <button class="button" id="wc-sgtm-export-logs"><?php _e('Exportar Logs', 'wc-sgtm-webhook'); ?></button>
        <button class="button" id="wc-sgtm-clear-logs"><?php _e('Limpar Logs', 'wc-sgtm-webhook'); ?></button>
    </div>
    
    <div class="wc-sgtm-webhook-logs-section">
        <h2><?php _e('Logs Recentes', 'wc-sgtm-webhook'); ?></h2>
        
        <?php if (empty($logs)): ?>
            <p><?php _e('Nenhum log encontrado.', 'wc-sgtm-webhook'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Data', 'wc-sgtm-webhook'); ?></th>
                        <th><?php _e('Pedido', 'wc-sgtm-webhook'); ?></th>
                        <th><?php _e('Evento', 'wc-sgtm-webhook'); ?></th>
                        <th><?php _e('Status', 'wc-sgtm-webhook'); ?></th>
                        <th><?php _e('Código', 'wc-sgtm-webhook'); ?></th>
                        <th><?php _e('Mensagem', 'wc-sgtm-webhook'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at)); ?></td>
                            <td>
                                <?php if ($log->order_id): ?>
                                    <a href="<?php echo admin_url('post.php?post=' . $log->order_id . '&action=edit'); ?>">
                                        #<?php echo $log->order_id; ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($log->event_type); ?></td>
                            <td>
                                <?php if ($log->status === 'success'): ?>
                                    <span class="wc-sgtm-webhook-status-success"><?php _e('Sucesso', 'wc-sgtm-webhook'); ?></span>
                                <?php else: ?>
                                    <span class="wc-sgtm-webhook-status-error"><?php _e('Erro', 'wc-sgtm-webhook'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $log->response_code ? $log->response_code : '-'; ?></td>
                            <td><?php echo esc_html($log->message); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($log_files)): ?>
    <div class="wc-sgtm-webhook-logs-section">
        <h2><?php _e('Arquivos de Log', 'wc-sgtm-webhook'); ?></h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Arquivo', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Tamanho', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Data de Modificação', 'wc-sgtm-webhook'); ?></th>
                    <th><?php _e('Ações', 'wc-sgtm-webhook'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log_files as $log_file): ?>
                    <tr>
                        <td><?php echo esc_html($log_file['name']); ?></td>
                        <td><?php echo esc_html($log_file['size']); ?></td>
                        <td><?php echo esc_html($log_file['modified']); ?></td>
                        <td>
                            <a href="<?php echo esc_url($log_file['url']); ?>" class="button button-small" target="_blank">
                                <?php _e('Visualizar', 'wc-sgtm-webhook'); ?>
                            </a>
                            <a href="<?php echo esc_url($log_file['download_url']); ?>" class="button button-small">
                                <?php _e('Download', 'wc-sgtm-webhook'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>