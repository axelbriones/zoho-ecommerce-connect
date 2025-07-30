<?php
/**
 * Uninstall Script for Zoho Sync Core
 * 
 * This file is executed when the plugin is uninstalled (deleted).
 * It cleans up all plugin data, settings, and database tables.
 * 
 * @package ZohoSyncCore
 * @since 8.0.0
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit('Direct access denied.');
}

// Security check - ensure this is a legitimate uninstall
if (!current_user_can('activate_plugins')) {
    exit('Insufficient permissions.');
}

/**
 * Zoho Sync Core Uninstaller Class
 */
class Zoho_Sync_Core_Uninstaller {
    
    /**
     * Run the uninstallation process
     */
    public static function uninstall() {
        // Load WordPress database interface
        global $wpdb;
        
        // Log the uninstallation
        self::log_uninstall();
        
        // Remove plugin options
        self::remove_options();
        
        // Remove user meta
        self::remove_user_meta();
        
        // Drop custom tables
        self::drop_tables();
        
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Remove uploaded files
        self::remove_uploaded_files();
        
        // Clear cache
        self::clear_cache();
        
        // Remove capabilities
        self::remove_capabilities();
        
        // Final cleanup
        self::final_cleanup();
    }
    
    /**
     * Log the uninstallation process
     */
    private static function log_uninstall() {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'action' => 'plugin_uninstalled',
            'plugin' => 'zoho-sync-core',
            'version' => get_option('zoho_sync_core_version', 'unknown')
        );
        
        // Try to log to WordPress debug log if available
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Zoho Sync Core: Plugin uninstalled by user ID ' . get_current_user_id());
        }
    }
    
    /**
     * Remove all plugin options
     */
    private static function remove_options() {
        $options_to_remove = array(
            // Main settings
            'zoho_sync_core_settings',
            'zoho_sync_core_version',
            'zoho_sync_core_db_version',
            'zoho_sync_core_activation_time',
            
            // Authentication data
            'zoho_sync_core_auth_tokens',
            'zoho_sync_core_refresh_token',
            'zoho_sync_core_access_token',
            'zoho_sync_core_token_expires',
            
            // Configuration options
            'zoho_sync_core_api_config',
            'zoho_sync_core_sync_config',
            'zoho_sync_core_log_config',
            'zoho_sync_core_cache_config',
            
            // Status and statistics
            'zoho_sync_core_last_sync',
            'zoho_sync_core_sync_stats',
            'zoho_sync_core_error_count',
            'zoho_sync_core_connection_status',
            
            // Temporary data
            'zoho_sync_core_temp_data',
            'zoho_sync_core_queue_data',
            'zoho_sync_core_batch_data',
            
            // Feature flags
            'zoho_sync_core_features',
            'zoho_sync_core_modules_status',
            
            // Migration flags
            'zoho_sync_core_migration_status',
            'zoho_sync_core_upgrade_notices'
        );
        
        foreach ($options_to_remove as $option) {
            delete_option($option);
            delete_site_option($option); // For multisite
        }
        
        // Remove options with dynamic names
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE 'zoho_sync_core_%' 
             OR option_name LIKE '_transient_zoho_sync_%' 
             OR option_name LIKE '_transient_timeout_zoho_sync_%'"
        );
        
        // For multisite
        if (is_multisite()) {
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta} 
                 WHERE meta_key LIKE 'zoho_sync_core_%'"
            );
        }
    }
    
    /**
     * Remove user meta data
     */
    private static function remove_user_meta() {
        global $wpdb;
        
        $meta_keys_to_remove = array(
            'zoho_sync_core_preferences',
            'zoho_sync_core_dashboard_config',
            'zoho_sync_core_last_login',
            'zoho_sync_core_notifications',
            'zoho_sync_core_user_settings'
        );
        
        foreach ($meta_keys_to_remove as $meta_key) {
            $wpdb->delete(
                $wpdb->usermeta,
                array('meta_key' => $meta_key),
                array('%s')
            );
        }
        
        // Remove meta with dynamic names
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'zoho_sync_core_%'"
        );
    }
    
    /**
     * Drop custom database tables
     */
    private static function drop_tables() {
        global $wpdb;
        
        $tables_to_drop = array(
            $wpdb->prefix . 'zoho_sync_logs',
            $wpdb->prefix . 'zoho_sync_queue',
            $wpdb->prefix . 'zoho_sync_mappings',
            $wpdb->prefix . 'zoho_sync_cache',
            $wpdb->prefix . 'zoho_sync_settings',
            $wpdb->prefix . 'zoho_sync_tokens',
            $wpdb->prefix . 'zoho_sync_sync_history',
            $wpdb->prefix . 'zoho_sync_error_log',
            $wpdb->prefix . 'zoho_sync_webhooks',
            $wpdb->prefix . 'zoho_sync_batch_operations'
        );
        
        foreach ($tables_to_drop as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
        
        // Verify tables are dropped
        foreach ($tables_to_drop as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if ($table_exists) {
                error_log("Zoho Sync Core: Failed to drop table {$table}");
            }
        }
    }
    
    /**
     * Clear all scheduled events
     */
    private static function clear_scheduled_events() {
        $scheduled_hooks = array(
            'zoho_sync_core_hourly_sync',
            'zoho_sync_core_daily_cleanup',
            'zoho_sync_core_weekly_maintenance',
            'zoho_sync_core_token_refresh',
            'zoho_sync_core_health_check',
            'zoho_sync_core_log_cleanup',
            'zoho_sync_core_cache_cleanup',
            'zoho_sync_core_queue_process',
            'zoho_sync_core_batch_process',
            'zoho_sync_core_webhook_cleanup'
        );
        
        foreach ($scheduled_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
            
            // Clear all instances of the hook
            wp_clear_scheduled_hook($hook);
        }
        
        // Clear any remaining cron jobs with our prefix
        $cron_jobs = get_option('cron', array());
        foreach ($cron_jobs as $timestamp => $jobs) {
            if (!is_array($jobs)) continue;
            
            foreach ($jobs as $hook => $job_data) {
                if (strpos($hook, 'zoho_sync_') === 0) {
                    wp_unschedule_event($timestamp, $hook);
                }
            }
        }
    }
    
    /**
     * Remove uploaded files and directories
     */
    private static function remove_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/zoho-sync-core';
        
        if (is_dir($plugin_upload_dir)) {
            self::remove_directory($plugin_upload_dir);
        }
        
        // Remove log files
        $log_files = array(
            WP_CONTENT_DIR . '/zoho-sync-core.log',
            WP_CONTENT_DIR . '/debug-zoho-sync.log',
            WP_CONTENT_DIR . '/zoho-sync-errors.log'
        );
        
        foreach ($log_files as $log_file) {
            if (file_exists($log_file)) {
                unlink($log_file);
            }
        }
        
        // Remove backup files
        $backup_pattern = WP_CONTENT_DIR . '/zoho-sync-backup-*.sql';
        $backup_files = glob($backup_pattern);
        if ($backup_files) {
            foreach ($backup_files as $backup_file) {
                unlink($backup_file);
            }
        }
    }
    
    /**
     * Recursively remove directory and its contents
     */
    private static function remove_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                self::remove_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Clear all cache data
     */
    private static function clear_cache() {
        // Clear WordPress transients
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_zoho_sync_%' 
             OR option_name LIKE '_transient_timeout_zoho_sync_%'"
        );
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear external cache plugins
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        if (class_exists('WpFastestCache')) {
            $wpfc = new WpFastestCache();
            $wpfc->deleteCache();
        }
        
        // Clear Redis cache if available
        if (class_exists('Redis')) {
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $keys = $redis->keys('zoho_sync_*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }
                $redis->close();
            } catch (Exception $e) {
                // Redis not available or connection failed
            }
        }
    }
    
    /**
     * Remove custom capabilities
     */
    private static function remove_capabilities() {
        $capabilities = array(
            'manage_zoho_sync',
            'view_zoho_sync_logs',
            'configure_zoho_sync',
            'sync_zoho_data',
            'manage_zoho_webhooks',
            'export_zoho_data',
            'import_zoho_data'
        );
        
        // Remove from all roles
        $roles = wp_roles();
        foreach ($roles->roles as $role_name => $role_data) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $capability) {
                    $role->remove_cap($capability);
                }
            }
        }
    }
    
    /**
     * Final cleanup tasks
     */
    private static function final_cleanup() {
        // Remove any remaining temporary files
        $temp_files = glob(sys_get_temp_dir() . '/zoho-sync-*');
        if ($temp_files) {
            foreach ($temp_files as $temp_file) {
                if (is_file($temp_file)) {
                    unlink($temp_file);
                }
            }
        }
        
        // Clear any remaining WordPress hooks
        remove_all_actions('zoho_sync_core_init');
        remove_all_actions('zoho_sync_core_loaded');
        remove_all_filters('zoho_sync_core_settings');
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Log completion
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Zoho Sync Core: Uninstallation completed successfully');
        }
    }
    
    /**
     * Check if we should preserve data
     */
    private static function should_preserve_data() {
        // Check if there's a flag to preserve data
        $preserve_data = get_option('zoho_sync_core_preserve_data_on_uninstall', false);
        
        // Check if other Zoho Sync plugins are still active
        $active_plugins = get_option('active_plugins', array());
        $zoho_plugins_active = false;
        
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'zoho-sync-') === 0 && $plugin !== 'zoho-sync-core/zoho-sync-core.php') {
                $zoho_plugins_active = true;
                break;
            }
        }
        
        return $preserve_data || $zoho_plugins_active;
    }
    
    /**
     * Create backup before uninstall
     */
    private static function create_backup() {
        global $wpdb;
        
        $backup_data = array(
            'timestamp' => current_time('mysql'),
            'version' => get_option('zoho_sync_core_version', 'unknown'),
            'settings' => get_option('zoho_sync_core_settings', array()),
            'tables' => array()
        );
        
        // Backup custom tables
        $tables = array(
            $wpdb->prefix . 'zoho_sync_logs',
            $wpdb->prefix . 'zoho_sync_queue',
            $wpdb->prefix . 'zoho_sync_mappings'
        );
        
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if ($table_exists) {
                $backup_data['tables'][$table] = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
            }
        }
        
        // Save backup to file
        $backup_file = WP_CONTENT_DIR . '/zoho-sync-backup-' . date('Y-m-d-H-i-s') . '.json';
        file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
        
        return $backup_file;
    }
}

// Run the uninstallation
try {
    // Check if we should preserve data
    if (!Zoho_Sync_Core_Uninstaller::should_preserve_data()) {
        // Create backup before uninstall
        $backup_file = Zoho_Sync_Core_Uninstaller::create_backup();
        
        // Run uninstallation
        Zoho_Sync_Core_Uninstaller::uninstall();
        
        // Log backup location
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("Zoho Sync Core: Backup created at {$backup_file}");
        }
    } else {
        // Log that data was preserved
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Zoho Sync Core: Data preserved due to preserve flag or active related plugins');
        }
    }
} catch (Exception $e) {
    // Log any errors during uninstallation
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('Zoho Sync Core Uninstall Error: ' . $e->getMessage());
    }
    
    // Don't let uninstall errors prevent plugin removal
    // WordPress will still remove the plugin files
}

// Final message for debugging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Zoho Sync Core: Uninstall script completed');
}
