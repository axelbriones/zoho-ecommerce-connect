<?php

class ZSSO_Orders_Activator extends ZSCORE_Module_Activator {
    
    protected $required_capabilities = [
        'manage_zoho_orders',
        'view_order_sync_status',
        'edit_order_mappings'
    ];

    protected $custom_tables = [
        'zsso_order_mappings' => "
            CREATE TABLE IF NOT EXISTS {prefix}zsso_order_mappings (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                wc_order_id bigint(20) NOT NULL,
                zoho_sales_order_id varchar(100) NOT NULL,
                zoho_invoice_id varchar(100) DEFAULT NULL,
                last_sync datetime DEFAULT NULL,
                sync_status varchar(20) DEFAULT 'pending',
                sync_notes text DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY wc_order_id (wc_order_id),
                UNIQUE KEY zoho_sales_order_id (zoho_sales_order_id)
            ) {charset_collate};
        ",
        'zsso_order_items' => "
            CREATE TABLE IF NOT EXISTS {prefix}zsso_order_items (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                order_mapping_id bigint(20) NOT NULL,
                wc_order_item_id bigint(20) NOT NULL,
                zoho_line_item_id varchar(100) NOT NULL,
                sync_status varchar(20) DEFAULT 'pending',
                PRIMARY KEY  (id),
                KEY order_mapping_id (order_mapping_id)
            ) {charset_collate};
        "
    ];

    public function __construct() {
        parent::__construct('zoho-sync-orders');
    }

    protected function set_default_options() {
        $defaults = [
            'zsso_sync_frequency' => 'hourly',
            'zsso_auto_sync' => true,
            'zsso_sync_status_mapping' => [
                'pending' => 'Draft',
                'processing' => 'Confirmed',
                'completed' => 'Invoiced',
                'cancelled' => 'Cancelled'
            ],
            'zsso_create_invoices' => true,
            'zsso_batch_size' => 25,
            'zsso_sync_notes' => true,
            'zsso_sync_custom_fields' => true
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }

    protected function custom_activation() {
        // Programar sincronización automática
        if (!wp_next_scheduled('zsso_hourly_sync')) {
            wp_schedule_event(time(), 'hourly', 'zsso_hourly_sync');
        }

        // Crear directorio para archivos temporales
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/zoho-sync/orders';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
    }

    protected function custom_deactivation() {
        // Limpiar tareas programadas
        wp_clear_scheduled_hook('zsso_hourly_sync');
        wp_clear_scheduled_hook('zsso_cleanup_temp_files');
    }
}