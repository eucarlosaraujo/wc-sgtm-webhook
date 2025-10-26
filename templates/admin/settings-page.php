<?php
/**
 * Template para a página de configurações
 * 
 * @package WC_SGTM_Webhook_Pro
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wc-sgtm-webhook-wrap">
    <h1 class="wp-heading-inline"><?php _e('Configurações do SGTM Webhook', 'wc-sgtm-webhook'); ?></h1>
    
    <?php
    $settings = WC_SGTM_Webhook::get_settings();
    ?>
    <form method="post" action="">
        <?php wp_nonce_field('wc_sgtm_save_settings', 'wc_sgtm_settings_nonce'); ?>
    
        <label for="webhook_url"><?php echo esc_html__('Webhook URL', 'wc-sgtm-webhook'); ?></label>
        <input type="url" id="webhook_url" name="webhook_url" value="<?php echo esc_attr($settings['webhook_url'] ?? ''); ?>" />
    
        <label for="retention_days"><?php echo esc_html__('Dias de retenção de logs', 'wc-sgtm-webhook'); ?></label>
        <input type="number" min="1" max="3650" id="retention_days" name="retention_days" value="<?php echo esc_attr($settings['retention_days'] ?? 30); ?>" />
    
        <label for="timeout"><?php echo esc_html__('Timeout (s)', 'wc-sgtm-webhook'); ?></label>
        <input type="number" min="5" max="60" id="timeout" name="timeout" value="<?php echo esc_attr($settings['timeout'] ?? 10); ?>" />
    
        <label for="retry_attempts"><?php echo esc_html__('Tentativas de reenvio', 'wc-sgtm-webhook'); ?></label>
        <input type="number" min="0" max="10" id="retry_attempts" name="retry_attempts" value="<?php echo esc_attr($settings['retry_attempts'] ?? 3); ?>" />
    
        <label for="sgtm_auth_token"><?php echo esc_html__('Auth Token', 'wc-sgtm-webhook'); ?></label>
        <input type="text" id="sgtm_auth_token" name="sgtm_auth_token" value="<?php echo esc_attr($settings['sgtm_auth_token'] ?? ''); ?>" />
    
        <label for="client_id"><?php echo esc_html__('Client ID', 'wc-sgtm-webhook'); ?></label>
        <input type="text" id="client_id" name="client_id" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>" />
    
        <button type="submit" class="button button-primary"><?php echo esc_html__('Salvar', 'wc-sgtm-webhook'); ?></button>
    </form>
    <div class="wc-sgtm-webhook-settings-section">
        <h2><?php _e('Configurações Básicas', 'wc-sgtm-webhook'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="webhook_url"><?php _e('URL do Webhook', 'wc-sgtm-webhook'); ?></label>
                </th>
                <td>
                    <input type="url" name="webhook_url" id="webhook_url" class="regular-text" value="<?php echo esc_attr($webhook_url); ?>" />
                    <p class="description"><?php _e('URL para onde os dados serão enviados quando um pedido for concluído.', 'wc-sgtm-webhook'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php _e('Status do Webhook', 'wc-sgtm-webhook'); ?>
                </th>
                <td>
                    <label for="webhook_enabled">
                        <input type="checkbox" name="webhook_enabled" id="webhook_enabled" value="1" <?php checked($webhook_enabled); ?> />
                        <?php _e('Ativar envio de webhooks', 'wc-sgtm-webhook'); ?>
                    </label>
                    <p class="description"><?php _e('Quando ativado, os dados serão enviados para a URL configurada quando um pedido for concluído.', 'wc-sgtm-webhook'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php _e('Modo Debug', 'wc-sgtm-webhook'); ?>
                </th>
                <td>
                    <label for="debug_mode">
                        <input type="checkbox" name="debug_mode" id="debug_mode" value="1" <?php checked($settings['debug_mode'] ?? false); ?> />
                        <?php _e('Ativar modo debug', 'wc-sgtm-webhook'); ?>
                    </label>
                    <p class="description"><?php _e('Quando ativado, informações detalhadas serão registradas nos logs.', 'wc-sgtm-webhook'); ?></p>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="wc-sgtm-webhook-settings-section">
        <h2><?php _e('Captura de Dados', 'wc-sgtm-webhook'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Captura de Dados do Navegador', 'wc-sgtm-webhook'); ?>
                </th>
                <td>
                    <label for="browser_capture_enabled">
                        <input type="checkbox" name="browser_capture_enabled" id="browser_capture_enabled" value="1" <?php checked($settings['browser_capture_enabled'] ?? false); ?> />
                        <?php _e('Ativar captura de dados do navegador', 'wc-sgtm-webhook'); ?>
                    </label>
                    <p class="description"><?php _e('Quando ativado, dados do navegador (como cookies, referrer, etc.) serão capturados e enviados junto com o webhook.', 'wc-sgtm-webhook'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php _e('Captura de Dados do Facebook', 'wc-sgtm-webhook'); ?>
                </th>
                <td>
                    <label for="pixel_tracking_facebook">
                        <input type="checkbox" name="pixel_tracking_facebook" id="pixel_tracking_facebook" value="1" <?php checked($settings['pixel_tracking']['facebook'] ?? false); ?> />
                        <?php _e('Ativar captura de dados do Facebook Pixel', 'wc-sgtm-webhook'); ?>
                    </label>
                    <p class="description"><?php _e('Quando ativado, dados do Facebook Pixel (fbp, fbc) serão capturados e enviados junto com o webhook.', 'wc-sgtm-webhook'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php _e('Captura de Dados do Google', 'wc-sgtm-webhook'); ?>
                </th>
                <td>
                    <label for="pixel_tracking_google">
                        <input type="checkbox" name="pixel_tracking_google" id="pixel_tracking_google" value="1" <?php checked($settings['pixel_tracking']['google'] ?? false); ?> />
                        <?php _e('Ativar captura de dados do Google Ads', 'wc-sgtm-webhook'); ?>
                    </label>
                    <p class="description"><?php _e('Quando ativado, dados do Google Ads (gclid) serão capturados e enviados junto com o webhook.', 'wc-sgtm-webhook'); ?></p>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="wc-sgtm-webhook-settings-section">
        <h2><?php _e('Configurações Avançadas', 'wc-sgtm-webhook'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="data_retention_days"><?php _e('Retenção de Dados', 'wc-sgtm-webhook'); ?></label>
                </th>
                <td>
                    <input type="number" name="data_retention_days" id="data_retention_days" class="small-text" value="<?php echo esc_attr($settings['data_retention_days'] ?? 90); ?>" min="1" max="365" />
                    <p class="description"><?php _e('Número de dias para manter os dados de estatísticas. Dados mais antigos serão removidos automaticamente.', 'wc-sgtm-webhook'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="timeout"><?php _e('Timeout', 'wc-sgtm-webhook'); ?></label>
                </th>
                <td>
                    <input type="number" name="timeout" id="timeout" class="small-text" value="<?php echo esc_attr($settings['timeout'] ?? 30); ?>" min="1" max="60" />
                    <p class="description"><?php _e('Tempo máximo (em segundos) para aguardar a resposta do servidor ao enviar o webhook.', 'wc-sgtm-webhook'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="retry_attempts"><?php _e('Tentativas de Reenvio', 'wc-sgtm-webhook'); ?></label>
                </th>
                <td>
                    <input type="number" name="retry_attempts" id="retry_attempts" class="small-text" value="<?php echo esc_attr($settings['retry_attempts'] ?? 3); ?>" min="0" max="10" />
                    <p class="description"><?php _e('Número de tentativas de reenvio em caso de falha. 0 para desativar.', 'wc-sgtm-webhook'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php _e('Validação SSL', 'wc-sgtm-webhook'); ?>
                </th>
                <td>
                    <label for="validate_ssl">
                        <input type="checkbox" name="validate_ssl" id="validate_ssl" value="1" <?php checked($settings['validate_ssl'] ?? true); ?> />
                        <?php _e('Validar certificado SSL', 'wc-sgtm-webhook'); ?>
                    </label>
                    <p class="description"><?php _e('Quando ativado, o certificado SSL do servidor será validado. Desative apenas para testes ou se estiver tendo problemas com certificados auto-assinados.', 'wc-sgtm-webhook'); ?></p>
                </td>
            </tr>
        </table>
    </div>
    
    <p class="submit">
        <input type="submit" name="save_settings" class="button button-primary" value="<?php _e('Salvar Configurações', 'wc-sgtm-webhook'); ?>" />
        <button type="button" class="button" id="wc-sgtm-test-webhook"><?php _e('Testar Webhook', 'wc-sgtm-webhook'); ?></button>
    </p>
</div>