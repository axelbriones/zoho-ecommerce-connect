<?php
/**
 * Script de Desinstalación para Zoho Sync Core
 * 
 * Este archivo se ejecuta cuando el plugin es desinstalado desde WordPress.
 * Limpia todos los datos, tablas y configuraciones del plugin.
 * 
 * @package ZohoSyncCore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Verificar permisos
if (!current_user_can('activate_plugins')) {
    return;
}

// Verificar que es el plugin correcto
if (plugin_basename(__FILE__) !== plugin_basename(dirname(__FILE__) . '/zoho-sync-core.php')) {
    return;
}

/**
 * Clase para manejar la desinstalación completa
 */
class Zoho_Sync_Core_Uninstaller {
    
    /**
     * Instancia de wpdb
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Prefijo de tablas
     * @var string
     */
    private $table_prefix;
    
    /**
     * Log de desinstalación
     * @var array
     */
    private $uninstall_log = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix;
        
        $this->log('Iniciando proceso de desinstalación de Zoho Sync Core');
    }
    
    /**
     * Ejecutar desinstalación completa
     */
    public function run_uninstall() {
        try {
            // 1. Limpiar tareas programadas
            $this->cleanup_scheduled_tasks();
            
            // 2. Eliminar tablas de base de datos
            $this->drop_database_tables();
            
            // 3. Limpiar opciones de WordPress
            $this->cleanup_wordpress_options();
            
            // 4. Limpiar transients
            $this->cleanup_transients();
            
            // 5. Limpiar archivos de logs
            $this->cleanup_log_files();
            
            // 6. Limpiar cache
            $this->cleanup_cache();
            
            // 7. Limpiar rewrite rules
            $this->cleanup_rewrite_rules();
            
            // 8. Limpiar user meta
            $this->cleanup_user_meta();
            
            // 9. Limpiar capabilities personalizadas
            $this->cleanup_custom_capabilities();
            
            // 10. Log final
            $this->log('Desinstalación completada exitosamente');
            $this->save_uninstall_log();
            
        } catch (Exception $e) {
            $this->log('Error durante la desinstalación: ' . $e->getMessage());
            $this->save_uninstall_log();
        }
    }
    
    /**
     * Limpiar tareas programadas
     */
    private function cleanup_scheduled_tasks() {
        $this->log('Limpiando tareas programadas...');
        
        // Lista de tareas del core
        $core_tasks = array(
            'zoho_sync_core_token_refresh',
            'zoho_sync_core_health_check',
            'zoho_sync_core_cleanup_logs',
            'zoho_sync_core_dependency_check',
            'zoho_sync_core_stats_collection',
            'zoho_sync_core_daily_cleanup'
        );
        
        $cleaned_tasks = 0;
        
        foreach ($core_tasks as $task) {
            $timestamp = wp_next_scheduled($task);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $task);
                $cleaned_tasks++;
            }
        }
        
        // Limpiar todas las tareas que empiecen con 'zoho_sync'
        $cron_jobs = _get_cron_array();
        foreach ($cron_jobs as $timestamp => $jobs) {
            foreach ($jobs as $hook => $job_data) {
                if (strpos($hook, 'zoho_sync') === 0) {
                    foreach ($job_data as $args) {
                        wp_unschedule_event($timestamp, $hook, $args['args']);
                        $cleaned_tasks++;
                    }
                }
            }
        }
        
        $this->log("Limpiadas {$cleaned_tasks} tareas programadas");
    }
    
    /**
     * Eliminar tablas de base de datos
     */
    private function drop_database_tables() {
        $this->log('Eliminando tablas de base de datos...');
        
        $tables = array(
            'zoho_sync_settings',
            'zoho_sync_logs',
            'zoho_sync_tokens',
            'zoho_sync_modules'
        );
        
        $dropped_tables = 0;
        
        foreach ($tables as $table) {
            $table_name = $this->table_prefix . $table;
            
            // Verificar si la tabla existe
            $table_exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table_name
                )
            );
            
            if ($table_exists) {
                $result = $this->wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
                if ($result !== false) {
                    $dropped_tables++;
                    $this->log("Tabla eliminada: {$table_name}");
                } else {
                    $this->log("Error eliminando tabla: {$table_name}");
                }
            }
        }
        
        $this->log("Eliminadas {$dropped_tables} tablas de base de datos");
    }
    
    /**
     * Limpiar opciones de WordPress
     */
    private function cleanup_wordpress_options() {
        $this->log('Limpiando opciones de WordPress...');
        
        // Opciones específicas del plugin
        $options = array(
            'zoho_sync_core_db_version',
            'zoho_sync_core_encryption_key',
            'zoho_sync_core_dependency_check',
            'zoho_sync_core_health_check',
            'zoho_sync_core_stats',
            'zoho_sync_core_settings_cache',
            'zoho_sync_core_modules_cache'
        );
        
        $deleted_options = 0;
        
        foreach ($options as $option) {
            if (delete_option($option)) {
                $deleted_options++;
                $this->log("Opción eliminada: {$option}");
            }
        }
        
        // Limpiar opciones que empiecen con 'zoho_sync_'
        $all_options = $this->wpdb->get_results(
            "SELECT option_name FROM {$this->wpdb->options} WHERE option_name LIKE 'zoho_sync_%'"
        );
        
        foreach ($all_options as $option) {
            if (delete_option($option->option_name)) {
                $deleted_options++;
                $this->log("Opción eliminada: {$option->option_name}");
            }
        }
        
        $this->log("Eliminadas {$deleted_options} opciones");
    }
    
    /**
     * Limpiar transients
     */
    private function cleanup_transients() {
        $this->log('Limpiando transients...');
        
        $deleted_transients = 0;
        
        // Transients específicos
        $transients = array(
            'zoho_sync_core_health_status',
            'zoho_sync_core_api_stats',
            'zoho_sync_core_dependency_status'
        );
        
        foreach ($transients as $transient) {
            if (delete_transient($transient)) {
                $deleted_transients++;
            }
        }
        
        // Limpiar transients que empiecen con 'zoho_sync_'
        $transient_options = $this->wpdb->get_results(
            "SELECT option_name FROM {$this->wpdb->options} 
             WHERE option_name LIKE '_transient_zoho_sync_%' 
             OR option_name LIKE '_transient_timeout_zoho_sync_%'"
        );
        
        foreach ($transient_options as $option) {
            $transient_name = str_replace(array('_transient_', '_transient_timeout_'), '', $option->option_name);
            if (delete_transient($transient_name)) {
                $deleted_transients++;
            }
        }
        
        // Limpiar transients de rate limiting de webhooks
        $rate_limit_transients = $this->wpdb->get_results(
            "SELECT option_name FROM {$this->wpdb->options} 
             WHERE option_name LIKE '_transient_zoho_webhook_rate_limit_%'"
        );
        
        foreach ($rate_limit_transients as $option) {
            if (delete_option($option->option_name)) {
                $deleted_transients++;
            }
        }
        
        $this->log("Eliminados {$deleted_transients} transients");
    }
    
    /**
     * Limpiar archivos de logs
     */
    private function cleanup_log_files() {
        $this->log('Limpiando archivos de logs...');
        
        $log_dir = WP_CONTENT_DIR . '/uploads/zoho-sync-logs/';
        $deleted_files = 0;
        
        if (is_dir($log_dir)) {
            $files = glob($log_dir . '*');
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    if (unlink($file)) {
                        $deleted_files++;
                    }
                }
            }
            
            // Intentar eliminar el directorio
            if (rmdir($log_dir)) {
                $this->log("Directorio de logs eliminado: {$log_dir}");
            }
        }
        
        $this->log("Eliminados {$deleted_files} archivos de logs");
    }
    
    /**
     * Limpiar cache
     */
    private function cleanup_cache() {
        $this->log('Limpiando cache...');
        
        // Limpiar cache de objetos si está disponible
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $this->log('Cache de objetos limpiado');
        }
        
        // Limpiar cache específico del plugin
        $cache_keys = array(
            'zoho_sync_settings_cache',
            'zoho_sync_modules_cache',
            'zoho_sync_tokens_cache'
        );
        
        foreach ($cache_keys as $key) {
            wp_cache_delete($key, 'zoho_sync_core');
        }
        
        $this->log('Cache del plugin limpiado');
    }
    
    /**
     * Limpiar rewrite rules
     */
    private function cleanup_rewrite_rules() {
        $this->log('Limpiando rewrite rules...');
        
        // Flush rewrite rules para limpiar las reglas del webhook
        flush_rewrite_rules();
        
        $this->log('Rewrite rules limpiadas');
    }
    
    /**
     * Limpiar user meta relacionada
     */
    private function cleanup_user_meta() {
        $this->log('Limpiando user meta...');
        
        $deleted_meta = 0;
        
        // Meta keys específicas del plugin
        $meta_keys = array(
            'zoho_sync_core_preferences',
            'zoho_sync_core_last_login',
            'zoho_sync_core_notifications'
        );
        
        foreach ($meta_keys as $meta_key) {
            $deleted = $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->wpdb->usermeta} WHERE meta_key = %s",
                    $meta_key
                )
            );
            
            if ($deleted !== false) {
                $deleted_meta += $deleted;
            }
        }
        
        // Limpiar meta que empiece con 'zoho_sync_'
        $deleted = $this->wpdb->query(
            "DELETE FROM {$this->wpdb->usermeta} WHERE meta_key LIKE 'zoho_sync_%'"
        );
        
        if ($deleted !== false) {
            $deleted_meta += $deleted;
        }
        
        $this->log("Eliminados {$deleted_meta} registros de user meta");
    }
    
    /**
     * Limpiar capabilities personalizadas
     */
    private function cleanup_custom_capabilities() {
        $this->log('Limpiando capabilities personalizadas...');
        
        // Capabilities que podría haber agregado el plugin
        $custom_caps = array(
            'manage_zoho_sync',
            'view_zoho_sync_logs',
            'configure_zoho_sync',
            'manage_zoho_webhooks'
        );
        
        $roles = wp_roles();
        $removed_caps = 0;
        
        foreach ($roles->roles as $role_name => $role_info) {
            $role = get_role($role_name);
            
            if ($role) {
                foreach ($custom_caps as $cap) {
                    if ($role->has_cap($cap)) {
                        $role->remove_cap($cap);
                        $removed_caps++;
                    }
                }
            }
        }
        
        $this->log("Eliminadas {$removed_caps} capabilities personalizadas");
    }
    
    /**
     * Verificar si hay módulos dependientes
     * @return array Lista de módulos dependientes
     */
    private function check_dependent_modules() {
        $dependent_modules = array();
        
        // Lista de plugins del ecosistema
        $ecosystem_plugins = array(
            'zoho-sync-orders/zoho-sync-orders.php',
            'zoho-sync-customers/zoho-sync-customers.php',
            'zoho-sync-products/zoho-sync-products.php',
            'zoho-sync-zone-blocker/zoho-sync-zone-blocker.php',
            'zoho-sync-inventory/zoho-sync-inventory.php',
            'zoho-sync-reports/zoho-sync-reports.php',
            'zoho-distributor-portal/zoho-distributor-portal.php'
        );
        
        foreach ($ecosystem_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                $dependent_modules[] = $plugin;
            }
        }
        
        return $dependent_modules;
    }
    
    /**
     * Crear backup de configuraciones críticas
     */
    private function create_settings_backup() {
        $this->log('Creando backup de configuraciones...');
        
        $backup_data = array(
            'timestamp' => current_time('mysql'),
            'version' => '1.0.0',
            'settings' => array(),
            'tokens' => array(),
            'modules' => array()
        );
        
        // Backup de configuraciones críticas (sin datos sensibles)
        $critical_settings = array(
            'zoho_region',
            'api_timeout',
            'log_level',
            'sync_enabled'
        );
        
        foreach ($critical_settings as $setting) {
            $value = get_option('zoho_sync_core_' . $setting);
            if ($value !== false) {
                $backup_data['settings'][$setting] = $value;
            }
        }
        
        // Guardar backup en archivo
        $backup_file = WP_CONTENT_DIR . '/zoho-sync-core-backup-' . date('Y-m-d-H-i-s') . '.json';
        
        if (file_put_contents($backup_file, wp_json_encode($backup_data, JSON_PRETTY_PRINT))) {
            $this->log("Backup creado: {$backup_file}");
        } else {
            $this->log("Error creando backup");
        }
    }
    
    /**
     * Agregar entrada al log
     * @param string $message Mensaje
     */
    private function log($message) {
        $this->uninstall_log[] = array(
            'timestamp' => current_time('mysql'),
            'message' => $message
        );
    }
    
    /**
     * Guardar log de desinstalación
     */
    private function save_uninstall_log() {
        $log_content = "=== ZOHO SYNC CORE UNINSTALL LOG ===\n";
        $log_content .= "Fecha: " . current_time('mysql') . "\n";
        $log_content .= "Versión: 1.0.0\n\n";
        
        foreach ($this->uninstall_log as $entry) {
            $log_content .= "[{$entry['timestamp']}] {$entry['message']}\n";
        }
        
        $log_file = WP_CONTENT_DIR . '/zoho-sync-core-uninstall-' . date('Y-m-d-H-i-s') . '.log';
        file_put_contents($log_file, $log_content);
    }
    
    /**
     * Mostrar advertencia sobre módulos dependientes
     * @param array $dependent_modules Módulos dependientes
     */
    private function show_dependency_warning($dependent_modules) {
        if (!empty($dependent_modules)) {
            $message = "ADVERTENCIA: Los siguientes plugins dependen de Zoho Sync Core y pueden no funcionar correctamente:\n";
            foreach ($dependent_modules as $module) {
                $message .= "- {$module}\n";
            }
            $message .= "\nSe recomienda desactivar estos plugins antes de desinstalar Zoho Sync Core.";
            
            $this->log($message);
        }
    }
}

// Ejecutar desinstalación solo si no hay módulos dependientes activos
$uninstaller = new Zoho_Sync_Core_Uninstaller();

// Verificar módulos dependientes
$dependent_modules = $uninstaller->check_dependent_modules();

if (!empty($dependent_modules)) {
    // Si hay módulos dependientes, solo crear un log de advertencia
    $uninstaller->log('ADVERTENCIA: Desinstalación cancelada debido a módulos dependientes activos');
    $uninstaller->show_dependency_warning($dependent_modules);
    $uninstaller->save_uninstall_log();
    
    // Mostrar mensaje al usuario
    wp_die(
        '<h1>No se puede desinstalar Zoho Sync Core</h1>' .
        '<p>Los siguientes plugins del ecosistema Zoho Sync están activos y dependen de este plugin:</p>' .
        '<ul><li>' . implode('</li><li>', $dependent_modules) . '</li></ul>' .
        '<p>Por favor, desactiva estos plugins antes de desinstalar Zoho Sync Core.</p>' .
        '<p><a href="' . admin_url('plugins.php') . '">Volver a Plugins</a></p>',
        'Desinstalación Cancelada',
        array('back_link' => true)
    );
} else {
    // Crear backup antes de desinstalar
    $uninstaller->create_settings_backup();
    
    // Ejecutar desinstalación completa
    $uninstaller->run_uninstall();
}

// Limpiar instancia
unset($uninstaller);
