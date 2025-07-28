<?php

class ZSCU_Customers_Activator extends ZSCORE_Module_Activator {
    
    protected $required_capabilities = [
        'manage_zoho_customers',
        'view_customer_sync_status',
        'edit_customer_mappings'
    ];

    protected $custom_tables = [
        'zscu_customer_mappings' => "
            CREATE TABLE IF NOT EXISTS {prefix}zscu_customer_mappings (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                wp_user_id bigint(20) NOT NULL,
                zoho_contact_id varchar(100) NOT NULL,
                last_sync datetime DEFAULT NULL,
                sync_status varchar(20) DEFAULT 'pending',
                PRIMARY KEY  (id),
                UNIQUE KEY wp_user_id (wp_user_id),
                UNIQUE KEY zoho_contact_id (zoho_contact_id)
            ) {charset_collate};
        "
    ];

    public function __construct() {
        parent::__construct('zoho-sync-customers');
    }

    protected function set_default_options() {
        $defaults = [
            'zscu_sync_frequency' => 'hourly',
            'zscu_customer_roles' => ['customer', 'subscriber'],
            'zscu_sync_fields' => [
                'first_name' => true,
                'last_name' => true,
                'email' => true,
                'billing_address' => true,
                'shipping_address' => true
            ],
            'zscu_auto_sync' => true,
            'zscu_batch_size' => 50
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }

    protected function custom_activation() {
        // Crear rol personalizado para distribuidores si no existe
        add_role(
            'distributor',
            __('Distribuidor', 'zoho-sync-customers'),
            [
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'view_pricing' => true,
                'access_distributor_portal' => true
            ]
        );

        // Programar tarea cron inicial
        if (!wp_next_scheduled('zscu_hourly_sync')) {
            wp_schedule_event(time(), 'hourly', 'zscu_hourly_sync');
        }

        // Crear páginas necesarias
        $this->create_required_pages();
    }

    protected function custom_deactivation() {
        // Limpiar tarea cron
        wp_clear_scheduled_hook('zscu_hourly_sync');

        // No eliminamos el rol de distribuidor para preservar datos
    }

    private function create_required_pages() {
        $pages = [
            'distributor-portal' => [
                'title' => __('Portal de Distribuidores', 'zoho-sync-customers'),
                'content' => '[distributor_portal]'
            ],
            'distributor-registration' => [
                'title' => __('Registro de Distribuidores', 'zoho-sync-customers'),
                'content' => '[distributor_registration]'
            ]
        ];

        foreach ($pages as $slug => $page_data) {
            if (!get_page_by_path($slug)) {
                wp_insert_post([
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug
                ]);
            }
        }
    }
}