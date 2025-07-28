<?php
/**
 * Order Triggers Class
 *
 * Handles specific triggers for order synchronization
 *
 * @package ZohoSyncOrders
 * @subpackage Hooks
 * @since 1.0.0
 */

namespace ZohoSyncOrders\Hooks;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order synchronization triggers
 */
class OrderTriggers {
    
    /**
     * Orders sync instance
     *
     * @var \ZohoSyncOrders\OrdersSync
     */
    private $orders_sync;
    
    /**
     * Trigger conditions
     *
     * @var array
     */
    private $trigger_conditions = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->orders_sync = new \ZohoSyncOrders\OrdersSync();
        $this->load_trigger_conditions();
        $this->init_triggers();
    }
    
    /**
     * Load trigger conditions from options
     */
    private function load_trigger_conditions() {
        $this->trigger_conditions = array(
            'order_total_threshold' => get_option('zoho_sync_orders_total_threshold', 0),
            'customer_types' => get_option('zoho_sync_orders_customer_types', array('all')),
            'product_categories' => get_option('zoho_sync_orders_product_categories', array()),
            'payment_methods' => get_option('zoho_sync_orders_payment_methods', array()),
            'shipping_methods' => get_option('zoho_sync_orders_shipping_methods', array()),
            'time_conditions' => get_option('zoho_sync_orders_time_conditions', array()),
            'custom_fields' => get_option('zoho_sync_orders_custom_field_triggers', array())
        );
    }
    
    /**
     * Initialize triggers
     */
    private function init_triggers() {
        // Custom trigger hooks
        add_action('zoho_sync_orders_trigger_check', array($this, 'check_custom_triggers'), 10, 2);
        add_action('zoho_sync_orders_conditional_sync', array($this, 'conditional_sync'), 10, 2);
        
        // Time-based triggers
        add_action('zoho_sync_orders_hourly_check', array($this, 'hourly_trigger_check'));
        add_action('zoho_sync_orders_daily_check', array($this, 'daily_trigger_check'));
        
        // Customer-based triggers
        add_action('woocommerce_customer_save_address', array($this, 'customer_address_change'), 10, 2);
        add_action('profile_update', array($this, 'customer_profile_update'), 10, 2);
        
        // Product-based triggers
        add_action('woocommerce_product_set_stock', array($this, 'product_stock_change'), 10, 1);
        add_action('woocommerce_variation_set_stock', array($this, 'variation_stock_change'), 10, 1);
        
        // Cart and checkout triggers
        add_action('woocommerce_add_to_cart', array($this, 'cart_item_added'), 10, 6);
        add_action('woocommerce_cart_item_removed', array($this, 'cart_item_removed'), 10, 2);
        
        // Coupon triggers
        add_action('woocommerce_applied_coupon', array($this, 'coupon_applied'), 10, 1);
        add_action('woocommerce_removed_coupon', array($this, 'coupon_removed'), 10, 1);
        
        // Admin triggers
        add_action('wp_ajax_zoho_trigger_order_sync', array($this, 'manual_trigger_sync'));
        add_action('wp_ajax_zoho_test_trigger_conditions', array($this, 'test_trigger_conditions'));
    }
    
    /**
     * Check custom triggers for an order
     *
     * @param int $order_id Order ID
     * @param string $context Trigger context
     */
    public function check_custom_triggers($order_id, $context = 'general') {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            
            $should_sync = false;
            $trigger_reasons = array();
            
            // Check order total threshold
            if ($this->check_total_threshold($order)) {
                $should_sync = true;
                $trigger_reasons[] = 'total_threshold';
            }
            
            // Check customer type conditions
            if ($this->check_customer_type($order)) {
                $should_sync = true;
                $trigger_reasons[] = 'customer_type';
            }
            
            // Check product category conditions
            if ($this->check_product_categories($order)) {
                $should_sync = true;
                $trigger_reasons[] = 'product_categories';
            }
            
            // Check payment method conditions
            if ($this->check_payment_method($order)) {
                $should_sync = true;
                $trigger_reasons[] = 'payment_method';
            }
            
            // Check shipping method conditions
            if ($this->check_shipping_method($order)) {
                $should_sync = true;
                $trigger_reasons[] = 'shipping_method';
            }
            
            // Check custom field conditions
            if ($this->check_custom_fields($order)) {
                $should_sync = true;
                $trigger_reasons[] = 'custom_fields';
            }
            
            // Check time conditions
            if ($this->check_time_conditions($order)) {
                $should_sync = true;
                $trigger_reasons[] = 'time_conditions';
            }
            
            // Apply custom filters
            $should_sync = apply_filters('zoho_sync_orders_custom_trigger_check', $should_sync, $order, $context, $trigger_reasons);
            
            if ($should_sync) {
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Triggers personalizados activados para pedido %d: %s', 'zoho-sync-orders'), 
                        $order_id, implode(', ', $trigger_reasons)),
                    'info',
                    'orders'
                );
                
                // Trigger sync
                do_action('zoho_sync_orders_conditional_sync', $order_id, $trigger_reasons);
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error verificando triggers personalizados para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Conditional sync based on triggers
     *
     * @param int $order_id Order ID
     * @param array $trigger_reasons Trigger reasons
     */
    public function conditional_sync($order_id, $trigger_reasons) {
        try {
            // Determine sync type based on existing record
            $sync_record = $this->get_sync_record($order_id);
            $sync_type = ($sync_record && $sync_record->zoho_id) ? 'update' : 'create';
            
            // Perform sync
            $result = $this->orders_sync->sync_order($order_id, $sync_type);
            
            if ($result['success']) {
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Sincronización condicional exitosa para pedido %d (triggers: %s)', 'zoho-sync-orders'), 
                        $order_id, implode(', ', $trigger_reasons)),
                    'info',
                    'orders'
                );
            } else {
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Sincronización condicional fallida para pedido %d: %s', 'zoho-sync-orders'), 
                        $order_id, $result['message']),
                    'error',
                    'orders'
                );
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error en sincronización condicional para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Check order total threshold
     *
     * @param \WC_Order $order Order object
     * @return bool
     */
    private function check_total_threshold($order) {
        $threshold = $this->trigger_conditions['order_total_threshold'];
        
        if ($threshold <= 0) {
            return false; // No threshold set
        }
        
        return $order->get_total() >= $threshold;
    }
    
    /**
     * Check customer type conditions
     *
     * @param \WC_Order $order Order object
     * @return bool
     */
    private function check_customer_type($order) {
        $allowed_types = $this->trigger_conditions['customer_types'];
        
        if (empty($allowed_types) || in_array('all', $allowed_types)) {
            return true; // All customer types allowed
        }
        
        $customer_id = $order->get_customer_id();
        
        if (!$customer_id) {
            // Guest customer
            return in_array('guest', $allowed_types);
        }
        
        $user = get_user_by('id', $customer_id);
        if (!$user) {
            return false;
        }
        
        // Check user roles
        $user_roles = $user->roles;
        
        foreach ($user_roles as $role) {
            if (in_array($role, $allowed_types)) {
                return true;
            }
        }
        
        // Check for specific customer types
        if (in_array('b2b', $allowed_types) && in_array('b2b_customer', $user_roles)) {
            return true;
        }
        
        if (in_array('distributor', $allowed_types) && in_array('distributor', $user_roles)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check product category conditions
     *
     * @param \WC_Order $order Order object
     * @return bool
     */
    private function check_product_categories($order) {
        $required_categories = $this->trigger_conditions['product_categories'];
        
        if (empty($required_categories)) {
            return false; // No categories specified
        }
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            
            $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'));
            
            // Check if any product category matches required categories
            if (!empty(array_intersect($product_categories, $required_categories))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check payment method conditions
     *
     * @param \WC_Order $order Order object
     * @return bool
     */
    private function check_payment_method($order) {
        $allowed_methods = $this->trigger_conditions['payment_methods'];
        
        if (empty($allowed_methods)) {
            return false; // No payment methods specified
        }
        
        $order_payment_method = $order->get_payment_method();
        
        return in_array($order_payment_method, $allowed_methods);
    }
    
    /**
     * Check shipping method conditions
     *
     * @param \WC_Order $order Order object
     * @return bool
     */
    private function check_shipping_method($order) {
        $allowed_methods = $this->trigger_conditions['shipping_methods'];
        
        if (empty($allowed_methods)) {
            return false; // No shipping methods specified
        }
        
        $shipping_methods = $order->get_shipping_methods();
        
        foreach ($shipping_methods as $shipping_method) {
            if (in_array($shipping_method->get_method_id(), $allowed_methods)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check custom field conditions
     *
     * @param \WC_Order $order Order object
     * @return bool
     */
    private function check_custom_fields($order) {
        $custom_conditions = $this->trigger_conditions['custom_fields'];
        
        if (empty($custom_conditions)) {
            return false;
        }
        
        foreach ($custom_conditions as $field_key => $condition) {
            $field_value = $order->get_meta($field_key);
            
            if (!$this->evaluate_field_condition($field_value, $condition)) {
                return false; // All conditions must be met
            }
        }
        
        return true;
    }
    
    /**
     * Check time-based conditions
     *
     * @param \WC_Order $order Order object
     * @return bool
     */
    private function check_time_conditions($order) {
        $time_conditions = $this->trigger_conditions['time_conditions'];
        
        if (empty($time_conditions)) {
            return false;
        }
        
        $order_date = $order->get_date_created();
        $current_time = new \DateTime();
        
        // Check business hours
        if (isset($time_conditions['business_hours_only']) && $time_conditions['business_hours_only']) {
            $hour = intval($current_time->format('H'));
            $start_hour = intval($time_conditions['business_start'] ?? 9);
            $end_hour = intval($time_conditions['business_end'] ?? 17);
            
            if ($hour < $start_hour || $hour >= $end_hour) {
                return false;
            }
        }
        
        // Check business days
        if (isset($time_conditions['business_days_only']) && $time_conditions['business_days_only']) {
            $day_of_week = intval($current_time->format('N')); // 1 = Monday, 7 = Sunday
            
            if ($day_of_week > 5) { // Weekend
                return false;
            }
        }
        
        // Check order age
        if (isset($time_conditions['max_order_age'])) {
            $max_age_hours = intval($time_conditions['max_order_age']);
            $order_age = $current_time->getTimestamp() - $order_date->getTimestamp();
            $order_age_hours = $order_age / 3600;
            
            if ($order_age_hours > $max_age_hours) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Evaluate field condition
     *
     * @param mixed $field_value Field value
     * @param array $condition Condition array
     * @return bool
     */
    private function evaluate_field_condition($field_value, $condition) {
        $operator = $condition['operator'] ?? 'equals';
        $expected_value = $condition['value'] ?? '';
        
        switch ($operator) {
            case 'equals':
                return $field_value == $expected_value;
                
            case 'not_equals':
                return $field_value != $expected_value;
                
            case 'contains':
                return strpos($field_value, $expected_value) !== false;
                
            case 'not_contains':
                return strpos($field_value, $expected_value) === false;
                
            case 'greater_than':
                return floatval($field_value) > floatval($expected_value);
                
            case 'less_than':
                return floatval($field_value) < floatval($expected_value);
                
            case 'empty':
                return empty($field_value);
                
            case 'not_empty':
                return !empty($field_value);
                
            default:
                return false;
        }
    }
    
    /**
     * Hourly trigger check
     */
    public function hourly_trigger_check() {
        // Check for orders that need sync based on time conditions
        $this->check_pending_orders('hourly');
    }
    
    /**
     * Daily trigger check
     */
    public function daily_trigger_check() {
        // Check for orders that need sync based on daily conditions
        $this->check_pending_orders('daily');
        
        // Cleanup old trigger logs
        $this->cleanup_trigger_logs();
    }
    
    /**
     * Check pending orders for sync
     *
     * @param string $frequency Check frequency
     */
    private function check_pending_orders($frequency) {
        global $wpdb;
        
        // Get orders that haven't been synced yet
        $pending_orders = $wpdb->get_results(
            "SELECT p.ID as order_id 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}zoho_orders_sync zos ON p.ID = zos.order_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed')
            AND (zos.id IS NULL OR zos.sync_status = 'pending')
            AND p.post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            LIMIT 50"
        );
        
        foreach ($pending_orders as $pending_order) {
            do_action('zoho_sync_orders_trigger_check', $pending_order->order_id, $frequency);
        }
    }
    
    /**
     * Handle customer address change
     *
     * @param int $user_id User ID
     * @param string $load_address Address type
     */
    public function customer_address_change($user_id, $load_address) {
        // Find recent orders for this customer that might need updating
        $recent_orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status' => array('processing', 'completed'),
            'date_created' => '>' . (time() - WEEK_IN_SECONDS),
            'limit' => 5
        ));
        
        foreach ($recent_orders as $order) {
            do_action('zoho_sync_orders_trigger_check', $order->get_id(), 'address_change');
        }
    }
    
    /**
     * Handle customer profile update
     *
     * @param int $user_id User ID
     * @param \WP_User $old_user_data Old user data
     */
    public function customer_profile_update($user_id, $old_user_data) {
        // Similar to address change, check recent orders
        $this->customer_address_change($user_id, 'profile_update');
    }
    
    /**
     * Handle product stock change
     *
     * @param \WC_Product $product Product object
     */
    public function product_stock_change($product) {
        // Find recent orders containing this product
        $this->check_orders_with_product($product->get_id());
    }
    
    /**
     * Handle variation stock change
     *
     * @param \WC_Product_Variation $variation Variation object
     */
    public function variation_stock_change($variation) {
        // Find recent orders containing this variation
        $this->check_orders_with_product($variation->get_id());
    }
    
    /**
     * Check orders containing specific product
     *
     * @param int $product_id Product ID
     */
    private function check_orders_with_product($product_id) {
        global $wpdb;
        
        $orders_with_product = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID as order_id
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-processing', 'wc-completed')
                AND p.post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND oim.meta_key IN ('_product_id', '_variation_id')
                AND oim.meta_value = %d
                LIMIT 20",
                $product_id
            )
        );
        
        foreach ($orders_with_product as $order_data) {
            do_action('zoho_sync_orders_trigger_check', $order_data->order_id, 'product_change');
        }
    }
    
    /**
     * Handle cart item added
     *
     * @param string $cart_item_key Cart item key
     * @param int $product_id Product ID
     * @param int $quantity Quantity
     * @param int $variation_id Variation ID
     * @param array $variation Variation data
     * @param array $cart_item_data Cart item data
     */
    public function cart_item_added($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        // Store cart activity for potential order triggers
        $this->store_cart_activity('item_added', $product_id, $quantity);
    }
    
    /**
     * Handle cart item removed
     *
     * @param string $cart_item_key Cart item key
     * @param \WC_Cart $cart Cart object
     */
    public function cart_item_removed($cart_item_key, $cart) {
        // Store cart activity
        $this->store_cart_activity('item_removed', 0, 0);
    }
    
    /**
     * Store cart activity
     *
     * @param string $action Action type
     * @param int $product_id Product ID
     * @param int $quantity Quantity
     */
    private function store_cart_activity($action, $product_id, $quantity) {
        // Store in user session or transient for later use
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $activity = get_user_meta($user_id, '_zoho_cart_activity', true) ?: array();
            
            $activity[] = array(
                'action' => $action,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'timestamp' => time()
            );
            
            // Keep only last 10 activities
            $activity = array_slice($activity, -10);
            
            update_user_meta($user_id, '_zoho_cart_activity', $activity);
        }
    }
    
    /**
     * Handle coupon applied
     *
     * @param string $coupon_code Coupon code
     */
    public function coupon_applied($coupon_code) {
        // Store coupon activity
        $this->store_coupon_activity('applied', $coupon_code);
    }
    
    /**
     * Handle coupon removed
     *
     * @param string $coupon_code Coupon code
     */
    public function coupon_removed($coupon_code) {
        // Store coupon activity
        $this->store_coupon_activity('removed', $coupon_code);
    }
    
    /**
     * Store coupon activity
     *
     * @param string $action Action type
     * @param string $coupon_code Coupon code
     */
    private function store_coupon_activity($action, $coupon_code) {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $activity = get_user_meta($user_id, '_zoho_coupon_activity', true) ?: array();
            
            $activity[] = array(
                'action' => $action,
                'coupon_code' => $coupon_code,
                'timestamp' => time()
            );
            
            // Keep only last 5 activities
            $activity = array_slice($activity, -5);
            
            update_user_meta($user_id, '_zoho_coupon_activity', $activity);
        }
    }
    
    /**
     * Manual trigger sync via AJAX
     */
    public function manual_trigger_sync() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zoho_sync_orders_nonce')) {
            wp_die(__('Token de seguridad inválido', 'zoho-sync-orders'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $order_id = intval($_POST['order_id']);
        $trigger_type = sanitize_text_field($_POST['trigger_type']);
        
        try {
            do_action('zoho_sync_orders_trigger_check', $order_id, $trigger_type);
            
            wp_send_json(array(
                'success' => true,
                'message' => __('Trigger ejecutado correctamente', 'zoho-sync-orders')
            ));
            
        } catch (\Exception $e) {
            wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Test trigger conditions via AJAX
     */
    public function test_trigger_conditions() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zoho_sync_orders_nonce')) {
            wp_die(__('Token de seguridad inválido', 'zoho-sync-orders'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $order_id = intval($_POST['order_id']);
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new \Exception(__('Pedido no encontrado', 'zoho-sync-orders'));
            }
            
            $test_results = array(
                'total_threshold' => $this->check_total_threshold($order),
                'customer_type' => $this->check_customer_type($order),
                'product_categories' => $this->check_product_categories($order),
                'payment_method' => $this->check_payment_method($order),
                'shipping_method' => $this->check_shipping_method($order),
                'custom_fields' => $this->check_custom_fields($order),
                'time_conditions' => $this->check_time_conditions($order)
            );
            
            wp_send_json(array(
                'success' => true,
                'results' => $test_results,
                'should_sync' => in_array(true, $test_results)
            ));
            
        } catch (\Exception $e) {
            wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get sync record
     *
     * @param int $order_id Order ID
     * @return object|null
     */
    private function get_sync_record($order_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zoho_orders_sync WHERE order_id = %d",
                $order_id
            )
        );
    }
    
    /**
     * Cleanup old trigger logs
     */
    private function cleanup_trigger_logs() {
        // Clean up old cart and coupon activities
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} 
            WHERE meta_key IN ('_zoho_cart_activity', '_zoho_coupon_activity')
            AND meta_value LIKE '%timestamp%'
            AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(meta_value, 'timestamp', -1), ':', 2) AS UNSIGNED) < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))"
        );
    }
    
    /**
     * Get trigger statistics
     *
     * @return array Trigger statistics
     */
    public function get_trigger_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Count orders by trigger conditions
        $stats['total_orders'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'"
        );
        
        $stats['synced_orders'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync WHERE sync_status = 'completed'"
        );
        
        $stats['pending_orders'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync WHERE sync_status = 'pending'"
        );
        
        // Active trigger conditions
        $stats['active_conditions'] = array_filter($this->trigger_conditions, function($condition) {
            return !empty($condition);
        });
        
        return $stats;
    }
    
    /**
     * Update trigger conditions
     *
     * @param array $conditions New conditions
     * @return bool Success status
     */
    public function update_trigger_conditions($conditions) {
        $updated = true;
        
        foreach ($conditions as $key => $value) {
            $option_key = 'zoho_sync_orders_' . $key;
            $updated = $updated && update_option($option_key, $value);
        }
        
        if ($updated) {
            $this->load_trigger_conditions();
        }
        
        return $updated;
    }
    
    /**
     * Get trigger conditions
     *
     * @return array Trigger conditions
     */
    public function get_trigger_conditions() {
        return $this->trigger_conditions;
    }
}