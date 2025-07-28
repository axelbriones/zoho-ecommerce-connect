<?php

class ZSIV_Inventory_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Inventario Zoho', 'zoho-sync-inventory'),
            __('Inventario Zoho', 'zoho-sync-inventory'),
            'manage_options',
            'zsiv-inventory',
            [$this, 'render_admin_page'],
            'dashicons-cart',
            30
        );

        add_submenu_page(
            'zsiv-inventory',
            __('Configuración', 'zoho-sync-inventory'),
            __('Configuración', 'zoho-sync-inventory'),
            'manage_options',
            'zsiv-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_admin_page() {
        require_once ZSIV_PLUGIN_DIR . 'admin/partials/inventory-dashboard.php';
    }

    public function render_settings_page() {
        require_once ZSIV_PLUGIN_DIR . 'admin/partials/inventory-settings.php';
    }

    public function register_settings() {
        register_setting('zsiv_options', 'zsiv_sync_interval');
        register_setting('zsiv_options', 'zsiv_low_stock_threshold');
        register_setting('zsiv_options', 'zsiv_enable_notifications');
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'zsiv-inventory') === false) {
            return;
        }

        wp_enqueue_style(
            'zsiv-admin-css',
            ZSIV_PLUGIN_URL . 'admin/assets/css/inventory-admin.css',
            [],
            ZSIV_VERSION
        );

        wp_enqueue_script(
            'zsiv-admin-js',
            ZSIV_PLUGIN_URL . 'admin/assets/js/inventory-admin.js',
            ['jquery'],
            ZSIV_VERSION,
            true
        );

        wp_localize_script('zsiv-admin-js', 'zsivAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zsiv_admin_nonce')
        ]);
    }
}