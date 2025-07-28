<?php

/**
 * Plugin Name: Zoho Sync Zone Blocker
 * Description: Bloqueo de acceso por zona postal.
 * Version: 1.0
 * Author: Tu Nombre
 * Text Domain: zoho-sync-zone-blocker
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Definir constantes del plugin
define('ZSZB_VERSION', '1.0');
define('ZSZB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZSZB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autocarga de clases
spl_autoload_register(function ($class) {
    if (strpos($class, 'ZSZB_') === 0) {
        $file = ZSZB_PLUGIN_DIR . 'includes/' . strtolower(str_replace('ZSZB_', 'class-', $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Activación/desactivación
register_activation_hook(__FILE__, ['ZSZB_Zone_Blocker', 'activate']);
register_deactivation_hook(__FILE__, ['ZSZB_Zone_Blocker', 'deactivate']);

// Inicializar plugin
add_action('plugins_loaded', function () {
    // Verificar dependencias
    if (!class_exists('ZohoSyncCore')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            _e('El plugin Zoho Sync Zone Blocker requiere que Zoho Sync Core esté instalado y activado.', 'zoho-sync-zone-blocker');
            echo '</p></div>';
        });
        return;
    }

    if (!class_exists('ZSZB_Zone_Blocker')) {
        require_once ZSZB_PLUGIN_DIR . 'includes/class-zone-blocker.php';
        require_once ZSZB_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once ZSZB_PLUGIN_DIR . 'includes/class-zoho-sync.php';
        require_once ZSZB_PLUGIN_DIR . 'includes/class-zone-reports.php';
        require_once ZSZB_PLUGIN_DIR . 'includes/class-zone-cache.php';
    }
    
    // Registrar el módulo en el core
    ZohoSyncCore::register_module('zone_blocker', [
        'name' => __('Bloqueo por Zona', 'zoho-sync-zone-blocker'),
        'version' => ZSZB_VERSION,
        'description' => __('Control de acceso por código postal', 'zoho-sync-zone-blocker'),
        'dependencies' => ['core', 'woocommerce'],
        'settings' => [
            'enable_geolocation' => true,
            'default_redirect' => home_url('/restricted'),
            'cache_time' => 3600
        ]
    ]);
    
    ZSZB_Zone_Blocker::instance();
    ZSZB_Ajax_Handler::init();
    new ZSZB_Zoho_Sync();
    
    // Hooks para el core
    add_filter('zoho_sync_api_request_args', function($args, $service) {
        if ($service === 'territories') {
            $args['module'] = 'zone_blocker';
        }
        return $args;
    }, 10, 2);
    
    // Limpiar caché al actualizar zonas
    add_action('edited_term', function($term_id, $tt_id, $taxonomy) {
        if ($taxonomy === 'postal_zone') {
            ZSZB_Zone_Cache::delete_zone_cache($term_id);
            
            // Notificar al core
            do_action('zoho_sync_data_updated', 'zone_blocker', 'zone', $term_id);
        }
    }, 10, 3);
});

// Registrar scripts y estilos
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'zszb-frontend',
        ZSZB_PLUGIN_URL . 'assets/css/frontend.css',
        [],
        ZSZB_VERSION
    );

    wp_enqueue_script(
        'zszb-frontend',
        ZSZB_PLUGIN_URL . 'assets/js/frontend.js',
        ['jquery'],
        ZSZB_VERSION,
        true
    );

    wp_localize_script('zszb-frontend', 'zszbFront', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('zszb_check_postal'),
        'i18n' => [
            'errorConnection' => __('Error de conexión. Por favor intenta nuevamente.', 'zoho-sync-zone-blocker'),
            'invalidPostal' => __('Por favor ingresa un código postal válido.', 'zoho-sync-zone-blocker')
        ]
    ]);
});

// Registrar endpoints de la API REST
add_action('rest_api_init', function() {
    register_rest_route('zoho-sync/v1', '/zones', [
        'methods' => 'GET',
        'callback' => ['ZSZB_Zone_Manager', 'get_zones_api'],
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});
