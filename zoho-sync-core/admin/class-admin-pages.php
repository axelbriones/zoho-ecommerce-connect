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

        add_settings_field(
            'zoho_client_secret',
            __('Client Secret', 'zoho-sync-core'),
            array($this, 'render_client_secret_field'),
            'zoho_sync_core',
            'zoho_sync_core_section'
        );

        add_settings_field(
            'zoho_refresh_token',
            __('Refresh Token', 'zoho-sync-core'),
            array($this, 'render_refresh_token_field'),
            'zoho_sync_core',
            'zoho_sync_core_section'
        );
    }

    public function render_client_id_field() {
        $options = get_option('zoho_sync_core_settings');
        $client_id = isset($options['zoho_client_id']) ? $options['zoho_client_id'] : '';
        ?>
        <input type='text' name='zoho_sync_core_settings[zoho_client_id]' value='<?php echo esc_attr($client_id); ?>'>
        <?php
    }

    public function render_client_secret_field() {
        $options = get_option('zoho_sync_core_settings');
        $client_secret = isset($options['zoho_client_secret']) ? $options['zoho_client_secret'] : '';
        ?>
        <input type='text' name='zoho_sync_core_settings[zoho_client_secret]' value='<?php echo esc_attr($client_secret); ?>'>
        <?php
    }

    public function render_refresh_token_field() {
        $options = get_option('zoho_sync_core_settings');
        $refresh_token = isset($options['zoho_refresh_token']) ? $options['zoho_refresh_token'] : '';
        ?>
        <input type='text' name='zoho_sync_core_settings[zoho_refresh_token]' value='<?php echo esc_attr($refresh_token); ?>'>
        <?php
    }
}