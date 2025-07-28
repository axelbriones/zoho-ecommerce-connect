<?php

class ZSSI_Inventory_Activator extends ZSCORE_Module_Activator {
    
    protected $required_capabilities = [
        'manage_zoho_inventory',
        'view_stock_sync_status',
        'manage_stock_thresholds'
    ];

    protected $custom_tables = [
        'zssi_stock_sync' => "
            CREATE TABLE IF NOT EXISTS {prefix}zssi_stock_sync (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) NOT NULL,
                zoho_item_id varchar(100) NOT NULL,
                wc_stock int(11) NOT NULL DEFAULT 0,
                zoho_stock int(11) NOT NULL DEFAULT 0,
                last_sync datetime DEFAULT NULL,
                sync_status varchar(20) DEFAULT 'pending',
                PRIMARY KEY  (id),
                UNIQUE KEY product_id (product_id)
            ) {charset_collate};
        ",
        'zssi_stock_log' => "
            CREATE TABLE IF NOT EXISTS {prefix}zssi_stock_log (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) NOT NULL,
                old_stock int(11) NOT NULL,
                new_stock int(11) NOT NULL,
                source enum('woocommerce','zoho') NOT NULL,
                change_date datetime DEFAULT CURRENT_TIMESTAMP,
                sync_id bigint(20) DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY product_id (product_id)
            ) {charset_collate};
        ",
        'zssi_stock_alerts' => "
            CREATE TABLE IF NOT EXISTS {prefix}zssi_stock_alerts (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) NOT NULL,
                threshold int(11) NOT NULL,
                status enum('active','triggered','dismissed') DEFAULT 'active',
                last_triggered datetime DEFAULT NULL,
                notification_sent datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY product_id (product_id)
            ) {charset_collate};
        "
    ];

    public function __construct() {
        parent::__construct('zoho-sync-inventory');
    }

    protected function set_default_options() {
        $defaults = [
            'zssi_sync_frequency' => 'hourly',
            'zssi_sync_direction' => 'both', // zoho_to_wc, wc_to_zoho, both
            'zssi_auto_sync' => true,
            'zssi_stock_threshold' => 5,
            'zssi_alert_emails' => get_option('admin_email'),
            'zssi_batch_size' => 50,
            'zssi_log_retention' => 30, // días
            'zssi_conflict_resolution' => 'zoho', // zoho, woocommerce, manual
            'zssi_sync_on_order' => true,
            'zssi_notify_distributors' => true
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }

    protected function custom_activation() {
        // Programar tarea de sincronización
        if (!wp_next_scheduled('zssi_stock_sync')) {
            wp_schedule_event(time(), 'hourly', 'zssi_stock_sync');
        }

        // Programar limpieza de logs
        if (!wp_next_scheduled('zssi_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'zssi_cleanup_logs');
        }

        // Crear directorios necesarios
        $upload_dir = wp_upload_dir();
        $dirs = [
            'zoho-sync/inventory',
            'zoho-sync/inventory/logs',
            'zoho-sync/inventory/exports'
        ];

        foreach ($dirs as $dir) {
            $path = $upload_dir['basedir'] . '/' . $dir;
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }
        }
    }

    protected function custom_deactivation() {
        // Limpiar tareas programadas
        wp_clear_scheduled_hook('zssi_stock_sync');
        wp_clear_scheduled_hook('zssi_cleanup_logs');
    }
}