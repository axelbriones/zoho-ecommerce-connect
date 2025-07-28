<?php

class ZSDP_Account_Endpoints {
    
    private $endpoints = [];

    public function __construct() {
        add_action('init', [$this, 'register_endpoints']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_menu_items']);
        add_action('template_redirect', [$this, 'endpoint_content']);
    }

    public function register_endpoints() {
        $this->endpoints = [
            'distributor-dashboard' => [
                'title' => __('Dashboard', 'zoho-distributor-portal'),
                'callback' => [$this, 'render_dashboard']
            ],
            'special-pricing' => [
                'title' => __('Precios Especiales', 'zoho-distributor-portal'),
                'callback' => [$this, 'render_special_pricing']
            ],
            'distributor-orders' => [
                'title' => __('Mis Pedidos', 'zoho-distributor-portal'),
                'callback' => [$this, 'render_orders']
            ],
            'distributor-reports' => [
                'title' => __('Reportes', 'zoho-distributor-portal'),
                'callback' => [$this, 'render_reports']
            ]
        ];

        foreach ($this->endpoints as $endpoint => $data) {
            add_rewrite_endpoint($endpoint, EP_ROOT | EP_PAGES);
        }
    }

    public function add_query_vars($vars) {
        foreach ($this->endpoints as $endpoint => $data) {
            $vars[] = $endpoint;
        }
        return $vars;
    }

    public function add_menu_items($items) {
        foreach ($this->endpoints as $endpoint => $data) {
            $items[$endpoint] = $data['title'];
        }
        return $items;
    }

    public function endpoint_content() {
        foreach ($this->endpoints as $endpoint => $data) {
            if ($this->is_endpoint_active($endpoint)) {
                call_user_func($data['callback']);
                break;
            }
        }
    }

    public function is_portal_endpoint() {
        foreach ($this->endpoints as $endpoint => $data) {
            if ($this->is_endpoint_active($endpoint)) {
                return true;
            }
        }
        return false;
    }

    private function is_endpoint_active($endpoint) {
        global $wp_query;
        return isset($wp_query->query_vars[$endpoint]);
    }

    // Callbacks de renderizado
    public function render_dashboard() {
        ZSDP_Template_Loader::get_template('dashboard.php');
    }

    public function render_special_pricing() {
        ZSDP_Template_Loader::get_template('special-pricing.php');
    }

    public function render_orders() {
        ZSDP_Template_Loader::get_template('orders.php');
    }

    public function render_reports() {
        ZSDP_Template_Loader::get_template('reports.php');
    }
}