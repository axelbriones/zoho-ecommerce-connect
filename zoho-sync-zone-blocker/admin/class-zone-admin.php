<?php
<?php

class ZSZB_Zone_Admin {
    private $settings_page = 'zszb_zone_admin';
    private $option_group = 'zszb_options';

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
            [$this, 'render_admin_page'],
            'dashicons-location-alt'
        );

        // Submenús
        add_submenu_page(
            $this->settings_page,
            __('Configuración de Zonas', 'zoho-sync-zone-blocker'),
            __('Configuración', 'zoho-sync-zone-blocker'),
            'manage_options',
            $this->settings_page
        );

        add_submenu_page(
            $this->settings_page,
            __('Asignación de Zonas', 'zoho-sync-zone-blocker'),
            __('Asignar Zonas', 'zoho-sync-zone-blocker'),
            'manage_options',
            'zszb_zone_assignment',
            [$this, 'render_assignment_page']
        );
    }

    public function register_settings() {
        register_setting($this->option_group, 'zszb_default_redirect');
        register_setting($this->option_group, 'zszb_enable_geolocation');
        
        add_settings_section(
            'zszb_general_section',
            __('Configuración General', 'zoho-sync-zone-blocker'),
            [$this, 'render_section_info'],
            $this->settings_page
        );

        add_settings_field(
            'zszb_default_redirect',
            __('URL de Redirección', 'zoho-sync-zone-blocker'),
            [$this, 'render_redirect_field'],
            $this->settings_page,
            'zszb_general_section'
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, $this->settings_page) === false) {
            return;
        }

        wp_enqueue_style(
            'zszb-admin-style',
            ZSZB_PLUGIN_URL . 'admin/assets/css/admin.css',
            [],
            ZSZB_VERSION
        );

        wp_enqueue_script(
            'zszb-admin-script',
            ZSZB_PLUGIN_URL . 'admin/assets/js/admin.js',
            ['jquery'],
            ZSZB_VERSION,
            true
        );

        wp_localize_script('zszb-admin-script', 'zszbAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zszb_admin_nonce')
        ]);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        include ZSZB_PLUGIN_DIR . 'admin/partials/zone-settings.php';
    }

    public function render_assignment_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        include ZSZB_PLUGIN_DIR . 'admin/partials/zone-assignment.php';
    }

    public function render_section_info() {
        echo '<p>' . __('Configure las opciones generales del bloqueo por zona.', 'zoho-sync-zone-blocker') . '</p>';
    }

    public function render_redirect_field() {
        $value = get_option('zszb_default_redirect');
        echo '<input type="url" name="zszb_default_redirect" value="' . esc_attr($value) . '" class="regular-text">';
    }
}

new ZSZB_Zone_Admin();