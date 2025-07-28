<?php
/**
 * Plugin Name: Zoho Distributor Portal
 * Description: Portal B2B para distribuidores con integración completa del ecosistema Zoho
 * Version: 1.0
 * Author: Tu Nombre
 * Text Domain: zoho-distributor-portal
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('ZSDP_VERSION', '1.0');
define('ZSDP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZSDP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZSDP_MIN_CORE_VERSION', '1.0');

class Zoho_Distributor_Portal {
    private static $instance = null;
    private $dependencies = [];
    private $module_status = [];

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Verificar dependencias core y módulos opcionales
        add_action('plugins_loaded', [$this, 'check_dependencies'], 5);
        add_action('plugins_loaded', [$this, 'initialize_portal'], 15);
        
        // Registrar hooks de activación/desactivación
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function check_dependencies() {
        $this->dependencies = [
            'core' => [
                'plugin' => 'zoho-sync-core/zoho-sync-core.php',
                'name' => 'Zoho Sync Core',
                'required' => true,
                'min_version' => ZSDP_MIN_CORE_VERSION
            ],
            'orders' => [
                'plugin' => 'zoho-sync-orders/zoho-sync-orders.php',
                'name' => 'Zoho Sync Orders',
                'required' => false
            ],
            'customers' => [
                'plugin' => 'zoho-sync-customers/zoho-sync-customers.php',
                'name' => 'Zoho Sync Customers',
                'required' => false
            ],
            'inventory' => [
                'plugin' => 'zoho-sync-inventory/zoho-sync-inventory.php',
                'name' => 'Zoho Sync Inventory',
                'required' => false
            ],
            'reports' => [
                'plugin' => 'zoho-sync-reports/zoho-sync-reports.php',
                'name' => 'Zoho Sync Reports',
                'required' => false
            ],
            'zones' => [
                'plugin' => 'zoho-sync-zone-blocker/zoho-sync-zone-blocker.php',
                'name' => 'Zoho Sync Zone Blocker',
                'required' => false
            ]
        ];

        foreach ($this->dependencies as $key => $dependency) {
            $this->module_status[$key] = $this->check_module($dependency);
        }

        // Verificar dependencias requeridas
        if (!$this->module_status['core']['active']) {
            add_action('admin_notices', [$this, 'core_missing_notice']);
            return false;
        }

        return true;
    }

    private function check_module($dependency) {
        $status = [
            'active' => false,
            'installed' => false,
            'version_ok' => true
        ];

        if (file_exists(WP_PLUGIN_DIR . '/' . $dependency['plugin'])) {
            $status['installed'] = true;
            if (is_plugin_active($dependency['plugin'])) {
                $status['active'] = true;
                if (isset($dependency['min_version'])) {
                    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $dependency['plugin']);
                    $status['version_ok'] = version_compare($plugin_data['Version'], $dependency['min_version'], '>=');
                }
            }
        }

        return $status;
    }

    public function initialize_portal() {
        if (!$this->module_status['core']['active']) {
            return;
        }

        $this->includes();
        $this->init_hooks();

        // Registrar el módulo en el core
        add_action('zoho_sync_core_loaded', [$this, 'register_module']);

        // Inicializar componentes según módulos disponibles
        if ($this->module_status['orders']['active']) {
            new ZSDP_Orders_Integration();
        }
        if ($this->module_status['inventory']['active']) {
            new ZSDP_Inventory_Integration();
        }
        // ... etc para cada módulo
    }

    private function includes() {
        // Core del portal
        require_once ZSDP_PLUGIN_DIR . 'includes/class-distributor-portal.php';
        require_once ZSDP_PLUGIN_DIR . 'includes/class-account-endpoints.php';
        require_once ZSDP_PLUGIN_DIR . 'includes/class-portal-security.php';
        
        // Integraciones
        require_once ZSDP_PLUGIN_DIR . 'integrations/abstract-module-integration.php';
        foreach ($this->module_status as $module => $status) {
            if ($status['active']) {
                require_once ZSDP_PLUGIN_DIR . "integrations/class-{$module}-integration.php";
            }
        }

        // Admin
        if (is_admin()) {
            require_once ZSDP_PLUGIN_DIR . 'admin/class-portal-admin.php';
        }
    }

    public function register_module() {
        ZohoSyncCore::register_module('distributor_portal', [
            'name' => __('Portal de Distribuidores', 'zoho-distributor-portal'),
            'version' => ZSDP_VERSION,
            'dependencies' => array_keys($this->module_status),
            'settings' => [
                'default_role' => 'distributor',
                'enable_support' => true,
                'enable_whatsapp' => true,
                'portal_theme' => 'default'
            ]
        ]);
    }

    public function core_missing_notice() {
        echo '<div class="error"><p>';
        printf(
            __('El plugin %s requiere que Zoho Sync Core esté instalado y activado.', 'zoho-distributor-portal'),
            '<strong>Zoho Distributor Portal</strong>'
        );
        echo '</p></div>';
    }

    public function activate() {
        if (!$this->check_dependencies()) {
            wp_die(
                __('Este plugin requiere Zoho Sync Core para funcionar.', 'zoho-distributor-portal'),
                'Error de Activación',
                ['back_link' => true]
            );
        }

        // Crear roles y capacidades
        $this->create_distributor_role();
        
        // Crear páginas necesarias
        $this->create_portal_pages();

        // Registrar endpoints
        flush_rewrite_rules();
    }

    private function create_distributor_role() {
        add_role('distributor', __('Distribuidor', 'zoho-distributor-portal'), [
            'read' => true,
            'view_portal' => true,
            'access_special_pricing' => true,
            'view_distributor_reports' => true
        ]);
    }

    private function create_portal_pages() {
        // Crear páginas necesarias si no existen
        $pages = [
            'portal' => [
                'title' => __('Portal de Distribuidores', 'zoho-distributor-portal'),
                'content' => '[distributor_portal]'
            ],
            'special-pricing' => [
                'title' => __('Precios Especiales', 'zoho-distributor-portal'),
                'content' => '[distributor_special_pricing]'
            ]
        ];

        foreach ($pages as $slug => $page) {
            if (!get_page_by_path($slug)) {
                wp_insert_post([
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug
                ]);
            }
        }
    }
}

function zsdp_init() {
    return Zoho_Distributor_Portal::instance();
}

add_action('plugins_loaded', 'zsdp_init');
