<?php
/**
 * Plugin Name: Zoho Sync Reports
 * Description: Sistema de reportes de ventas B2B/B2C
 * Version: 1.0
 * Author: Tu Nombre
 * Text Domain: zoho-sync-reports
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('ZSRP_VERSION', '1.0');
define('ZSRP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZSRP_PLUGIN_URL', plugin_dir_url(__FILE__));

class Zoho_Sync_Reports {
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
                _e('El plugin Zoho Sync Reports requiere que Zoho Sync Core esté instalado y activado.', 'zoho-sync-reports');
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
        require_once ZSRP_PLUGIN_DIR . 'includes/class-reports-generator.php';
        require_once ZSRP_PLUGIN_DIR . 'includes/class-sales-analyzer.php';
        require_once ZSRP_PLUGIN_DIR . 'includes/class-b2b-calculator.php';
        require_once ZSRP_PLUGIN_DIR . 'includes/class-export-manager.php';
        require_once ZSRP_PLUGIN_DIR . 'includes/class-chart-generator.php';
        require_once ZSRP_PLUGIN_DIR . 'admin/class-reports-admin.php';
    }

    private function init_hooks() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_zsrp_generate_report', ['ZSRP_Reports_Generator', 'ajax_generate_report']);
        add_action('zsrp_weekly_report', ['ZSRP_Reports_Generator', 'generate_scheduled_report']);
    }

    public function register_module() {
        ZohoSyncCore::register_module('reports', [
            'name' => __('Reportes de Ventas', 'zoho-sync-reports'),
            'version' => ZSRP_VERSION,
            'dependencies' => ['core', 'woocommerce'],
            'settings' => [
                'auto_reports' => true,
                'report_frequency' => 'weekly',
                'email_recipients' => get_option('admin_email'),
                'default_format' => 'pdf'
            ]
        ]);
    }

    public function load_textdomain() {
        load_plugin_textdomain('zoho-sync-reports', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'zsrp-reports') === false) {
            return;
        }

        wp_enqueue_style('zsrp-admin-css', ZSRP_PLUGIN_URL . 'admin/assets/css/reports-admin.css', [], ZSRP_VERSION);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.7.0', true);
        wp_enqueue_script('zsrp-admin-js', ZSRP_PLUGIN_URL . 'admin/assets/js/reports-admin.js', ['jquery', 'chart-js'], ZSRP_VERSION, true);
        
        wp_localize_script('zsrp-admin-js', 'zsrpAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zsrp_admin_nonce'),
            'i18n' => [
                'generating' => __('Generando reporte...', 'zoho-sync-reports'),
                'error' => __('Error al generar reporte', 'zoho-sync-reports'),
                'success' => __('Reporte generado con éxito', 'zoho-sync-reports')
            ]
        ]);
    }
}

// Inicializar plugin
function zsrp_init() {
    return Zoho_Sync_Reports::instance();
}

add_action('plugins_loaded', 'zsrp_init');
