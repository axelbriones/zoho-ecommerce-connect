<?php
/**
 * Gestor de Tareas Programadas para Zoho Sync Core
 * 
 * @package ZohoSyncCore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Cron Manager para el ecosistema Zoho Sync
 * Gestiona todas las tareas programadas del sistema
 */
class Zoho_Sync_Core_Cron_Manager {
    
    /**
     * Tareas registradas
     * @var array
     */
    private $registered_tasks = array();
    
    /**
     * Intervalos personalizados
     * @var array
     */
    private $custom_intervals = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->define_custom_intervals();
        $this->register_core_tasks();
        $this->init_hooks();
    }
    
    /**
     * Definir intervalos personalizados
     */
    private function define_custom_intervals() {
        $this->custom_intervals = array(
            'every_5_minutes' => array(
                'interval' => 300,
                'display' => __('Cada 5 minutos', 'zoho-sync-core')
            ),
            'every_15_minutes' => array(
                'interval' => 900,
                'display' => __('Cada 15 minutos', 'zoho-sync-core')
            ),
            'every_30_minutes' => array(
                'interval' => 1800,
                'display' => __('Cada 30 minutos', 'zoho-sync-core')
            ),
            'every_2_hours' => array(
                'interval' => 7200,
                'display' => __('Cada 2 horas', 'zoho-sync-core')
            ),
            'every_6_hours' => array(
                'interval' => 21600,
                'display' => __('Cada 6 horas', 'zoho-sync-core')
            ),
            'every_12_hours' => array(
                'interval' => 43200,
                'display' => __('Cada 12 horas', 'zoho-sync-core')
            )
        );
    }
    
    /**
     * Registrar tareas principales del core
     */
    private function register_core_tasks() {
        $this->registered_tasks = array(
            'zoho_sync_core_token_refresh' => array(
                'callback' => array($this, 'refresh_tokens_task'),
                'interval' => 'hourly',
                'description' => __('Refrescar tokens de Zoho', 'zoho-sync-core'),
                'enabled' => true,
                'module' => 'core'
            ),
            'zoho_sync_core_health_check' => array(
                'callback' => array($this, 'health_check_task'),
                'interval' => 'every_30_minutes',
                'description' => __('Verificación de salud del sistema', 'zoho-sync-core'),
                'enabled' => true,
                'module' => 'core'
            ),
            'zoho_sync_core_cleanup_logs' => array(
                'callback' => array($this, 'cleanup_logs_task'),
                'interval' => 'daily',
                'description' => __('Limpiar logs antiguos', 'zoho-sync-core'),
                'enabled' => true,
                'module' => 'core'
            ),
            'zoho_sync_core_dependency_check' => array(
                'callback' => array($this, 'dependency_check_task'),
                'interval' => 'every_6_hours',
                'description' => __('Verificar dependencias del sistema', 'zoho-sync-core'),
                'enabled' => true,
                'module' => 'core'
            ),
            'zoho_sync_core_stats_collection' => array(
                'callback' => array($this, 'collect_stats_task'),
                'interval' => 'hourly',
                'description' => __('Recopilar estadísticas del sistema', 'zoho-sync-core'),
                'enabled' => true,
                'module' => 'core'
            )
        );
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Agregar intervalos personalizados
        add_filter('cron_schedules', array($this, 'add_custom_intervals'));
        
        // Registrar callbacks de tareas
        foreach ($this->registered_tasks as $hook => $task) {
            if ($task['enabled']) {
                add_action($hook, $task['callback']);
            }
        }
        
        // Hook para limpiar tareas al desactivar
        add_action('zoho_sync_core_deactivate', array($this, 'clear_all_scheduled_events'));
        
        // Hook para debug de cron
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_cron', array($this, 'log_cron_execution'));
        }
    }
    
    /**
     * Agregar intervalos personalizados a WordPress
     * @param array $schedules Intervalos existentes
     * @return array Intervalos actualizados
     */
    public function add_custom_intervals($schedules) {
        foreach ($this->custom_intervals as $key => $interval) {
            $schedules[$key] = $interval;
        }
        return $schedules;
    }
    
    /**
     * Programar todas las tareas del core
     */
    public function schedule_events() {
        foreach ($this->registered_tasks as $hook => $task) {
            if ($task['enabled']) {
                $this->schedule_task($hook, $task['interval']);
            }
        }
        
        ZohoSyncCore::log('info', 'Tareas programadas del core iniciadas', array(
            'tasks_count' => count($this->registered_tasks)
        ));
    }
    
    /**
     * Programar una tarea específica
     * @param string $hook Hook de la tarea
     * @param string $interval Intervalo de ejecución
     * @param array $args Argumentos (opcional)
     * @return bool Éxito de la programación
     */
    public function schedule_task($hook, $interval, $args = array()) {
        // Verificar si ya está programada
        if (wp_next_scheduled($hook, $args)) {
            return true; // Ya está programada
        }
        
        // Programar la tarea
        $result = wp_schedule_event(time(), $interval, $hook, $args);
        
        if ($result !== false) {
            ZohoSyncCore::log('debug', 'Tarea programada', array(
                'hook' => $hook,
                'interval' => $interval,
                'next_run' => wp_next_scheduled($hook, $args)
            ), 'cron');
            
            return true;
        }
        
        ZohoSyncCore::log('error', 'Error programando tarea', array(
            'hook' => $hook,
            'interval' => $interval
        ), 'cron');
        
        return false;
    }
    
    /**
     * Desprogramar una tarea específica
     * @param string $hook Hook de la tarea
     * @param array $args Argumentos (opcional)
     * @return bool Éxito de la desprogramación
     */
    public function unschedule_task($hook, $args = array()) {
        $timestamp = wp_next_scheduled($hook, $args);
        
        if ($timestamp) {
            $result = wp_unschedule_event($timestamp, $hook, $args);
            
            if ($result !== false) {
                ZohoSyncCore::log('debug', 'Tarea desprogramada', array(
                    'hook' => $hook,
                    'timestamp' => $timestamp
                ), 'cron');
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Limpiar todas las tareas programadas
     */
    public function clear_scheduled_events() {
        foreach ($this->registered_tasks as $hook => $task) {
            $this->unschedule_task($hook);
        }
        
        ZohoSyncCore::log('info', 'Todas las tareas programadas del core han sido limpiadas');
    }
    
    /**
     * Limpiar todas las tareas programadas (incluyendo de módulos)
     */
    public function clear_all_scheduled_events() {
        // Obtener todas las tareas programadas
        $cron_jobs = _get_cron_array();
        
        foreach ($cron_jobs as $timestamp => $jobs) {
            foreach ($jobs as $hook => $job_data) {
                // Solo limpiar tareas de Zoho Sync
                if (strpos($hook, 'zoho_sync') === 0) {
                    foreach ($job_data as $args) {
                        wp_unschedule_event($timestamp, $hook, $args['args']);
                    }
                }
            }
        }
        
        ZohoSyncCore::log('info', 'Todas las tareas programadas de Zoho Sync han sido limpiadas');
    }
    
    /**
     * Registrar una nueva tarea
     * @param string $hook Hook único de la tarea
     * @param array $config Configuración de la tarea
     * @return bool Éxito del registro
     */
    public function register_task($hook, $config) {
        // Validar configuración
        $validation = $this->validate_task_config($hook, $config);
        if (!$validation['valid']) {
            ZohoSyncCore::log('error', 'Error registrando tarea cron', array(
                'hook' => $hook,
                'error' => $validation['message']
            ), 'cron');
            return false;
        }
        
        // Configuración por defecto
        $default_config = array(
            'callback' => null,
            'interval' => 'hourly',
            'description' => '',
            'enabled' => true,
            'module' => 'unknown',
            'args' => array()
        );
        
        $config = array_merge($default_config, $config);
        
        // Registrar la tarea
        $this->registered_tasks[$hook] = $config;
        
        // Agregar el callback
        if ($config['enabled'] && $config['callback']) {
            add_action($hook, $config['callback']);
        }
        
        // Programar si está habilitada
        if ($config['enabled']) {
            $this->schedule_task($hook, $config['interval'], $config['args']);
        }
        
        ZohoSyncCore::log('info', 'Tarea cron registrada', array(
            'hook' => $hook,
            'interval' => $config['interval'],
            'module' => $config['module']
        ), 'cron');
        
        return true;
    }
    
    /**
     * Validar configuración de tarea
     * @param string $hook Hook de la tarea
     * @param array $config Configuración
     * @return array Resultado de validación
     */
    private function validate_task_config($hook, $config) {
        $validation = array(
            'valid' => true,
            'message' => ''
        );
        
        // Validar hook
        if (empty($hook) || !is_string($hook)) {
            $validation['valid'] = false;
            $validation['message'] = __('Hook de tarea inválido', 'zoho-sync-core');
            return $validation;
        }
        
        // Validar callback
        if (!isset($config['callback']) || !is_callable($config['callback'])) {
            $validation['valid'] = false;
            $validation['message'] = __('Callback de tarea inválido', 'zoho-sync-core');
            return $validation;
        }
        
        // Validar intervalo
        if (isset($config['interval'])) {
            $available_intervals = wp_get_schedules();
            if (!isset($available_intervals[$config['interval']])) {
                $validation['valid'] = false;
                $validation['message'] = __('Intervalo de tarea inválido', 'zoho-sync-core');
                return $validation;
            }
        }
        
        return $validation;
    }
    
    /**
     * Desregistrar una tarea
     * @param string $hook Hook de la tarea
     * @return bool Éxito de la operación
     */
    public function unregister_task($hook) {
        if (!isset($this->registered_tasks[$hook])) {
            return false;
        }
        
        // Desprogramar la tarea
        $this->unschedule_task($hook);
        
        // Remover el callback
        $task = $this->registered_tasks[$hook];
        if ($task['callback']) {
            remove_action($hook, $task['callback']);
        }
        
        // Remover del registro
        unset($this->registered_tasks[$hook]);
        
        ZohoSyncCore::log('info', 'Tarea cron desregistrada', array(
            'hook' => $hook
        ), 'cron');
        
        return true;
    }
    
    /**
     * Habilitar/deshabilitar una tarea
     * @param string $hook Hook de la tarea
     * @param bool $enabled Estado deseado
     * @return bool Éxito de la operación
     */
    public function toggle_task($hook, $enabled) {
        if (!isset($this->registered_tasks[$hook])) {
            return false;
        }
        
        $this->registered_tasks[$hook]['enabled'] = $enabled;
        
        if ($enabled) {
            // Habilitar: agregar callback y programar
            add_action($hook, $this->registered_tasks[$hook]['callback']);
            $this->schedule_task($hook, $this->registered_tasks[$hook]['interval']);
        } else {
            // Deshabilitar: remover callback y desprogramar
            remove_action($hook, $this->registered_tasks[$hook]['callback']);
            $this->unschedule_task($hook);
        }
        
        ZohoSyncCore::log('info', 'Estado de tarea cron cambiado', array(
            'hook' => $hook,
            'enabled' => $enabled
        ), 'cron');
        
        return true;
    }
    
    /**
     * Ejecutar una tarea manualmente
     * @param string $hook Hook de la tarea
     * @param array $args Argumentos (opcional)
     * @return bool Éxito de la ejecución
     */
    public function run_task_now($hook, $args = array()) {
        if (!isset($this->registered_tasks[$hook])) {
            return false;
        }
        
        $task = $this->registered_tasks[$hook];
        
        if (!$task['callback'] || !is_callable($task['callback'])) {
            return false;
        }
        
        ZohoSyncCore::log('info', 'Ejecutando tarea cron manualmente', array(
            'hook' => $hook,
            'args' => $args
        ), 'cron');
        
        try {
            call_user_func_array($task['callback'], $args);
            
            ZohoSyncCore::log('info', 'Tarea cron ejecutada exitosamente', array(
                'hook' => $hook
            ), 'cron');
            
            return true;
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 'Error ejecutando tarea cron', array(
                'hook' => $hook,
                'error' => $e->getMessage()
            ), 'cron');
            
            return false;
        }
    }
    
    /**
     * Obtener información de todas las tareas
     * @return array Información de tareas
     */
    public function get_tasks_info() {
        $tasks_info = array();
        
        foreach ($this->registered_tasks as $hook => $task) {
            $next_run = wp_next_scheduled($hook);
            
            $tasks_info[$hook] = array(
                'description' => $task['description'],
                'interval' => $task['interval'],
                'enabled' => $task['enabled'],
                'module' => $task['module'],
                'next_run' => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
                'next_run_timestamp' => $next_run,
                'is_scheduled' => (bool) $next_run
            );
        }
        
        return $tasks_info;
    }
    
    /**
     * Obtener estadísticas de cron
     * @return array Estadísticas
     */
    public function get_cron_stats() {
        $stats = array(
            'total_tasks' => count($this->registered_tasks),
            'enabled_tasks' => 0,
            'scheduled_tasks' => 0,
            'overdue_tasks' => 0,
            'next_execution' => null
        );
        
        $next_executions = array();
        
        foreach ($this->registered_tasks as $hook => $task) {
            if ($task['enabled']) {
                $stats['enabled_tasks']++;
            }
            
            $next_run = wp_next_scheduled($hook);
            if ($next_run) {
                $stats['scheduled_tasks']++;
                $next_executions[] = $next_run;
                
                // Verificar si está atrasada (más de 2 intervalos)
                $schedules = wp_get_schedules();
                $interval = $schedules[$task['interval']]['interval'] ?? 3600;
                
                if (time() - $next_run > ($interval * 2)) {
                    $stats['overdue_tasks']++;
                }
            }
        }
        
        if (!empty($next_executions)) {
            $stats['next_execution'] = min($next_executions);
        }
        
        return $stats;
    }
    
    /**
     * Tarea: Refrescar tokens de Zoho
     */
    public function refresh_tokens_task() {
        ZohoSyncCore::log('info', 'Iniciando tarea de refresco de tokens', array(), 'cron');
        
        $auth_manager = ZohoSyncCore::auth();
        if ($auth_manager) {
            $auth_manager->check_token_expiration();
        }
        
        ZohoSyncCore::log('info', 'Tarea de refresco de tokens completada', array(), 'cron');
    }
    
    /**
     * Tarea: Verificación de salud del sistema
     */
    public function health_check_task() {
        ZohoSyncCore::log('debug', 'Iniciando verificación de salud del sistema', array(), 'cron');
        
        $core = ZohoSyncCore::instance()->core;
        if ($core) {
            $health_data = $core->check_system_health();
            
            // Log solo si hay problemas
            if ($health_data['overall_status'] !== 'healthy') {
                ZohoSyncCore::log('warning', 'Problemas detectados en verificación de salud', array(
                    'status' => $health_data['overall_status'],
                    'components' => $health_data['components']
                ), 'cron');
            }
        }
    }
    
    /**
     * Tarea: Limpiar logs antiguos
     */
    public function cleanup_logs_task() {
        ZohoSyncCore::log('info', 'Iniciando limpieza de logs antiguos', array(), 'cron');
        
        $logger = ZohoSyncCore::logger();
        if ($logger) {
            $logger->cleanup_old_logs();
        }
        
        ZohoSyncCore::log('info', 'Limpieza de logs completada', array(), 'cron');
    }
    
    /**
     * Tarea: Verificar dependencias del sistema
     */
    public function dependency_check_task() {
        ZohoSyncCore::log('debug', 'Iniciando verificación de dependencias', array(), 'cron');
        
        $dependency_checker = ZohoSyncCore::instance()->dependency_checker;
        if ($dependency_checker) {
            $results = $dependency_checker->check_all_dependencies();
            
            // Log solo si hay problemas críticos
            if ($results['critical_issues'] > 0) {
                ZohoSyncCore::log('critical', 'Problemas críticos en dependencias', array(
                    'critical_issues' => $results['critical_issues'],
                    'warnings' => $results['warnings']
                ), 'cron');
            }
        }
    }
    
    /**
     * Tarea: Recopilar estadísticas del sistema
     */
    public function collect_stats_task() {
        ZohoSyncCore::log('debug', 'Recopilando estadísticas del sistema', array(), 'cron');
        
        $stats = array(
            'timestamp' => current_time('mysql'),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cron_stats' => $this->get_cron_stats()
        );
        
        // Agregar estadísticas de base de datos
        $db_manager = ZohoSyncCore::instance()->database_manager;
        if ($db_manager) {
            $stats['database'] = $db_manager->get_database_stats();
        }
        
        // Agregar estadísticas de API
        $api_client = ZohoSyncCore::api();
        if ($api_client) {
            $stats['api'] = $api_client->get_api_stats();
        }
        
        // Guardar estadísticas
        update_option('zoho_sync_core_stats', $stats);
        
        ZohoSyncCore::log('debug', 'Estadísticas del sistema recopiladas', array(
            'memory_usage_mb' => round($stats['memory_usage'] / 1024 / 1024, 2),
            'cron_tasks' => $stats['cron_stats']['total_tasks']
        ), 'cron');
    }
    
    /**
     * Log de ejecución de cron (solo en debug)
     */
    public function log_cron_execution() {
        ZohoSyncCore::log('debug', 'Ejecución de WP Cron iniciada', array(
            'timestamp' => current_time('mysql'),
            'doing_cron' => defined('DOING_CRON') && DOING_CRON
        ), 'cron');
    }
    
    /**
     * Obtener próximas ejecuciones
     * @param int $limit Límite de resultados
     * @return array Próximas ejecuciones
     */
    public function get_upcoming_executions($limit = 10) {
        $executions = array();
        
        foreach ($this->registered_tasks as $hook => $task) {
            $next_run = wp_next_scheduled($hook);
            if ($next_run) {
                $executions[] = array(
                    'hook' => $hook,
                    'description' => $task['description'],
                    'module' => $task['module'],
                    'timestamp' => $next_run,
                    'datetime' => date('Y-m-d H:i:s', $next_run),
                    'time_until' => human_time_diff(time(), $next_run)
                );
            }
        }
        
        // Ordenar por timestamp
        usort($executions, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        return array_slice($executions, 0, $limit);
    }
    
    /**
     * Verificar si el sistema de cron está funcionando
     * @return array Estado del cron
     */
    public function check_cron_health() {
        $health = array(
            'status' => 'healthy',
            'issues' => array(),
            'info' => array()
        );
        
        // Verificar si WP Cron está habilitado
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $health['status'] = 'warning';
            $health['issues'][] = __('WP Cron está deshabilitado', 'zoho-sync-core');
        }
        
        // Verificar tareas atrasadas
        $stats = $this->get_cron_stats();
        if ($stats['overdue_tasks'] > 0) {
            $health['status'] = 'degraded';
            $health['issues'][] = sprintf(
                __('%d tareas están atrasadas', 'zoho-sync-core'),
                $stats['overdue_tasks']
            );
        }
        
        // Información adicional
        $health['info'] = array(
            'total_tasks' => $stats['total_tasks'],
            'enabled_tasks' => $stats['enabled_tasks'],
            'scheduled_tasks' => $stats['scheduled_tasks'],
            'next_execution' => $stats['next_execution'] ? date('Y-m-d H:i:s', $stats['next_execution']) : null
        );
        
        return $health;
    }
}
