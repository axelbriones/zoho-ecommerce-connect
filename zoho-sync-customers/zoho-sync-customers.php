<?php
/**
 * Plugin Name: Zoho Sync Customers
 * Plugin URI: https://github.com/zoho-ecommerce-connect/zoho-sync-customers
 * Description: Sincronización de clientes y gestión de distribuidores entre WooCommerce y Zoho
 * Version: 1.0
 * Author: Tu Nombre
 * Author URI: https://github.com/zoho-ecommerce-connect
 * Text Domain: zoho-sync-customers
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('ZSCU_VERSION', '1.0');
define('ZSCU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZSCU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZSCU_MIN_CORE_VERSION', '1.0');

/**
 * Main Zoho Sync Customers Plugin Class
 */
final class Zoho_Sync_Customers {
    /**
     * Plugin instance
     *
     * @var Zoho_Sync_Customers
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return Zoho_Sync_Customers
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
        // Verificar dependencias
        if (!$this->check_dependencies()) {
            return;
        }

        $this->includes();
        $this->init_hooks();

        // Registrar el módulo en el core
        add_action('zoho_sync_core_loaded', [$this, 'register_module']);
    }

    /**
     * Check plugin dependencies
     *
     * @return bool
     */
    private function check_dependencies() {
        if (!class_exists('ZohoSyncCore')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                _e('El plugin Zoho Sync Customers requiere que Zoho Sync Core esté instalado y activado.', 'zoho-sync-customers');
                echo '</p></div>';
            });
            return false;
        }
        return true;
    }

    /**
     * Load plugin classes
     */
    private function includes() {
        // Core
        require_once ZSCU_PLUGIN_DIR . 'includes/class-customers-sync.php';
        require_once ZSCU_PLUGIN_DIR . 'includes/class-customer-mapper.php';
        require_once ZSCU_PLUGIN_DIR . 'includes/class-distributor-manager.php';
        require_once ZSCU_PLUGIN_DIR . 'includes/class-pricing-manager.php';

        // Frontend
        require_once ZSCU_PLUGIN_DIR . 'frontend/class-pricing-display.php';
        require_once ZSCU_PLUGIN_DIR . 'frontend/class-customer-portal.php';
        require_once ZSCU_PLUGIN_DIR . 'frontend/class-registration-handler.php';

        // Admin
        if (is_admin()) {
            require_once ZSCU_PLUGIN_DIR . 'admin/class-customers-admin.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Inicialización
        add_action('init', [$this, 'init']);
        add_action('woocommerce_init', [$this, 'wc_init']);

        // Roles y capacidades
        add_action('init', [$this, 'register_roles']);

        // Sincronización
        add_action('user_register', ['ZSCU_Customers_Sync', 'sync_new_customer']);
        add_action('profile_update', ['ZSCU_Customers_Sync', 'sync_customer_update']);
    }

    /**
     * Register plugin with Zoho Sync Core
     */
    public function register_module() {
        ZohoSyncCore::register_module('customers', [
            'name' => __('Sincronización de Clientes', 'zoho-sync-customers'),
            'version' => ZSCU_VERSION,
            'dependencies' => ['core'],
            'settings' => [
                'sync_frequency' => 'hourly',
                'distributor_levels' => [
                    'bronze' => 10,
                    'silver' => 20,
                    'gold' => 30,
                    'platinum' => 40
                ],
                'enable_registration' => true,
                'auto_approval' => false
            ]
        ]);
    }

    /**
     * Load plugin textdomain
     */
    public function init() {
        load_plugin_textdomain('zoho-sync-customers', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function wc_init() {
        // Integración con WooCommerce
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Inicializar gestores
        new ZSCU_Pricing_Display();
        new ZSCU_Customer_Portal();
        new ZSCU_Registration_Handler();
    }

    public function register_roles() {
        add_role('distributor', __('Distribuidor', 'zoho-sync-customers'), [
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'view_pricing' => true,
            'access_distributor_portal' => true
        ]);
    }
}

/**
 * Initialize the plugin
 */
function zscu_init() {
    return Zoho_Sync_Customers::instance();
}

// Start the plugin
add_action('plugins_loaded', 'zscu_init');