<?php

class ZSCU_Customer_Portal {
    
    private $pricing_manager;
    private $distributor_manager;
    
    public function __construct() {
        $this->pricing_manager = new ZSCU_Pricing_Manager();
        $this->distributor_manager = new ZSCU_Distributor_Manager();
        
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('customer_portal', [$this, 'render_portal']);
    }

    public function init() {
        // Registrar endpoints personalizados
        add_rewrite_endpoint('special-pricing', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('order-history', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('account-status', EP_ROOT | EP_PAGES);
        
        // Filtrar menú de cuenta
        add_filter('woocommerce_account_menu_items', [$this, 'add_portal_menu_items']);
    }

    public function enqueue_assets() {
        if (!$this->is_portal_page()) {
            return;
        }

        wp_enqueue_style(
            'zscu-portal',
            plugins_url('assets/css/portal.css', dirname(__FILE__)),
            [],
            ZSCU_VERSION
        );

        wp_enqueue_script(
            'zscu-portal',
            plugins_url('assets/js/portal.js', dirname(__FILE__)),
            ['jquery'],
            ZSCU_VERSION,
            true
        );

        wp_localize_script('zscu-portal', 'zscuPortal', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zscu-portal-nonce')
        ]);
    }

    public function render_portal($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_form();
        }

        $user_id = get_current_user_id();
        
        ob_start();
        
        if ($this->distributor_manager->is_distributor($user_id)) {
            $this->render_distributor_dashboard($user_id);
        } else {
            $this->render_customer_dashboard($user_id);
        }
        
        return ob_get_clean();
    }

    private function render_distributor_dashboard($user_id) {
        $level = $this->distributor_manager->get_distributor_level($user_id);
        $stats = $this->get_distributor_stats($user_id);
        
        include ZSCU_PLUGIN_DIR . 'templates/distributor-dashboard.php';
    }

    private function render_customer_dashboard($user_id) {
        $customer = new WC_Customer($user_id);
        $recent_orders = wc_get_orders([
            'customer' => $user_id,
            'limit' => 5
        ]);
        
        include ZSCU_PLUGIN_DIR . 'templates/customer-dashboard.php';
    }

    private function get_distributor_stats($user_id) {
        return [
            'total_orders' => wc_get_customer_order_count($user_id),
            'total_spent' => wc_price(wc_get_customer_total_spent($user_id)),
            'average_order' => $this->calculate_average_order($user_id),
            'last_order' => $this->get_last_order_date($user_id),
            'discount_level' => $this->distributor_manager->get_user_discount($user_id) . '%'
        ];
    }

    private function calculate_average_order($user_id) {
        $total_spent = wc_get_customer_total_spent($user_id);
        $order_count = wc_get_customer_order_count($user_id);
        
        if ($order_count === 0) {
            return wc_price(0);
        }
        
        return wc_price($total_spent / $order_count);
    }

    private function get_last_order_date($user_id) {
        $last_order = wc_get_customer_last_order($user_id);
        
        if (!$last_order) {
            return __('Sin pedidos', 'zoho-sync-customers');
        }
        
        return $last_order->get_date_created()->date_i18n(get_option('date_format'));
    }

    private function is_portal_page() {
        return is_account_page() || 
               is_page('customer-portal') || 
               has_shortcode(get_post()->post_content, 'customer_portal');
    }

    public function add_portal_menu_items($items) {
        $new_items = [];
        
        foreach ($items as $key => $item) {
            $new_items[$key] = $item;
            
            if ($key === 'dashboard') {
                $new_items['special-pricing'] = __('Precios Especiales', 'zoho-sync-customers');
                $new_items['order-history'] = __('Historial de Pedidos', 'zoho-sync-customers');
                $new_items['account-status'] = __('Estado de Cuenta', 'zoho-sync-customers');
            }
        }
        
        return $new_items;
    }

    private function render_login_form() {
        ob_start();
        include ZSCU_PLUGIN_DIR . 'templates/login-form.php';
        return ob_get_clean();
    }
}