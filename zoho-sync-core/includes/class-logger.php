<?php
/**
 * Sistema de Logging Centralizado para Zoho Sync Core
 * 
 * @package ZohoSyncCore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Logger para el ecosistema Zoho Sync
 * Proporciona logging centralizado para todos los plugins del ecosistema
 */
class Zoho_Sync_Core_Logger {
    
    /**
     * Niveles de log disponibles
     */
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    /**
     * Instancia de wpdb
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Nombre de la tabla de logs
     * @var string
     */
    private $table_name;
    
    /**
     * Configuración del logger
     * @var array
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . ZOHO_SYNC_CORE_LOGS_TABLE;
        
        $this->config = array(
            'max_log_level' => $this->get_max_log_level(),
            'log_to_file' => $this->should_log_to_file(),
            'log_to_email' => $this->should_log_to_email(),
            'retention_days' => ZOHO_SYNC_CORE_LOG_RETENTION_DAYS
        );
        
        // Programar limpieza de logs antiguos
        add_action('zoho_sync_core_cleanup_logs', array($this, 'cleanup_old_logs'));
        if (!wp_next_scheduled('zoho_sync_core_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'zoho_sync_core_cleanup_logs');
        }
    }
    
    /**
     * Escribir log de emergencia
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @param string $module Módulo origen
     */
    public function emergency($message, $context = array(), $module = 'core') {
        $this->log(self::EMERGENCY, $message, $context, $module);
    }
    
    /**
     * Escribir log de alerta
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @param string $module Módulo origen
     */
    public function alert($message, $context = array(), $module = 'core') {
        $this->log(self::ALERT, $message, $context, $module);
    }
    
    /**
     * Escribir log crítico
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @param string $module Módulo origen
     */
    public function critical($message, $context = array(), $module = 'core') {
        $this->log(self::CRITICAL, $message, $context, $module);
    }
    
    /**
     * Escribir log de error
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @param string $module Módulo origen
     */
    public function error($message, $context = array(), $module = 'core') {
        $this->log(self::ERROR, $message, $context, $module);
    }
    
    /**
     * Escribir log de advertencia
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @param string $module Módulo origen
     */
    public function warning($message, $context = array(), $module = 'core') {
        $this->log(self::WARNING, $message, $context, $module);
    }
    
    /**
     * Escribir log de aviso
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @param string $module Módulo origen
     */
    public function notice($message, $context = array(), $module = 'core') {
        $this->log(self::NOTICE, $message, $context, $module);
    }
    
    /**
     * Escribir log informativo
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @param string $module Módulo origen
     */
    public function info($message, $context = array(), $module = 'core') {
        $this->log(self::INFO, $message, $context, $module);
    }
    
    /**
     * Escribir log de debug
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @param string $module Módulo origen
     */
    public function debug($message, $context = array(), $module = 'core') {
        $this->log(self::DEBUG, $message, $context, $module);
    }
    
    /**
     * Método principal de logging
     * @param string $level Nivel del log
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @param string $module Módulo origen
     */
    public function log($level, $message, $context = array(), $module = 'core') {
        // Verificar si el nivel está habilitado
        if (!$this->is_level_enabled($level)) {
            return;
        }
        
        // Aplicar filtro al mensaje
        $message = apply_filters('zoho_sync_log_message', $message, $level, $module);
        
        // Preparar datos del log
        $log_data = $this->prepare_log_data($level, $message, $context, $module);
        
        // Escribir a base de datos
        $this->write_to_database($log_data);
        
        // Escribir a archivo si está habilitado
        if ($this->config['log_to_file']) {
            $this->write_to_file($log_data);
        }
        
        // Enviar por email si es crítico
        if ($this->should_email_log($level)) {
            $this->send_email_notification($log_data);
        }
        
        // Hook personalizado después del log
        do_action('zoho_sync_log_written', $level, $message, $context, $module);
    }
    
    /**
     * Preparar datos del log
     * @param string $level Nivel
     * @param string $message Mensaje
     * @param array $context Contexto
     * @param string $module Módulo
     * @return array Datos preparados
     */
    private function prepare_log_data($level, $message, $context, $module) {
        // Obtener información del usuario actual
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID ?: null;
        
        // Obtener información de la request
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Preparar contexto
        if (!empty($context)) {
            $context = wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        } else {
            $context = null;
        }
        
        return array(
            'module' => sanitize_text_field($module),
            'level' => sanitize_text_field($level),
            'message' => sanitize_textarea_field($message),
            'context' => $context,
            'user_id' => $user_id,
            'ip_address' => sanitize_text_field($ip_address),
            'user_agent' => sanitize_textarea_field($user_agent),
            'created_at' => current_time('mysql')
        );
    }
    
    /**
     * Escribir log a la base de datos
     * @param array $log_data Datos del log
     */
    private function write_to_database($log_data) {
        $result = $this->wpdb->insert(
            $this->table_name,
            $log_data,
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            // Si falla la escritura a BD, intentar escribir a archivo
            error_log('Zoho Sync Core: Error escribiendo log a base de datos - ' . $this->wpdb->last_error);
        }
    }
    
    /**
     * Escribir log a archivo
     * @param array $log_data Datos del log
     */
    private function write_to_file($log_data) {
        $log_dir = WP_CONTENT_DIR . '/uploads/zoho-sync-logs/';
        
        // Crear directorio si no existe
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Crear archivo .htaccess para proteger los logs
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($log_dir . '.htaccess', $htaccess_content);
        }
        
        $log_file = $log_dir . 'zoho-sync-' . date('Y-m-d') . '.log';
        
        $log_line = sprintf(
            "[%s] %s.%s: %s %s\n",
            $log_data['created_at'],
            strtoupper($log_data['module']),
            strtoupper($log_data['level']),
            $log_data['message'],
            $log_data['context'] ? ' | Context: ' . $log_data['context'] : ''
        );
        
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Obtener logs con filtros
     * @param array $args Argumentos de filtrado
     * @return array Logs encontrados
     */
    public function get_logs($args = array()) {
        $defaults = array(
            'module' => '',
            'level' => '',
            'limit' => 100,
            'offset' => 0,
            'date_from' => '',
            'date_to' => '',
            'user_id' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        // Filtro por módulo
        if (!empty($args['module'])) {
            $where_conditions[] = 'module = %s';
            $where_values[] = $args['module'];
        }
        
        // Filtro por nivel
        if (!empty($args['level'])) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $args['level'];
        }
        
        // Filtro por usuario
        if (!empty($args['user_id'])) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        // Filtro por fecha desde
        if (!empty($args['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        // Filtro por fecha hasta
        if (!empty($args['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        // Filtro de búsqueda
        if (!empty($args['search'])) {
            $where_conditions[] = 'message LIKE %s';
            $where_values[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Construir query
        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause}";
        
        // Ordenamiento
        $allowed_orderby = array('id', 'module', 'level', 'created_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql .= " ORDER BY {$orderby} {$order}";
        
        // Límite y offset
        if ($args['limit'] > 0) {
            $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        // Ejecutar query
        if (!empty($where_values)) {
            $sql = $this->wpdb->prepare($sql, $where_values);
        }
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Contar logs con filtros
     * @param array $args Argumentos de filtrado
     * @return int Número de logs
     */
    public function count_logs($args = array()) {
        $args['limit'] = 0; // Sin límite para contar
        $logs = $this->get_logs($args);
        return count($logs);
    }
    
    /**
     * Obtener estadísticas de logs
     * @return array Estadísticas
     */
    public function get_log_stats() {
        $stats = array();
        
        // Total de logs
        $stats['total'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // Logs por nivel
        $levels = $this->wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM {$this->table_name} GROUP BY level"
        );
        
        foreach ($levels as $level) {
            $stats['by_level'][$level->level] = $level->count;
        }
        
        // Logs por módulo
        $modules = $this->wpdb->get_results(
            "SELECT module, COUNT(*) as count FROM {$this->table_name} GROUP BY module"
        );
        
        foreach ($modules as $module) {
            $stats['by_module'][$module->module] = $module->count;
        }
        
        // Logs de hoy
        $stats['today'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(created_at) = CURDATE()"
        );
        
        // Logs de esta semana
        $stats['this_week'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        return $stats;
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanup_old_logs() {
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . $this->config['retention_days'] . ' days'));
        
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );
        
        if ($deleted > 0) {
            $this->info('Logs antiguos limpiados', array(
                'deleted_count' => $deleted,
                'cutoff_date' => $cutoff_date
            ));
        }
    }
    
    /**
     * Verificar si un nivel de log está habilitado
     * @param string $level Nivel a verificar
     * @return bool
     */
    private function is_level_enabled($level) {
        $levels = array(
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7
        );
        
        $max_level = $levels[$this->config['max_log_level']] ?? 6;
        $current_level = $levels[$level] ?? 7;
        
        return $current_level <= $max_level;
    }
    
    /**
     * Obtener nivel máximo de log desde configuración
     * @return string
     */
    private function get_max_log_level() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return self::DEBUG;
        }
        
        return get_option('zoho_sync_core_log_level', self::INFO);
    }
    
    /**
     * Verificar si debe escribir a archivo
     * @return bool
     */
    private function should_log_to_file() {
        return get_option('zoho_sync_core_log_to_file', false);
    }
    
    /**
     * Verificar si debe enviar logs por email
     * @return bool
     */
    private function should_log_to_email() {
        return get_option('zoho_sync_core_log_to_email', false);
    }
    
    /**
     * Verificar si debe enviar este log por email
     * @param string $level Nivel del log
     * @return bool
     */
    private function should_email_log($level) {
        if (!$this->config['log_to_email']) {
            return false;
        }
        
        $email_levels = array(self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR);
        return in_array($level, $email_levels);
    }
    
    /**
     * Enviar notificación por email
     * @param array $log_data Datos del log
     */
    private function send_email_notification($log_data) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(
            __('[%s] Alerta Zoho Sync: %s', 'zoho-sync-core'),
            $site_name,
            strtoupper($log_data['level'])
        );
        
        $message = sprintf(
            __("Se ha registrado un evento importante en Zoho Sync Core:\n\nMódulo: %s\nNivel: %s\nMensaje: %s\nFecha: %s\n\nContexto: %s", 'zoho-sync-core'),
            $log_data['module'],
            $log_data['level'],
            $log_data['message'],
            $log_data['created_at'],
            $log_data['context'] ?: __('Sin contexto adicional', 'zoho-sync-core')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Obtener IP del cliente
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
}
