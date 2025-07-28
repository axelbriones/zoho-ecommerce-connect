<?php
/**
 * Clase Core Principal para Zoho Sync Core
 * 
 * @package ZohoSyncCore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Core para el ecosistema Zoho Sync
 * Coordina todos los componentes y proporciona API para otros plugins
 */
class Zoho_Sync_Core_Core {
    
    /**
     * Módulos registrados en el ecosistema
     * @var array
     */
    private $registered_modules = array();
    
    /**
     * Estado del sistema
     * @var array
     */
    private $system_status = array();
    
    /**
     * Hooks registrados
     * @var array
     */
    private $registered_hooks = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_system_status();
        $this->register_core_hooks();
        $this->load_registered_modules();
        
        // Hook para inicialización completa
        add_action('init', array($this, 'system_ready'), 20);
    }
    
    /**
     * Inicializar estado del sistema
     */
    private function init_system_status() {
        $this->system_status = array(
            'initialized' => false,
            'database_ready' => false,
            'auth_configured' => false,
            'modules_loaded' => false,
            'last_health_check' => null,
            'errors' => array(),
            'warnings' => array()
        );
    }
    
    /**
     * Registrar hooks principales del core
     */
    private function register_core_hooks() {
        // Hooks de WordPress
        add_action('wp_loaded', array($this, 'check_system_health'));
        add_action('admin_init', array($this, 'admin_init'));
        
        // Hooks personalizados del ecosistema
        add_action('zoho_sync_module_registered', array($this, 'on_module_registered'), 10, 2);
        add_action('zoho_sync_module_activated', array($this, 'on_module_activated'), 10, 1);
        add_action('zoho_sync_module_deactivated', array($this, 'on_module_deactivated'), 10, 1);
        
        // Hooks de limpieza
        add_action('zoho_sync_core_daily_cleanup', array($this, 'daily_cleanup'));
        if (!wp_next_scheduled('zoho_sync_core_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'zoho_sync_core_daily_cleanup');
        }
    }
    
    /**
     * Cargar módulos registrados desde la base de datos
     */
    private function load_registered_modules() {
        global $wpdb;
        $table_name = $wpdb->prefix . ZOHO_SYNC_CORE_MODULES_TABLE;
        
        $modules = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY module_name"
        );
        
        foreach ($modules as $module) {
            $this->registered_modules[$module->module_slug] = array(
                'name' => $module->module_name,
                'slug' => $module->module_slug,
                'version' => $module->version,
                'config' => json_decode($module->config, true) ?: array(),
                'dependencies' => json_decode($module->dependencies, true) ?: array(),
                'last_sync' => $module->last_sync,
                'sync_status' => $module->sync_status,
                'error_count' => $module->error_count,
                'last_error' => $module->last_error
            );
        }
        
        $this->system_status['modules_loaded'] = true;
    }
    
    /**
     * Sistema listo - inicialización completa
     */
    public function system_ready() {
        // Verificar que todos los componentes estén listos
        $this->check_system_readiness();
        
        if ($this->system_status['initialized']) {
            // Hook para notificar que el sistema está completamente listo
            do_action('zoho_sync_core_system_ready', $this->system_status);
            
            ZohoSyncCore::log('info', 'Sistema Zoho Sync Core completamente inicializado', array(
                'modules_count' => count($this->registered_modules),
                'system_status' => $this->system_status
            ));
        }
    }
    
    /**
     * Verificar que el sistema esté listo
     */
    private function check_system_readiness() {
        $errors = array();
        $warnings = array();
        
        // Verificar base de datos
        $db_manager = ZohoSyncCore::instance()->database_manager;
        if ($db_manager) {
            $table_integrity = $db_manager->check_tables_integrity();
            $missing_tables = array_filter($table_integrity, function($exists) {
                return !$exists;
            });
            
            if (empty($missing_tables)) {
                $this->system_status['database_ready'] = true;
            } else {
                $errors[] = sprintf(
                    __('Tablas de base de datos faltantes: %s', 'zoho-sync-core'),
                    implode(', ', array_keys($missing_tables))
                );
            }
        }
        
        // Verificar configuración de autenticación
        $client_id = ZohoSyncCore::settings()->get('zoho_client_id');
        $client_secret = ZohoSyncCore::settings()->get('zoho_client_secret');
        
        if (!empty($client_id) && !empty($client_secret)) {
            $this->system_status['auth_configured'] = true;
        } else {
            $warnings[] = __('Credenciales de Zoho no configuradas', 'zoho-sync-core');
        }
        
        // Verificar dependencias críticas
        $dependency_checker = ZohoSyncCore::instance()->dependency_checker;
        if ($dependency_checker) {
            $status_summary = $dependency_checker->get_status_summary();
            if ($status_summary['status'] === 'failed') {
                $errors[] = __('Dependencias críticas no cumplidas', 'zoho-sync-core');
            } elseif ($status_summary['status'] === 'warning') {
                $warnings[] = $status_summary['message'];
            }
        }
        
        // Actualizar estado del sistema
        $this->system_status['errors'] = $errors;
        $this->system_status['warnings'] = $warnings;
        $this->system_status['initialized'] = empty($errors);
        
        // Log de errores y advertencias
        if (!empty($errors)) {
            ZohoSyncCore::log('error', 'Errores en inicialización del sistema', array(
                'errors' => $errors
            ));
        }
        
        if (!empty($warnings)) {
            ZohoSyncCore::log('warning', 'Advertencias en inicialización del sistema', array(
                'warnings' => $warnings
            ));
        }
    }
    
    /**
     * Inicialización del admin
     */
    public function admin_init() {
        // Verificar permisos y mostrar notificaciones si es necesario
        if (current_user_can('manage_options')) {
            $this->maybe_show_admin_notices();
        }
    }
    
    /**
     * Mostrar notificaciones de admin si es necesario
     */
    private function maybe_show_admin_notices() {
        if (!$this->system_status['initialized']) {
            add_action('admin_notices', array($this, 'show_initialization_error_notice'));
        }
        
        if (!empty($this->system_status['warnings'])) {
            add_action('admin_notices', array($this, 'show_warnings_notice'));
        }
    }
    
    /**
     * Mostrar notificación de error de inicialización
     */
    public function show_initialization_error_notice() {
        $errors = $this->system_status['errors'];
        
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . __('Zoho Sync Core: Error de inicialización', 'zoho-sync-core') . '</strong><br>';
        echo implode('<br>', array_map('esc_html', $errors));
        echo '</p></div>';
    }
    
    /**
     * Mostrar notificación de advertencias
     */
    public function show_warnings_notice() {
        $warnings = $this->system_status['warnings'];
        
        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo '<strong>' . __('Zoho Sync Core: Advertencias del sistema', 'zoho-sync-core') . '</strong><br>';
        echo implode('<br>', array_map('esc_html', $warnings));
        echo '</p></div>';
    }
    
    /**
     * Registrar un módulo en el ecosistema
     * @param string $module_slug Slug único del módulo
     * @param array $config Configuración del módulo
     * @return bool Éxito del registro
     */
    public function register_module($module_slug, $config = array()) {
        // Validar configuración del módulo
        $validation = $this->validate_module_config($module_slug, $config);
        if (!$validation['valid']) {
            ZohoSyncCore::log('error', 'Error registrando módulo', array(
                'module_slug' => $module_slug,
                'error' => $validation['message']
            ));
            return false;
        }
        
        // Configuración por defecto
        $default_config = array(
            'name' => $module_slug,
            'version' => '1.0.0',
            'description' => '',
            'dependencies' => array(),
            'hooks' => array(),
            'capabilities' => array(),
            'settings' => array()
        );
        
        $config = array_merge($default_config, $config);
        
        // Guardar en base de datos
        global $wpdb;
        $table_name = $wpdb->prefix . ZOHO_SYNC_CORE_MODULES_TABLE;
        
        $data = array(
            'module_name' => $config['name'],
            'module_slug' => $module_slug,
            'version' => $config['version'],
            'is_active' => 1,
            'sync_status' => 'idle',
            'error_count' => 0,
            'config' => wp_json_encode($config),
            'dependencies' => wp_json_encode($config['dependencies']),
            'updated_at' => current_time('mysql')
        );
        
        // Verificar si ya existe
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE module_slug = %s",
                $module_slug
            )
        );
        
        if ($exists) {
            // Actualizar
            $result = $wpdb->update(
                $table_name,
                $data,
                array('module_slug' => $module_slug),
                array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s'),
                array('%s')
            );
        } else {
            // Insertar
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s')
            );
        }
        
        if ($result !== false) {
            // Actualizar cache local
            $this->registered_modules[$module_slug] = array(
                'name' => $config['name'],
                'slug' => $module_slug,
                'version' => $config['version'],
                'config' => $config,
                'dependencies' => $config['dependencies'],
                'last_sync' => null,
                'sync_status' => 'idle',
                'error_count' => 0,
                'last_error' => null
            );
            
            // Hook personalizado
            do_action('zoho_sync_module_registered', $module_slug, $config);
            
            ZohoSyncCore::log('info', 'Módulo registrado exitosamente', array(
                'module_slug' => $module_slug,
                'module_name' => $config['name'],
                'version' => $config['version']
            ));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Validar configuración de módulo
     * @param string $module_slug Slug del módulo
     * @param array $config Configuración
     * @return array Resultado de validación
     */
    private function validate_module_config($module_slug, $config) {
        $validation = array(
            'valid' => true,
            'message' => ''
        );
        
        // Validar slug
        if (empty($module_slug) || !preg_match('/^[a-z0-9-_]+$/', $module_slug)) {
            $validation['valid'] = false;
            $validation['message'] = __('Slug de módulo inválido', 'zoho-sync-core');
            return $validation;
        }
        
        // Validar nombre
        if (isset($config['name']) && empty($config['name'])) {
            $validation['valid'] = false;
            $validation['message'] = __('Nombre de módulo requerido', 'zoho-sync-core');
            return $validation;
        }
        
        // Validar versión
        if (isset($config['version']) && !preg_match('/^\d+\.\d+\.\d+$/', $config['version'])) {
            $validation['valid'] = false;
            $validation['message'] = __('Formato de versión inválido', 'zoho-sync-core');
            return $validation;
        }
        
        // Validar dependencias
        if (isset($config['dependencies']) && !is_array($config['dependencies'])) {
            $validation['valid'] = false;
            $validation['message'] = __('Dependencias deben ser un array', 'zoho-sync-core');
            return $validation;
        }
        
        return $validation;
    }
    
    /**
     * Desregistrar un módulo
     * @param string $module_slug Slug del módulo
     * @return bool Éxito de la operación
     */
    public function unregister_module($module_slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . ZOHO_SYNC_CORE_MODULES_TABLE;
        
        $result = $wpdb->delete(
            $table_name,
            array('module_slug' => $module_slug),
            array('%s')
        );
        
        if ($result !== false) {
            // Remover del cache local
            unset($this->registered_modules[$module_slug]);
            
            // Hook personalizado
            do_action('zoho_sync_module_unregistered', $module_slug);
            
            ZohoSyncCore::log('info', 'Módulo desregistrado', array(
                'module_slug' => $module_slug
            ));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener módulo registrado
     * @param string $module_slug Slug del módulo
     * @return array|null Datos del módulo
     */
    public function get_module($module_slug) {
        return $this->registered_modules[$module_slug] ?? null;
    }
    
    /**
     * Obtener todos los módulos registrados
     * @return array Módulos registrados
     */
    public function get_modules() {
        return $this->registered_modules;
    }
    
    /**
     * Verificar si un módulo está registrado
     * @param string $module_slug Slug del módulo
     * @return bool True si está registrado
     */
    public function is_module_registered($module_slug) {
        return isset($this->registered_modules[$module_slug]);
    }
    
    /**
     * Activar un módulo
     * @param string $module_slug Slug del módulo
     * @return bool Éxito de la operación
     */
    public function activate_module($module_slug) {
        if (!$this->is_module_registered($module_slug)) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . ZOHO_SYNC_CORE_MODULES_TABLE;
        
        $result = $wpdb->update(
            $table_name,
            array('is_active' => 1, 'updated_at' => current_time('mysql')),
            array('module_slug' => $module_slug),
            array('%d', '%s'),
            array('%s')
        );
        
        if ($result !== false) {
            // Hook personalizado
            do_action('zoho_sync_module_activated', $module_slug);
            
            ZohoSyncCore::log('info', 'Módulo activado', array(
                'module_slug' => $module_slug
            ));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Desactivar un módulo
     * @param string $module_slug Slug del módulo
     * @return bool Éxito de la operación
     */
    public function deactivate_module($module_slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . ZOHO_SYNC_CORE_MODULES_TABLE;
        
        $result = $wpdb->update(
            $table_name,
            array('is_active' => 0, 'updated_at' => current_time('mysql')),
            array('module_slug' => $module_slug),
            array('%d', '%s'),
            array('%s')
        );
        
        if ($result !== false) {
            // Hook personalizado
            do_action('zoho_sync_module_deactivated', $module_slug);
            
            ZohoSyncCore::log('info', 'Módulo desactivado', array(
                'module_slug' => $module_slug
            ));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Actualizar estado de sincronización de un módulo
     * @param string $module_slug Slug del módulo
     * @param string $status Estado de sincronización
     * @param string $error_message Mensaje de error (opcional)
     * @return bool Éxito de la operación
     */
    public function update_module_sync_status($module_slug, $status, $error_message = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . ZOHO_SYNC_CORE_MODULES_TABLE;
        
        $data = array(
            'sync_status' => $status,
            'last_sync' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        if ($error_message) {
            $data['last_error'] = $error_message;
            $data['error_count'] = new stdClass(); // Para incrementar
        }
        
        // Si hay error, incrementar contador
        if ($error_message) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table_name SET 
                     sync_status = %s, 
                     last_sync = %s, 
                     last_error = %s, 
                     error_count = error_count + 1,
                     updated_at = %s 
                     WHERE module_slug = %s",
                    $status,
                    current_time('mysql'),
                    $error_message,
                    current_time('mysql'),
                    $module_slug
                )
            );
        } else {
            $wpdb->update(
                $table_name,
                $data,
                array('module_slug' => $module_slug),
                array('%s', '%s', '%s'),
                array('%s')
            );
        }
        
        // Actualizar cache local
        if (isset($this->registered_modules[$module_slug])) {
            $this->registered_modules[$module_slug]['sync_status'] = $status;
            $this->registered_modules[$module_slug]['last_sync'] = current_time('mysql');
            if ($error_message) {
                $this->registered_modules[$module_slug]['last_error'] = $error_message;
                $this->registered_modules[$module_slug]['error_count']++;
            }
        }
        
        return true;
    }
    
    /**
     * Verificar salud del sistema
     */
    public function check_system_health() {
        $health_data = array(
            'timestamp' => current_time('mysql'),
            'overall_status' => 'healthy',
            'components' => array(),
            'metrics' => array()
        );
        
        // Verificar componentes principales
        $health_data['components']['database'] = $this->check_database_health();
        $health_data['components']['authentication'] = $this->check_auth_health();
        $health_data['components']['modules'] = $this->check_modules_health();
        $health_data['components']['dependencies'] = $this->check_dependencies_health();
        
        // Calcular estado general
        $unhealthy_components = array_filter($health_data['components'], function($status) {
            return $status !== 'healthy';
        });
        
        if (!empty($unhealthy_components)) {
            $health_data['overall_status'] = 'degraded';
            
            $critical_issues = array_filter($unhealthy_components, function($status) {
                return $status === 'critical';
            });
            
            if (!empty($critical_issues)) {
                $health_data['overall_status'] = 'critical';
            }
        }
        
        // Obtener métricas
        $health_data['metrics'] = $this->get_system_metrics();
        
        // Actualizar estado del sistema
        $this->system_status['last_health_check'] = $health_data['timestamp'];
        
        // Guardar en cache
        update_option('zoho_sync_core_health_check', $health_data);
        
        // Hook personalizado
        do_action('zoho_sync_core_health_checked', $health_data);
        
        return $health_data;
    }
    
    /**
     * Verificar salud de la base de datos
     * @return string Estado de salud
     */
    private function check_database_health() {
        $db_manager = ZohoSyncCore::instance()->database_manager;
        if (!$db_manager) {
            return 'critical';
        }
        
        $table_integrity = $db_manager->check_tables_integrity();
        $missing_tables = array_filter($table_integrity, function($exists) {
            return !$exists;
        });
        
        if (!empty($missing_tables)) {
            return 'critical';
        }
        
        // Verificar estadísticas de la base de datos
        $stats = $db_manager->get_database_stats();
        
        // Si hay muchos errores en logs, marcar como degradado
        if ($stats['logs_errors'] > 100) {
            return 'degraded';
        }
        
        return 'healthy';
    }
    
    /**
     * Verificar salud de autenticación
     * @return string Estado de salud
     */
    private function check_auth_health() {
        $auth_status = ZohoSyncCore::auth()->get_authentication_status();
        
        $expired_tokens = array_filter($auth_status, function($status) {
            return $status['expires_soon'];
        });
        
        if (!empty($expired_tokens)) {
            return 'degraded';
        }
        
        $authenticated_services = array_filter($auth_status, function($status) {
            return $status['authenticated'];
        });
        
        if (empty($authenticated_services)) {
            return 'degraded';
        }
        
        return 'healthy';
    }
    
    /**
     * Verificar salud de módulos
     * @return string Estado de salud
     */
    private function check_modules_health() {
        $modules_with_errors = array_filter($this->registered_modules, function($module) {
            return $module['error_count'] > 5;
        });
        
        if (!empty($modules_with_errors)) {
            return 'degraded';
        }
        
        return 'healthy';
    }
    
    /**
     * Verificar salud de dependencias
     * @return string Estado de salud
     */
    private function check_dependencies_health() {
        $dependency_checker = ZohoSyncCore::instance()->dependency_checker;
        if (!$dependency_checker) {
            return 'critical';
        }
        
        $status_summary = $dependency_checker->get_status_summary();
        
        switch ($status_summary['status']) {
            case 'failed':
                return 'critical';
            case 'warning':
                return 'degraded';
            default:
                return 'healthy';
        }
    }
    
    /**
     * Obtener métricas del sistema
     * @return array Métricas
     */
    private function get_system_metrics() {
        $metrics = array();
        
        // Métricas de base de datos
        $db_manager = ZohoSyncCore::instance()->database_manager;
        if ($db_manager) {
            $metrics['database'] = $db_manager->get_database_stats();
        }
        
        // Métricas de API
        $api_client = ZohoSyncCore::api();
        if ($api_client) {
            $metrics['api'] = $api_client->get_api_stats();
        }
        
        // Métricas de módulos
        $metrics['modules'] = array(
            'total' => count($this->registered_modules),
            'active' => count(array_filter($this->registered_modules, function($module) {
                return $module['sync_status'] !== 'error';
            })),
            'with_errors' => count(array_filter($this->registered_modules, function($module) {
                return $module['error_count'] > 0;
            }))
        );
        
        return $metrics;
    }
    
    /**
     * Limpieza diaria del sistema
     */
    public function daily_cleanup() {
        ZohoSyncCore::log('info', 'Iniciando limpieza diaria del sistema');
        
        // Limpiar logs antiguos
        $db_manager = ZohoSyncCore::instance()->database_manager;
        if ($db_manager) {
            $db_manager->cleanup_old_logs();
            $db_manager->optimize_tables();
        }
        
        // Verificar salud del sistema
        $this->check_system_health();
        
        // Hook personalizado para que otros módulos puedan agregar su limpieza
        do_action('zoho_sync_core_daily_cleanup');
        
        ZohoSyncCore::log('info', 'Limpieza diaria completada');
    }
    
    /**
     * Callback cuando se registra un módulo
     * @param string $module_slug Slug del módulo
     * @param array $config Configuración
     */
    public function on_module_registered($module_slug, $config) {
        // Verificar dependencias del módulo
        if (!empty($config['dependencies'])) {
            $this->check_module_dependencies($module_slug, $config['dependencies']);
        }
    }
    
    /**
     * Callback cuando se activa un módulo
     * @param string $module_slug Slug del módulo
     */
    public function on_module_activated($module_slug) {
        ZohoSyncCore::log('info', 'Módulo activado por el sistema', array(
            'module_slug' => $module_slug
        ));
    }
    
    /**
     * Callback cuando se desactiva un módulo
     * @param string $module_slug Slug del módulo
     */
    public function on_module_deactivated($module_slug) {
        ZohoSyncCore::log('info', 'Módulo desactivado por el sistema', array(
            'module_slug' => $module_slug
        ));
    }
    
    /**
     * Verificar dependencias de un módulo
     * @param string $module_slug Slug del módulo
     * @param array $dependencies Dependencias
     */
    private function check_module_dependencies($module_slug, $dependencies) {
        $missing_dependencies = array();
        
        foreach ($dependencies as $dependency) {
            if (!$this->is_module_registered($dependency)) {
                $missing_dependencies[] = $dependency;
            }
        }
        
        if (!empty($missing_dependencies)) {
            ZohoSyncCore::log('warning', 'Módulo con dependencias faltantes', array(
                'module_slug' => $module_slug,
                'missing_dependencies' => $missing_dependencies
            ));
        }
    }
    
    /**
     * Obtener estado del sistema
     * @return array Estado del sistema
     */
    public function get_system_status() {
        return $this->system_status;
    }
    
    /**
     * Obtener información completa del ecosistema
     * @return array Información del ecosistema
     */
    public function get_ecosystem_info() {
        return array(
            'core_version' => ZOHO_SYNC_CORE_VERSION,
            'system_status' => $this->system_status,
            'registered_modules' => $this->registered_modules,
            'health_check' => get_option('zoho_sync_core_health_check', array()),
            'last_updated' => current_time('mysql')
        );
    }
}

class ZohoSyncCore_Manager {
    private static $instance = null;
    private $modules = [];
    private $plugin_data = [
        'author' => 'Byron Briones',
        'author_uri' => 'https://bbrion.es',
        'plugin_uri' => 'https://bbrion.es/zoho-sync-inventory',
        'version' => '1.0.0',
        'requires' => '5.8',
        'tested' => '6.4'
    ];

    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
        $this->load_dependencies();
    }

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function define_constants() {
        define('ZSCORE_VERSION', $this->plugin_data['version']);
        define('ZSCORE_PATH', plugin_dir_path(dirname(__FILE__)));
        define('ZSCORE_URL', plugin_dir_url(dirname(__FILE__)));
        define('ZSCORE_MODULES_PATH', WP_PLUGIN_DIR . '/');
    }

    private function init_hooks() {
        add_action('admin_menu', [$this, 'register_admin_menu'], 5);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('plugin_action_links', [$this, 'add_plugin_links'], 10, 2);
        add_action('admin_init', [$this, 'check_environment']);
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Zoho Sync', 'zoho-sync-core'),
            __('Zoho Sync', 'zoho-sync-core'),
            'manage_options',
            'zoho-sync',
            [$this, 'render_dashboard'],
            'dashicons-update',
            30
        );

        // Submenús para cada módulo activo
        foreach ($this->modules as $module => $data) {
            if ($this->is_module_active($module)) {
                add_submenu_page(
                    'zoho-sync',
                    $data['name'],
                    $data['name'],
                    'manage_options',
                    "zoho-sync-{$module}",
                    [$this, "render_{$module}_page"]
                );
            }
        }
    }

    public function register_module($module_id, $module_data) {
        $required_fields = ['name', 'version', 'dependencies'];
        
        foreach ($required_fields as $field) {
            if (!isset($module_data[$field])) {
                $this->log('error', sprintf(
                    __('Módulo %s: Falta campo requerido %s', 'zoho-sync-core'),
                    $module_id,
                    $field
                ));
                return false;
            }
        }

        // Validar dependencias
        foreach ($module_data['dependencies'] as $dependency) {
            if ($dependency !== 'core' && !$this->is_module_active($dependency)) {
                $this->log('warning', sprintf(
                    __('Módulo %s: Dependencia no satisfecha %s', 'zoho-sync-core'),
                    $module_id,
                    $dependency
                ));
                return false;
            }
        }

        $this->modules[$module_id] = array_merge($module_data, [
            'status' => true,
            'last_sync' => get_option("zscore_{$module_id}_last_sync", false),
            'errors' => get_option("zscore_{$module_id}_errors", [])
        ]);

        do_action('zscore_module_registered', $module_id, $module_data);
        
        return true;
    }

    public function is_module_active($module_id) {
        return isset($this->modules[$module_id]) && $this->modules[$module_id]['status'];
    }

    public function check_environment() {
        $environment_errors = [];

        // Verificar versión de PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $environment_errors[] = sprintf(
                __('Zoho Sync requiere PHP 7.4 o superior. Actual: %s', 'zoho-sync-core'),
                PHP_VERSION
            );
        }

        // Verificar versión de WordPress
        global $wp_version;
        if (version_compare($wp_version, '5.8', '<')) {
            $environment_errors[] = sprintf(
                __('Zoho Sync requiere WordPress 5.8 o superior. Actual: %s', 'zoho-sync-core'),
                $wp_version
            );
        }

        // Verificar WooCommerce
        if (!class_exists('WooCommerce')) {
            $environment_errors[] = __('Zoho Sync requiere WooCommerce activo.', 'zoho-sync-core');
        }

        if (!empty($environment_errors)) {
            add_action('admin_notices', function() use ($environment_errors) {
                echo '<div class="error">';
                foreach ($environment_errors as $error) {
                    echo '<p>' . esc_html($error) . '</p>';
                }
                echo '</div>';
            });
        }
    }

    public function get_module_status($module_id) {
        if (!isset($this->modules[$module_id])) {
            return false;
        }

        return [
            'active' => $this->modules[$module_id]['status'],
            'version' => $this->modules[$module_id]['version'],
            'last_sync' => $this->modules[$module_id]['last_sync'],
            'errors' => $this->modules[$module_id]['errors']
        ];
    }

    public function log($level, $message, $context = [], $module = 'core') {
        if (!in_array($level, ['error', 'warning', 'info', 'debug'])) {
            return false;
        }

        $log_entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'module' => $module
        ];

        // Guardar en base de datos
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'zscore_logs',
            $log_entry,
            ['%s', '%s', '%s', '%s', '%s']
        );

        // Si es error, actualizar estado del módulo
        if ($level === 'error' && $module !== 'core') {
            $module_errors = get_option("zscore_{$module}_errors", []);
            $module_errors[] = $log_entry;
            update_option("zscore_{$module}_errors", array_slice($module_errors, -10));
        }

        return true;
    }
}
