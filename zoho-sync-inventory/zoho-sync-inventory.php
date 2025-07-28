<?php
/**
 * Plugin Name: Zoho Sync Inventory
 * Description: Sincronización de inventario entre WooCommerce y Zoho
 * Version: 1.0
 * Author: Tu Nombre
 * Text Domain: zoho-sync-inventory
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('ZSIV_VERSION', '1.0');
define('ZSIV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZSIV_PLUGIN_URL', plugin_dir_url(__FILE__));

class Zoho_Sync_Inventory {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Verificar dependencias
        if (!class_exists('ZohoSyncCore')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                _e('El plugin Zoho Sync Inventory requiere que Zoho Sync Core esté instalado y activado.', 'zoho-sync-inventory');
                echo '</p></div>';
            });
            return;
        }

        $this->includes();
        $this->init_hooks();

        // Registrar el módulo en el core
        add_action('zoho_sync_core_loaded', [$this, 'register_module']);
    }

    private function includes() {
        require_once ZSIV_PLUGIN_DIR . 'includes/class-inventory-sync.php';
        require_once ZSIV_PLUGIN_DIR . 'includes/class-stock-manager.php';
        require_once ZSIV_PLUGIN_DIR . 'includes/class-low-stock-monitor.php';
        require_once ZSIV_PLUGIN_DIR . 'admin/class-inventory-admin.php';
    }

    private function init_hooks() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('woocommerce_product_set_stock', ['ZSIV_Stock_Manager', 'handle_stock_change']);
        add_action('woocommerce_variation_set_stock', ['ZSIV_Stock_Manager', 'handle_stock_change']);
    }

    public function register_module() {
        ZohoSyncCore::register_module('inventory', [
            'name' => __('Sincronización de Inventario', 'zoho-sync-inventory'),
            'version' => ZSIV_VERSION,
            'dependencies' => ['core', 'woocommerce'],
            'settings' => [
                'sync_interval' => 'hourly',
                'low_stock_threshold' => 5,
                'enable_notifications' => true
            ]
        ]);
    }

    public function load_textdomain() {
        load_plugin_textdomain('zoho-sync-inventory', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

// Inicializar plugin
function zsiv_init() {
    return Zoho_Sync_Inventory::instance();
}

add_action('plugins_loaded', 'zsiv_init');