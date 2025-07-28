<?php
/**
 * Orders Sync Class
 *
 * Handles the main synchronization logic between WooCommerce orders and Zoho
 *
 * @package ZohoSyncOrders
 * @subpackage Includes
 * @since 1.0.0
 */

namespace ZohoSyncOrders;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Orders synchronization handler
 */
class OrdersSync {
    
    /**
     * Order mapper instance
     *
     * @var OrderMapper
     */
    private $mapper;
    
    /**
     * Order validator instance
     *
     * @var OrderValidator
     */
    private $validator;
    
    /**
     * Zoho Orders API instance
     *
     * @var ZohoOrdersApi
     */
    private $api;
    
    /**
     * Status handler instance
     *
     * @var OrderStatusHandler
     */
    private $status_handler;
    
    /**
     * Retry manager instance
     *
     * @var RetryManager
     */
    private $retry_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->mapper = new OrderMapper();
        $this->validator = new OrderValidator();
        $this->api = new ZohoOrdersApi();
        $this->status_handler = new OrderStatusHandler();
        $this->retry_manager = new RetryManager();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Cron hooks for retry and cleanup
        add_action('zoho_sync_orders_retry_failed', array($this, 'retry_failed_orders'));
        add_action('zoho_sync_orders_cleanup', array($this, 'cleanup_old_records'));
        
        // Manual sync hooks
        add_action('wp_ajax_zoho_sync_order_manual', array($this, 'manual_sync_order'));
        add_action('wp_ajax_zoho_sync_orders_bulk', array($this, 'bulk_sync_orders'));
    }
    
    /**
     * Sync a single order to Zoho
     *
     * @param int $order_id WooCommerce order ID
     * @param string $sync_type Type of sync (create, update)
     * @return array Sync result
     */
    public function sync_order($order_id, $sync_type = 'create') {
        try {
            // Get WooCommerce order
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new \Exception(__('Pedido no encontrado', 'zoho-sync-orders'));
            }
            
            // Check if auto sync is enabled
            if (!$this->should_sync_order($order)) {
                return array(
                    'success' => false,
                    'message' => __('Pedido no cumple criterios de sincronización', 'zoho-sync-orders')
                );
            }
            
            // Validate order data
            $validation_result = $this->validator->validate_order($order);
            if (!$validation_result['valid']) {
                throw new \Exception($validation_result['message']);
            }
            
            // Map order data to Zoho format
            $zoho_data = $this->mapper->map_order_to_zoho($order);
            
            // Get existing sync record
            $sync_record = $this->get_sync_record($order_id);
            
            // Determine sync action
            if ($sync_record && $sync_record->zoho_id && $sync_type === 'update') {
                $result = $this->update_order_in_zoho($sync_record->zoho_id, $zoho_data);
            } else {
                $result = $this->create_order_in_zoho($zoho_data);
            }
            
            // Update sync record
            $this->update_sync_record($order_id, $result, $zoho_data);
            
            // Log success
            \ZohoSyncCore\Logger::log(
                sprintf(__('Pedido %d sincronizado exitosamente con Zoho ID: %s', 'zoho-sync-orders'), $order_id, $result['zoho_id']),
                'info',
                'orders'
            );
            
            return array(
                'success' => true,
                'message' => __('Pedido sincronizado correctamente', 'zoho-sync-orders'),
                'zoho_id' => $result['zoho_id']
            );
            
        } catch (\Exception $e) {
            // Handle sync failure
            $this->handle_sync_failure($order_id, $e->getMessage(), $sync_type);
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Create order in Zoho
     *
     * @param array $zoho_data Mapped order data
     * @return array API response
     */
    private function create_order_in_zoho($zoho_data) {
        $convert_to = get_option('zoho_sync_orders_convert_to', 'quote');
        
        if ($convert_to === 'quote') {
            return $this->api->create_quote($zoho_data);
        } else {
            return $this->api->create_sales_order($zoho_data);
        }
    }
    
    /**
     * Update order in Zoho
     *
     * @param string $zoho_id Zoho record ID
     * @param array $zoho_data Updated order data
     * @return array API response
     */
    private function update_order_in_zoho($zoho_id, $zoho_data) {
        $convert_to = get_option('zoho_sync_orders_convert_to', 'quote');
        
        if ($convert_to === 'quote') {
            return $this->api->update_quote($zoho_id, $zoho_data);
        } else {
            return $this->api->update_sales_order($zoho_id, $zoho_data);
        }
    }
    
    /**
     * Check if order should be synced
     *
     * @param \WC_Order $order WooCommerce order
     * @return bool
     */
    private function should_sync_order($order) {
        // Check if auto sync is enabled
        if (get_option('zoho_sync_orders_auto_sync', 'yes') !== 'yes') {
            return false;
        }
        
        // Check order status
        $sync_statuses = get_option('zoho_sync_orders_sync_status', array('processing', 'completed'));
        if (!in_array($order->get_status(), $sync_statuses)) {
            return false;
        }
        
        // Check if order has valid customer
        if (!$order->get_customer_id() && !$order->get_billing_email()) {
            return false;
        }
        
        // Check if order has items
        if (count($order->get_items()) === 0) {
            return false;
        }
        
        // Apply custom filters
        return apply_filters('zoho_sync_orders_should_sync', true, $order);
    }
    
    /**
     * Handle sync failure
     *
     * @param int $order_id Order ID
     * @param string $error_message Error message
     * @param string $sync_type Sync type
     */
    private function handle_sync_failure($order_id, $error_message, $sync_type) {
        // Get or create sync record
        $sync_record = $this->get_sync_record($order_id);
        
        if ($sync_record) {
            // Update existing record
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'zoho_orders_sync',
                array(
                    'sync_status' => 'failed',
                    'error_message' => $error_message,
                    'retry_count' => $sync_record->retry_count + 1,
                    'updated_at' => current_time('mysql')
                ),
                array('order_id' => $order_id),
                array('%s', '%s', '%d', '%s'),
                array('%d')
            );
        } else {
            // Create new record
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'zoho_orders_sync',
                array(
                    'order_id' => $order_id,
                    'sync_status' => 'failed',
                    'sync_type' => $sync_type,
                    'error_message' => $error_message,
                    'retry_count' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
            );
        }
        
        // Log error
        \ZohoSyncCore\Logger::log(
            sprintf(__('Error sincronizando pedido %d: %s', 'zoho-sync-orders'), $order_id, $error_message),
            'error',
            'orders'
        );
        
        // Schedule retry if within limits
        $max_retries = get_option('zoho_sync_orders_retry_attempts', 3);
        $current_retries = $sync_record ? $sync_record->retry_count + 1 : 1;
        
        if ($current_retries <= $max_retries) {
            $this->retry_manager->schedule_retry($order_id, $sync_type);
        }
    }
    
    /**
     * Update sync record
     *
     * @param int $order_id Order ID
     * @param array $result Sync result
     * @param array $zoho_data Zoho data
     */
    private function update_sync_record($order_id, $result, $zoho_data) {
        global $wpdb;
        
        $sync_record = $this->get_sync_record($order_id);
        
        $data = array(
            'zoho_id' => $result['zoho_id'],
            'sync_status' => 'completed',
            'last_sync' => current_time('mysql'),
            'error_message' => null,
            'zoho_data' => json_encode($zoho_data),
            'updated_at' => current_time('mysql')
        );
        
        if ($sync_record) {
            // Update existing record
            $wpdb->update(
                $wpdb->prefix . 'zoho_orders_sync',
                $data,
                array('order_id' => $order_id),
                array('%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new record
            $data['order_id'] = $order_id;
            $data['sync_type'] = 'create';
            $data['retry_count'] = 0;
            $data['created_at'] = current_time('mysql');
            
            $wpdb->insert(
                $wpdb->prefix . 'zoho_orders_sync',
                $data,
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
            );
        }
    }
    
    /**
     * Get sync record for order
     *
     * @param int $order_id Order ID
     * @return object|null Sync record
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
     * Retry failed orders
     */
    public function retry_failed_orders() {
        global $wpdb;
        
        $max_retries = get_option('zoho_sync_orders_retry_attempts', 3);
        $retry_interval = get_option('zoho_sync_orders_retry_interval', 300); // 5 minutes
        
        // Get failed orders ready for retry
        $failed_orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zoho_orders_sync 
                WHERE sync_status = 'failed' 
                AND retry_count < %d 
                AND updated_at < DATE_SUB(NOW(), INTERVAL %d SECOND)
                LIMIT 10",
                $max_retries,
                $retry_interval
            )
        );
        
        foreach ($failed_orders as $record) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Reintentando sincronización del pedido %d (intento %d)', 'zoho-sync-orders'), 
                    $record->order_id, $record->retry_count + 1),
                'info',
                'orders'
            );
            
            $this->sync_order($record->order_id, $record->sync_type);
        }
    }
    
    /**
     * Cleanup old sync records
     */
    public function cleanup_old_records() {
        global $wpdb;
        
        // Delete records older than 30 days
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}zoho_orders_sync 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        \ZohoSyncCore\Logger::log(
            __('Limpieza de registros antiguos de sincronización completada', 'zoho-sync-orders'),
            'info',
            'orders'
        );
    }
    
    /**
     * Manual sync order via AJAX
     */
    public function manual_sync_order() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zoho_sync_orders_nonce')) {
            wp_die(__('Token de seguridad inválido', 'zoho-sync-orders'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $order_id = intval($_POST['order_id']);
        $sync_type = sanitize_text_field($_POST['sync_type']);
        
        $result = $this->sync_order($order_id, $sync_type);
        
        wp_send_json($result);
    }
    
    /**
     * Bulk sync orders via AJAX
     */
    public function bulk_sync_orders() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zoho_sync_orders_nonce')) {
            wp_die(__('Token de seguridad inválido', 'zoho-sync-orders'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $order_ids = array_map('intval', $_POST['order_ids']);
        $results = array();
        
        foreach ($order_ids as $order_id) {
            $results[$order_id] = $this->sync_order($order_id);
        }
        
        wp_send_json(array(
            'success' => true,
            'results' => $results
        ));
    }
    
    /**
     * Get sync statistics
     *
     * @return array Statistics
     */
    public function get_sync_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total synced orders
        $stats['total_synced'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync WHERE sync_status = 'completed'"
        );
        
        // Failed orders
        $stats['failed'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync WHERE sync_status = 'failed'"
        );
        
        // Pending orders
        $stats['pending'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync WHERE sync_status = 'pending'"
        );
        
        // Last sync
        $stats['last_sync'] = $wpdb->get_var(
            "SELECT MAX(last_sync) FROM {$wpdb->prefix}zoho_orders_sync WHERE sync_status = 'completed'"
        );
        
        return $stats;
    }
    
    /**
     * Get orders sync status
     *
     * @param array $args Query arguments
     * @return array Orders with sync status
     */
    public function get_orders_sync_status($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'status' => '',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = "zos.sync_status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_clauses[] = "(p.post_title LIKE %s OR zos.zoho_id LIKE %s)";
            $where_values[] = '%' . $args['search'] . '%';
            $where_values[] = '%' . $args['search'] . '%';
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $query = "
            SELECT p.ID as order_id, p.post_title, p.post_date, p.post_status,
                   zos.zoho_id, zos.sync_status, zos.last_sync, zos.error_message, zos.retry_count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}zoho_orders_sync zos ON p.ID = zos.order_id
            WHERE p.post_type = 'shop_order'
            {$where_sql}
            ORDER BY p.post_date DESC
            LIMIT %d OFFSET %d
        ";
        
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
}