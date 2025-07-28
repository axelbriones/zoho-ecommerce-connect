<?php

class Zoho_Sync_Core_Admin_Pages {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Zoho Sync',                    // Page title
            'Zoho Sync',                    // Menu title
            'manage_options',               // Capability
            'zoho-sync-core',              // Menu slug
            array($this, 'admin_page'),    // Callback
            'dashicons-update',            // Icon
            30                             // Position
        );
        
        // Submenús
        add_submenu_page(
            'zoho-sync-core',
            'Dashboard',
            'Dashboard', 
            'manage_options',
            'zoho-sync-dashboard',
            array($this, 'dashboard_page')
        );
    }

    public function admin_page() {
        require_once ZOHO_SYNC_CORE_ADMIN_DIR . 'partials/settings-display.php';
    }

    public function dashboard_page() {
        require_once ZOHO_SYNC_CORE_ADMIN_DIR . 'partials/dashboard-display.php';
    }

    public function settings_init() {
        register_setting('zoho_sync_core', 'zoho_sync_core_settings');

        add_settings_section(
            'zoho_sync_core_section',
            __('Zoho API Settings', 'zoho-sync-core'),
            null,
            'zoho_sync_core'
        );

        add_settings_field(
            'zoho_client_id',
            __('Client ID', 'zoho-sync-core'),
            array($this, 'render_client_id_field'),
            'zoho_sync_core',
            'zoho_sync_core_section'
        );
    }

    public function render_client_id_field() {
        $options = get_option('zoho_sync_core_settings');
        ?>
        <input type='text' name='zoho_sync_core_settings[zoho_client_id]' value='<?php echo esc_attr($options['zoho_client_id']); ?>'>
        <?php
    }
}