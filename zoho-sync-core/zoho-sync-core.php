<?php
/**
 * Plugin Name: Zoho Sync Core
 * Description: Core plugin for Zoho synchronization.
 * Version: 7.0.0
 * Author: Jules
 * Text Domain: zoho-sync-core
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definición de constantes
if (!defined('ZOHO_SYNC_CORE_VERSION')) {
    define('ZOHO_SYNC_CORE_VERSION', '7.0.0');
}
if (!defined('ZOHO_SYNC_CORE_PLUGIN_FILE')) {
    define('ZOHO_SYNC_CORE_PLUGIN_FILE', __FILE__);
}
if (!defined('ZOHO_SYNC_CORE_PLUGIN_DIR')) {
    define('ZOHO_SYNC_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('ZOHO_SYNC_CORE_PLUGIN_URL')) {
    define('ZOHO_SYNC_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('ZOHO_SYNC_CORE_INCLUDES_DIR')) {
    define('ZOHO_SYNC_CORE_INCLUDES_DIR', ZOHO_SYNC_CORE_PLUGIN_DIR . 'includes/');
}
if (!defined('ZOHO_SYNC_CORE_ADMIN_DIR')) {
    define('ZOHO_SYNC_CORE_ADMIN_DIR', ZOHO_SYNC_CORE_PLUGIN_DIR . 'admin/');
}
if (!defined('ZOHO_SYNC_CORE_ADMIN_URL')) {
    define('ZOHO_SYNC_CORE_ADMIN_URL', ZOHO_SYNC_CORE_PLUGIN_URL . 'admin/');
}
if (!defined('ZOHO_SYNC_CORE_PLUGIN_BASENAME')) {
    define('ZOHO_SYNC_CORE_PLUGIN_BASENAME', plugin_basename(__FILE__));
}
if (!defined('ZOHO_SYNC_NONCE_ACTION')) {
    define('ZOHO_SYNC_NONCE_ACTION', 'zoho_sync_core_nonce_action');
}
if (!defined('ZOHO_SYNC_CORE_API_NAMESPACE')) {
    define('ZOHO_SYNC_CORE_API_NAMESPACE', 'zoho-sync-core/v1');
}

// Database table constants
global $wpdb;
if (!defined('ZOHO_SYNC_SETTINGS_TABLE')) {
    define('ZOHO_SYNC_SETTINGS_TABLE', $wpdb->prefix . 'zoho_sync_settings');
}
if (!defined('ZOHO_SYNC_LOGS_TABLE')) {
    define('ZOHO_SYNC_LOGS_TABLE', $wpdb->prefix . 'zoho_sync_logs');
}
if (!defined('ZOHO_SYNC_TOKENS_TABLE')) {
    define('ZOHO_SYNC_TOKENS_TABLE', $wpdb->prefix . 'zoho_sync_tokens');
}
if (!defined('ZOHO_SYNC_WEBHOOKS_TABLE')) {
    define('ZOHO_SYNC_WEBHOOKS_TABLE', $wpdb->prefix . 'zoho_sync_webhooks');
}

// Autoload clases
spl_autoload_register(function ($class) {
    $prefix = 'Zoho_Sync_Core_';
    $base_dir = ZOHO_SYNC_CORE_INCLUDES_DIR;

    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Instancia principal singleton - VERSIÓN COMPLETA PERO SEGURA
final class ZohoSyncCore {

    private static $instance = null;
    private $components = array();
    private $initialized = false;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Inicializar de forma segura cuando WordPress esté listo
        add_action('plugins_loaded', array($this, 'safe_init'), 10);
    }

    public function safe_init() {
        // Evitar inicialización múltiple
        if ($this->initialized) {
            return;
        }

        // Verificar que WordPress esté completamente cargado
        if (!did_action('init')) {
            add_action('init', array($this, 'safe_init'), 999);
            return;
        }

        $this->initialized = true;
        $this->setup_capabilities(); // Configurar capacidades primero
        $this->includes();
        $this->init_components();
        $this->init_hooks();
    }

    private function includes() {
        // Cargar archivos de clases de forma segura
        $files = array(
            'class-database-manager.php',
            'class-auth-manager.php',
            'class-settings-manager.php',
            'class-cron-manager.php',
            'class-webhook-handler.php',
            'class-api-client.php',
            'class-logger.php',
            'class-dependency-checker.php'
        );

        foreach ($files as $file) {
            $file_path = ZOHO_SYNC_CORE_INCLUDES_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }

        // Cargar archivos de admin
        $admin_files = array(
            'class-admin-pages.php',
            'class-admin-notices.php'
        );

        foreach ($admin_files as $file) {
            $file_path = ZOHO_SYNC_CORE_ADMIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    private function init_components() {
        // Inicializar componentes de forma segura
        try {
            if (class_exists('Zoho_Sync_Core_Logger')) {
                $this->components['logger'] = new Zoho_Sync_Core_Logger();
            }
            
            if (class_exists('Zoho_Sync_Core_Settings_Manager')) {
                $this->components['settings'] = new Zoho_Sync_Core_Settings_Manager();
            }
            
            if (class_exists('Zoho_Sync_Core_Auth_Manager')) {
                $this->components['auth'] = new Zoho_Sync_Core_Auth_Manager();
            }
            
            if (class_exists('Zoho_Sync_Core_API_Client')) {
                $this->components['api_client'] = new Zoho_Sync_Core_API_Client();
            }
            
            if (class_exists('Zoho_Sync_Core_Database_Manager')) {
                $this->components['database_manager'] = new Zoho_Sync_Core_Database_Manager();
            }
            
            if (class_exists('Zoho_Sync_Core_Cron_Manager')) {
                $this->components['cron_manager'] = new Zoho_Sync_Core_Cron_Manager();
            }
            
            if (class_exists('Zoho_Sync_Core_Webhook_Handler')) {
                $this->components['webhook_handler'] = new Zoho_Sync_Core_Webhook_Handler();
            }
            
            if (class_exists('Zoho_Sync_Core_Dependency_Checker')) {
                $this->components['dependency_checker'] = new Zoho_Sync_Core_Dependency_Checker();
            }
            
            if (class_exists('Zoho_Sync_Core_Admin_Pages')) {
                $this->components['admin_pages'] = new Zoho_Sync_Core_Admin_Pages();
            }
            
            if (class_exists('Zoho_Sync_Core_Admin_Notices')) {
                $this->components['admin_notices'] = new Zoho_Sync_Core_Admin_Notices();
            }
        } catch (Exception $e) {
            // Log error but don't break the plugin
            error_log('Zoho Sync Core: Error initializing components - ' . $e->getMessage());
        }
    }

    public function get_component($name) {
        return isset($this->components[$name]) ? $this->components[$name] : null;
    }

    /**
     * Setup custom capabilities
     */
    private function setup_capabilities() {
        $capabilities = array(
            'manage_zoho_sync',
            'view_zoho_sync_logs',
            'manage_zoho_sync_settings',
            'manage_zoho_sync_modules'
        );

        // Agregar capacidades al rol de administrador
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $capability) {
                $admin_role->add_cap($capability);
            }
        }

        // Agregar capacidades básicas al shop manager si WooCommerce está activo
        if (class_exists('WooCommerce')) {
            $shop_manager = get_role('shop_manager');
            if ($shop_manager) {
                $shop_manager->add_cap('manage_zoho_sync');
                $shop_manager->add_cap('view_zoho_sync_logs');
            }
        }
    }

    private function init_hooks() {
        // Solo hooks esenciales para evitar bucles infinitos
        register_activation_hook(ZOHO_SYNC_CORE_PLUGIN_FILE, array($this, 'on_activation'));
        register_deactivation_hook(ZOHO_SYNC_CORE_PLUGIN_FILE, array($this, 'on_deactivation'));

        // Hooks de WordPress de forma segura
        if (!has_action('admin_init', array($this, 'handle_zoho_auth_callback'))) {
            add_action('admin_init', array($this, 'handle_zoho_auth_callback'));
        }
        
        if (!has_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'))) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
        
        if (!has_action('wp_ajax_zoho_sync_core_check_connection', array($this, 'check_connection_ajax'))) {
            add_action('wp_ajax_zoho_sync_core_check_connection', array($this, 'check_connection_ajax'));
        }
    }

    public function on_activation() {
        // Crear tablas de base de datos
        if (class_exists('Zoho_Sync_Core_Database_Manager')) {
            Zoho_Sync_Core_Database_Manager::create_tables();
        }
    }

    public function on_deactivation() {
        // Limpiar cron jobs
        if (class_exists('Zoho_Sync_Core_Cron_Manager')) {
            Zoho_Sync_Core_Cron_Manager::unschedule_all_cron_jobs();
        }
    }

    public function handle_zoho_auth_callback() {
        if (isset($_GET['page']) && $_GET['page'] === 'zoho-sync-core' && isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $auth_manager = $this->get_component('auth');
            if ($auth_manager && method_exists($auth_manager, 'exchange_code_for_tokens')) {
                $auth_manager->exchange_code_for_tokens($code, 'com', admin_url('admin.php?page=zoho-sync-core'));
                wp_redirect(admin_url('admin.php?page=zoho-sync-core'));
                exit;
            }
        }
    }

    public function enqueue_admin_scripts($hook) {
        $admin_pages = array(
            'toplevel_page_zoho-sync-core',
            'zoho-sync-core_page_zoho-sync-dashboard',
            'zoho-sync-core_page_zoho-sync-settings',
            'zoho-sync-core_page_zoho-sync-auth',
            'zoho-sync-core_page_zoho-sync-modules',
            'zoho-sync-core_page_zoho-sync-logs',
            'zoho-sync-core_page_zoho-sync-system',
            'zoho-sync-core_page_zoho-sync-tools'
        );

        if (!in_array($hook, $admin_pages)) {
            return;
        }

        wp_enqueue_style('zoho-sync-core-admin', ZOHO_SYNC_CORE_ADMIN_URL . 'assets/css/admin-styles.css', array('wp-admin', 'dashicons'), ZOHO_SYNC_CORE_VERSION);
        wp_enqueue_script('zoho-sync-core-admin', ZOHO_SYNC_CORE_ADMIN_URL . 'assets/js/admin.js', array('jquery'), ZOHO_SYNC_CORE_VERSION, true);

        wp_localize_script('zoho-sync-core-admin', 'zohoSyncCore', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'check_connection_nonce' => wp_create_nonce('zoho_sync_core_check_connection')
        ));
    }

    public function check_connection_ajax() {
        check_ajax_referer('zoho_sync_core_check_connection', 'nonce');
        
        $settings = $this->get_component('settings');
        if (!$settings) {
            wp_send_json_error(array('message' => 'Settings manager not available'));
            return;
        }

        $client_id = $settings->get('zoho_client_id', '');
        $client_secret = $settings->get('zoho_client_secret', '');
        $refresh_token = $settings->get('zoho_refresh_token', '');
        
        $auth_manager = $this->get_component('auth');
        if (!$auth_manager || !method_exists($auth_manager, 'validate_credentials')) {
            wp_send_json_error(array('message' => 'Auth manager not available'));
            return;
        }

        $result = $auth_manager->validate_credentials($client_id, $client_secret, $refresh_token);
        
        if ($result['valid']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
        wp_die();
    }

    /**
     * Get system status for dashboard
     */
    public function get_system_status() {
        return array(
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'wc_active' => class_exists('WooCommerce'),
            'wc_version' => class_exists('WooCommerce') ? WC()->version : 'N/A',
            'ssl_enabled' => is_ssl(),
            'curl_enabled' => function_exists('curl_init'),
            'json_enabled' => function_exists('json_encode'),
            'mbstring_enabled' => extension_loaded('mbstring'),
            'openssl_enabled' => extension_loaded('openssl'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        );
    }

    /**
     * Get modules status for dashboard
     */
    public function get_modules_status() {
        return array(
            'zoho-sync-orders' => array(
                'name' => 'Zoho Sync Orders',
                'active' => is_plugin_active('zoho-sync-orders/zoho-sync-orders.php'),
                'version' => defined('ZOHO_SYNC_ORDERS_VERSION') ? ZOHO_SYNC_ORDERS_VERSION : 'N/A'
            ),
            'zoho-sync-customers' => array(
                'name' => 'Zoho Sync Customers',
                'active' => is_plugin_active('zoho-sync-customers/zoho-sync-customers.php'),
                'version' => defined('ZOHO_SYNC_CUSTOMERS_VERSION') ? ZOHO_SYNC_CUSTOMERS_VERSION : 'N/A'
            ),
            'zoho-sync-products' => array(
                'name' => 'Zoho Sync Products',
                'active' => is_plugin_active('zoho-sync-products/zoho-sync-products.php'),
                'version' => defined('ZOHO_SYNC_PRODUCTS_VERSION') ? ZOHO_SYNC_PRODUCTS_VERSION : 'N/A'
            ),
            'zoho-sync-inventory' => array(
                'name' => 'Zoho Sync Inventory',
                'active' => is_plugin_active('zoho-sync-inventory/zoho-sync-inventory.php'),
                'version' => defined('ZOHO_SYNC_INVENTORY_VERSION') ? ZOHO_SYNC_INVENTORY_VERSION : 'N/A'
            ),
            'zoho-sync-zone-blocker' => array(
                'name' => 'Zoho Sync Zone Blocker',
                'active' => is_plugin_active('zoho-sync-zone-blocker/zoho-sync-zone-blocker.php'),
                'version' => defined('ZOHO_SYNC_ZONE_BLOCKER_VERSION') ? ZOHO_SYNC_ZONE_BLOCKER_VERSION : 'N/A'
            ),
            'zoho-sync-reports' => array(
                'name' => 'Zoho Sync Reports',
                'active' => is_plugin_active('zoho-sync-reports/zoho-sync-reports.php'),
                'version' => defined('ZOHO_SYNC_REPORTS_VERSION') ? ZOHO_SYNC_REPORTS_VERSION : 'N/A'
            ),
            'zoho-distributor-portal' => array(
                'name' => 'Zoho Distributor Portal',
                'active' => is_plugin_active('zoho-distributor-portal/zoho-distributor-portal.php'),
                'version' => defined('ZOHO_DISTRIBUTOR_PORTAL_VERSION') ? ZOHO_DISTRIBUTOR_PORTAL_VERSION : 'N/A'
            )
        );
    }

    /**
     * Get connection status
     */
    public function get_connection_status() {
        $settings = $this->get_component('settings');
        if (!$settings) {
            return array(
                'connected' => false,
                'message' => 'Settings manager not available'
            );
        }

        $client_id = $settings->get('zoho_client_id', '');
        $client_secret = $settings->get('zoho_client_secret', '');
        $refresh_token = $settings->get('zoho_refresh_token', '');

        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            return array(
                'connected' => false,
                'message' => 'API credentials not configured'
            );
        }

        return array(
            'connected' => true,
            'message' => 'API credentials configured'
        );
    }
}

// Inicializar el plugin de forma segura
add_action('plugins_loaded', function() {
    ZohoSyncCore::instance();
}, 5);

/**
 * Helper functions para otros plugins
 */
function zoho_sync_core() {
    return ZohoSyncCore::instance();
}

function zoho_sync_core_logger() {
    $core = zoho_sync_core();
    return $core ? $core->get_component('logger') : null;
}

function zoho_sync_core_settings() {
    $core = zoho_sync_core();
    return $core ? $core->get_component('settings') : null;
}

function zoho_sync_core_auth() {
    $core = zoho_sync_core();
    return $core ? $core->get_component('auth') : null;
}

function zoho_sync_core_api() {
    $core = zoho_sync_core();
    return $core ? $core->get_component('api_client') : null;
}

function zoho_sync_core_log($level, $message, $context = array()) {
    $logger = zoho_sync_core_logger();
    if ($logger && method_exists($logger, 'log')) {
        $logger->log($level, $message, $context);
    }
}
