<?php

class ZSSP_Products_Activator extends ZSCORE_Module_Activator {
    
    protected $required_capabilities = [
        'manage_zoho_products',
        'view_product_sync_status',
        'edit_product_mappings'
    ];

    protected $custom_tables = [
        'zssp_product_mappings' => "
            CREATE TABLE IF NOT EXISTS {prefix}zssp_product_mappings (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                wc_product_id bigint(20) NOT NULL,
                zoho_item_id varchar(100) NOT NULL,
                last_sync datetime DEFAULT NULL,
                sync_status varchar(20) DEFAULT 'pending',
                PRIMARY KEY  (id),
                UNIQUE KEY wc_product_id (wc_product_id),
                UNIQUE KEY zoho_item_id (zoho_item_id)
            ) {charset_collate};
        ",
        'zssp_price_history' => "
            CREATE TABLE IF NOT EXISTS {prefix}zssp_price_history (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                product_mapping_id bigint(20) NOT NULL,
                old_price decimal(10,2) NOT NULL,
                new_price decimal(10,2) NOT NULL,
                change_date datetime DEFAULT CURRENT_TIMESTAMP,
                sync_status varchar(20) DEFAULT 'pending',
                PRIMARY KEY  (id),
                KEY product_mapping_id (product_mapping_id)
            ) {charset_collate};
        "
    ];

    public function __construct() {
        parent::__construct('zoho-sync-products');
    }

    protected function set_default_options() {
        $defaults = [
            'zssp_sync_frequency' => 'daily',
            'zssp_auto_sync' => true,
            'zssp_sync_fields' => [
                'name' => true,
                'description' => true,
                'price' => true,
                'stock' => true,
                'categories' => true,
                'images' => true
            ],
            'zssp_price_sync_direction' => 'both',
            'zssp_batch_size' => 50,
            'zssp_track_price_history' => true
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }

    protected function custom_activation() {
        // Programar sincronización automática
        if (!wp_next_scheduled('zssp_daily_sync')) {
            wp_schedule_event(time(), 'daily', 'zssp_daily_sync');
        }

        // Crear taxonomías personalizadas si es necesario
        $this->register_custom_taxonomies();
    }

    protected function custom_deactivation() {
        wp_clear_scheduled_hook('zssp_daily_sync');
        wp_clear_scheduled_hook('zssp_price_history_cleanup');
    }

    private function register_custom_taxonomies() {
        // Registrar taxonomía para marcas si no existe
        if (!taxonomy_exists('product_brand')) {
            register_taxonomy('product_brand', 'product', [
                'label' => __('Marcas', 'zoho-sync-products'),
                'hierarchical' => true,
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => ['slug' => 'marca']
            ]);
        }
    }
}