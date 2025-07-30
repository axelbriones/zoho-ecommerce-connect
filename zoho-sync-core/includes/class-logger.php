<?php

if (!defined('ABSPATH')) {
    exit;
}

class Zoho_Sync_Core_Logger {

    private $log_file;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = trailingslashit($upload_dir['basedir']) . 'zoho-sync-core.log';
    }

    /**
     * Log a message with a level and context
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, $context = array()) {
        if (!is_string($message)) {
            $message = print_r($message, true);
        }
        $time = current_time('mysql');
        $context_str = !empty($context) ? json_encode($context) : '';
        $log_entry = sprintf("[%s] [%s] %s %s\n", $time, strtoupper($level), $message, $context_str);
        error_log($log_entry, 3, $this->log_file);
    }

    /**
     * Get available log levels
     * 
     * @return array Log levels
     */
    public function get_log_levels() {
        return array(
            'debug' => __('Debug', 'zoho-sync-core'),
            'info' => __('Info', 'zoho-sync-core'),
            'warning' => __('Warning', 'zoho-sync-core'),
            'error' => __('Error', 'zoho-sync-core'),
            'critical' => __('Critical', 'zoho-sync-core')
        );
    }
}
