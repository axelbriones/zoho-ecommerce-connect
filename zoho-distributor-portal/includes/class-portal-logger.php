<?php

class ZSDP_Portal_Logger {
    
    private $log_dir;
    private $enabled_levels = ['error', 'warning', 'info', 'debug'];
    
    public function __construct() {
        $this->log_dir = WP_CONTENT_DIR . '/uploads/zoho-logs/distributor-portal/';
        
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }

        add_action('init', [$this, 'init']);
    }

    public function init() {
        // Configurar niveles de log según el entorno
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->enabled_levels = ['error', 'warning', 'info', 'debug'];
        } else {
            $this->enabled_levels = ['error', 'warning'];
        }
    }

    public function log($level, $message, $context = []) {
        if (!in_array($level, $this->enabled_levels)) {
            return false;
        }

        $log_entry = $this->format_log_entry($level, $message, $context);
        
        // Enviar al log central de Zoho
        ZohoSyncCore::log($level, $message, $context, 'distributor_portal');
        
        // Guardar en archivo local
        return $this->write_log($level, $log_entry);
    }

    private function format_log_entry($level, $message, $context) {
        $timestamp = current_time('mysql');
        $user_id = get_current_user_id();
        
        $entry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'user_id' => $user_id,
            'message' => $message,
            'context' => $context
        ];

        // Añadir información adicional si está disponible
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $entry['ip'] = $_SERVER['REMOTE_ADDR'];
        }

        if ($user_id) {
            $user = get_userdata($user_id);
            $entry['user'] = [
                'login' => $user->user_login,
                'role' => implode(', ', $user->roles)
            ];
        }

        return wp_json_encode($entry) . PHP_EOL;
    }

    private function write_log($level, $entry) {
        $filename = $this->log_dir . date('Y-m-d') . '-' . $level . '.log';
        
        return file_put_contents($filename, $entry, FILE_APPEND | LOCK_EX);
    }

    public function get_logs($level = null, $date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }

        $logs = [];
        
        if ($level) {
            $filename = $this->log_dir . $date . '-' . $level . '.log';
            if (file_exists($filename)) {
                $logs = array_merge($logs, $this->parse_log_file($filename));
            }
        } else {
            foreach ($this->enabled_levels as $log_level) {
                $filename = $this->log_dir . $date . '-' . $log_level . '.log';
                if (file_exists($filename)) {
                    $logs = array_merge($logs, $this->parse_log_file($filename));
                }
            }
        }

        return $logs;
    }

    private function parse_log_file($filename) {
        $logs = [];
        $handle = fopen($filename, 'r');
        
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $logs[] = json_decode(trim($line), true);
            }
            fclose($handle);
        }
        
        return $logs;
    }
}