<?php
/**
 * Order Status Handler Class
 *
 * Manages order status synchronization between WooCommerce and Zoho
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
 * Order status handler
 */
class OrderStatusHandler {
    
    /**
     * Status mapping between WooCommerce and Zoho
     *
     * @var array
     */
    private $status_mapping = array(
        'woo_to_zoho' => array(
            'pending' => 'Draft',
            'processing' => 'Negotiation',
            'on-hold' => 'Negotiation',
            'completed' => 'Closed Won',
            'cancelled' => 'Closed Lost',
            'refunded' => 'Closed Lost',
            'failed' => 'Closed Lost'
        ),
        'zoho_to_woo' => array(
            'Draft' => 'pending',
            'Negotiation' => 'processing',
            'Delivered' => 'completed',
            'Closed Won' => 'completed',
            'Closed Lost' => 'cancelled',
            'Cancelled' => 'cancelled'
        )
    );
    
    /**
     * Zoho API instance
     *
     * @var ZohoOrdersApi
     */
    private $api;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new ZohoOrdersApi();
        $this->load_custom_mappings();
        $this->init_hooks();
    }
    
    /**
     * Load custom status mappings from options
     */
    private function load_custom_mappings() {
        $custom_mapping = get_option('zoho_sync_orders_status_mapping', array());
        if (!empty($custom_mapping)) {
            $this->status_mapping = array_merge_recursive($this->status_mapping, $custom_mapping);
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // WooCommerce status change hooks
        add_action('woocommerce_order_status_changed', array($this, 'handle_woo_status_change'), 10, 4);
        
        // Zoho webhook for status updates
        add_action('zoho_webhook_quote_updated', array($this, 'handle_zoho_status_change'), 10, 2);
        add_action('zoho_webhook_salesorder_updated', array($this, 'handle_zoho_status_change'), 10, 2);
        
        // Manual status sync
        add_action('wp_ajax_zoho_sync_order_status', array($this, 'manual_status_sync'));
    }
    
    /**
     * Handle WooCommerce order status change
     *
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @param \WC_Order $order Order object
     */
    public function handle_woo_status_change($order_id, $old_status, $new_status, $order) {
        try {
            // Check if auto sync is enabled
            if (get_option('zoho_sync_orders_auto_status_sync', 'yes') !== 'yes') {
                return;
            }
            
            // Check if this status should be synced
            if (!$this->should_sync_status($new_status)) {
                return;
            }
            
            // Get sync record
            $sync_record = $this->get_sync_record($order_id);
            if (!$sync_record || !$sync_record->zoho_id) {
                // Order not synced yet, skip status sync
                return;
            }
            
            // Map WooCommerce status to Zoho
            $zoho_status = $this->map_woo_to_zoho_status($new_status);
            if (!$zoho_status) {
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Estado de WooCommerce "%s" no tiene mapeo a Zoho', 'zoho-sync-orders'), $new_status),
                    'warning',
                    'orders'
                );
                return;
            }
            
            // Update status in Zoho
            $result = $this->update_zoho_status($sync_record->zoho_id, $zoho_status, $sync_record->sync_type);
            
            if ($result['success']) {
                // Update sync record
                $this->update_sync_record_status($order_id, $zoho_status, 'woo_to_zoho');
                
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Estado del pedido %d actualizado en Zoho: %s -> %s', 'zoho-sync-orders'), 
                        $order_id, $old_status, $zoho_status),
                    'info',
                    'orders'
                );
                
                // Add order note
                $order->add_order_note(
                    sprintf(__('Estado sincronizado con Zoho: %s', 'zoho-sync-orders'), $zoho_status)
                );
            } else {
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Error actualizando estado en Zoho para pedido %d: %s', 'zoho-sync-orders'), 
                        $order_id, $result['message']),
                    'error',
                    'orders'
                );
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error en sincronización de estado para pedido %d: %s', 'zoho-sync-orders'), 
                    $order_id, $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Handle Zoho status change via webhook
     *
     * @param string $zoho_id Zoho record ID
     * @param array $webhook_data Webhook data
     */
    public function handle_zoho_status_change($zoho_id, $webhook_data) {
        try {
            // Check if auto sync is enabled
            if (get_option('zoho_sync_orders_auto_status_sync', 'yes') !== 'yes') {
                return;
            }
            
            // Find WooCommerce order by Zoho ID
            $order_id = $this->find_order_by_zoho_id($zoho_id);
            if (!$order_id) {
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Pedido de WooCommerce no encontrado para Zoho ID: %s', 'zoho-sync-orders'), $zoho_id),
                    'warning',
                    'orders'
                );
                return;
            }
            
            // Get order
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            
            // Extract new status from webhook data
            $zoho_status = $this->extract_status_from_webhook($webhook_data);
            if (!$zoho_status) {
                return;
            }
            
            // Map Zoho status to WooCommerce
            $woo_status = $this->map_zoho_to_woo_status($zoho_status);
            if (!$woo_status) {
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Estado de Zoho "%s" no tiene mapeo a WooCommerce', 'zoho-sync-orders'), $zoho_status),
                    'warning',
                    'orders'
                );
                return;
            }
            
            // Check if status actually changed
            if ($order->get_status() === $woo_status) {
                return; // No change needed
            }
            
            // Update WooCommerce order status
            $old_status = $order->get_status();
            $order->update_status($woo_status, sprintf(
                __('Estado actualizado desde Zoho: %s', 'zoho-sync-orders'), 
                $zoho_status
            ));
            
            // Update sync record
            $this->update_sync_record_status($order_id, $zoho_status, 'zoho_to_woo');
            
            \ZohoSyncCore\Logger::log(
                sprintf(__('Estado del pedido %d actualizado desde Zoho: %s -> %s', 'zoho-sync-orders'), 
                    $order_id, $old_status, $woo_status),
                'info',
                'orders'
            );
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error procesando cambio de estado desde Zoho: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
        }
    }
    
    /**
     * Update status in Zoho
     *
     * @param string $zoho_id Zoho record ID
     * @param string $status New status
     * @param string $record_type Record type (quote or salesorder)
     * @return array Update result
     */
    private function update_zoho_status($zoho_id, $status, $record_type = 'quote') {
        try {
            $update_data = array();
            
            if ($record_type === 'quote') {
                $update_data['Quote_Stage'] = $status;
                $result = $this->api->update_quote($zoho_id, $update_data);
            } else {
                $update_data['Status'] = $status;
                $result = $this->api->update_sales_order($zoho_id, $update_data);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Check if status should be synced
     *
     * @param string $status Status to check
     * @return bool
     */
    private function should_sync_status($status) {
        $sync_statuses = get_option('zoho_sync_orders_sync_statuses', array(
            'processing', 'completed', 'cancelled', 'refunded'
        ));
        
        return in_array($status, $sync_statuses);
    }
    
    /**
     * Map WooCommerce status to Zoho status
     *
     * @param string $woo_status WooCommerce status
     * @return string|null Zoho status
     */
    private function map_woo_to_zoho_status($woo_status) {
        return isset($this->status_mapping['woo_to_zoho'][$woo_status]) 
            ? $this->status_mapping['woo_to_zoho'][$woo_status] 
            : null;
    }
    
    /**
     * Map Zoho status to WooCommerce status
     *
     * @param string $zoho_status Zoho status
     * @return string|null WooCommerce status
     */
    private function map_zoho_to_woo_status($zoho_status) {
        return isset($this->status_mapping['zoho_to_woo'][$zoho_status]) 
            ? $this->status_mapping['zoho_to_woo'][$zoho_status] 
            : null;
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
     * Find order by Zoho ID
     *
     * @param string $zoho_id Zoho record ID
     * @return int|null Order ID
     */
    private function find_order_by_zoho_id($zoho_id) {
        global $wpdb;
        
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT order_id FROM {$wpdb->prefix}zoho_orders_sync WHERE zoho_id = %s",
                $zoho_id
            )
        );
        
        return $result ? intval($result) : null;
    }
    
    /**
     * Update sync record status
     *
     * @param int $order_id Order ID
     * @param string $zoho_status Zoho status
     * @param string $sync_direction Sync direction
     */
    private function update_sync_record_status($order_id, $zoho_status, $sync_direction) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'zoho_orders_sync',
            array(
                'last_status_sync' => current_time('mysql'),
                'zoho_status' => $zoho_status,
                'last_sync_direction' => $sync_direction,
                'updated_at' => current_time('mysql')
            ),
            array('order_id' => $order_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Extract status from webhook data
     *
     * @param array $webhook_data Webhook data
     * @return string|null Status
     */
    private function extract_status_from_webhook($webhook_data) {
        // For quotes
        if (isset($webhook_data['Quote_Stage'])) {
            return $webhook_data['Quote_Stage'];
        }
        
        // For sales orders
        if (isset($webhook_data['Status'])) {
            return $webhook_data['Status'];
        }
        
        // Check in nested data
        if (isset($webhook_data['data']) && is_array($webhook_data['data'])) {
            foreach ($webhook_data['data'] as $record) {
                if (isset($record['Quote_Stage'])) {
                    return $record['Quote_Stage'];
                }
                if (isset($record['Status'])) {
                    return $record['Status'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Manual status sync via AJAX
     */
    public function manual_status_sync() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zoho_sync_orders_nonce')) {
            wp_die(__('Token de seguridad inválido', 'zoho-sync-orders'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $order_id = intval($_POST['order_id']);
        $direction = sanitize_text_field($_POST['direction']); // 'woo_to_zoho' or 'zoho_to_woo'
        
        try {
            if ($direction === 'woo_to_zoho') {
                $result = $this->sync_woo_to_zoho_status($order_id);
            } else {
                $result = $this->sync_zoho_to_woo_status($order_id);
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
     * Sync WooCommerce status to Zoho
     *
     * @param int $order_id Order ID
     * @return array Sync result
     */
    public function sync_woo_to_zoho_status($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new \Exception(__('Pedido no encontrado', 'zoho-sync-orders'));
        }
        
        $sync_record = $this->get_sync_record($order_id);
        if (!$sync_record || !$sync_record->zoho_id) {
            throw new \Exception(__('Pedido no sincronizado con Zoho', 'zoho-sync-orders'));
        }
        
        $woo_status = $order->get_status();
        $zoho_status = $this->map_woo_to_zoho_status($woo_status);
        
        if (!$zoho_status) {
            throw new \Exception(sprintf(__('Estado "%s" no tiene mapeo a Zoho', 'zoho-sync-orders'), $woo_status));
        }
        
        $result = $this->update_zoho_status($sync_record->zoho_id, $zoho_status, $sync_record->sync_type);
        
        if ($result['success']) {
            $this->update_sync_record_status($order_id, $zoho_status, 'woo_to_zoho');
        }
        
        return $result;
    }
    
    /**
     * Sync Zoho status to WooCommerce
     *
     * @param int $order_id Order ID
     * @return array Sync result
     */
    public function sync_zoho_to_woo_status($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new \Exception(__('Pedido no encontrado', 'zoho-sync-orders'));
        }
        
        $sync_record = $this->get_sync_record($order_id);
        if (!$sync_record || !$sync_record->zoho_id) {
            throw new \Exception(__('Pedido no sincronizado con Zoho', 'zoho-sync-orders'));
        }
        
        // Get current status from Zoho
        $zoho_data = $this->api->get_quote($sync_record->zoho_id);
        if (!$zoho_data['success']) {
            throw new \Exception(__('Error obteniendo estado desde Zoho', 'zoho-sync-orders'));
        }
        
        $zoho_status = isset($zoho_data['data']['Quote_Stage']) 
            ? $zoho_data['data']['Quote_Stage'] 
            : (isset($zoho_data['data']['Status']) ? $zoho_data['data']['Status'] : null);
        
        if (!$zoho_status) {
            throw new \Exception(__('Estado no encontrado en datos de Zoho', 'zoho-sync-orders'));
        }
        
        $woo_status = $this->map_zoho_to_woo_status($zoho_status);
        if (!$woo_status) {
            throw new \Exception(sprintf(__('Estado de Zoho "%s" no tiene mapeo a WooCommerce', 'zoho-sync-orders'), $zoho_status));
        }
        
        // Update WooCommerce status
        $old_status = $order->get_status();
        if ($old_status !== $woo_status) {
            $order->update_status($woo_status, sprintf(
                __('Estado sincronizado desde Zoho: %s', 'zoho-sync-orders'), 
                $zoho_status
            ));
            
            $this->update_sync_record_status($order_id, $zoho_status, 'zoho_to_woo');
        }
        
        return array(
            'success' => true,
            'message' => sprintf(__('Estado sincronizado: %s -> %s', 'zoho-sync-orders'), $old_status, $woo_status),
            'old_status' => $old_status,
            'new_status' => $woo_status,
            'zoho_status' => $zoho_status
        );
    }
    
    /**
     * Get status mapping configuration
     *
     * @return array Status mappings
     */
    public function get_status_mappings() {
        return $this->status_mapping;
    }
    
    /**
     * Update status mappings
     *
     * @param array $mappings New status mappings
     * @return bool Success status
     */
    public function update_status_mappings($mappings) {
        $this->status_mapping = $mappings;
        return update_option('zoho_sync_orders_status_mapping', $mappings);
    }
    
    /**
     * Get available WooCommerce statuses
     *
     * @return array WooCommerce statuses
     */
    public function get_woo_statuses() {
        return wc_get_order_statuses();
    }
    
    /**
     * Get available Zoho statuses
     *
     * @return array Zoho statuses
     */
    public function get_zoho_statuses() {
        return array(
            'quotes' => array(
                'Draft' => __('Borrador', 'zoho-sync-orders'),
                'Negotiation' => __('Negociación', 'zoho-sync-orders'),
                'Delivered' => __('Entregado', 'zoho-sync-orders'),
                'Closed Won' => __('Cerrado Ganado', 'zoho-sync-orders'),
                'Closed Lost' => __('Cerrado Perdido', 'zoho-sync-orders'),
                'Cancelled' => __('Cancelado', 'zoho-sync-orders')
            ),
            'salesorders' => array(
                'Created' => __('Creado', 'zoho-sync-orders'),
                'Confirmed' => __('Confirmado', 'zoho-sync-orders'),
                'Delivered' => __('Entregado', 'zoho-sync-orders'),
                'Cancelled' => __('Cancelado', 'zoho-sync-orders')
            )
        );
    }
    
    /**
     * Get status sync statistics
     *
     * @return array Statistics
     */
    public function get_status_sync_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total status syncs
        $stats['total_syncs'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync WHERE last_status_sync IS NOT NULL"
        );
        
        // Syncs by direction
        $stats['woo_to_zoho'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync WHERE last_sync_direction = 'woo_to_zoho'"
        );
        
        $stats['zoho_to_woo'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zoho_orders_sync WHERE last_sync_direction = 'zoho_to_woo'"
        );
        
        // Recent syncs
        $stats['recent_syncs'] = $wpdb->get_results(
            "SELECT order_id, zoho_status, last_sync_direction, last_status_sync 
            FROM {$wpdb->prefix}zoho_orders_sync 
            WHERE last_status_sync IS NOT NULL 
            ORDER BY last_status_sync DESC 
            LIMIT 10"
        );
        
        return $stats;
    }
    
    /**
     * Bulk status sync
     *
     * @param array $order_ids Order IDs
     * @param string $direction Sync direction
     * @return array Sync results
     */
    public function bulk_status_sync($order_ids, $direction = 'woo_to_zoho') {
        $results = array();
        
        foreach ($order_ids as $order_id) {
            try {
                if ($direction === 'woo_to_zoho') {
                    $result = $this->sync_woo_to_zoho_status($order_id);
                } else {
                    $result = $this->sync_zoho_to_woo_status($order_id);
                }
                
                $results[$order_id] = $result;
                
            } catch (\Exception $e) {
                $results[$order_id] = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }
        
        return $results;
    }
}