<?php

class ZSDP_Cache_Manager {
    
    private $cache_group = 'zsdp_cache';
    private $cache_expiration = 3600; // 1 hora por defecto
    
    public function __construct() {
        add_action('save_post', [$this, 'invalidate_product_cache']);
        add_action('woocommerce_order_status_changed', [$this, 'invalidate_order_cache']);
        add_action('profile_update', [$this, 'invalidate_distributor_cache']);
    }

    public function get_cached_data($key, $expiration = null) {
        $cached_data = wp_cache_get($key, $this->cache_group);
        
        // Verificar si los datos están expirados
        if ($cached_data && isset($cached_data['expires']) && $cached_data['expires'] < time()) {
            $this->clear_cache($key);
            return false;
        }

        return $cached_data ? $cached_data['data'] : false;
    }

    public function set_cached_data($key, $data, $expiration = null) {
        if ($expiration === null) {
            $expiration = $this->cache_expiration;
        }

        $cache_data = [
            'data' => $data,
            'expires' => time() + $expiration
        ];

        return wp_cache_set($key, $cache_data, $this->cache_group);
    }

    public function clear_cache($key = '') {
        if (empty($key)) {
            // Limpiar todo el grupo de caché
            wp_cache_delete_group($this->cache_group);
        } else {
            wp_cache_delete($key, $this->cache_group);
        }
    }

    public function invalidate_product_cache($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $this->clear_cache('product_' . $post_id);
        $this->clear_cache('product_list');
        $this->clear_cache('special_prices');
    }

    public function invalidate_order_cache($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = $order->get_user_id();
        $this->clear_cache('distributor_data_' . $user_id);
        $this->clear_cache('distributor_orders_' . $user_id);
    }

    public function invalidate_distributor_cache($user_id) {
        $user = get_userdata($user_id);
        if (!$user || !in_array('distributor', $user->roles)) {
            return;
        }

        $this->clear_cache('distributor_data_' . $user_id);
        $this->clear_cache('distributor_metrics_' . $user_id);
    }
}