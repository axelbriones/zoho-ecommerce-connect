<?php

class ZSZB_Admin_Dashboard {
    private $settings_page = 'zszb_dashboard';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Bloqueo por Zona', 'zoho-sync-zone-blocker'),
            __('Bloqueo por Zona', 'zoho-sync-zone-blocker'),
            'manage_options',
            $this->settings_page,
            [$this, 'render_dashboard'],
            'dashicons-location',
            30
        );
        
        // Submenús
        add_submenu_page(
            $this->settings_page,
            __('Zonas & Distribuidores', 'zoho-sync-zone-blocker'),
            __('Zonas', 'zoho-sync-zone-blocker'),
            'manage_options',
            'zszb_zones',
            [$this, 'render_zones_page']
        );
    }
    
    public function render_dashboard() {
        require_once ZSZB_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }
}