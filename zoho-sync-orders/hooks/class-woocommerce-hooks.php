<?php
/**
 * WooCommerce Hooks Class
 *
 * Handles WooCommerce hooks for automatic order synchronization
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
 * WooCommerce hooks handler
 */
class WooCommerceHooks {
    
    /**
     * Orders sync instance
     *
     * @var \ZohoSyncOrders\OrdersSync
     */
    private $orders_sync;
    
    /**
     * Processed orders to avoid duplicate syncs
     *
     * @var array
     */
    private $processed_orders = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->orders_sync = new \ZohoSyncOrders\OrdersSync();
        $this->init_hooks();
    }
    
    /**
     * Initialize WooCommerce hooks
     */
    private function init_hooks() {
        // Order creation and status change hooks
        add_action('woocommerce_new_order', array($this, 'handle_new_order'), 10, 2);
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // Order update hooks
        add_action('woocommerce_process_shop_order_meta', array($this, 'handle_order_update'), 20, 2);
        add_action('woocommerce_update_order', array($this, 'handle_order_update_action'), 10, 1);
        
        // Order item hooks
        add_action('woocommerce_order_item_added', array($this, 'handle_order_item_change'), 10, 3);
        add_action('woocommerce_order_item_removed', array($this, 'handle_order_item_change'), 10, 1);
        add_action('woocommerce_order_item_updated', array($this, 'handle_order_item_change'), 10, 3);
        
        // Payment hooks
        add_action('woocommerce_payment_complete', array($this, 'handle_payment_complete'), 10, 1);
        add_action('woocommerce_order_refunded', array($this, 'handle_order_refund'), 10, 2);
        
        // Checkout hooks
        add_action('woocommerce_checkout_order_processed', array($this, 'handle_checkout_processed'), 10, 3);
        
        // Admin order hooks
        add_action('woocommerce_process_shop_order_meta', array($this, 'handle_admin_order_save'), 10, 2);
        
        // Bulk actions
        add_action('woocommerce_order_bulk_action', array($this, 'handle_bulk_action'), 10, 3);
        
        // REST API hooks
        add_action('woocommerce_rest_insert_shop_order_object', array($this, 'handle_rest_order_create'), 10, 3);
        add_action('woocommerce_rest_update_shop_order_object', array($this, 'handle_rest_order_update'), 10, 3);
        
        // Cleanup hook
        add_action('shutdown', array($this, 'process_pending_syncs'));
    }
    
    /**
     * Handle new order creation
     *
     * @param int $order_id Order ID
     * @param \WC_Order $order Order object
     */
    public function handle_new_order($order_id, $order = null) {
        if ($this->should_skip_sync($order_id, 'new_order')) {
            return;
        }
        
        try {
            // Get order if not provided
            if (!$order) {
                $order = wc_get_order($order_id);
            }
            
            if (!$order) {
                return;
            }
            
            // Check if order should be synced immediately
            if ($this->should_sync_immediately($order)) {
                $this->schedule_sync($order_id, 'create', 'new_order');
            } else {
                // Mark for potential future sync
                $this->mark_for_sync($order_id, 'pending');
            }
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Nuevo pedido detectado: %d', 'zoho-sync-orders'), $order_id),
                'info',
                'orders'
            );
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error manejando nuevo pedido %d: %s', 'zoho-sync-orders'), $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Handle order status change
     *
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param \WC_Order $order Order object
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        if ($this->should_skip_sync($order_id, 'status_change')) {
            return;
        }
        
        try {
            // Check if new status should trigger sync
            if ($this->should_sync_on_status($new_status)) {
                // Check if order is already synced
                $sync_record = $this->get_sync_record($order_id);
                
                if ($sync_record && $sync_record->zoho_id) {
                    // Update existing record
                    $this->schedule_sync($order_id, 'update', 'status_change');
                } else {
                    // Create new record
                    $this->schedule_sync($order_id, 'create', 'status_change');
                }
            }
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Cambio de estado detectado para pedido %d: %s -> %s', 'zoho-sync-orders'), 
                    $order_id, $old_status, $new_status),
                'info',
                'orders'
            );
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error manejando cambio de estado para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Handle order update from admin
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     */
    public function handle_order_update($post_id, $post = null) {
        // Only process shop_order post type
        if ($post && $post->post_type !== 'shop_order') {
            return;
        }
        
        if ($this->should_skip_sync($post_id, 'order_update')) {
            return;
        }
        
        try {
            $order = wc_get_order($post_id);
            if (!$order) {
                return;
            }
            
            // Check if order should be synced
            if ($this->should_sync_order($order)) {
                $sync_record = $this->get_sync_record($post_id);
                $sync_type = ($sync_record && $sync_record->zoho_id) ? 'update' : 'create';
                
                $this->schedule_sync($post_id, $sync_type, 'admin_update');
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error manejando actualización de pedido %d: %s', 'zoho-sync-orders'), 
                    $post_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Handle order update action
     *
     * @param int $order_id Order ID
     */
    public function handle_order_update_action($order_id) {
        if ($this->should_skip_sync($order_id, 'update_action')) {
            return;
        }
        
        // Defer sync to avoid multiple triggers
        $this->defer_sync($order_id, 'update', 'update_action');
    }
    
    /**
     * Handle order item changes
     *
     * @param int $item_id Item ID
     * @param \WC_Order_Item $item Item object (optional)
     * @param int $order_id Order ID (optional)
     */
    public function handle_order_item_change($item_id, $item = null, $order_id = null) {
        // Get order ID if not provided
        if (!$order_id && $item) {
            $order_id = $item->get_order_id();
        }
        
        if (!$order_id || $this->should_skip_sync($order_id, 'item_change')) {
            return;
        }
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            
            // Only sync if order is in syncable status
            if ($this->should_sync_order($order)) {
                $this->defer_sync($order_id, 'update', 'item_change');
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error manejando cambio de item para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Handle payment completion
     *
     * @param int $order_id Order ID
     */
    public function handle_payment_complete($order_id) {
        if ($this->should_skip_sync($order_id, 'payment_complete')) {
            return;
        }
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            
            // Payment completion usually triggers status change, so defer sync
            $this->defer_sync($order_id, 'update', 'payment_complete');
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Pago completado para pedido %d', 'zoho-sync-orders'), $order_id),
                'info',
                'orders'
            );
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error manejando pago completado para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Handle order refund
     *
     * @param int $order_id Order ID
     * @param int $refund_id Refund ID
     */
    public function handle_order_refund($order_id, $refund_id) {
        if ($this->should_skip_sync($order_id, 'refund')) {
            return;
        }
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            
            // Schedule sync to update refund information
            $this->schedule_sync($order_id, 'update', 'refund');
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Reembolso procesado para pedido %d', 'zoho-sync-orders'), $order_id),
                'info',
                'orders'
            );
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error manejando reembolso para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Handle checkout processed
     *
     * @param int $order_id Order ID
     * @param array $posted_data Posted data
     * @param \WC_Order $order Order object
     */
    public function handle_checkout_processed($order_id, $posted_data, $order) {
        if ($this->should_skip_sync($order_id, 'checkout_processed')) {
            return;
        }
        
        try {
            // Defer sync to allow order to be fully processed
            $this->defer_sync($order_id, 'create', 'checkout_processed', 30); // 30 second delay
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error manejando checkout procesado para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Handle admin order save
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     */
    public function handle_admin_order_save($post_id, $post) {
        // Only process if this is an admin save
        if (!is_admin() || !current_user_can('edit_shop_orders')) {
            return;
        }
        
        $this->handle_order_update($post_id, $post);
    }
    
    /**
     * Handle bulk actions
     *
     * @param string $action Action name
     * @param array $order_ids Order IDs
     * @param string $redirect_to Redirect URL
     */
    public function handle_bulk_action($action, $order_ids, $redirect_to) {
        // Only handle status change bulk actions
        if (strpos($action, 'mark_') !== 0) {
            return;
        }
        
        try {
            foreach ($order_ids as $order_id) {
                if (!$this->should_skip_sync($order_id, 'bulk_action')) {
                    $this->defer_sync($order_id, 'update', 'bulk_action');
                }
            }
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Acción masiva procesada: %s para %d pedidos', 'zoho-sync-orders'), 
                    $action, count($order_ids)),
                'info',
                'orders'
            );
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error manejando acción masiva: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Handle REST API order creation
     *
     * @param \WC_Order $order Order object
     * @param \WP_REST_Request $request Request object
     * @param bool $creating Whether creating or updating
     */
    public function handle_rest_order_create($order, $request, $creating) {
        if (!$creating) {
            return;
        }
        
        $order_id = $order->get_id();
        
        if ($this->should_skip_sync($order_id, 'rest_create')) {
            return;
        }
        
        try {
            // Defer sync to allow order to be fully processed
            $this->defer_sync($order_id, 'create', 'rest_create', 10);
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error manejando creación REST para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Handle REST API order update
     *
     * @param \WC_Order $order Order object
     * @param \WP_REST_Request $request Request object
     * @param bool $creating Whether creating or updating
     */
    public function handle_rest_order_update($order, $request, $creating) {
        if ($creating) {
            return;
        }
        
        $order_id = $order->get_id();
        
        if ($this->should_skip_sync($order_id, 'rest_update')) {
            return;
        }
        
        try {
            $this->defer_sync($order_id, 'update', 'rest_update');
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error manejando actualización REST para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Check if sync should be skipped
     *
     * @param int $order_id Order ID
     * @param string $trigger Trigger type
     * @return bool
     */
    private function should_skip_sync($order_id, $trigger) {
        // Check if auto sync is disabled
        if (get_option('zoho_sync_orders_auto_sync', 'yes') !== 'yes') {
            return true;
        }
        
        // Check if already processed in this request
        $key = $order_id . '_' . $trigger;
        if (isset($this->processed_orders[$key])) {
            return true;
        }
        
        // Mark as processed
        $this->processed_orders[$key] = true;
        
        // Check if order exists
        $order = wc_get_order($order_id);
        if (!$order) {
            return true;
        }
        
        // Apply filters
        return apply_filters('zoho_sync_orders_should_skip_sync', false, $order_id, $trigger, $order);
    }
    
    /**
     * Check if order should be synced immediately
     *
     * @param \WC_Order $order Order object
     * @return bool
     */
    private function should_sync_immediately($order) {
        $immediate_statuses = get_option('zoho_sync_orders_immediate_sync_statuses', array('processing', 'completed'));
        return in_array($order->get_status(), $immediate_statuses);
    }
    
    /**
     * Check if order should be synced
     *
     * @param \WC_Order $order Order object
     * @return bool
     */
    private function should_sync_order($order) {
        $sync_statuses = get_option('zoho_sync_orders_sync_status', array('processing', 'completed'));
        return in_array($order->get_status(), $sync_statuses);
    }
    
    /**
     * Check if status should trigger sync
     *
     * @param string $status Order status
     * @return bool
     */
    private function should_sync_on_status($status) {
        $sync_statuses = get_option('zoho_sync_orders_sync_status', array('processing', 'completed'));
        return in_array($status, $sync_statuses);
    }
    
    /**
     * Schedule immediate sync
     *
     * @param int $order_id Order ID
     * @param string $sync_type Sync type
     * @param string $trigger Trigger type
     */
    private function schedule_sync($order_id, $sync_type, $trigger) {
        // Use WordPress cron to schedule immediate sync
        wp_schedule_single_event(
            time() + 5, // 5 second delay
            'zoho_sync_orders_immediate_sync',
            array($order_id, $sync_type, $trigger)
        );
        
        // Add hook handler if not already added
        if (!has_action('zoho_sync_orders_immediate_sync')) {
            add_action('zoho_sync_orders_immediate_sync', array($this, 'process_immediate_sync'), 10, 3);
        }
    }
    
    /**
     * Defer sync for later processing
     *
     * @param int $order_id Order ID
     * @param string $sync_type Sync type
     * @param string $trigger Trigger type
     * @param int $delay Delay in seconds
     */
    private function defer_sync($order_id, $sync_type, $trigger, $delay = 60) {
        // Store in transient for later processing
        $deferred_syncs = get_transient('zoho_sync_orders_deferred') ?: array();
        
        $deferred_syncs[$order_id] = array(
            'sync_type' => $sync_type,
            'trigger' => $trigger,
            'scheduled_at' => time() + $delay
        );
        
        set_transient('zoho_sync_orders_deferred', $deferred_syncs, HOUR_IN_SECONDS);
    }
    
    /**
     * Mark order for potential sync
     *
     * @param int $order_id Order ID
     * @param string $status Status
     */
    private function mark_for_sync($order_id, $status) {
        global $wpdb;
        
        // Check if record exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}zoho_orders_sync WHERE order_id = %d",
                $order_id
            )
        );
        
        if (!$exists) {
            // Create new record
            $wpdb->insert(
                $wpdb->prefix . 'zoho_orders_sync',
                array(
                    'order_id' => $order_id,
                    'sync_status' => $status,
                    'sync_type' => 'create',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
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
     * Process immediate sync
     *
     * @param int $order_id Order ID
     * @param string $sync_type Sync type
     * @param string $trigger Trigger type
     */
    public function process_immediate_sync($order_id, $sync_type, $trigger) {
        try {
            $result = $this->orders_sync->sync_order($order_id, $sync_type);
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Sincronización inmediata procesada para pedido %d (trigger: %s): %s', 'zoho-sync-orders'), 
                    $order_id, $trigger, $result['success'] ? 'exitosa' : 'fallida'),
                $result['success'] ? 'info' : 'error',
                'orders'
            );
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error en sincronización inmediata para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Process pending syncs on shutdown
     */
    public function process_pending_syncs() {
        $deferred_syncs = get_transient('zoho_sync_orders_deferred');
        
        if (!$deferred_syncs) {
            return;
        }
        
        $current_time = time();
        $processed = array();
        
        foreach ($deferred_syncs as $order_id => $sync_data) {
            if ($sync_data['scheduled_at'] <= $current_time) {
                // Process this sync
                try {
                    $this->orders_sync->sync_order($order_id, $sync_data['sync_type']);
                    $processed[] = $order_id;
                } catch (\Exception $e) {
                    // Log error but continue processing
                    \ZohoSyncCore\Logger::log(
                        sprintf(__('Error procesando sincronización diferida para pedido %d: %s', 'zoho-sync-orders'), 
                            $order_id, $e->getMessage()),
                        'error',
                        'orders'
                    );
                }
            }
        }
        
        // Remove processed syncs
        foreach ($processed as $order_id) {
            unset($deferred_syncs[$order_id]);
        }
        
        // Update transient
        if (!empty($deferred_syncs)) {
            set_transient('zoho_sync_orders_deferred', $deferred_syncs, HOUR_IN_SECONDS);
        } else {
            delete_transient('zoho_sync_orders_deferred');
        }
    }
    
    /**
     * Get hook statistics
     *
     * @return array Hook statistics
     */
    public function get_hook_stats() {
        return array(
            'processed_orders' => count($this->processed_orders),
            'deferred_syncs' => count(get_transient('zoho_sync_orders_deferred') ?: array()),
            'auto_sync_enabled' => get_option('zoho_sync_orders_auto_sync', 'yes') === 'yes',
            'sync_statuses' => get_option('zoho_sync_orders_sync_status', array()),
            'immediate_sync_statuses' => get_option('zoho_sync_orders_immediate_sync_statuses', array())
        );
    }
    
    /**
     * Clear processed orders cache
     */
    public function clear_processed_cache() {
        $this->processed_orders = array();
    }
    
    /**
     * Force sync order
     *
     * @param int $order_id Order ID
     * @param string $sync_type Sync type
     * @return array Sync result
     */
    public function force_sync_order($order_id, $sync_type = 'create') {
        // Remove from processed cache
        foreach ($this->processed_orders as $key => $value) {
            if (strpos($key, $order_id . '_') === 0) {
                unset($this->processed_orders[$key]);
            }
        }
        
        // Perform sync
        return $this->orders_sync->sync_order($order_id, $sync_type);
    }
}