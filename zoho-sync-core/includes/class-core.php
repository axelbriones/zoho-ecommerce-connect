<?php
/**
 * Core Class
 * 
 * Main core functionality for Zoho Sync Core plugin
 * 
 * @package ZohoSyncCore
 * @subpackage Core
 * @since 8.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Zoho Sync Core Main Class
 * 
 * @class Zoho_Sync_Core_Core
 * @version 8.0.0
 * @since 8.0.0
 */
class Zoho_Sync_Core_Core {

    /**
     * Plugin version
     * 
     * @var string
     */
    public $version = '8.0.0';

    /**
     * Minimum WordPress version
     * 
     * @var string
     */
    public $min_wp_version = '5.0';

    /**
     * Minimum WooCommerce version
     * 
     * @var string
     */
    public $min_wc_version = '4.0';

    /**
     * Plugin capabilities
     * 
     * @var array
     */
    private $capabilities = array(
        'manage_zoho_sync',
        'view_zoho_sync_logs',
        'manage_zoho_sync_settings',
        'manage_zoho_sync_modules'
    );

    /**
     * Supported Zoho services
     * 
     * @var array
     */
    private $supported_services = array(
        'inventory' => array(
            'name' => 'Zoho Inventory',
            'scopes' => 'ZohoInventory.FullAccess.all',
            'required' => true
        ),
        'crm' => array(
            'name' => 'Zoho CRM',
            'scopes' => 'ZohoCRM.modules.ALL,ZohoCRM.settings.ALL',
            'required' => true
        ),
        'books' => array(
            'name' => 'Zoho Books',
            'scopes' => 'ZohoBooks.FullAccess.all',
            'required' => false
        ),
        'creator' => array(
            'name' => 'Zoho Creator',
            'scopes' => 'ZohoCreator.form.CREATE,ZohoCreator.report.READ',
            'required' => false
        )
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize core functionality
     */
    private function init() {
        add_action('init', array($this, 'setup_capabilities'));
        add_action('wp_loaded', array($this, 'check_system_status'));
        add_filter('plugin_action_links_' . ZOHO_SYNC_CORE_PLUGIN_BASENAME, array($this, 'add_action_links'));
        add_filter('plugin_row_meta', array($this, 'add_row_meta'), 10, 2);
    }

    /**
     * Setup custom capabilities
     */
    public function setup_capabilities() {
        $role = get_role('administrator');
        
        if ($role) {
            foreach ($this->capabilities as $capability) {
                $role->add_cap($capability);
            }
        }

        // Add capabilities to shop manager if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $shop_manager = get_role('shop_manager');
            if ($shop_manager) {
                $shop_manager->add_cap('manage_zoho_sync');
                $shop_manager->add_cap('view_zoho_sync_logs');
            }
        }
    }

    /**
     * Check system status
     */
    public function check_system_status() {
        $status = array(
            'php_version' => version_compare(PHP_VERSION, '7.4', '>='),
            'wp_version' => version_compare(get_bloginfo('version'), $this->min_wp_version, '>='),
            'wc_active' => class_exists('WooCommerce'),
            'wc_version' => class_exists('WooCommerce') ? version_compare(WC()->version, $this->min_wc_version, '>=') : false,
            'ssl_enabled' => is_ssl(),
            'curl_enabled' => function_exists('curl_init'),
            'json_enabled' => function_exists('json_encode'),
            'mbstring_enabled' => extension_loaded('mbstring'),
            'openssl_enabled' => extension_loaded('openssl')
        );

        // Store system status
        update_option('zoho_sync_core_system_status', $status);

        // Check for critical issues
        $critical_issues = array();
        
        if (!$status['php_version']) {
            $critical_issues[] = sprintf(
                __('PHP versión %s o superior requerida. Versión actual: %s', 'zoho-sync-core'),
                '7.4',
                PHP_VERSION
            );
        }

        if (!$status['wp_version']) {
            $critical_issues[] = sprintf(
                __('WordPress versión %s o superior requerida. Versión actual: %s', 'zoho-sync-core'),
                $this->min_wp_version,
                get_bloginfo('version')
            );
        }

        if (!$status['wc_active']) {
            $critical_issues[] = __('WooCommerce es requerido para el funcionamiento completo del plugin.', 'zoho-sync-core');
        } elseif (!$status['wc_version']) {
            $critical_issues[] = sprintf(
                __('WooCommerce versión %s o superior requerida. Versión actual: %s', 'zoho-sync-core'),
                $this->min_wc_version,
                WC()->version
            );
        }

        if (!$status['curl_enabled']) {
            $critical_issues[] = __('La extensión cURL de PHP es requerida para las conexiones con Zoho.', 'zoho-sync-core');
        }

        if (!$status['openssl_enabled']) {
            $critical_issues[] = __('La extensión OpenSSL de PHP es requerida para conexiones seguras.', 'zoho-sync-core');
        }

        // Store critical issues
        update_option('zoho_sync_core_critical_issues', $critical_issues);

        // Log system status check
        zoho_sync_core_log('info', 'Verificación de estado del sistema completada', array(
            'status' => $status,
            'critical_issues_count' => count($critical_issues)
        ));

        return $status;
    }

    /**
     * Add plugin action links
     * 
     * @param array $links
     * @return array
     */
    public function add_action_links($links) {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=zoho-sync-core') . '">' . __('Configuración', 'zoho-sync-core') . '</a>',
            'dashboard' => '<a href="' . admin_url('admin.php?page=zoho-sync-dashboard') . '">' . __('Dashboard', 'zoho-sync-core') . '</a>'
        );

        return array_merge($action_links, $links);
    }

    /**
     * Add plugin row meta
     * 
     * @param array $links
     * @param string $file
     * @return array
     */
    public function add_row_meta($links, $file) {
        if (ZOHO_SYNC_CORE_PLUGIN_BASENAME === $file) {
            $row_meta = array(
                'docs' => '<a href="https://bbrion.es/zoho-ecommerce-connect" target="_blank">' . __('Documentación', 'zoho-sync-core') . '</a>',
                'support' => '<a href="https://bbrion.es/contacto" target="_blank">' . __('Soporte', 'zoho-sync-core') . '</a>',
                'author' => '<a href="https://bbrion.es" target="_blank">' . __('Byron Briones', 'zoho-sync-core') . '</a>'
            );

            return array_merge($links, $row_meta);
        }

        return $links;
    }

    /**
     * Get supported services
     * 
     * @return array
     */
    public function get_supported_services() {
        return $this->supported_services;
    }

    /**
     * Get service scopes
     * 
     * @param string $service
     * @return string|false
     */
    public function get_service_scopes($service) {
        if (isset($this->supported_services[$service])) {
            return $this->supported_services[$service]['scopes'];
        }
        return false;
    }

    /**
     * Check if service is required
     * 
     * @param string $service
     * @return bool
     */
    public function is_service_required($service) {
        if (isset($this->supported_services[$service])) {
            return $this->supported_services[$service]['required'];
        }
        return false;
    }

    /**
     * Get all required scopes
     * 
     * @return string
     */
    public function get_all_scopes() {
        $scopes = array();
        
        foreach ($this->supported_services as $service) {
            $scopes[] = $service['scopes'];
        }

        return implode(',', $scopes);
    }

    /**
     * Get system requirements
     * 
     * @return array
     */
    public function get_system_requirements() {
        return array(
            'php_version' => '7.4+',
            'wp_version' => $this->min_wp_version . '+',
            'wc_version' => $this->min_wc_version . '+',
            'extensions' => array(
                'curl' => __('cURL - Para conexiones HTTP', 'zoho-sync-core'),
                'json' => __('JSON - Para procesamiento de datos', 'zoho-sync-core'),
                'mbstring' => __('Multibyte String - Para manejo de caracteres', 'zoho-sync-core'),
                'openssl' => __('OpenSSL - Para conexiones seguras', 'zoho-sync-core')
            ),
            'server' => array(
                'ssl' => __('SSL/HTTPS - Requerido para OAuth', 'zoho-sync-core'),
                'memory_limit' => __('Memoria PHP mínima: 128MB', 'zoho-sync-core'),
                'max_execution_time' => __('Tiempo de ejecución mínimo: 60 segundos', 'zoho-sync-core')
            )
        );
    }

    /**
     * Get plugin capabilities
     * 
     * @return array
     */
    public function get_capabilities() {
        return $this->capabilities;
    }

    /**
     * Check if user has capability
     * 
     * @param string $capability
     * @param int $user_id
     * @return bool
     */
    public function user_can($capability, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        return user_can($user_id, $capability);
    }

    /**
     * Get plugin info
     * 
     * @return array
     */
    public function get_plugin_info() {
        return array(
            'name' => 'Zoho Sync Core',
            'version' => $this->version,
            'author' => 'Byron Briones',
            'author_uri' => 'https://bbrion.es',
            'plugin_uri' => 'https://bbrion.es/zoho-ecommerce-connect',
            'description' => __('Plugin núcleo para la sincronización con Zoho. Base del ecosistema de 8 plugins interconectados.', 'zoho-sync-core'),
            'text_domain' => 'zoho-sync-core',
            'domain_path' => '/languages',
            'requires_wp' => $this->min_wp_version,
            'requires_wc' => $this->min_wc_version,
            'requires_php' => '7.4',
            'network' => false,
            'license' => 'GPL v2 or later',
            'license_uri' => 'https://www.gnu.org/licenses/gpl-2.0.html'
        );
    }

    /**
     * Get ecosystem plugins
     * 
     * @return array
     */
    public function get_ecosystem_plugins() {
        return array(
            'zoho-sync-core' => array(
                'name' => __('Zoho Sync Core', 'zoho-sync-core'),
                'description' => __('Plugin núcleo del ecosistema', 'zoho-sync-core'),
                'required' => true,
                'active' => true
            ),
            'zoho-sync-orders' => array(
                'name' => __('Zoho Sync Orders', 'zoho-sync-core'),
                'description' => __('Sincronización de pedidos', 'zoho-sync-core'),
                'required' => false,
                'active' => is_plugin_active('zoho-sync-orders/zoho-sync-orders.php')
            ),
            'zoho-sync-customers' => array(
                'name' => __('Zoho Sync Customers', 'zoho-sync-core'),
                'description' => __('Sincronización de clientes', 'zoho-sync-core'),
                'required' => false,
                'active' => is_plugin_active('zoho-sync-customers/zoho-sync-customers.php')
            ),
            'zoho-sync-products' => array(
                'name' => __('Zoho Sync Products', 'zoho-sync-core'),
                'description' => __('Sincronización de productos', 'zoho-sync-core'),
                'required' => false,
                'active' => is_plugin_active('zoho-sync-products/zoho-sync-products.php')
            ),
            'zoho-sync-inventory' => array(
                'name' => __('Zoho Sync Inventory', 'zoho-sync-core'),
                'description' => __('Sincronización de inventario', 'zoho-sync-core'),
                'required' => false,
                'active' => is_plugin_active('zoho-sync-inventory/zoho-sync-inventory.php')
            ),
            'zoho-sync-zone-blocker' => array(
                'name' => __('Zoho Sync Zone Blocker', 'zoho-sync-core'),
                'description' => __('Bloqueo por zonas postales', 'zoho-sync-core'),
                'required' => false,
                'active' => is_plugin_active('zoho-sync-zone-blocker/zoho-sync-zone-blocker.php')
            ),
            'zoho-sync-reports' => array(
                'name' => __('Zoho Sync Reports', 'zoho-sync-core'),
                'description' => __('Reportes de ventas B2B/B2C', 'zoho-sync-core'),
                'required' => false,
                'active' => is_plugin_active('zoho-sync-reports/zoho-sync-reports.php')
            ),
            'zoho-distributor-portal' => array(
                'name' => __('Zoho Distributor Portal', 'zoho-sync-core'),
                'description' => __('Portal de distribuidores B2B', 'zoho-sync-core'),
                'required' => false,
                'active' => is_plugin_active('zoho-distributor-portal/zoho-distributor-portal.php')
            )
        );
    }

    /**
     * Validate ecosystem integrity
     * 
     * @return array
     */
    public function validate_ecosystem() {
        $plugins = $this->get_ecosystem_plugins();
        $issues = array();
        $active_count = 0;

        foreach ($plugins as $slug => $plugin) {
            if ($plugin['active']) {
                $active_count++;
                
                // Check if plugin has proper integration
                $integration_hook = 'zoho_sync_' . str_replace('-', '_', str_replace('zoho-', '', $slug)) . '_integration';
                if (!has_action($integration_hook) && $slug !== 'zoho-sync-core') {
                    $issues[] = sprintf(
                        __('El plugin %s está activo pero no se ha integrado correctamente con el core.', 'zoho-sync-core'),
                        $plugin['name']
                    );
                }
            }
        }

        return array(
            'total_plugins' => count($plugins),
            'active_plugins' => $active_count,
            'issues' => $issues,
            'health_score' => $active_count > 1 ? round(($active_count / count($plugins)) * 100) : 0
        );
    }

    /**
     * Get version
     * 
     * @return string
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Check if debug mode is enabled
     * 
     * @return bool
     */
    public function is_debug_mode() {
        return defined('WP_DEBUG') && WP_DEBUG && zoho_sync_core_settings()->get('enable_debug', false);
    }

    /**
     * Get memory usage
     * 
     * @return array
     */
    public function get_memory_usage() {
        return array(
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'formatted' => array(
                'current' => size_format(memory_get_usage(true)),
                'peak' => size_format(memory_get_peak_usage(true)),
                'limit' => ini_get('memory_limit')
            )
        );
    }
}
