<?php
/**
 * Logger simples para o plugin, usando WooCommerce logger quando disponível
 */
if (!defined('ABSPATH')) { exit; }

class WC_SGTM_Logger {
    private $source = 'sgtm-webhook';

    private function wc_logger() {
        return function_exists('wc_get_logger') ? wc_get_logger() : null;
    }

    public function info($message, $context = array()) {
        $logger = $this->wc_logger();
        if ($logger) { $logger->info($message, array('source' => $this->source) + $context); }
        error_log('[SGTM INFO] ' . $message . (empty($context) ? '' : ' ' . wp_json_encode($context)));
    }

    public function debug($message, $context = array()) {
        $logger = $this->wc_logger();
        if ($logger) { $logger->debug($message, array('source' => $this->source) + $context); }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SGTM DEBUG] ' . $message . (empty($context) ? '' : ' ' . wp_json_encode($context)));
        }
    }

    public function error($message, $context = array()) {
        $logger = $this->wc_logger();
        if ($logger) { $logger->error($message, array('source' => $this->source) + $context); }
        error_log('[SGTM ERROR] ' . $message . (empty($context) ? '' : ' ' . wp_json_encode($context)));
    }
}