<?php

class Zoho_Sync_Core_Admin_Pages {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Zoho Sync',
            'Zoho Sync',
            'manage_options',
            'zoho-sync-core',
            array($this, 'settings_page'),
            'dashicons-update',
            30
        );

        add_submenu_page(
            'zoho-sync-core',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'zoho-sync-dashboard',
            array($this, 'dashboard_page')
        );
    }

    public function settings_page() {
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
            array($this, 'render_text_field'),
            'zoho_sync_core',
            'zoho_sync_core_section',
            array('id' => 'zoho_client_id')
        );

        add_settings_field(
            'zoho_client_secret',
            __('Client Secret', 'zoho-sync-core'),
            array($this, 'render_text_field'),
            'zoho_sync_core',
            'zoho_sync_core_section',
            array('id' => 'zoho_client_secret')
        );

        add_settings_field(
            'zoho_refresh_token',
            __('Refresh Token', 'zoho-sync-core'),
            array($this, 'render_text_field'),
            'zoho_sync_core',
            'zoho_sync_core_section',
            array('id' => 'zoho_refresh_token')
        );
    }

    public function render_text_field($args) {
        $options = get_option('zoho_sync_core_settings');
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
        echo "<input type='text' id='{$args['id']}' name='zoho_sync_core_settings[{$args['id']}]' value='" . esc_attr($value) . "' class='regular-text'>";
    }
}
