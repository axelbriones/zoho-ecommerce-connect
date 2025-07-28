<?php

class ZSDP_Module_Integrator {
    
    private $active_modules = [];
    private $cache_manager;
    private $logger;
    
    public function __construct() {
        $this->cache_manager = new ZSDP_Cache_Manager();
        $this->logger = new ZSDP_Portal_Logger();
        
        // Detectar módulos activos
        $this->detect_active_modules();
        
        // Inicializar integraciones
        $this->init_integrations();
    }

    private function detect_active_modules() {
        $core = ZohoSyncCore::instance();
        $this->active_modules = $core->get_active_modules();
    }

    private function init_integrations() {
        // Integración con Orders
        if (isset($this->active_modules['orders'])) {
            add_filter('zsso_order_data', [$this, 'enhance_order_data'], 10, 2);
            add_action('zsso_after_order_sync', [$this, 'handle_order_sync'], 10, 2);
        }

        // Integración con Inventory
        if (isset($this->active_modules['inventory'])) {
            add_filter('zssi_stock_levels', [$this, 'adjust_distributor_stock'], 10, 2);
            add_action('zssi_low_stock_alert', [$this, 'notify_distributor_stock'], 10, 2);
        }

        // Integración con Reports
        if (isset($this->active_modules['reports'])) {
            add_filter('zssp_report_data', [$this, 'add_distributor_metrics'], 10, 2);
            add_action('zssp_after_report_generation', [$this, 'process_distributor_report'], 10, 2);
        }
    }

    public function enhance_order_data($order_data, $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return $order_data;

        // Añadir información del distribuidor si aplica
        $user_id = $order->get_user_id();
        if ($this->is_distributor($user_id)) {
            $distributor_data = $this->get_distributor_data($user_id);
            $order_data['distributor'] = $distributor_data;
        }

        return $order_data;
    }

    public function handle_order_sync($order_id, $sync_result) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = $order->get_user_id();
        if (!$this->is_distributor($user_id)) return;

        // Actualizar métricas del distribuidor
        $this->update_distributor_metrics($user_id, $order);

        // Cachear datos actualizados
        $cache_key = "distributor_data_{$user_id}";
        $this->cache_manager->clear_cache($cache_key);
    }

    public function adjust_distributor_stock($stock_levels, $product_id) {
        // Ajustar niveles de stock por distribuidor
        if ($distributor_allocations = $this->get_distributor_allocations($product_id)) {
            foreach ($distributor_allocations as $distributor_id => $allocation) {
                if (isset($stock_levels[$distributor_id])) {
                    $stock_levels[$distributor_id] = min(
                        $stock_levels[$distributor_id],
                        $allocation
                    );
                }
            }
        }

        return $stock_levels;
    }

    public function notify_distributor_stock($product_id, $stock_level) {
        // Notificar a distribuidores sobre niveles bajos de stock
        $distributors = $this->get_product_distributors($product_id);
        
        foreach ($distributors as $distributor_id) {
            if ($this->should_notify_distributor($distributor_id, $stock_level)) {
                $this->send_stock_notification($distributor_id, $product_id, $stock_level);
            }
        }
    }

    public function add_distributor_metrics($report_data, $params) {
        if (!isset($params['distributor_id'])) {
            return $report_data;
        }

        // Añadir métricas específicas del distribuidor
        $distributor_metrics = $this->calculate_distributor_metrics($params['distributor_id']);
        
        return array_merge($report_data, [
            'distributor_metrics' => $distributor_metrics
        ]);
    }

    private function is_distributor($user_id) {
        $user = get_userdata($user_id);
        return $user && in_array('distributor', $user->roles);
    }

    private function get_distributor_data($user_id) {
        $cache_key = "distributor_data_{$user_id}";
        
        // Intentar obtener de caché
        $data = $this->cache_manager->get_cached_data($cache_key);
        if ($data !== false) {
            return $data;
        }

        // Obtener datos frescos
        $data = [
            'id' => $user_id,
            'level' => get_user_meta($user_id, 'distributor_level', true),
            'zone' => get_user_meta($user_id, 'assigned_zone', true),
            'metrics' => $this->calculate_distributor_metrics($user_id)
        ];

        // Cachear datos
        $this->cache_manager->set_cached_data($cache_key, $data, 3600);

        return $data;
    }

    private function calculate_distributor_metrics($distributor_id) {
        return [
            'total_orders' => $this->get_total_orders($distributor_id),
            'total_revenue' => $this->get_total_revenue($distributor_id),
            'average_order' => $this->get_average_order($distributor_id),
            'active_customers' => $this->get_active_customers($distributor_id)
        ];
    }

    private function get_total_orders($distributor_id) {
        return wc_get_customer_order_count($distributor_id);
    }

    private function get_total_revenue($distributor_id) {
        return wc_get_customer_total_spent($distributor_id);
    }

    private function get_average_order($distributor_id) {
        $total = $this->get_total_revenue($distributor_id);
        $count = $this->get_total_orders($distributor_id);
        
        return $count > 0 ? $total / $count : 0;
    }

    private function get_active_customers($distributor_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT post_author)
            FROM {$wpdb->posts}
            WHERE post_type = 'shop_order'
            AND post_status IN ('wc-completed',