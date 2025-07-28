<?php
/**
 * Orders Admin Class
 *
 * Handles the administrative interface for order synchronization
 *
 * @package ZohoSyncOrders
 * @subpackage Admin
 * @since 1.0.0
 */

namespace ZohoSyncOrders\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Orders administration handler
 */
class OrdersAdmin {
    
    /**
     * Orders sync instance
     *
     * @var \ZohoSyncOrders\OrdersSync
     */
    private $orders_sync;
    
    /**
     * Order mapper instance
     *
     * @var \ZohoSyncOrders\OrderMapper
     */
    private $mapper;
    
    /**
     * Order validator instance
     *
     * @var \ZohoSyncOrders\OrderValidator
     */
    private $validator;
    
    /**
     * Status handler instance
     *
     * @var \ZohoSyncOrders\OrderStatusHandler
     */
    private $status_handler;
    
    /**
     * Retry manager instance
     *
     * @var \ZohoSyncOrders\RetryManager
     */
    private $retry_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->orders_sync = new \ZohoSyncOrders\OrdersSync();
        $this->mapper = new \ZohoSyncOrders\OrderMapper();
        $this->validator = new \ZohoSyncOrders\OrderValidator();
        $this->status_handler = new \ZohoSyncOrders\OrderStatusHandler();
        $this->retry_manager = new \ZohoSyncOrders\RetryManager();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_zoho_orders_sync_single', array($this, 'ajax_sync_single_order'));
        add_action('wp_ajax_zoho_orders_sync_bulk', array($this, 'ajax_sync_bulk_orders'));
        add_action('wp_ajax_zoho_orders_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_zoho_orders_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_zoho_orders_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_zoho_orders_get_sync_status', array($this, 'ajax_get_sync_status'));
        add_action('wp_ajax_zoho_orders_retry_failed', array($this, 'ajax_retry_failed'));
        add_action('wp_ajax_zoho_orders_clear_logs', array($this, 'ajax_clear_logs'));
        
        // Order list customizations
        add_filter('manage_shop_order_posts_columns', array($this, 'add_order_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'populate_order_columns'), 10, 2);
        add_filter('manage_edit-shop_order_sortable_columns', array($this, 'make_columns_sortable'));
        
        // Order edit page customizations
        add_action('add_meta_boxes', array($this, 'add_order_meta_boxes'));
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_order_meta'), 10, 2);
        
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Settings page
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Zoho Sync Orders', 'zoho-sync-orders'),
            __('Zoho Orders', 'zoho-sync-orders'),
            'manage_woocommerce',
            'zoho-sync-orders',
            array($this, 'display_dashboard_page'),
            'dashicons-update',
            56
        );
        
        // Dashboard submenu
        add_submenu_page(
            'zoho-sync-orders',
            __('Dashboard', 'zoho-sync-orders'),
            __('Dashboard', 'zoho-sync-orders'),
            'manage_woocommerce',
            'zoho-sync-orders',
            array($this, 'display_dashboard_page')
        );
        
        // Sync submenu
        add_submenu_page(
            'zoho-sync-orders',
            __('Sincronización', 'zoho-sync-orders'),
            __('Sincronización', 'zoho-sync-orders'),
            'manage_woocommerce',
            'zoho-sync-orders-sync',
            array($this, 'display_sync_page')
        );
        
        // Status submenu
        add_submenu_page(
            'zoho-sync-orders',
            __('Estado de Pedidos', 'zoho-sync-orders'),
            __('Estado', 'zoho-sync-orders'),
            'manage_woocommerce',
            'zoho-sync-orders-status',
            array($this, 'display_status_page')
        );
        
        // Mapping submenu
        add_submenu_page(
            'zoho-sync-orders',
            __('Mapeo de Campos', 'zoho-sync-orders'),
            __('Mapeo', 'zoho-sync-orders'),
            'manage_woocommerce',
            'zoho-sync-orders-mapping',
            array($this, 'display_mapping_page')
        );
        
        // Logs submenu
        add_submenu_page(
            'zoho-sync-orders',
            __('Registros', 'zoho-sync-orders'),
            __('Registros', 'zoho-sync-orders'),
            'manage_woocommerce',
            'zoho-sync-orders-logs',
            array($this, 'display_logs_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'zoho-sync-orders',
            __('Configuración', 'zoho-sync-orders'),
            __('Configuración', 'zoho-sync-orders'),
            'manage_woocommerce',
            'zoho-sync-orders-settings',
            array($this, 'display_settings_page')
        );
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'zoho-sync-orders') === false && $hook !== 'edit.php' && $hook !== 'post.php') {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'zoho-sync-orders-admin',
            ZOHO_SYNC_ORDERS_PLUGIN_URL . 'admin/assets/css/admin-styles.css',
            array(),
            ZOHO_SYNC_ORDERS_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'zoho-sync-orders-admin',
            ZOHO_SYNC_ORDERS_PLUGIN_URL . 'admin/assets/js/admin-scripts.js',
            array('jquery', 'wp-util'),
            ZOHO_SYNC_ORDERS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('zoho-sync-orders-admin', 'zohoOrdersAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zoho_sync_orders_nonce'),
            'strings' => array(
                'confirmSync' => __('¿Está seguro de que desea sincronizar este pedido?', 'zoho-sync-orders'),
                'confirmBulkSync' => __('¿Está seguro de que desea sincronizar los pedidos seleccionados?', 'zoho-sync-orders'),
                'syncInProgress' => __('Sincronización en progreso...', 'zoho-sync-orders'),
                'syncComplete' => __('Sincronización completada', 'zoho-sync-orders'),
                'syncError' => __('Error en la sincronización', 'zoho-sync-orders'),
                'noOrdersSelected' => __('No se han seleccionado pedidos', 'zoho-sync-orders'),
                'connectionTesting' => __('Probando conexión...', 'zoho-sync-orders'),
                'connectionSuccess' => __('Conexión exitosa', 'zoho-sync-orders'),
                'connectionError' => __('Error de conexión', 'zoho-sync-orders')
            )
        ));
    }
    
    /**
     * Display dashboard page
     */
    public function display_dashboard_page() {
        $stats = $this->orders_sync->get_sync_stats();
        $recent_syncs = $this->get_recent_syncs();
        $system_status = $this->get_system_status();
        
        include ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'admin/partials/dashboard-display.php';
    }
    
    /**
     * Display sync page
     */
    public function display_sync_page() {
        $sync_settings = $this->get_sync_settings();
        $pending_orders = $this->get_pending_orders();
        
        include ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'admin/partials/sync-display.php';
    }
    
    /**
     * Display status page
     */
    public function display_status_page() {
        $orders_status = $this->orders_sync->get_orders_sync_status();
        $status_mappings = $this->status_handler->get_status_mappings();
        
        include ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'admin/partials/status-display.php';
    }
    
    /**
     * Display mapping page
     */
    public function display_mapping_page() {
        $field_mappings = $this->mapper->get_field_mappings();
        $payment_mappings = $this->mapper->get_payment_mappings();
        $available_fields = $this->get_available_fields();
        
        include ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'admin/partials/mapping-display.php';
    }
    
    /**
     * Display logs page
     */
    public function display_logs_page() {
        $logs = \ZohoSyncCore\Logger::get_logs('orders', 50);
        $log_levels = array('info', 'warning', 'error');
        
        include ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'admin/partials/logs-display.php';
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        $settings = $this->get_all_settings();
        $retry_config = $this->retry_manager->get_retry_config();
        
        include ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'admin/partials/settings-display.php';
    }
    
    /**
     * Add order columns to admin list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_order_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            // Add Zoho sync column after order status
            if ($key === 'order_status') {
                $new_columns['zoho_sync_status'] = __('Zoho Sync', 'zoho-sync-orders');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Populate custom order columns
     *
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public function populate_order_columns($column, $post_id) {
        if ($column === 'zoho_sync_status') {
            global $wpdb;
            
            $sync_record = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}zoho_orders_sync WHERE order_id = %d",
                    $post_id
                )
            );
            
            if ($sync_record) {
                $status_class = 'zoho-sync-' . $sync_record->sync_status;
                $status_text = $this->get_sync_status_text($sync_record->sync_status);
                
                echo '<span class="' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span>';
                
                if ($sync_record->zoho_id) {
                    echo '<br><small>ID: ' . esc_html($sync_record->zoho_id) . '</small>';
                }
                
                if ($sync_record->retry_count > 0) {
                    echo '<br><small>' . sprintf(__('Reintentos: %d', 'zoho-sync-orders'), $sync_record->retry_count) . '</small>';
                }
            } else {
                echo '<span class="zoho-sync-not-synced">' . __('No sincronizado', 'zoho-sync-orders') . '</span>';
            }
        }
    }
    
    /**
     * Make columns sortable
     *
     * @param array $columns Sortable columns
     * @return array Modified columns
     */
    public function make_columns_sortable($columns) {
        $columns['zoho_sync_status'] = 'zoho_sync_status';
        return $columns;
    }
    
    /**
     * Add order meta boxes
     */
    public function add_order_meta_boxes() {
        add_meta_box(
            'zoho-sync-orders-meta',
            __('Zoho Sync Status', 'zoho-sync-orders'),
            array($this, 'display_order_meta_box'),
            'shop_order',
            'side',
            'high'
        );
    }
    
    /**
     * Display order meta box
     *
     * @param \WP_Post $post Post object
     */
    public function display_order_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }
        
        global $wpdb;
        $sync_record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zoho_orders_sync WHERE order_id = %d",
                $post->ID
            )
        );
        
        include ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'admin/partials/order-meta-box.php';
    }
    
    /**
     * Save order meta
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     */
    public function save_order_meta($post_id, $post) {
        // Check if manual sync was requested
        if (isset($_POST['zoho_manual_sync']) && $_POST['zoho_manual_sync'] === '1') {
            // Verify nonce
            if (!wp_verify_nonce($_POST['zoho_sync_nonce'], 'zoho_sync_order_' . $post_id)) {
                return;
            }
            
            // Perform sync
            try {
                $result = $this->orders_sync->sync_order($post_id);
                
                if ($result['success']) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success"><p>' . __('Pedido sincronizado correctamente con Zoho', 'zoho-sync-orders') . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() use ($result) {
                        echo '<div class="notice notice-error"><p>' . sprintf(__('Error sincronizando pedido: %s', 'zoho-sync-orders'), $result['message']) . '</p></div>';
                    });
                }
            } catch (\Exception $e) {
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-error"><p>' . sprintf(__('Error sincronizando pedido: %s', 'zoho-sync-orders'), $e->getMessage()) . '</p></div>';
                });
            }
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if core plugin is active
        if (!class_exists('ZohoSyncCore\Core')) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . __('Zoho Sync Orders', 'zoho-sync-orders') . '</strong>: ';
            echo __('Requiere que Zoho Sync Core esté activo.', 'zoho-sync-orders');
            echo '</p></div>';
        }
        
        // Check API connection
        if (get_transient('zoho_sync_orders_connection_error')) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>' . __('Zoho Sync Orders', 'zoho-sync-orders') . '</strong>: ';
            echo __('Problema de conexión con Zoho. Verifique la configuración.', 'zoho-sync-orders');
            echo '</p></div>';
        }
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Register setting groups
        register_setting('zoho_sync_orders_settings', 'zoho_sync_orders_auto_sync');
        register_setting('zoho_sync_orders_settings', 'zoho_sync_orders_sync_status');
        register_setting('zoho_sync_orders_settings', 'zoho_sync_orders_convert_to');
        register_setting('zoho_sync_orders_settings', 'zoho_sync_orders_include_taxes');
        register_setting('zoho_sync_orders_settings', 'zoho_sync_orders_include_shipping');
        register_setting('zoho_sync_orders_settings', 'zoho_sync_orders_retry_attempts');
        register_setting('zoho_sync_orders_settings', 'zoho_sync_orders_retry_interval');
    }
    
    /**
     * AJAX: Sync single order
     */
    public function ajax_sync_single_order() {
        check_ajax_referer('zoho_sync_orders_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $order_id = intval($_POST['order_id']);
        $sync_type = sanitize_text_field($_POST['sync_type'] ?? 'create');
        
        try {
            $result = $this->orders_sync->sync_order($order_id, $sync_type);
            wp_send_json($result);
        } catch (\Exception $e) {
            wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Sync bulk orders
     */
    public function ajax_sync_bulk_orders() {
        check_ajax_referer('zoho_sync_orders_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $order_ids = array_map('intval', $_POST['order_ids']);
        $results = array();
        
        foreach ($order_ids as $order_id) {
            try {
                $results[$order_id] = $this->orders_sync->sync_order($order_id);
            } catch (\Exception $e) {
                $results[$order_id] = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }
        
        wp_send_json(array(
            'success' => true,
            'results' => $results
        ));
    }
    
    /**
     * AJAX: Get statistics
     */
    public function ajax_get_stats() {
        check_ajax_referer('zoho_sync_orders_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $stats = $this->orders_sync->get_sync_stats();
        $retry_stats = $this->retry_manager->get_retry_statistics();
        
        wp_send_json(array(
            'success' => true,
            'stats' => $stats,
            'retry_stats' => $retry_stats
        ));
    }
    
    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('zoho_sync_orders_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        try {
            $api = new \ZohoSyncOrders\ZohoOrdersApi();
            $result = $api->test_connection();
            wp_send_json($result);
        } catch (\Exception $e) {
            wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('zoho_sync_orders_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        try {
            $settings = $_POST['settings'];
            
            foreach ($settings as $key => $value) {
                update_option($key, $value);
            }
            
            wp_send_json(array(
                'success' => true,
                'message' => __('Configuración guardada correctamente', 'zoho-sync-orders')
            ));
        } catch (\Exception $e) {
            wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Get sync status
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('zoho_sync_orders_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        $args = array(
            'limit' => intval($_POST['limit'] ?? 20),
            'offset' => intval($_POST['offset'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'search' => sanitize_text_field($_POST['search'] ?? '')
        );
        
        $orders = $this->orders_sync->get_orders_sync_status($args);
        
        wp_send_json(array(
            'success' => true,
            'orders' => $orders
        ));
    }
    
    /**
     * AJAX: Retry failed orders
     */
    public function ajax_retry_failed() {
        check_ajax_referer('zoho_sync_orders_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        try {
            $this->retry_manager->process_retry_queue();
            
            wp_send_json(array(
                'success' => true,
                'message' => __('Reintentos procesados correctamente', 'zoho-sync-orders')
            ));
        } catch (\Exception $e) {
            wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('zoho_sync_orders_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-orders'));
        }
        
        try {
            \ZohoSyncCore\Logger::clear_logs('orders');
            
            wp_send_json(array(
                'success' => true,
                'message' => __('Registros limpiados correctamente', 'zoho-sync-orders')
            ));
        } catch (\Exception $e) {
            wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get sync status text
     *
     * @param string $status Status code
     * @return string Status text
     */
    private function get_sync_status_text($status) {
        $statuses = array(
            'pending' => __('Pendiente', 'zoho-sync-orders'),
            'completed' => __('Completado', 'zoho-sync-orders'),
            'failed' => __('Fallido', 'zoho-sync-orders'),
            'permanently_failed' => __('Fallido Permanente', 'zoho-sync-orders')
        );
        
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
    
    /**
     * Get recent syncs
     *
     * @return array Recent sync records
     */
    private function get_recent_syncs() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT zos.*, p.post_title 
            FROM {$wpdb->prefix}zoho_orders_sync zos
            LEFT JOIN {$wpdb->posts} p ON zos.order_id = p.ID
            WHERE zos.last_sync IS NOT NULL
            ORDER BY zos.last_sync DESC
            LIMIT 10"
        );
    }
    
    /**
     * Get system status
     *
     * @return array System status
     */
    private function get_system_status() {
        return array(
            'core_active' => class_exists('ZohoSyncCore\Core'),
            'woocommerce_active' => class_exists('WooCommerce'),
            'auto_sync_enabled' => get_option('zoho_sync_orders_auto_sync', 'yes') === 'yes',
            'api_connected' => !get_transient('zoho_sync_orders_connection_error')
        );
    }
    
    /**
     * Get sync settings
     *
     * @return array Sync settings
     */
    private function get_sync_settings() {
        return array(
            'auto_sync' => get_option('zoho_sync_orders_auto_sync', 'yes'),
            'sync_status' => get_option('zoho_sync_orders_sync_status', array('processing', 'completed')),
            'convert_to' => get_option('zoho_sync_orders_convert_to', 'quote'),
            'include_taxes' => get_option('zoho_sync_orders_include_taxes', 'yes'),
            'include_shipping' => get_option('zoho_sync_orders_include_shipping', 'yes')
        );
    }
    
    /**
     * Get pending orders
     *
     * @return array Pending orders
     */
    private function get_pending_orders() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT p.ID as order_id, p.post_title, p.post_date, p.post_status
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}zoho_orders_sync zos ON p.ID = zos.order_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed')
            AND (zos.id IS NULL OR zos.sync_status = 'pending')
            ORDER BY p.post_date DESC
            LIMIT 20"
        );
    }
    
    /**
     * Get available fields
     *
     * @return array Available fields
     */
    private function get_available_fields() {
        return array(
            'woocommerce' => array(
                'billing_first_name' => __('Nombre de Facturación', 'zoho-sync-orders'),
                'billing_last_name' => __('Apellido de Facturación', 'zoho-sync-orders'),
                'billing_email' => __('Email de Facturación', 'zoho-sync-orders'),
                'billing_phone' => __('Teléfono de Facturación', 'zoho-sync-orders'),
                'billing_address_1' => __('Dirección de Facturación 1', 'zoho-sync-orders'),
                'billing_city' => __('Ciudad de Facturación', 'zoho-sync-orders'),
                'billing_state' => __('Estado de Facturación', 'zoho-sync-orders'),
                'billing_country' => __('País de Facturación', 'zoho-sync-orders'),
                'total' => __('Total del Pedido', 'zoho-sync-orders'),
                'currency' => __('Moneda', 'zoho-sync-orders'),
                'payment_method' => __('Método de Pago', 'zoho-sync-orders'),
                'status' => __('Estado del Pedido', 'zoho-sync-orders')
            ),
            'zoho' => array(
                'Subject' => __('Asunto', 'zoho-sync-orders'),
                'Contact_Name' => __('Nombre del Contacto', 'zoho-sync-orders'),
                'Quote_Stage' => __('Etapa de Cotización', 'zoho-sync-orders'),
                'Grand_Total' => __('Total General', 'zoho-sync-orders'),
                'Currency' => __('Moneda', 'zoho-sync-orders'),
                'Billing_Street' => __('Dirección de Facturación', 'zoho-sync-orders'),
                'Billing_City' => __('Ciudad de Facturación', 'zoho-sync-orders'),
                'Billing_State' => __('Estado de Facturación', 'zoho-sync-orders'),
                'Billing_Country' => __('País de Facturación', 'zoho-sync-orders')
            )
        );
    }
    
    /**
     * Get all settings
     *
     * @return array All settings
     */
    private function get_all_settings() {
        return array(
            'auto_sync' => get_option('zoho_sync_orders_auto_sync', 'yes'),
            'sync_status' => get_option('zoho_sync_orders_sync_status', array('processing', 'completed')),
            'convert_to' => get_option('zoho_sync_orders_convert_to', 'quote'),
            'include_taxes' => get_option('zoho_sync_orders_include_taxes', 'yes'),
            'include_shipping' => get_option('zoho_sync_orders_include_shipping', 'yes'),
            'retry_attempts' => get_option('zoho_sync_orders_retry_attempts', 3),
            'retry_interval' => get_option('zoho_sync_orders_retry_interval', 300),
            'field_mapping' => get_option('zoho_sync_orders_field_mapping', array()),
            'payment_mapping' => get_option('zoho_sync_orders_payment_mapping', array()),
            'status_mapping' => get_option('zoho_sync_orders_status_mapping', array())
        );
    }
}