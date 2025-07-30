<?php
/**
 * Plugin Name: Zoho Sync Core
 * Description: Core plugin for Zoho synchronization.
 * Version: 5.0.0
 * Author: Jules
 * Text Domain: zoho-sync-core
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ZohoSyncCore {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    private function define_constants() {
        define('ZOHO_SYNC_CORE_VERSION', '5.0.0');
        define('ZOHO_SYNC_CORE_PLUGIN_FILE', __FILE__);
        define('ZOHO_SYNC_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('ZOHO_SYNC_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('ZOHO_SYNC_CORE_INCLUDES_DIR', ZOHO_SYNC_CORE_PLUGIN_DIR . 'includes/');
        define('ZOHO_SYNC_CORE_ADMIN_DIR', ZOHO_SYNC_CORE_PLUGIN_DIR . 'admin/');
        define('ZOHO_SYNC_CORE_ADMIN_URL', ZOHO_SYNC_CORE_PLUGIN_URL . 'admin/');
    }

    private function includes() {
        require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-autoloader.php';
        $autoloader = new Zoho_Sync_Core_Autoloader();
        $autoloader->register();
    }

    private function init_hooks() {
        register_activation_hook(ZOHO_SYNC_CORE_PLUGIN_FILE, array('Zoho_Sync_Core_Database_Manager', 'create_tables'));
        add_action('plugins_loaded', array($this, 'on_plugins_loaded'));
    }

    public function on_plugins_loaded() {
        if (is_admin()) {
            new Zoho_Sync_Core_Admin_Pages();
            add_action('admin_init', array($this, 'handle_zoho_auth_callback'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            add_action('wp_ajax_zoho_sync_core_check_connection', array($this, 'check_connection_ajax'));
        }
    }

    public function handle_zoho_auth_callback() {
        if (isset($_GET['page']) && $_GET['page'] === 'zoho-sync-core' && isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $auth_manager = new Zoho_Sync_Core_Auth_Manager();
            $auth_manager->exchange_code_for_tokens($code, 'inventory', 'com', admin_url('admin.php?page=zoho-sync-core'));
            wp_redirect(admin_url('admin.php?page=zoho-sync-core'));
            exit;
        }
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_zoho-sync-core' !== $hook) {
            return;
        }
        wp_enqueue_script('zoho-sync-core-admin', ZOHO_SYNC_CORE_ADMIN_URL . 'assets/js/admin.js', array('jquery'), ZOHO_SYNC_CORE_VERSION, true);
        wp_localize_script('zoho-sync-core-admin', 'zohoSyncCore', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'check_connection_nonce'   => wp_create_nonce('zoho_sync_core_check_connection')
        ));
    }

    public function check_connection_ajax() {
        check_ajax_referer('zoho_sync_core_check_connection', 'nonce');
        $options = get_option('zoho_sync_core_settings');
        $client_id = isset($options['zoho_client_id']) ? $options['zoho_client_id'] : '';
        $client_secret = isset($options['zoho_client_secret']) ? $options['zoho_client_secret'] : '';
        $refresh_token = isset($options['zoho_refresh_token']) ? $options['zoho_refresh_token'] : '';
        $auth_manager = new Zoho_Sync_Core_Auth_Manager();
        $result = $auth_manager->validate_credentials($client_id, $client_secret, $refresh_token);
        if ($result['valid']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
        wp_die();
    }
}

function zoho_sync_core() {
    return ZohoSyncCore::instance();
}

zoho_sync_core();
