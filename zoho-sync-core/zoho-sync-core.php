<?php
/**
 * Plugin Name: Zoho Sync Core
 * Plugin URI: https://github.com/tu-usuario/zoho-sync-core
 * Description: Plugin central para la sincronización con Zoho. Proporciona servicios compartidos para todo el ecosistema de plugins de Zoho.
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://tu-sitio.com
 * Text Domain: zoho-sync-core
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('ZOHO_SYNC_CORE_VERSION', '1.0.0');
define('ZOHO_SYNC_CORE_PLUGIN_FILE', __FILE__);
define('ZOHO_SYNC_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZOHO_SYNC_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZOHO_SYNC_CORE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('ZOHO_SYNC_CORE_INCLUDES_DIR', ZOHO_SYNC_CORE_PLUGIN_DIR . 'includes/');
define('ZOHO_SYNC_CORE_ADMIN_DIR', ZOHO_SYNC_CORE_PLUGIN_DIR . 'admin/');
define('ZOHO_SYNC_CORE_ASSETS_URL', ZOHO_SYNC_CORE_PLUGIN_URL . 'assets/');
define('ZOHO_SYNC_CORE_LANGUAGES_DIR', ZOHO_SYNC_CORE_PLUGIN_DIR . 'languages/');

// Definir constantes de base de datos
define('ZOHO_SYNC_CORE_DB_VERSION', '1.0.0');
define('ZOHO_SYNC_CORE_SETTINGS_TABLE', 'zoho_sync_settings');
define('ZOHO_SYNC_CORE_LOGS_TABLE', 'zoho_sync_logs');
define('ZOHO_SYNC_CORE_TOKENS_TABLE', 'zoho_sync_tokens');
define('ZOHO_SYNC_CORE_MODULES_TABLE', 'zoho_sync_modules');

// Definir constantes de configuración
define('ZOHO_SYNC_CORE_LOG_RETENTION_DAYS', 30);
define('ZOHO_SYNC_CORE_API_TIMEOUT', 30);
define('ZOHO_SYNC_CORE_MAX_RETRY_ATTEMPTS', 3);

/**
 * Clase principal del plugin Zoho Sync Core
 */
final class ZohoSyncCore {
    
    /**
     * Instancia única del plugin
     * @var ZohoSyncCore
     */
    private static $instance = null;
    
    /**
     * Instancias de las clases principales
     */
    public $core;
    public $auth_manager;
    public $settings_manager;
    public $logger;
    public $api_client;
    public $dashboard;
    public $cron_manager;
    public $webhook_handler;
    public $dependency_checker;
    public $database_manager;
    
    /**
     * Constructor privado para implementar singleton
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Obtener la instancia única del plugin
     * @return ZohoSyncCore
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Prevenir clonación
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, __('No se permite clonar esta clase.', 'zoho-sync-core'), ZOHO_SYNC_CORE_VERSION);
    }
    
    /**
     * Prevenir deserialización
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, __('No se permite deserializar esta clase.', 'zoho-sync-core'), ZOHO_SYNC_CORE_VERSION);
    }
    
    /**
     * Inicializar hooks de WordPress
     */
    private function init_hooks() {
        // Hook de activación
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Hook de desactivación
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Inicializar el plugin después de que WordPress esté completamente cargado
        add_action('plugins_loaded', array($this, 'init'), 10);
        
        // Cargar traducciones
        add_action('init', array($this, 'load_textdomain'));
        
        // Verificar dependencias
        add_action('admin_init', array($this, 'check_dependencies'));

        // Inicializar componentes de admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'init_admin_components'));
        }
    }
    
    /**
     * Inicializar el plugin
     */
    public function init() {
        // Verificar versión mínima de PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return;
        }
        
        // Verificar versión mínima de WordPress
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return;
        }
        
        // Cargar archivos de clases
        $this->load_dependencies();
        
        // Inicializar componentes principales
        $this->init_components();
        
        // Hook personalizado después de la inicialización
        do_action('zoho_sync_core_loaded');
    }
    
    /**
     * Cargar dependencias del plugin
     */
    private function load_dependencies() {
        // Autoloader simple para las clases del plugin
        spl_autoload_register(array($this, 'autoload'));
        
        // Cargar archivos principales
        require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-core.php';
        require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-auth-manager.php';
        require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-settings-manager.php';
        require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-logger.php';
        require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-api-client.php';
        require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-dashboard.php';
        require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-cron-manager.php';
        require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-webhook-handler.php';
        require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-dependency-checker.php';
        require_once ZOHO_SYNC_CORE_PLUGIN_DIR . 'database/class-database-manager.php';
        
        // Cargar archivos de administración si estamos en el admin
        if (is_admin()) {
            require_once ZOHO_SYNC_CORE_ADMIN_DIR . 'class-admin-pages.php';
            require_once ZOHO_SYNC_CORE_ADMIN_DIR . 'class-admin-notices.php';
        }
    }
    
    /**
     * Autoloader para las clases del plugin
     */
    public function autoload($class_name) {
        // Solo cargar clases de nuestro plugin
        if (strpos($class_name, 'Zoho_Sync_Core_') !== 0) {
            return;
        }
        
        // Convertir nombre de clase a nombre de archivo
        $class_file = strtolower(str_replace('_', '-', $class_name));
        $class_file = str_replace('zoho-sync-core-', '', $class_file);
        $file_path = ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-' . $class_file . '.php';
        
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
    
    /**
     * Inicializar componentes principales
     */
    private function init_components() {
        // Inicializar gestor de base de datos
        $this->database_manager = new Zoho_Sync_Core_Database_Manager();
        
        // Inicializar verificador de dependencias
        $this->dependency_checker = new Zoho_Sync_Core_Dependency_Checker();
        
        // Inicializar logger
        $this->logger = new Zoho_Sync_Core_Logger();
        
        // Inicializar gestor de configuraciones
        $this->settings_manager = new Zoho_Sync_Core_Settings_Manager();
        
        // Inicializar gestor de autenticación
        $this->auth_manager = new Zoho_Sync_Core_Auth_Manager();
        
        // Inicializar cliente API
        $this->api_client = new Zoho_Sync_Core_Api_Client();
        
        // Inicializar gestor de cron
        $this->cron_manager = new Zoho_Sync_Core_Cron_Manager();
        
        // Inicializar manejador de webhooks
        $this->webhook_handler = new Zoho_Sync_Core_Webhook_Handler();
        
        // Inicializar dashboard
        $this->dashboard = new Zoho_Sync_Core_Dashboard();
        
        // Inicializar core principal
        $this->core = new Zoho_Sync_Core_Core();
        
        // Los componentes de administración se inicializan en su propio hook
    }

    /**
     * Inicializar componentes de administración
     */
    public function init_admin_components() {
        new Zoho_Sync_Core_Admin_Pages();
        new Zoho_Sync_Core_Admin_Notices();
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Crear tablas de base de datos
        if ($this->database_manager) {
            $this->database_manager->create_tables();
        }
        
        // Configurar tareas programadas
        if ($this->cron_manager) {
            $this->cron_manager->schedule_events();
        }
        
        // Limpiar rewrite rules
        flush_rewrite_rules();
        
        // Log de activación
        if ($this->logger) {
            $this->logger->info('Plugin Zoho Sync Core activado', array('version' => ZOHO_SYNC_CORE_VERSION));
        }
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Limpiar tareas programadas
        if ($this->cron_manager) {
            $this->cron_manager->clear_scheduled_events();
        }
        
        // Limpiar rewrite rules
        flush_rewrite_rules();
        
        // Log de desactivación
        if ($this->logger) {
            $this->logger->info('Plugin Zoho Sync Core desactivado');
        }
    }
    
    /**
     * Cargar traducciones
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'zoho-sync-core',
            false,
            dirname(ZOHO_SYNC_CORE_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Verificar dependencias del sistema
     */
    public function check_dependencies() {
        if ($this->dependency_checker) {
            $this->dependency_checker->check_all_dependencies();
        }
    }
    
    /**
     * Aviso de versión de PHP
     */
    public function php_version_notice() {
        $message = sprintf(
            __('Zoho Sync Core requiere PHP versión 7.4 o superior. Tu versión actual es %s.', 'zoho-sync-core'),
            PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
    
    /**
     * Aviso de versión de WordPress
     */
    public function wp_version_notice() {
        $message = sprintf(
            __('Zoho Sync Core requiere WordPress versión 5.0 o superior. Tu versión actual es %s.', 'zoho-sync-core'),
            get_bloginfo('version')
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
    
    /**
     * Métodos estáticos para acceso global
     */
    
    /**
     * Obtener instancia del logger
     * @return Zoho_Sync_Core_Logger
     */
    public static function logger() {
        return self::instance()->logger;
    }
    
    /**
     * Obtener instancia del gestor de configuraciones
     * @return Zoho_Sync_Core_Settings_Manager
     */
    public static function settings() {
        return self::instance()->settings_manager;
    }
    
    /**
     * Obtener instancia del cliente API
     * @return Zoho_Sync_Core_Api_Client
     */
    public static function api() {
        return self::instance()->api_client;
    }
    
    /**
     * Obtener instancia del gestor de autenticación
     * @return Zoho_Sync_Core_Auth_Manager
     */
    public static function auth() {
        return self::instance()->auth_manager;
    }
    
    /**
     * Registrar un módulo en el ecosistema
     * @param string $module_name Nombre del módulo
     * @param array $config Configuración del módulo
     */
    public static function register_module($module_name, $config = array()) {
        return self::instance()->core->register_module($module_name, $config);
    }
    
    /**
     * Escribir log desde cualquier parte del ecosistema
     * @param string $level Nivel del log
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @param string $module Módulo origen
     */
    public static function log($level, $message, $context = array(), $module = 'core') {
        if (self::instance()->logger) {
            self::instance()->logger->log($level, $message, $context, $module);
        }
    }
}

/**
 * Función principal para obtener la instancia del plugin
 * @return ZohoSyncCore
 */
function zoho_sync_core() {
    return ZohoSyncCore::instance();
}

// Inicializar el plugin
zoho_sync_core();
