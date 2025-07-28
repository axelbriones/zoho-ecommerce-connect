<?php
/**
 * Gestor de Base de Datos para Zoho Sync Core
 * 
 * @package ZohoSyncCore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar la base de datos del plugin
 */
class Zoho_Sync_Core_Database_Manager {
    
    /**
     * Versión actual de la base de datos
     * @var string
     */
    private $db_version;
    
    /**
     * Instancia de wpdb
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db_version = ZOHO_SYNC_CORE_DB_VERSION;
        
        // Hook para actualizaciones de base de datos
        add_action('plugins_loaded', array($this, 'check_database_version'));
    }
    
    /**
     * Verificar si necesita actualizar la base de datos
     */
    public function check_database_version() {
        $installed_version = get_option('zoho_sync_core_db_version', '0.0.0');
        
        if (version_compare($installed_version, $this->db_version, '<')) {
            $this->create_tables();
            update_option('zoho_sync_core_db_version', $this->db_version);
        }
    }
    
    /**
     * Crear todas las tablas necesarias
     */
    public function create_tables() {
        $this->create_settings_table();
        $this->create_logs_table();
        $this->create_tokens_table();
        $this->create_modules_table();
        
        // Log de creación de tablas
        if (class_exists('Zoho_Sync_Core_Logger')) {
            ZohoSyncCore::log('info', 'Tablas de base de datos creadas/actualizadas', array(
                'version' => $this->db_version
            ));
        }
    }
    
    /**
     * Crear tabla de configuraciones
     */
    private function create_settings_table() {
        $table_name = $this->wpdb->prefix . ZOHO_SYNC_CORE_SETTINGS_TABLE;
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext,
            module varchar(100) DEFAULT 'core',
            is_encrypted tinyint(1) DEFAULT 0,
            autoload tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key),
            KEY module (module),
            KEY autoload (autoload)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Crear tabla de logs
     */
    private function create_logs_table() {
        $table_name = $this->wpdb->prefix . ZOHO_SYNC_CORE_LOGS_TABLE;
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            module varchar(100) NOT NULL DEFAULT 'core',
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY module_level (module, level),
            KEY created_at (created_at),
            KEY user_id (user_id),
            KEY level (level)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Crear tabla de tokens de autenticación
     */
    private function create_tokens_table() {
        $table_name = $this->wpdb->prefix . ZOHO_SYNC_CORE_TOKENS_TABLE;
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            service varchar(100) NOT NULL,
            region varchar(10) NOT NULL DEFAULT 'com',
            access_token text,
            refresh_token text,
            token_type varchar(50) DEFAULT 'Bearer',
            expires_at datetime,
            scope text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY service_region (service, region),
            KEY expires_at (expires_at),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Crear tabla de módulos registrados
     */
    private function create_modules_table() {
        $table_name = $this->wpdb->prefix . ZOHO_SYNC_CORE_MODULES_TABLE;
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            module_name varchar(100) NOT NULL,
            module_slug varchar(100) NOT NULL,
            version varchar(20) DEFAULT '1.0.0',
            is_active tinyint(1) DEFAULT 1,
            last_sync datetime DEFAULT NULL,
            sync_status varchar(50) DEFAULT 'idle',
            error_count int DEFAULT 0,
            last_error text DEFAULT NULL,
            config longtext DEFAULT NULL,
            dependencies text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY module_slug (module_slug),
            KEY is_active (is_active),
            KEY sync_status (sync_status),
            KEY last_sync (last_sync)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Obtener nombre completo de tabla
     * @param string $table_name Nombre de la tabla sin prefijo
     * @return string Nombre completo de la tabla
     */
    public function get_table_name($table_name) {
        return $this->wpdb->prefix . $table_name;
    }
    
    /**
     * Limpiar logs antiguos
     * @param int $days Días de retención
     */
    public function cleanup_old_logs($days = null) {
        if ($days === null) {
            $days = ZOHO_SYNC_CORE_LOG_RETENTION_DAYS;
        }
        
        $table_name = $this->get_table_name(ZOHO_SYNC_CORE_LOGS_TABLE);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < %s",
                $cutoff_date
            )
        );
        
        if ($deleted !== false) {
            ZohoSyncCore::log('info', 'Logs antiguos limpiados', array(
                'deleted_count' => $deleted,
                'cutoff_date' => $cutoff_date
            ));
        }
        
        return $deleted;
    }
    
    /**
     * Optimizar tablas
     */
    public function optimize_tables() {
        $tables = array(
            ZOHO_SYNC_CORE_SETTINGS_TABLE,
            ZOHO_SYNC_CORE_LOGS_TABLE,
            ZOHO_SYNC_CORE_TOKENS_TABLE,
            ZOHO_SYNC_CORE_MODULES_TABLE
        );
        
        foreach ($tables as $table) {
            $table_name = $this->get_table_name($table);
            $this->wpdb->query("OPTIMIZE TABLE $table_name");
        }
        
        ZohoSyncCore::log('info', 'Tablas optimizadas');
    }
    
    /**
     * Obtener estadísticas de la base de datos
     * @return array Estadísticas
     */
    public function get_database_stats() {
        $stats = array();
        
        // Estadísticas de logs
        $logs_table = $this->get_table_name(ZOHO_SYNC_CORE_LOGS_TABLE);
        $stats['logs_total'] = $this->wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
        $stats['logs_today'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table WHERE DATE(created_at) = CURDATE()"
        );
        $stats['logs_errors'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $logs_table WHERE level IN ('error', 'critical')"
        );
        
        // Estadísticas de módulos
        $modules_table = $this->get_table_name(ZOHO_SYNC_CORE_MODULES_TABLE);
        $stats['modules_total'] = $this->wpdb->get_var("SELECT COUNT(*) FROM $modules_table");
        $stats['modules_active'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $modules_table WHERE is_active = 1"
        );
        
        // Estadísticas de tokens
        $tokens_table = $this->get_table_name(ZOHO_SYNC_CORE_TOKENS_TABLE);
        $stats['tokens_total'] = $this->wpdb->get_var("SELECT COUNT(*) FROM $tokens_table");
        $stats['tokens_active'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $tokens_table WHERE is_active = 1 AND expires_at > NOW()"
        );
        
        return $stats;
    }
    
    /**
     * Eliminar todas las tablas del plugin
     */
    public function drop_tables() {
        $tables = array(
            ZOHO_SYNC_CORE_SETTINGS_TABLE,
            ZOHO_SYNC_CORE_LOGS_TABLE,
            ZOHO_SYNC_CORE_TOKENS_TABLE,
            ZOHO_SYNC_CORE_MODULES_TABLE
        );
        
        foreach ($tables as $table) {
            $table_name = $this->get_table_name($table);
            $this->wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        // Eliminar opciones relacionadas
        delete_option('zoho_sync_core_db_version');
    }
    
    /**
     * Verificar integridad de las tablas
     * @return array Resultado de la verificación
     */
    public function check_tables_integrity() {
        $results = array();
        $tables = array(
            ZOHO_SYNC_CORE_SETTINGS_TABLE,
            ZOHO_SYNC_CORE_LOGS_TABLE,
            ZOHO_SYNC_CORE_TOKENS_TABLE,
            ZOHO_SYNC_CORE_MODULES_TABLE
        );
        
        foreach ($tables as $table) {
            $table_name = $this->get_table_name($table);
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            $results[$table] = ($exists === $table_name);
        }
        
        return $results;
    }
}