<?php
/**
 * Database Manager Class
 * 
 * @package ZohoSyncCore
 * @subpackage Database
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class Zoho_Sync_Core_Database_Manager {

    public static function create_tables() {
        global $wpdb;

        // Add a version check to prevent this from running on every activation
        $installed_ver = get_option("zoho_sync_core_db_version");
        if ($installed_ver == '1.0') {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array(
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zoho_sync_settings (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                setting_key varchar(255) NOT NULL,
                setting_value longtext,
                module varchar(100) DEFAULT 'core',
                is_encrypted tinyint(1) DEFAULT 0,
                autoload tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY setting_key (setting_key)
            ) $charset_collate;",
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zoho_sync_logs (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                module varchar(100) NOT NULL DEFAULT 'core',
                level varchar(20) NOT NULL DEFAULT 'info',
                message text NOT NULL,
                context longtext,
                user_id bigint(20) DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY module_level (module, level),
                KEY created_at (created_at)
            ) $charset_collate;",
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zoho_sync_tokens (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                service varchar(100) NOT NULL,
                region varchar(10) NOT NULL DEFAULT 'com',
                access_token text,
                refresh_token text,
                token_type varchar(50) DEFAULT 'Bearer',
                expires_at datetime,
                scope text,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY service_region (service, region)
            ) $charset_collate;",
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zoho_sync_modules (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                module_name varchar(100) NOT NULL,
                module_slug varchar(100) NOT NULL,
                version varchar(20) DEFAULT '1.0.0',
                is_active tinyint(1) DEFAULT 1,
                last_sync datetime DEFAULT NULL,
                sync_status varchar(50) DEFAULT 'idle',
                error_count int DEFAULT 0,
                last_error text DEFAULT NULL,
                config longtext DEFAULT NULL,
                dependencies text DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY module_slug (module_slug)
            ) $charset_collate;"
        );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $table) {
            dbDelta($table);
        }

        update_option("zoho_sync_core_db_version", '1.0');
    }
}
