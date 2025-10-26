<?php
/**
 * Enviador de Webhook para Stape/SSGTM
 */
if (!defined('ABSPATH')) { exit; }

class WC_SGTM_Webhook_Sender {
    private $settings;
    private $logger;

    public function __construct($settings, $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function init() {
        // Hook exemplo para enviar eventos reais (pode ser expandido depois)
    }

    /**
     * Enviar webhook de dados
     * @param array $payload
     * @return array { success: bool, response_code: int, error?: string }
     */
    public function send_webhook($payload) {
        $url = $this->settings['webhook_url'] ?? '';
        if (empty($url)) {
            return array('success' => false, 'response_code' => 0, 'error' => 'Webhook URL não configurada');
        }

        $args = array(
            'timeout' => intval($this->settings['timeout'] ?? 30),
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
        );

        $this->logger->debug('Enviando webhook', array('url' => $url));
        $resp = wp_remote_post($url, $args);

        if (is_wp_error($resp)) {
            $error = $resp->get_error_message();
            $this->logger->error('Falha ao enviar webhook', array('error' => $error));
            do_action('wc_sgtm_webhook_error', $payload['custom_data']['order_id'] ?? 0, array('code' => 0, 'error' => $error));
            return array('success' => false, 'response_code' => 0, 'error' => $error);
        }

        $code = wp_remote_retrieve_response_code($resp);
        $ok = $code >= 200 && $code < 300;
        $this->logger->info('Webhook enviado', array('code' => $code));
        if ($ok) {
            do_action('wc_sgtm_webhook_sent', $payload['custom_data']['order_id'] ?? 0, array('response_code' => $code));
        } else {
            do_action('wc_sgtm_webhook_error', $payload['custom_data']['order_id'] ?? 0, array('code' => $code, 'error' => 'HTTP ' . $code));
        }
        return array('success' => $ok, 'response_code' => $code, 'error' => $ok ? null : 'HTTP ' . $code);
    }
}