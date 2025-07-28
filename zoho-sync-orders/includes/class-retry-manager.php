<?php
/**
 * Retry Manager Class
 *
 * Handles automatic retry logic for failed order synchronizations
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
 * Retry manager for failed synchronizations
 */
class RetryManager {
    
    /**
     * Maximum retry attempts
     *
     * @var int
     */
    private $max_retries;
    
    /**
     * Retry intervals in seconds
     *
     * @var array
     */
    private $retry_intervals = array(
        1 => 300,   // 5 minutes
        2 => 900,   // 15 minutes
        3 => 3600,  // 1 hour
        4 => 7200,  // 2 hours
        5 => 21600  // 6 hours
    );
    
    /**
     * Retry strategies
     *
     * @var array
     */
    private $retry_strategies = array(
        'exponential' => 'Exponential Backoff',
        'linear' => 'Linear Backoff',
        'fixed' => 'Fixed Interval'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->max_retries = get_option('zoho_sync_orders_retry_attempts', 3);
        $this->load_custom_intervals();
        $this->init_hooks();
    }
    
    /**
     * Load custom retry intervals from options
     */
    private function load_custom_intervals() {
        $custom_intervals = get_option('zoho_sync_orders_retry_intervals', array());
        if (!empty($custom_intervals)) {
            $this->retry_intervals = array_merge($this->retry_intervals, $custom_intervals);
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Cron hook for processing retries
        add_action('zoho_sync_orders_process_retries', array($this, 'process_retry_queue'));
        
        // Hook for scheduling retries
        add_action('zoho_sync_orders_schedule_retry', array($this, 'schedule_retry_hook'), 10, 3);
        
        // Admin hooks for manual retry management
        add_action('wp_ajax_zoho_retry_failed_order', array($this, 'manual_retry_order'));
        add_action('wp_ajax_zoho_clear_retry_queue', array($this, 'clear_retry_queue'));
        add_action('wp_ajax_zoho_get_retry_stats', array($this, 'get_retry_stats_ajax'));
    }
    
    /**
     * Schedule a retry for a failed order
     *
     * @param int $order_id Order ID
     * @param string $sync_type Sync type (create, update)
     * @param string $error_message Error message
     * @return bool Success status
     */
    public function schedule_retry($order_id, $sync_type = 'create', $error_message = '') {
        try {
            // Get current retry count
            $retry_count = $this->get_retry_count($order_id);
            
            // Check if max retries exceeded
            if ($retry_count >= $this->max_retries) {
                $this->mark_as_permanently_failed($order_id, $error_message);
                return false;
            }
            
            // Calculate next retry time
            $next_retry = $this->calculate_next_retry_time($retry_count + 1);
            
            // Update retry record
            $this->update_retry_record($order_id, $retry_count + 1, $next_retry, $error_message);
            
            // Schedule the retry
            $this->schedule_retry_event($order_id, $sync_type, $next_retry);
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Reintento programado para pedido %d (intento %d de %d) en %s', 'zoho-sync-orders'), 
                    $order_id, $retry_count + 1, $this->max_retries, date('Y-m-d H:i:s', $next_retry)),
                'info',
                'orders'
            );
            
            return true;
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error programando reintento para pedido %d: %s', 'zoho-sync-orders'), $order_id, $e->getMessage()),
                'error',
                'orders'
            );
            
            return false;
        }
    }
    
    /**
     * Process retry queue
     */
    public function process_retry_queue() {
        try {
            // Get orders ready for retry
            $retry_orders = $this->get_orders_ready_for_retry();
            
            if (empty($retry_orders)) {
                return;
            }
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Procesando %d pedidos en cola de reintentos', 'zoho-sync-orders'), count($retry_orders)),
                'info',
                'orders'
            );
            
            foreach ($retry_orders as $retry_order) {
                $this->process_single_retry($retry_order);
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error procesando cola de reintentos: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Process a single retry
     *
     * @param object $retry_order Retry order record
     */
    private function process_single_retry($retry_order) {
        try {
            $order_id = $retry_order->order_id;
            $sync_type = $retry_order->sync_type;
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Procesando reintento para pedido %d (intento %d)', 'zoho-sync-orders'), 
                    $order_id, $retry_order->retry_count),
                'info',
                'orders'
            );
            
            // Get orders sync instance
            $orders_sync = new OrdersSync();
            
            // Attempt sync
            $result = $orders_sync->sync_order($order_id, $sync_type);
            
            if ($result['success']) {
                // Retry successful, clear retry record
                $this->clear_retry_record($order_id);
                
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Reintento exitoso para pedido %d', 'zoho-sync-orders'), $order_id),
                    'info',
                    'orders'
                );
            } else {
                // Retry failed, schedule next retry or mark as permanently failed
                $this->handle_retry_failure($order_id, $sync_type, $result['message']);
            }
            
        } catch (\Exception $e) {
            // Handle retry exception
            $this->handle_retry_failure($retry_order->order_id, $retry_order->sync_type, $e->getMessage());
        }
    }
    
    /**
     * Handle retry failure
     *
     * @param int $order_id Order ID
     * @param string $sync_type Sync type
     * @param string $error_message Error message
     */
    private function handle_retry_failure($order_id, $sync_type, $error_message) {
        $retry_count = $this->get_retry_count($order_id);
        
        if ($retry_count >= $this->max_retries) {
            // Max retries reached, mark as permanently failed
            $this->mark_as_permanently_failed($order_id, $error_message);
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Pedido %d marcado como fallido permanentemente después de %d intentos', 'zoho-sync-orders'), 
                    $order_id, $retry_count),
                'error',
                'orders'
            );
        } else {
            // Schedule next retry
            $this->schedule_retry($order_id, $sync_type, $error_message);
        }
    }
    
    /**
     * Calculate next retry time
     *
     * @param int $attempt_number Attempt number
     * @return int Timestamp for next retry
     */
    private function calculate_next_retry_time($attempt_number) {
        $strategy = get_option('zoho_sync_orders_retry_strategy', 'exponential');
        $base_interval = get_option('zoho_sync_orders_retry_base_interval', 300); // 5 minutes
        
        switch ($strategy) {
            case 'exponential':
                $interval = $base_interval * pow(2, $attempt_number - 1);
                break;
                
            case 'linear':
                $interval = $base_interval * $attempt_number;
                break;
                
            case 'fixed':
                $interval = $base_interval;
                break;
                
            default:
                // Use predefined intervals
                $interval = isset($this->retry_intervals[$attempt_number]) 
                    ? $this->retry_intervals[$attempt_number] 
                    : $this->retry_intervals[count($this->retry_intervals)];
        }
        
        // Add some jitter to prevent thundering herd
        $jitter = rand(0, 60); // 0-60 seconds
        
        return time() + $interval + $jitter;
    }
    
    /**
     * Schedule retry event
     *
     * @param int $order_id Order ID
     * @param string $sync_type Sync type
     * @param int $retry_time Retry timestamp
     */
    private function schedule_retry_event($order_id, $sync_type, $retry_time) {
        // Clear any existing scheduled retry for this order
        $this->clear_scheduled_retry($order_id);
        
        // Schedule new retry
        wp_schedule_single_event(
            $retry_time,
            'zoho_sync_orders_schedule_retry',
            array($order_id, $sync_type, $retry_time)
        );
    }
    
    /**
     * Clear scheduled retry for order
     *
     * @param int $order_id Order ID
     */
    private function clear_scheduled_retry($order_id) {
        // Get all scheduled events for this hook
        $scheduled = wp_get_scheduled_event('zoho_sync_orders_schedule_retry', array($order_id));
        
        if ($scheduled) {
            wp_unschedule_event($scheduled->timestamp, 'zoho_sync_orders_schedule_retry', array($order_id));
        }
    }
    
    /**
     * Get orders ready for retry
     *
     * @return array Orders ready for retry
     */
    private function get_orders_ready_for_retry() {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT zos.*, p.post_title 
                FROM {$wpdb->prefix}zoho_orders_sync zos
                LEFT JOIN {$wpdb->posts} p ON zos.order_id = p.ID
                WHERE zos.sync_status = 'failed' 
                AND zos.retry_count < %d 
                AND zos.next_retry_at <= NOW()
                AND zos.permanently_failed = 0
                ORDER BY zos.next_retry_at ASC
                LIMIT 10",
                $this->max_retries
            )
        );
    }
    
    /**
     * Get retry count for order
     *
     * @param int $order_id Order ID
     * @return int Retry count
     */
    private function get_retry_count($order_id) {
        global $wpdb;
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT retry_count FROM {$wpdb->prefix}zoho_orders_sync WHERE order_id = %d",
                $order_id
            )
        );
        
        return $count ? intval($count) : 0;
    }
    
    /**
     * Update retry record
     *
     * @param int $order_id Order ID
     * @param int $retry_count Retry count
     * @param int $next_retry Next retry timestamp
     * @param string $error_message Error message
     */
    private function update_retry_record($order_id, $retry_count, $next_retry, $error_message) {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}zoho_orders_sync 
                SET retry_count = %d, 
                    next_retry_at = %s, 
                    error_message = %s,
                    updated_at = NOW()
                WHERE order_id = %d",
                $retry_count,
                date('Y-m-d H:i:s', $next_retry),
                $error_message,
                $order_id
            )
        );
    }
    
    /**
     * Clear retry record
     *
     * @param int $order_id Order ID
     */
    private function clear_retry_record($order_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'zoho_orders_sync',
            array(
                'retry_count' => 0,
                'next_retry_at' => null,
                'error_message' => null,
                'permanently_failed' => 0,
                'updated_at' => current_time('mysql')
            ),
            array('order_id' => $order_id),
            array('%d', '%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        // Clear scheduled retry
        $this->clear_scheduled_retry($order_id);
    }
    
    /**
     * Mark order as permanently failed
     *
     * @param int $order_id Order ID
     * @param string $error_message Final error message
     */
    private function mark_as_permanently_failed($order_id, $error_message) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'zoho_orders_sync',
            array(
                'sync_status' => 'permanently_failed',
                'permanently_failed' => 1,
                'error_message' => $error_message,
                'failed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('order_id' => $order_id),
            array('%s', '%d', '%s', '%s', '%s'),
            array('%d')
        );
        
        // Clear scheduled retry
        $this->clear_scheduled_retry($order_id);
        
        // Send notification if enabled
        $this->send_failure_notification($order_id, $error_message);
    }
    
    /**
     * Send failure notification
     *
     * @param int $order_id Order ID
     * @param string $error_message Error message
     */
    private function send_failure_notification($order_id, $error_message) {
        if (get_option('zoho_sync_orders_failure_notifications', 'no') !== 'yes') {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(__('[%s] Error permanente en sincronización de pedido #%s', 'zoho-sync-orders'), 
            $site_name, $order->get_order_number());
        
        $message = sprintf(
            __('El pedido #%s ha fallado permanentemente en la sincronización con Zoho después de %d intentos.

Detalles del pedido:
- ID: %d
- Número: %s
- Cliente: %s
- Total: %s
- Fecha: %s

Error: %s

Por favor, revise la configuración de Zoho y reintente manualmente si es necesario.', 'zoho-sync-orders'),
            $order->get_order_number(),
            $this->max_retries,
            $order_id,
            $order->get_order_number(),
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            wc_price($order->get_total()),
            $order->get_date_created()->format('Y-m-d H:i:s'),
            $error_message
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Manual retry order via AJAX
     */
    public function manual_retry_order() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zoho_sync_orders_nonce')) {
            wp_die(__('Token de seguridad inválido', 'zoho-sync-orders'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $order_id = intval($_POST['order_id']);
        $reset_count = isset($_POST['reset_count']) && $_POST['reset_count'] === 'true';
        
        try {
            if ($reset_count) {
                // Reset retry count
                $this->clear_retry_record($order_id);
            }
            
            // Get orders sync instance
            $orders_sync = new OrdersSync();
            
            // Attempt sync
            $result = $orders_sync->sync_order($order_id);
            
            if ($result['success']) {
                $this->clear_retry_record($order_id);
            }
            
            wp_send_json($result);
            
        } catch (\Exception $e) {
            wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Clear retry queue via AJAX
     */
    public function clear_retry_queue() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zoho_sync_orders_nonce')) {
            wp_die(__('Token de seguridad inválido', 'zoho-sync-orders'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        try {
            global $wpdb;
            
            // Clear all retry records
            $cleared = $wpdb->query(
                "UPDATE {$wpdb->prefix}zoho_orders_sync 
                SET retry_count = 0, 
                    next_retry_at = NULL, 
                    error_message = NULL,
                    permanently_failed = 0,
                    updated_at = NOW()
                WHERE sync_status IN ('failed', 'permanently_failed')"
            );
            
            // Clear all scheduled retry events
            wp_clear_scheduled_hook('zoho_sync_orders_schedule_retry');
            
            wp_send_json(array(
                'success' => true,
                'message' => sprintf(__('Se limpiaron %d registros de la cola de reintentos', 'zoho-sync-orders'), $cleared)
            ));
            
        } catch (\Exception $e) {
            wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get retry statistics via AJAX
     */
    public function get_retry_stats_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zoho_sync_orders_nonce')) {
            wp_die(__('Token de seguridad inválido', 'zoho-sync-orders'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $stats = $this->get_retry_statistics();
        wp_send_json($stats);
    }
    
    /**
     * Get retry statistics
     *
     * @return array Retry statistics
     */
    public function get_retry_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total failed orders
        $stats['total_failed'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync WHERE sync_status = 'failed'"
        );
        
        // Permanently failed orders
        $stats['permanently_failed'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync WHERE permanently_failed = 1"
        );
        
        // Orders in retry queue
        $stats['in_retry_queue'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync 
                WHERE sync_status = 'failed' 
                AND retry_count < %d 
                AND permanently_failed = 0",
                $this->max_retries
            )
        );
        
        // Next retry time
        $stats['next_retry'] = $wpdb->get_var(
            "SELECT MIN(next_retry_at) FROM {$wpdb->prefix}zoho_orders_sync 
            WHERE sync_status = 'failed' 
            AND next_retry_at IS NOT NULL 
            AND permanently_failed = 0"
        );
        
        // Retry attempts by count
        $stats['retry_distribution'] = $wpdb->get_results(
            "SELECT retry_count, COUNT(*) as count 
            FROM {$wpdb->prefix}zoho_orders_sync 
            WHERE sync_status = 'failed' 
            GROUP BY retry_count 
            ORDER BY retry_count"
        );
        
        // Recent failures
        $stats['recent_failures'] = $wpdb->get_results(
            "SELECT zos.order_id, zos.retry_count, zos.error_message, zos.updated_at, p.post_title
            FROM {$wpdb->prefix}zoho_orders_sync zos
            LEFT JOIN {$wpdb->posts} p ON zos.order_id = p.ID
            WHERE zos.sync_status = 'failed'
            ORDER BY zos.updated_at DESC
            LIMIT 10"
        );
        
        return $stats;
    }
    
    /**
     * Schedule retry hook handler
     *
     * @param int $order_id Order ID
     * @param string $sync_type Sync type
     * @param int $retry_time Retry time
     */
    public function schedule_retry_hook($order_id, $sync_type, $retry_time) {
        // Check if retry is still needed
        $sync_record = $this->get_sync_record($order_id);
        
        if (!$sync_record || $sync_record->sync_status !== 'failed' || $sync_record->permanently_failed) {
            return; // No longer needs retry
        }
        
        // Process the retry
        $this->process_single_retry($sync_record);
    }
    
    /**
     * Get sync record
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
     * Get retry configuration
     *
     * @return array Retry configuration
     */
    public function get_retry_config() {
        return array(
            'max_retries' => $this->max_retries,
            'retry_intervals' => $this->retry_intervals,
            'retry_strategies' => $this->retry_strategies,
            'current_strategy' => get_option('zoho_sync_orders_retry_strategy', 'exponential'),
            'base_interval' => get_option('zoho_sync_orders_retry_base_interval', 300),
            'failure_notifications' => get_option('zoho_sync_orders_failure_notifications', 'no')
        );
    }
    
    /**
     * Update retry configuration
     *
     * @param array $config New configuration
     * @return bool Success status
     */
    public function update_retry_config($config) {
        $updated = true;
        
        if (isset($config['max_retries'])) {
            $this->max_retries = intval($config['max_retries']);
            $updated = $updated && update_option('zoho_sync_orders_retry_attempts', $this->max_retries);
        }
        
        if (isset($config['retry_intervals'])) {
            $this->retry_intervals = $config['retry_intervals'];
            $updated = $updated && update_option('zoho_sync_orders_retry_intervals', $this->retry_intervals);
        }
        
        if (isset($config['retry_strategy'])) {
            $updated = $updated && update_option('zoho_sync_orders_retry_strategy', $config['retry_strategy']);
        }
        
        if (isset($config['base_interval'])) {
            $updated = $updated && update_option('zoho_sync_orders_retry_base_interval', intval($config['base_interval']));
        }
        
        if (isset($config['failure_notifications'])) {
            $updated = $updated && update_option('zoho_sync_orders_failure_notifications', $config['failure_notifications']);
        }
        
        return $updated;
    }
}