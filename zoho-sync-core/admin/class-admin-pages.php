<?php
/**
 * Admin Pages Class
 * 
 * Enhanced administration interface for Zoho Sync Core plugin
 * 
 * @package ZohoSyncCore
 * @subpackage Admin
 * @since 8.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Zoho Sync Core Admin Pages Class
 * 
 * @class Zoho_Sync_Core_Admin_Pages
 * @version 8.0.0
 * @since 8.0.0
 */
class Zoho_Sync_Core_Admin_Pages {

    /**
     * Settings manager instance
     * 
     * @var Zoho_Sync_Core_Settings_Manager
     */
    private $settings;

    /**
     * Auth manager instance
     * 
     * @var Zoho_Sync_Core_Auth_Manager
     */
    private $auth;

    /**
     * Logger instance
     * 
     * @var Zoho_Sync_Core_Logger
     */
    private $logger;

    /**
     * Page hooks
     * 
     * @var array
     */
    private $page_hooks = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = zoho_sync_core_settings();
        $this->auth = zoho_sync_core_auth();
        $this->logger = zoho_sync_core_logger();
        
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . ZOHO_SYNC_CORE_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        $this->page_hooks['main'] = add_menu_page(
            __('Zoho Sync', 'zoho-sync-core'),
            __('Zoho Sync', 'zoho-sync-core'),
            'manage_zoho_sync',
            'zoho-sync-core',
            array($this, 'dashboard_page'),
            'dashicons-update-alt',
            30
        );

        // Dashboard submenu
        $this->page_hooks['dashboard'] = add_submenu_page(
            'zoho-sync-core',
            __('Dashboard', 'zoho-sync-core'),
            __('Dashboard', 'zoho-sync-core'),
            'manage_zoho_sync',
            'zoho-sync-dashboard',
            array($this, 'dashboard_page')
        );

        // Settings submenu
        $this->page_hooks['settings'] = add_submenu_page(
            'zoho-sync-core',
            __('Configuración', 'zoho-sync-core'),
            __('Configuración', 'zoho-sync-core'),
            'manage_zoho_sync_settings',
            'zoho-sync-settings',
            array($this, 'settings_page')
        );

        // Authentication submenu
        $this->page_hooks['auth'] = add_submenu_page(
            'zoho-sync-core',
            __('Autenticación', 'zoho-sync-core'),
            __('Autenticación', 'zoho-sync-core'),
            'manage_zoho_sync_settings',
            'zoho-sync-auth',
            array($this, 'auth_page')
        );

        // Modules submenu
        $this->page_hooks['modules'] = add_submenu_page(
            'zoho-sync-core',
            __('Módulos', 'zoho-sync-core'),
            __('Módulos', 'zoho-sync-core'),
            'manage_zoho_sync_modules',
            'zoho-sync-modules',
            array($this, 'modules_page')
        );

        // Logs submenu
        $this->page_hooks['logs'] = add_submenu_page(
            'zoho-sync-core',
            __('Logs', 'zoho-sync-core'),
            __('Logs', 'zoho-sync-core'),
            'view_zoho_sync_logs',
            'zoho-sync-logs',
            array($this, 'logs_page')
        );

        // System Info submenu
        $this->page_hooks['system'] = add_submenu_page(
            'zoho-sync-core',
            __('Información del Sistema', 'zoho-sync-core'),
            __('Sistema', 'zoho-sync-core'),
            'manage_zoho_sync',
            'zoho-sync-system',
            array($this, 'system_page')
        );

        // Tools submenu
        $this->page_hooks['tools'] = add_submenu_page(
            'zoho-sync-core',
            __('Herramientas', 'zoho-sync-core'),
            __('Herramientas', 'zoho-sync-core'),
            'manage_zoho_sync',
            'zoho-sync-tools',
            array($this, 'tools_page')
        );

        // Remove duplicate main menu item
        remove_submenu_page('zoho-sync-core', 'zoho-sync-core');
    }

    /**
     * Initialize settings
     */
    public function settings_init() {
        // Register main settings group
        register_setting('zoho_sync_core_settings', 'zoho_sync_core_settings', array(
            'sanitize_callback' => array($this->settings, 'sanitize_settings'),
            'show_in_rest' => false
        ));

        // API Settings Section
        add_settings_section(
            'zoho_sync_api_section',
            __('Configuración de API', 'zoho-sync-core'),
            array($this, 'api_section_callback'),
            'zoho_sync_core_settings'
        );

        // Client ID field
        add_settings_field(
            'zoho_client_id',
            __('Client ID', 'zoho-sync-core'),
            array($this, 'text_field_callback'),
            'zoho_sync_core_settings',
            'zoho_sync_api_section',
            array(
                'id' => 'zoho_client_id',
                'description' => __('ID del cliente de tu aplicación Zoho', 'zoho-sync-core'),
                'required' => true
            )
        );

        // Client Secret field
        add_settings_field(
            'zoho_client_secret',
            __('Client Secret', 'zoho-sync-core'),
            array($this, 'password_field_callback'),
            'zoho_sync_core_settings',
            'zoho_sync_api_section',
            array(
                'id' => 'zoho_client_secret',
                'description' => __('Secreto del cliente de tu aplicación Zoho', 'zoho-sync-core'),
                'required' => true
            )
        );

        // Region field
        add_settings_field(
            'zoho_region',
            __('Región de Zoho', 'zoho-sync-core'),
            array($this, 'select_field_callback'),
            'zoho_sync_core_settings',
            'zoho_sync_api_section',
            array(
                'id' => 'zoho_region',
                'options' => $this->auth->get_available_regions(),
                'description' => __('Selecciona la región donde está registrada tu cuenta de Zoho', 'zoho-sync-core')
            )
        );

        // Logging Settings Section
        add_settings_section(
            'zoho_sync_logging_section',
            __('Configuración de Logs', 'zoho-sync-core'),
            array($this, 'logging_section_callback'),
            'zoho_sync_core_settings'
        );

        // Log level field
        add_settings_field(
            'log_level',
            __('Nivel de Log', 'zoho-sync-core'),
            array($this, 'select_field_callback'),
            'zoho_sync_core_settings',
            'zoho_sync_logging_section',
            array(
                'id' => 'log_level',
                'options' => $this->logger->get_log_levels(),
                'description' => __('Nivel mínimo de logs a registrar', 'zoho-sync-core')
            )
        );

        // Log retention field
        add_settings_field(
            'log_retention_days',
            __('Retención de Logs (días)', 'zoho-sync-core'),
            array($this, 'number_field_callback'),
            'zoho_sync_core_settings',
            'zoho_sync_logging_section',
            array(
                'id' => 'log_retention_days',
                'min' => 1,
                'max' => 365,
                'description' => __('Número de días para mantener los logs antes de eliminarlos automáticamente', 'zoho-sync-core')
            )
        );

        // Sync Settings Section
        add_settings_section(
            'zoho_sync_sync_section',
            __('Configuración de Sincronización', 'zoho-sync-core'),
            array($this, 'sync_section_callback'),
            'zoho_sync_core_settings'
        );

        // Sync frequency field
        add_settings_field(
            'sync_frequency',
            __('Frecuencia de Sincronización', 'zoho-sync-core'),
            array($this, 'select_field_callback'),
            'zoho_sync_core_settings',
            'zoho_sync_sync_section',
            array(
                'id' => 'sync_frequency',
                'options' => array(
                    'every_minute' => __('Cada minuto', 'zoho-sync-core'),
                    'every_5_minutes' => __('Cada 5 minutos', 'zoho-sync-core'),
                    'every_15_minutes' => __('Cada 15 minutos', 'zoho-sync-core'),
                    'hourly' => __('Cada hora', 'zoho-sync-core'),
                    'daily' => __('Diariamente', 'zoho-sync-core')
                ),
                'description' => __('Frecuencia con la que se ejecutarán las sincronizaciones automáticas', 'zoho-sync-core')
            )
        );

        // Batch size field
        add_settings_field(
            'batch_size',
            __('Tamaño de Lote', 'zoho-sync-core'),
            array($this, 'number_field_callback'),
            'zoho_sync_core_settings',
            'zoho_sync_sync_section',
            array(
                'id' => 'batch_size',
                'min' => 1,
                'max' => 1000,
                'description' => __('Número de elementos a procesar en cada lote de sincronización', 'zoho-sync-core')
            )
        );

        // Advanced Settings Section
        add_settings_section(
            'zoho_sync_advanced_section',
            __('Configuración Avanzada', 'zoho-sync-core'),
            array($this, 'advanced_section_callback'),
            'zoho_sync_core_settings'
        );

        // Debug mode field
        add_settings_field(
            'enable_debug',
            __('Modo Debug', 'zoho-sync-core'),
            array($this, 'checkbox_field_callback'),
            'zoho_sync_core_settings',
            'zoho_sync_advanced_section',
            array(
                'id' => 'enable_debug',
                'description' => __('Habilitar modo debug para obtener información detallada de depuración', 'zoho-sync-core')
            )
        );

        // Enable webhooks field
        add_settings_field(
            'enable_webhooks',
            __('Habilitar Webhooks', 'zoho-sync-core'),
            array($this, 'checkbox_field_callback'),
            'zoho_sync_core_settings',
            'zoho_sync_advanced_section',
            array(
                'id' => 'enable_webhooks',
                'description' => __('Permitir que Zoho envíe notificaciones automáticas de cambios', 'zoho-sync-core')
            )
        );
    }

    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (!in_array($hook, $this->page_hooks)) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'zoho-sync-core-admin',
            ZOHO_SYNC_CORE_ADMIN_URL . 'assets/css/admin-styles.css',
            array('wp-admin', 'dashicons'),
            ZOHO_SYNC_CORE_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'zoho-sync-core-admin',
            ZOHO_SYNC_CORE_ADMIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util', 'postbox'),
            ZOHO_SYNC_CORE_VERSION,
            true
        );

        // Chart.js for dashboard
        if ($hook === $this->page_hooks['dashboard']) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                array(),
                '3.9.1',
                true
            );
        }

        // Localize script
        wp_localize_script('zoho-sync-core-admin', 'zohoSyncAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url(ZOHO_SYNC_CORE_API_NAMESPACE . '/'),
            'nonce' => wp_create_nonce(ZOHO_SYNC_NONCE_ACTION),
            'current_page' => $hook,
            'strings' => array(
                'loading' => __('Cargando...', 'zoho-sync-core'),
                'success' => __('Éxito', 'zoho-sync-core'),
                'error' => __('Error', 'zoho-sync-core'),
                'confirm' => __('¿Estás seguro?', 'zoho-sync-core'),
                'confirm_clear_logs' => __('¿Estás seguro de que quieres limpiar todos los logs? Esta acción no se puede deshacer.', 'zoho-sync-core'),
                'confirm_reset_settings' => __('¿Estás seguro de que quieres restablecer todas las configuraciones? Esta acción no se puede deshacer.', 'zoho-sync-core'),
                'test_connection' => __('Probando conexión...', 'zoho-sync-core'),
                'connection_success' => __('Conexión exitosa', 'zoho-sync-core'),
                'connection_failed' => __('Conexión fallida', 'zoho-sync-core')
            ),
            'auth_status' => $this->auth->get_auth_status(),
            'system_status' => get_option('zoho_sync_core_system_status', array())
        ));
    }

    /**
     * Add plugin action links
     * 
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_plugin_action_links($links) {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=zoho-sync-settings') . '">' . __('Configuración', 'zoho-sync-core') . '</a>',
            'dashboard' => '<a href="' . admin_url('admin.php?page=zoho-sync-dashboard') . '">' . __('Dashboard', 'zoho-sync-core') . '</a>'
        );

        return array_merge($action_links, $links);
    }

    /**
     * Dashboard page
     */
    public function dashboard_page() {
        if (!current_user_can('manage_zoho_sync')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'zoho-sync-core'));
        }

        include ZOHO_SYNC_CORE_ADMIN_DIR . 'partials/dashboard-display.php';
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if (!current_user_can('manage_zoho_sync_settings')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'zoho-sync-core'));
        }

        include ZOHO_SYNC_CORE_ADMIN_DIR . 'partials/settings-display.php';
    }

    /**
     * Authentication page
     */
    public function auth_page() {
        if (!current_user_can('manage_zoho_sync_settings')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'zoho-sync-core'));
        }

        include ZOHO_SYNC_CORE_ADMIN_DIR . 'partials/auth-display.php';
    }

    /**
     * Modules page
     */
    public function modules_page() {
        if (!current_user_can('manage_zoho_sync_modules')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'zoho-sync-core'));
        }

        include ZOHO_SYNC_CORE_ADMIN_DIR . 'partials/modules-display.php';
    }

    /**
     * Logs page
     */
    public function logs_page() {
        if (!current_user_can('view_zoho_sync_logs')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'zoho-sync-core'));
        }

        include ZOHO_SYNC_CORE_ADMIN_DIR . 'partials/logs-display.php';
    }

    /**
     * System info page
     */
    public function system_page() {
        if (!current_user_can('manage_zoho_sync')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'zoho-sync-core'));
        }

        include ZOHO_SYNC_CORE_ADMIN_DIR . 'partials/system-display.php';
    }

    /**
     * Tools page
     */
    public function tools_page() {
        if (!current_user_can('manage_zoho_sync')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'zoho-sync-core'));
        }

        include ZOHO_SYNC_CORE_ADMIN_DIR . 'partials/tools-display.php';
    }

    /**
     * API section callback
     */
    public function api_section_callback() {
        echo '<p>' . __('Configura las credenciales de tu aplicación Zoho para habilitar la sincronización.', 'zoho-sync-core') . '</p>';
        echo '<p><a href="https://bbrion.es/zoho-ecommerce-connect/configuracion" target="_blank">' . __('¿Necesitas ayuda para obtener estas credenciales?', 'zoho-sync-core') . '</a></p>';
    }

    /**
     * Logging section callback
     */
    public function logging_section_callback() {
        echo '<p>' . __('Configura cómo se registran y almacenan los logs del sistema.', 'zoho-sync-core') . '</p>';
    }

    /**
     * Sync section callback
     */
    public function sync_section_callback() {
        echo '<p>' . __('Configura los parámetros de sincronización automática con Zoho.', 'zoho-sync-core') . '</p>';
    }

    /**
     * Advanced section callback
     */
    public function advanced_section_callback() {
        echo '<p>' . __('Configuraciones avanzadas para usuarios experimentados.', 'zoho-sync-core') . '</p>';
        echo '<div class="notice notice-warning inline"><p><strong>' . __('Advertencia:', 'zoho-sync-core') . '</strong> ' . __('Cambiar estas configuraciones puede afectar el funcionamiento del plugin.', 'zoho-sync-core') . '</p></div>';
    }

    /**
     * Text field callback
     * 
     * @param array $args Field arguments
     */
    public function text_field_callback($args) {
        $value = $this->settings->get($args['id'], '');
        $required = isset($args['required']) && $args['required'] ? 'required' : '';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        
        echo '<input type="text" id="' . esc_attr($args['id']) . '" name="zoho_sync_core_settings[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" ' . $required . '>';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Password field callback
     * 
     * @param array $args Field arguments
     */
    public function password_field_callback($args) {
        $value = $this->settings->get($args['id'], '');
        $required = isset($args['required']) && $args['required'] ? 'required' : '';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        
        echo '<input type="password" id="' . esc_attr($args['id']) . '" name="zoho_sync_core_settings[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" ' . $required . '>';
        echo '<button type="button" class="button button-secondary toggle-password" data-target="' . esc_attr($args['id']) . '">' . __('Mostrar', 'zoho-sync-core') . '</button>';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Select field callback
     * 
     * @param array $args Field arguments
     */
    public function select_field_callback($args) {
        $value = $this->settings->get($args['id'], '');
        $options = isset($args['options']) ? $args['options'] : array();
        
        echo '<select id="' . esc_attr($args['id']) . '" name="zoho_sync_core_settings[' . esc_attr($args['id']) . ']">';
        
        foreach ($options as $option_value => $option_label) {
            $selected = selected($value, $option_value, false);
            echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
        }
        
        echo '</select>';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Number field callback
     * 
     * @param array $args Field arguments
     */
    public function number_field_callback($args) {
        $value = $this->settings->get($args['id'], '');
        $min = isset($args['min']) ? $args['min'] : '';
        $max = isset($args['max']) ? $args['max'] : '';
        $step = isset($args['step']) ? $args['step'] : '1';
        
        echo '<input type="number" id="' . esc_attr($args['id']) . '" name="zoho_sync_core_settings[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" class="small-text" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" step="' . esc_attr($step) . '">';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Checkbox field callback
     * 
     * @param array $args Field arguments
     */
    public function checkbox_field_callback($args) {
        $value = $this->settings->get($args['id'], false);
        $checked = checked($value, true, false);
        
        echo '<label for="' . esc_attr($args['id']) . '">';
        echo '<input type="checkbox" id="' . esc_attr($args['id']) . '" name="zoho_sync_core_settings[' . esc_attr($args['id']) . ']" value="1" ' . $checked . '>';
        
        if (isset($args['description'])) {
            echo ' ' . esc_html($args['description']);
        }
        
        echo '</label>';
    }

    /**
     * Textarea field callback
     * 
     * @param array $args Field arguments
     */
    public function textarea_field_callback($args) {
        $value = $this->settings->get($args['id'], '');
        $rows = isset($args['rows']) ? $args['rows'] : 5;
        $cols = isset($args['cols']) ? $args['cols'] : 50;
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        
        echo '<textarea id="' . esc_attr($args['id']) . '" name="zoho_sync_core_settings[' . esc_attr($args['id']) . ']" rows="' . esc_attr($rows) . '" cols="' . esc_attr($cols) . '" class="large-text" placeholder="' . esc_attr($placeholder) . '">' . esc_textarea($value) . '</textarea>';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Get page hooks
     * 
     * @return array Page hooks
     */
    public function get_page_hooks() {
        return $this->page_hooks;
    }

    /**
     * Check if current page is a Zoho Sync admin page
     * 
     * @return bool Is Zoho Sync admin page
     */
    public function is_zoho_sync_admin_page() {
        $current_screen = get_current_screen();
        
        if (!$current_screen) {
            return false;
        }
        
        return in_array($current_screen->id, $this->page_hooks);
    }

    /**
     * Add admin notices
     */
    public function add_admin_notices() {
        if (!$this->is_zoho_sync_admin_page()) {
            return;
        }

        // Check for settings updates
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            add_settings_error(
                'zoho_sync_core_messages',
                'zoho_sync_core_message',
                __('Configuración guardada correctamente.', 'zoho-sync-core'),
                'updated'
            );
        }

        // Show settings errors
        settings_errors('zoho_sync_core_messages');
    }

    /**
     * Get admin page URL
     * 
     * @param string $page Page slug
     * @param array $args Additional arguments
     * @return string Admin page URL
     */
    public function get_admin_url($page, $args = array()) {
        $base_url = admin_url('admin.php?page=' . $page);
        
        if (!empty($args)) {
            $base_url = add_query_arg($args, $base_url);
        }
        
        return $base_url;
    }

    /**
     * Render admin header
     * 
     * @param string $title Page title
     * @param string $description Page description
     */
    public function render_admin_header($title, $description = '') {
        echo '<div class="zoho-sync-admin-header">';
        echo '<div class="zoho-sync-admin-header-content">';
        echo '<h1 class="zoho-sync-admin-title">' . esc_html($title) . '</h1>';
        
        if ($description) {
            echo '<p class="zoho-sync-admin-description">' . esc_html($description) . '</p>';
        }
        
        echo '</div>';
        echo '<div class="zoho-sync-admin-header-actions">';
        
        // Add common header actions
        $this->render_header_actions();
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render header actions
     */
    private function render_header_actions() {
        $auth_status = $this->auth->get_auth_status();
        
        if ($auth_status['tokens_available'] && $auth_status['token_valid']) {
            echo '<span class="zoho-sync-status-indicator status-connected">';
            echo '<span class="dashicons dashicons-yes-alt"></span>';
            echo __('Conectado', 'zoho-sync-core');
            echo '</span>';
        } else {
            echo '<span class="zoho-sync-status-indicator status-disconnected">';
            echo '<span class="dashicons dashicons-warning"></span>';
            echo __('Desconectado', 'zoho-sync-core');
            echo '</span>';
        }
        
        echo '<a href="' . $this->get_admin_url('zoho-sync-dashboard') . '" class="button button-secondary">';
        echo '<span class="dashicons dashicons-dashboard"></span>';
        echo __('Dashboard', 'zoho-sync-core');
        echo '</a>';
    }
}
