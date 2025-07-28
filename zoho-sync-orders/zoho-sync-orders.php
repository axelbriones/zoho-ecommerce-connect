<?php
/**
 * Plugin Name: Zoho Sync Orders
 * Plugin URI: https://github.com/zoho-sync-ecosystem/zoho-sync-orders
 * Description: Sincronización automática de pedidos entre WooCommerce y Zoho CRM/Books. Convierte pedidos a cotizaciones y maneja el flujo completo de sincronización.
 * Version: 1.0.0
 * Author: Zoho Sync Ecosystem
 * Author URI: https://github.com/zoho-sync-ecosystem
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zoho-sync-orders
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * Network: false
 *
 * @package ZohoSyncOrders
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZOHO_SYNC_ORDERS_VERSION', '1.0.0');
define('ZOHO_SYNC_ORDERS_PLUGIN_FILE', __FILE__);
define('ZOHO_SYNC_ORDERS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZOHO_SYNC_ORDERS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZOHO_SYNC_ORDERS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class ZohoSyncOrders {
    
    /**
     * Single instance of the plugin
     *
     * @var ZohoSyncOrders
     */
    private static $instance = null;
    
    /**
     * Plugin version
     *
     * @var string
     */
    public $version = ZOHO_SYNC_ORDERS_VERSION;
    
    /**
     * Orders sync handler
     *
     * @var ZohoSyncOrders\OrdersSync
     */
    public $orders_sync;
    
    /**
     * Admin interface handler
     *
     * @var ZohoSyncOrders\Admin\OrdersAdmin
     */
    public $admin;
    
    /**
     * WooCommerce hooks handler
     *
     * @var ZohoSyncOrders\Hooks\WooCommerceHooks
     */
    public $woo_hooks;
    
    /**
     * Get single instance of the plugin
     *
     * @return ZohoSyncOrders
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'), 10);
        add_action('init', array($this, 'load_textdomain'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if core plugin is active
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Load plugin classes
        $this->load_classes();
        
        // Initialize components
        $this->init_components();
        
        // Plugin fully loaded action
        do_action('zoho_sync_orders_loaded');
    }
    
    /**
     * Check plugin dependencies
     *
     * @return bool
     */
    private function check_dependencies() {
        // Check if Zoho Sync Core is active
        if (!class_exists('ZohoSyncCore\Core')) {
            add_action('admin_notices', array($this, 'core_missing_notice'));
            return false;
        }
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return false;
        }
        
        // Check core version compatibility
        if (defined('ZOHO_SYNC_CORE_VERSION') && version_compare(ZOHO_SYNC_CORE_VERSION, '1.0.0', '<')) {
            add_action('admin_notices', array($this, 'core_version_notice'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Load plugin classes
     */
    private function load_classes() {
        // Core classes
        require_once ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'includes/class-orders-sync.php';
        require_once ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'includes/class-order-mapper.php';
        require_once ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'includes/class-order-validator.php';
        require_once ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'includes/class-zoho-orders-api.php';
        require_once ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'includes/class-order-status-handler.php';
        require_once ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'includes/class-retry-manager.php';
        
        // Admin classes
        if (is_admin()) {
            require_once ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'admin/class-orders-admin.php';
        }
        
        // Hooks classes
        require_once ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'hooks/class-woocommerce-hooks.php';
        require_once ZOHO_SYNC_ORDERS_PLUGIN_DIR . 'hooks/class-order-triggers.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize orders sync
        $this->orders_sync = new ZohoSyncOrders\OrdersSync();
        
        // Initialize admin interface
        if (is_admin()) {
            $this->admin = new ZohoSyncOrders\Admin\OrdersAdmin();
        }
        
        // Initialize WooCommerce hooks
        $this->woo_hooks = new ZohoSyncOrders\Hooks\WooCommerceHooks();
        
        // Initialize order triggers
        new ZohoSyncOrders\Hooks\OrderTriggers();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'zoho-sync-orders',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check dependencies on activation
        if (!$this->check_dependencies()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('Zoho Sync Orders requiere que Zoho Sync Core y WooCommerce estén activos.', 'zoho-sync-orders'),
                __('Error de Activación', 'zoho-sync-orders'),
                array('back_link' => true)
            );
        }
        
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron events
        $this->schedule_events();
        
        // Log activation
        if (class_exists('ZohoSyncCore\Logger')) {
            ZohoSyncCore\Logger::log('Plugin Zoho Sync Orders activado correctamente', 'info', 'orders');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        $this->clear_scheduled_events();
        
        // Log deactivation
        if (class_exists('ZohoSyncCore\Logger')) {
            ZohoSyncCore\Logger::log('Plugin Zoho Sync Orders desactivado', 'info', 'orders');
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Orders sync status table
        $table_name = $wpdb->prefix . 'zoho_orders_sync';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            zoho_id varchar(100) DEFAULT NULL,
            sync_status varchar(20) DEFAULT 'pending',
            sync_type varchar(20) DEFAULT 'create',
            last_sync datetime DEFAULT NULL,
            retry_count int(11) DEFAULT 0,
            error_message text DEFAULT NULL,
            zoho_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY sync_status (sync_status),
            KEY last_sync (last_sync)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'zoho_sync_orders_auto_sync' => 'yes',
            'zoho_sync_orders_sync_status' => array('processing', 'completed'),
            'zoho_sync_orders_retry_attempts' => 3,
            'zoho_sync_orders_retry_interval' => 300, // 5 minutes
            'zoho_sync_orders_convert_to' => 'quote', // quote or salesorder
            'zoho_sync_orders_include_taxes' => 'yes',
            'zoho_sync_orders_include_shipping' => 'yes',
            'zoho_sync_orders_field_mapping' => array(),
            'zoho_sync_orders_payment_mapping' => array(),
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Schedule cron events
     */
    private function schedule_events() {
        if (!wp_next_scheduled('zoho_sync_orders_retry_failed')) {
            wp_schedule_event(time(), 'hourly', 'zoho_sync_orders_retry_failed');
        }
        
        if (!wp_next_scheduled('zoho_sync_orders_cleanup')) {
            wp_schedule_event(time(), 'daily', 'zoho_sync_orders_cleanup');
        }
    }
    
    /**
     * Clear scheduled events
     */
    private function clear_scheduled_events() {
        wp_clear_scheduled_hook('zoho_sync_orders_retry_failed');
        wp_clear_scheduled_hook('zoho_sync_orders_cleanup');
    }
    
    /**
     * Core plugin missing notice
     */
    public function core_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Zoho Sync Orders', 'zoho-sync-orders'); ?></strong>: 
                <?php _e('Este plugin requiere que Zoho Sync Core esté instalado y activo.', 'zoho-sync-orders'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Zoho Sync Orders', 'zoho-sync-orders'); ?></strong>: 
                <?php _e('Este plugin requiere que WooCommerce esté instalado y activo.', 'zoho-sync-orders'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Core version notice
     */
    public function core_version_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Zoho Sync Orders', 'zoho-sync-orders'); ?></strong>: 
                <?php _e('Este plugin requiere Zoho Sync Core versión 1.0.0 o superior.', 'zoho-sync-orders'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Get plugin instance
     *
     * @return ZohoSyncOrders
     */
    public static function get_instance() {
        return self::instance();
    }
}

/**
 * Initialize the plugin
 */
function zoho_sync_orders() {
    return ZohoSyncOrders::instance();
}

// Initialize plugin
zoho_sync_orders();
