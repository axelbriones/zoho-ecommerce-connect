<?php

class ZSDP_Distributor_Portal {
    
    private $active_integrations = [];
    private $endpoints;
    private $security;

    public function __construct() {
        $this->security = new ZSDP_Portal_Security();
        $this->endpoints = new ZSDP_Account_Endpoints();
        
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('template_redirect', [$this, 'check_access']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_portal_menu_items']);
    }

    public function init() {
        // Registrar shortcodes
        add_shortcode('distributor_portal', [$this, 'render_portal']);
        add_shortcode('distributor_special_pricing', [$this, 'render_special_pricing']);

        // Inicializar integraciones activas
        $this->load_active_integrations();
    }

    private function load_active_integrations() {
        $core = ZohoSyncCore::instance();
        $modules = $core->get_active_modules();

        foreach ($modules as $module_name => $module_data) {
            $integration_class = 'ZSDP_' . ucfirst($module_name) . '_Integration';
            $integration_file = ZSDP_PLUGIN_DIR . "integrations/class-{$module_name}-integration.php";

            if (file_exists($integration_file)) {
                require_once $integration_file;
                if (class_exists($integration_class)) {
                    $this->active_integrations[$module_name] = new $integration_class(
                        $module_name,
                        $module_data['version']
                    );
                }
            }
        }
    }

    public function check_access() {
        if (!is_page('distributor-portal')) {
            return;
        }

        if (!$this->security->can_access_portal()) {
            wp_redirect(home_url());
            exit;
        }
    }

    public function enqueue_assets() {
        if (!$this->is_portal_page()) {
            return;
        }

        wp_enqueue_style(
            'zsdp-portal',
            ZSDP_PLUGIN_URL . 'assets/css/portal.css',
            [],
            ZSDP_VERSION
        );

        wp_enqueue_script(
            'zsdp-portal',
            ZSDP_PLUGIN_URL . 'assets/js/portal.js',
            ['jquery'],
            ZSDP_VERSION,
            true
        );

        wp_localize_script('zsdp-portal', 'zsdpPortal', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zsdp-portal-nonce'),
            'i18n' => [
                'loading' => __('Cargando...', 'zoho-distributor-portal'),
                'error' => __('Error al cargar datos', 'zoho-distributor-portal')
            ]
        ]);
    }

    public function render_portal() {
        if (!$this->security->can_access_portal()) {
            return $this->render_template('access-denied');
        }

        // Obtener datos del distribuidor
        $distributor_id = get_current_user_id();
        $dashboard_widgets = $this->get_dashboard_widgets($distributor_id);

        // Renderizar dashboard
        ob_start();
        $this->render_template('dashboard', [
            'distributor_id' => $distributor_id,
            'widgets' => $dashboard_widgets
        ]);
        return ob_get_clean();
    }

    private function get_dashboard_widgets($distributor_id) {
        $widgets = [];

        // Recopilar widgets de todas las integraciones activas
        foreach ($this->active_integrations as $integration) {
            $widget = $integration->get_dashboard_widget();
            if ($widget) {
                $widgets[] = $widget;
            }
        }

        return apply_filters('zsdp_dashboard_widgets', $widgets, $distributor_id);
    }

    private function render_template($template, $data = []) {
        $template_file = ZSDP_PLUGIN_DIR . 'templates/' . $template . '.php';
        
        if (!file_exists($template_file)) {
            return '';
        }

        extract($data);
        ob_start();
        include $template_file;
        return ob_get_clean();
    }

    public function add_portal_menu_items($items) {
        // Añadir elementos de menú de las integraciones
        foreach ($this->active_integrations as $integration) {
            $menu_items = $integration->get_menu_items();
            if (!empty($menu_items)) {
                $items = array_merge($items, $menu_items);
            }
        }

        return $items;
    }

    private function is_portal_page() {
        return is_page('distributor-portal') || 
               is_page('special-pricing') || 
               $this->endpoints->is_portal_endpoint();
    }
}