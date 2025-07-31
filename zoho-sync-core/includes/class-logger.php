<?php

if (!defined('ABSPATH')) {
    exit;
}

class Zoho_Sync_Core_Logger {

    private $log_file;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $upload_dir = wp_upload_dir();
        $this->log_file = trailingslashit($upload_dir['basedir']) . 'zoho-sync-core.log';
        $this->table_name = $wpdb->prefix . 'zoho_sync_logs';
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
        
        // Log to file
        $time = current_time('mysql');
        $context_str = !empty($context) ? json_encode($context) : '';
        $log_entry = sprintf("[%s] [%s] %s %s\n", $time, strtoupper($level), $message, $context_str);
        error_log($log_entry, 3, $this->log_file);
        
        // Log to database if table exists
        $this->log_to_database($level, $message, $context);
    }

    /**
     * Log to database
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log_to_database($level, $message, $context = array()) {
        global $wpdb;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            return;
        }
        
        $module = isset($context['module']) ? $context['module'] : 'core';
        
        $wpdb->insert(
            $this->table_name,
            array(
                'level' => $level,
                'message' => $message,
                'module' => $module,
                'context' => json_encode($context),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Get logs from database
     *
     * @param array $args Query arguments
     * @return array Log entries
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            return array();
        }
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'level' => '',
            'module' => '',
            'order' => 'DESC',
            'orderby' => 'created_at'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($args['level'])) {
            $where_conditions[] = "level = %s";
            $where_values[] = $args['level'];
        }
        
        if (!empty($args['module'])) {
            $where_conditions[] = "module = %s";
            $where_values[] = $args['module'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $order_clause = sprintf('ORDER BY %s %s', 
            sanitize_sql_orderby($args['orderby']), 
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );
        
        $limit_clause = sprintf('LIMIT %d OFFSET %d', 
            intval($args['limit']), 
            intval($args['offset'])
        );
        
        $query = "SELECT * FROM {$this->table_name} {$where_clause} {$order_clause} {$limit_clause}";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Parse context JSON
        foreach ($results as &$result) {
            $result['context'] = json_decode($result['context'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['context'] = array();
            }
        }
        
        return $results;
    }

    /**
     * Get log count
     *
     * @param array $args Query arguments
     * @return int Log count
     */
    public function get_log_count($args = array()) {
        global $wpdb;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            return 0;
        }
        
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($args['level'])) {
            $where_conditions[] = "level = %s";
            $where_values[] = $args['level'];
        }
        
        if (!empty($args['module'])) {
            $where_conditions[] = "module = %s";
            $where_values[] = $args['module'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return intval($wpdb->get_var($query));
    }

    /**
     * Clear logs
     *
     * @param array $args Clear arguments
     * @return bool Success status
     */
    public function clear_logs($args = array()) {
        global $wpdb;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            return false;
        }
        
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($args['level'])) {
            $where_conditions[] = "level = %s";
            $where_values[] = $args['level'];
        }
        
        if (!empty($args['module'])) {
            $where_conditions[] = "module = %s";
            $where_values[] = $args['module'];
        }
        
        if (!empty($args['older_than'])) {
            $where_conditions[] = "created_at < %s";
            $where_values[] = $args['older_than'];
        }
        
        if (empty($where_conditions)) {
            // Clear all logs
            $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        } else {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            $query = "DELETE FROM {$this->table_name} {$where_clause}";
            $query = $wpdb->prepare($query, $where_values);
            $result = $wpdb->query($query);
        }
        
        // Also clear log file if clearing all logs
        if (empty($where_conditions) && file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
        }
        
        return $result !== false;
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

    /**
     * Get log statistics
     *
     * @param int $days Number of days to analyze
     * @return array Statistics
     */
    public function get_log_statistics($days = 7) {
        global $wpdb;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            return array(
                'total' => 0,
                'by_level' => array(),
                'by_module' => array(),
                'by_day' => array()
            );
        }
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Total logs
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
            $date_from
        ));
        
        // By level
        $by_level = $wpdb->get_results($wpdb->prepare(
            "SELECT level, COUNT(*) as count FROM {$this->table_name} 
             WHERE created_at >= %s GROUP BY level ORDER BY count DESC",
            $date_from
        ), ARRAY_A);
        
        // By module
        $by_module = $wpdb->get_results($wpdb->prepare(
            "SELECT module, COUNT(*) as count FROM {$this->table_name} 
             WHERE created_at >= %s GROUP BY module ORDER BY count DESC",
            $date_from
        ), ARRAY_A);
        
        // By day
        $by_day = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count FROM {$this->table_name} 
             WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY date ASC",
            $date_from
        ), ARRAY_A);
        
        return array(
            'total' => intval($total),
            'by_level' => $by_level,
            'by_module' => $by_module,
            'by_day' => $by_day
        );
    }

    /**
     * Get log file path
     *
     * @return string Log file path
     */
    public function get_log_file_path() {
        return $this->log_file;
    }

    /**
     * Get log file size
     *
     * @return int File size in bytes
     */
    public function get_log_file_size() {
        if (file_exists($this->log_file)) {
            return filesize($this->log_file);
        }
        return 0;
    }
}
