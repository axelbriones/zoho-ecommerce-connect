<?php

class ZSCORE_Plugin_Activator {
    
    private $core;
    private $required_tables = [
        'zscore_logs',
        'zscore_sync_queue',
        'zscore_modules'
    ];

    public function __construct() {
        $this->core = ZohoSyncCore::instance();
        
        register_activation_hook(ZSCORE_PLUGIN_FILE, [$this, 'activate_core']);
        register_deactivation_hook(ZSCORE_PLUGIN_FILE, [$this, 'deactivate_core']);
        
        add_action('admin_init', [$this, 'check_ecosystem_health']);
    }

    public function activate_core() {
        if (!$this->check_requirements()) {
            $this->deactivate_with_message();
            return;
        }

        $this->create_database_tables();
        $this->create_directories();
        $this->set_default_options();
        
        flush_rewrite_rules();
    }

    public function deactivate_core() {
        $this->deactivate_dependent_plugins();
        
        flush_rewrite_rules();
    }

    private function check_requirements() {
        global $wp_version;
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                printf(
                    __('Zoho Sync Core requiere PHP 7.4 o superior. Tu versión: %s', 'zoho-sync-core'),
                    PHP_VERSION
                );
                echo '</p></div>';
            });
            return false;
        }

        if (version_compare($wp_version, '5.8', '<')) {
            add_action('admin_notices', function() use ($wp_version) {
                echo '<div class="error"><p>';
                printf(
                    __('Zoho Sync Core requiere WordPress 5.8 o superior. Tu versión: %s', 'zoho-sync-core'),
                    $wp_version
                );
                echo '</p></div>';
            });
            return false;
        }

        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                _e('Zoho Sync Core requiere que WooCommerce esté instalado y activado.', 'zoho-sync-core');
                echo '</p></div>';
            });
            return false;
        }

        return true;
    }

    private function create_database_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();

        // Tabla de logs
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zscore_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context text,
            module varchar(50) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        dbDelta($sql);

        // Tabla de cola de sincronización
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zscore_sync_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            object_id bigint(20) NOT NULL,
            object_type varchar(50) NOT NULL,
            action varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            last_attempt datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY object_status (object_type,status)
        ) $charset_collate;";
        
        dbDelta($sql);

        // Tabla de módulos
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zscore_modules (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            module_name varchar(50) NOT NULL,
            version varchar(20) NOT NULL,
            status varchar(20) DEFAULT 'active',
            settings text,
            last_sync datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY module_name (module_name)
        ) $charset_collate;";
        
        dbDelta($sql);
    }

    private function create_directories() {
        $upload_dir = wp_upload_dir();
        $dirs = [
            'zoho-sync',
            'zoho-sync/logs',
            'zoho-sync/temp',
            'zoho-sync/exports'
        ];

        foreach ($dirs as $dir) {
            $path = $upload_dir['basedir'] . '/' . $dir;
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }

            // Crear archivo index.php para protección
            $index = $path . '/index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }
    }

    private function set_default_options() {
        $defaults = [
            'zoho_sync_core_version' => ZSCORE_VERSION,
            'zoho_sync_environment' => 'production',
            'zoho_sync_debug_mode' => false,
            'zoho_sync_queue_batch_size' => 50,
            'zoho_sync_retry_attempts' => 3,
            'zoho_sync_modules' => []
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }

    private function deactivate_dependent_plugins() {
        $dependent_plugins = [
            'zoho-sync-orders/zoho-sync-orders.php',
            'zoho-sync-customers/zoho-sync-customers.php',
            'zoho-sync-products/zoho-sync-products.php',
            'zoho-sync-inventory/zoho-sync-inventory.php',
            'zoho-sync-reports/zoho-sync-reports.php',
            'zoho-sync-zone-blocker/zoho-sync-zone-blocker.php',
            'zoho-distributor-portal/zoho-distributor-portal.php'
        ];

        deactivate_plugins($dependent_plugins);
    }

    public function check_ecosystem_health() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $health_check = [
            'database' => $this->check_database_tables(),
            'directories' => $this->check_directories(),
            'permissions' => $this->check_permissions(),
            'modules' => $this->check_module_health()
        ];

        update_option('zoho_sync_core_health', $health_check);

        if (in_array(false, array_column($health_check, 'status'))) {
            add_action('admin_notices', [$this, 'display_health_warnings']);
        }
    }

    private function check_database_tables() {
        global $wpdb;
        $missing_tables = [];

        foreach ($this->required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $missing_tables[] = $table;
            }
        }

        return [
            'status' => empty($missing_tables),
            'message' => empty($missing_tables) ? 
                __('Todas las tablas están presentes', 'zoho-sync-core') :
                sprintf(__('Tablas faltantes: %s', 'zoho-sync-core'), implode(', ', $missing_tables))
        ];
    }

    public function display_health_warnings() {
        $health = get_option('zoho_sync_core_health');
        
        echo '<div class="error"><p>';
        _e('Se detectaron problemas en el ecosistema Zoho Sync:', 'zoho-sync-core');
        echo '<ul>';
        
        foreach ($health as $component => $status) {
            if (!$status['status']) {
                echo '<li>' . esc_html($status['message']) . '</li>';
            }
        }
        
        echo '</ul></p></div>';
    }
}