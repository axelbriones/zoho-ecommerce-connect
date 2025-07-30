<?php
/**
 * Zoho Dashboard
 * 
 * Main dashboard functionality and system overview
 * 
 * @package ZohoSyncCore
 * @subpackage Includes
 * @since 1.0.0
 * @author Byron Briones <bbrion.es>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Zoho Dashboard Class
 * 
 * Handles dashboard functionality and system status
 */
class Zoho_Sync_Core_Dashboard {

    /**
     * Logger instance
     * 
     * @var Zoho_Sync_Core_Logger
     */
    private $logger;

    /**
     * Settings Manager instance
     * 
     * @var Zoho_Sync_Core_Settings_Manager
     */
    private $settings_manager;

    /**
     * Auth Manager instance
     * 
     * @var Zoho_Sync_Core_Auth_Manager
     */
    private $auth_manager;

    /**
     * Dependency Checker instance
     * 
     * @var Zoho_Sync_Core_Dependency_Checker
     */
    private $dependency_checker;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Zoho_Sync_Core_Logger();
        $this->settings_manager = new Zoho_Sync_Core_Settings_Manager();
        $this->auth_manager = new Zoho_Sync_Core_Auth_Manager();
        $this->dependency_checker = new Zoho_Sync_Core_Dependency_Checker();
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        add_action('admin_init', array($this, 'maybe_show_welcome_notice'));
        add_filter('admin_footer_text', array($this, 'admin_footer_text'));
    }

    /**
     * Get system status
     * 
     * @return array System status data
     */
    public function get_system_status() {
        $status = array(
            'connection' => $this->get_connection_status(),
            'modules' => $this->get_modules_status(),
            'health' => $this->get_health_status(),
            'statistics' => $this->get_statistics(),
            'recent_activity' => $this->get_recent_activity(),
            'alerts' => $this->get_system_alerts()
        );

        return $status;
    }

    /**
     * Get connection status
     * 
     * @return array Connection status
     */
    private function get_connection_status() {
        $settings = $this->settings_manager->get_settings();
        $last_connection_test = get_option('zoho_sync_core_last_connection_test', 0);
        $connection_status = get_option('zoho_sync_core_connection_status', array());

        $status = array(
            'connected' => false,
            'last_test' => $last_connection_test,
            'message' => __('No se ha probado la conexión', 'zoho-sync-core'),
            'region' => $settings['zoho_region'] ?? 'com',
            'services' => array()
        );

        if (!empty($connection_status)) {
            $status['connected'] = $connection_status['success'] ?? false;
            $status['message'] = $connection_status['message'] ?? '';
            $status['services'] = $connection_status['services'] ?? array();
        }

        return $status;
    }

    /**
     * Get modules status
     * 
     * @return array Modules status
     */
    private function get_modules_status() {
        return $this->dependency_checker->get_modules_status();
    }

    /**
     * Get health status
     * 
     * @return array Health status
     */
    private function get_health_status() {
        $health_data = get_option('zoho_sync_core_health_status', array());
        
        if (empty($health_data)) {
            return array(
                'overall_healthy' => true,
                'status' => array(),
                'timestamp' => null,
                'score' => 100
            );
        }

        $health_data['score'] = $this->dependency_checker->get_ecosystem_health_score();
        
        return $health_data;
    }

    /**
     * Get system statistics
     * 
     * @return array Statistics
     */
    private function get_statistics() {
        global $wpdb;

        $stats = array(
            'total_logs' => 0,
            'error_logs' => 0,
            'sync_operations' => 0,
            'active_tokens' => 0,
            'webhook_calls' => 0,
            'uptime' => $this->get_system_uptime()
        );

        // Get log statistics
        $logs_table = ZOHO_SYNC_LOGS_TABLE;
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table) {
            $stats['total_logs'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
            $stats['error_logs'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE level IN ('error', 'critical')");
            
            // Sync operations in last 24 hours
            $stats['sync_operations'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $logs_table WHERE message LIKE %s AND created_at > %s",
                '%sync%',
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            ));
        }

        // Get token statistics
        $tokens_table = ZOHO_SYNC_TOKENS_TABLE;
        if ($wpdb->get_var("SHOW TABLES LIKE '$tokens_table'") == $tokens_table) {
            $stats['active_tokens'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tokens_table WHERE expires_at > NOW()");
        }

        // Get webhook statistics
        $webhooks_table = ZOHO_SYNC_WEBHOOKS_TABLE;
        if ($wpdb->get_var("SHOW TABLES LIKE '$webhooks_table'") == $webhooks_table) {
            $stats['webhook_calls'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $webhooks_table WHERE created_at > %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            ));
        }

        return $stats;
    }

    /**
     * Get recent activity
     * 
     * @param int $limit Number of activities to retrieve
     * @return array Recent activities
     */
    private function get_recent_activity($limit = 10) {
        global $wpdb;

        $activities = array();
        $logs_table = ZOHO_SYNC_LOGS_TABLE;

        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $logs_table 
                 WHERE level IN ('info', 'success', 'warning', 'error') 
                 ORDER BY created_at DESC 
                 LIMIT %d",
                $limit
            ), ARRAY_A);

            foreach ($results as $result) {
                $activities[] = array(
                    'id' => $result['id'],
                    'level' => $result['level'],
                    'message' => $result['message'],
                    'source' => $result['source'],
                    'created_at' => $result['created_at'],
                    'time_ago' => human_time_diff(strtotime($result['created_at']), current_time('timestamp'))
                );
            }
        }

        return $activities;
    }

    /**
     * Get system alerts
     * 
     * @return array System alerts
     */
    private function get_system_alerts() {
        $alerts = array();

        // Check connection status
        $connection = $this->get_connection_status();
        if (!$connection['connected']) {
            $alerts[] = array(
                'type' => 'error',
                'message' => __('No hay conexión con Zoho. Verifica tu configuración.', 'zoho-sync-core'),
                'action' => admin_url('admin.php?page=zoho-sync-core-settings'),
                'action_text' => __('Configurar', 'zoho-sync-core')
            );
        }

        // Check for recent errors
        global $wpdb;
        $logs_table = ZOHO_SYNC_LOGS_TABLE;
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table) {
            $recent_errors = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $logs_table 
                 WHERE level = 'error' AND created_at > %s",
                date('Y-m-d H:i:s', strtotime('-1 hour'))
            ));

            if ($recent_errors > 5) {
                $alerts[] = array(
                    'type' => 'warning',
                    'message' => sprintf(__('Se han detectado %d errores en la última hora.', 'zoho-sync-core'), $recent_errors),
                    'action' => admin_url('admin.php?page=zoho-sync-core-logs'),
                    'action_text' => __('Ver Logs', 'zoho-sync-core')
                );
            }
        }

        // Check ecosystem completeness
        $modules = $this->get_modules_status();
        $completion = $modules['overall_status']['completion_percentage'] ?? 0;
        
        if ($completion < 50) {
            $alerts[] = array(
                'type' => 'info',
                'message' => sprintf(__('El ecosistema está %d%% completo. Considera activar más módulos.', 'zoho-sync-core'), $completion),
                'action' => admin_url('admin.php?page=zoho-sync-core-modules'),
                'action_text' => __('Ver Módulos', 'zoho-sync-core')
            );
        }

        // Check for expired tokens
        $expired_tokens = $this->auth_manager->get_expired_tokens_count();
        if ($expired_tokens > 0) {
            $alerts[] = array(
                'type' => 'warning',
                'message' => sprintf(__('Hay %d tokens expirados que necesitan renovación.', 'zoho-sync-core'), $expired_tokens),
                'action' => admin_url('admin.php?page=zoho-sync-core-settings'),
                'action_text' => __('Renovar', 'zoho-sync-core')
            );
        }

        return $alerts;
    }

    /**
     * Get system uptime
     * 
     * @return array Uptime information
     */
    private function get_system_uptime() {
        $activation_time = get_option('zoho_sync_core_activation_time');
        
        if (!$activation_time) {
            return array(
                'seconds' => 0,
                'formatted' => __('Desconocido', 'zoho-sync-core')
            );
        }

        $uptime_seconds = current_time('timestamp') - $activation_time;
        
        return array(
            'seconds' => $uptime_seconds,
            'formatted' => $this->format_uptime($uptime_seconds)
        );
    }

    /**
     * Format uptime in human readable format
     * 
     * @param int $seconds Uptime in seconds
     * @return string Formatted uptime
     */
    private function format_uptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = array();
        
        if ($days > 0) {
            $parts[] = sprintf(_n('%d día', '%d días', $days, 'zoho-sync-core'), $days);
        }
        
        if ($hours > 0) {
            $parts[] = sprintf(_n('%d hora', '%d horas', $hours, 'zoho-sync-core'), $hours);
        }
        
        if ($minutes > 0 && $days == 0) {
            $parts[] = sprintf(_n('%d minuto', '%d minutos', $minutes, 'zoho-sync-core'), $minutes);
        }

        return !empty($parts) ? implode(', ', $parts) : __('Menos de un minuto', 'zoho-sync-core');
    }

    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'zoho_sync_core_status',
            __('Estado de Zoho Sync', 'zoho-sync-core'),
            array($this, 'dashboard_widget_status')
        );

        wp_add_dashboard_widget(
            'zoho_sync_core_activity',
            __('Actividad Reciente - Zoho Sync', 'zoho-sync-core'),
            array($this, 'dashboard_widget_activity')
        );
    }

    /**
     * Dashboard widget: System status
     */
    public function dashboard_widget_status() {
        $status = $this->get_system_status();
        $connection = $status['connection'];
        $health = $status['health'];
        $stats = $status['statistics'];

        echo '<div class="zoho-sync-dashboard-widget">';
        
        // Connection status
        echo '<div class="connection-status">';
        echo '<h4>' . __('Estado de Conexión', 'zoho-sync-core') . '</h4>';
        if ($connection['connected']) {
            echo '<span class="status-indicator connected">' . __('Conectado', 'zoho-sync-core') . '</span>';
            echo '<p>' . sprintf(__('Región: %s', 'zoho-sync-core'), strtoupper($connection['region'])) . '</p>';
        } else {
            echo '<span class="status-indicator disconnected">' . __('Desconectado', 'zoho-sync-core') . '</span>';
            echo '<p>' . esc_html($connection['message']) . '</p>';
        }
        echo '</div>';

        // Health score
        echo '<div class="health-status">';
        echo '<h4>' . __('Salud del Sistema', 'zoho-sync-core') . '</h4>';
        $health_score = $health['score'] ?? 100;
        $health_class = $health_score >= 80 ? 'good' : ($health_score >= 60 ? 'warning' : 'critical');
        echo '<div class="health-score ' . $health_class . '">' . $health_score . '%</div>';
        echo '</div>';

        // Quick stats
        echo '<div class="quick-stats">';
        echo '<h4>' . __('Estadísticas Rápidas', 'zoho-sync-core') . '</h4>';
        echo '<ul>';
        echo '<li>' . sprintf(__('Logs totales: %d', 'zoho-sync-core'), $stats['total_logs']) . '</li>';
        echo '<li>' . sprintf(__('Errores: %d', 'zoho-sync-core'), $stats['error_logs']) . '</li>';
        echo '<li>' . sprintf(__('Tokens activos: %d', 'zoho-sync-core'), $stats['active_tokens']) . '</li>';
        echo '<li>' . sprintf(__('Tiempo activo: %s', 'zoho-sync-core'), $stats['uptime']['formatted']) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<p><a href="' . admin_url('admin.php?page=zoho-sync-core') . '" class="button">' . __('Ver Dashboard Completo', 'zoho-sync-core') . '</a></p>';
        
        echo '</div>';

        // Add some basic styling
        echo '<style>
        .zoho-sync-dashboard-widget { font-size: 13px; }
        .zoho-sync-dashboard-widget h4 { margin: 10px 0 5px 0; font-weight: 600; }
        .status-indicator { padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
        .status-indicator.connected { background: #d4edda; color: #155724; }
        .status-indicator.disconnected { background: #f8d7da; color: #721c24; }
        .health-score { font-size: 24px; font-weight: bold; text-align: center; padding: 10px; border-radius: 5px; }
        .health-score.good { background: #d4edda; color: #155724; }
        .health-score.warning { background: #fff3cd; color: #856404; }
        .health-score.critical { background: #f8d7da; color: #721c24; }
        .quick-stats ul { margin: 0; }
        .quick-stats li { margin: 2px 0; }
        </style>';
    }

    /**
     * Dashboard widget: Recent activity
     */
    public function dashboard_widget_activity() {
        $activities = $this->get_recent_activity(5);

        echo '<div class="zoho-sync-activity-widget">';
        
        if (empty($activities)) {
            echo '<p>' . __('No hay actividad reciente.', 'zoho-sync-core') . '</p>';
        } else {
            echo '<ul class="activity-list">';
            foreach ($activities as $activity) {
                $level_class = 'activity-' . $activity['level'];
                echo '<li class="' . $level_class . '">';
                echo '<span class="activity-time">' . $activity['time_ago'] . '</span>';
                echo '<span class="activity-message">' . esc_html($activity['message']) . '</span>';
                if ($activity['source']) {
                    echo '<span class="activity-source">(' . esc_html($activity['source']) . ')</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '<p><a href="' . admin_url('admin.php?page=zoho-sync-core-logs') . '" class="button">' . __('Ver Todos los Logs', 'zoho-sync-core') . '</a></p>';
        
        echo '</div>';

        // Add styling
        echo '<style>
        .activity-list { margin: 0; }
        .activity-list li { margin: 8px 0; padding: 5px; border-left: 3px solid #ddd; }
        .activity-list li.activity-error { border-left-color: #dc3545; }
        .activity-list li.activity-warning { border-left-color: #ffc107; }
        .activity-list li.activity-success { border-left-color: #28a745; }
        .activity-list li.activity-info { border-left-color: #17a2b8; }
        .activity-time { font-size: 11px; color: #666; display: block; }
        .activity-message { font-weight: 500; }
        .activity-source { font-size: 11px; color: #888; }
        </style>';
    }

    /**
     * Maybe show welcome notice for new installations
     */
    public function maybe_show_welcome_notice() {
        $dismissed = get_option('zoho_sync_core_welcome_dismissed', false);
        $activation_time = get_option('zoho_sync_core_activation_time');
        
        if (!$dismissed && $activation_time && (current_time('timestamp') - $activation_time) < 604800) { // 1 week
            add_action('admin_notices', array($this, 'welcome_notice'));
        }
    }

    /**
     * Display welcome notice
     */
    public function welcome_notice() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'zoho-sync') !== false) {
            return; // Don't show on our own pages
        }

        echo '<div class="notice notice-info is-dismissible" data-notice="zoho-sync-welcome">';
        echo '<h3>' . __('¡Bienvenido a Zoho Sync Core!', 'zoho-sync-core') . '</h3>';
        echo '<p>' . __('Gracias por instalar Zoho Sync Core. Para comenzar, configura tu conexión con Zoho.', 'zoho-sync-core') . '</p>';
        echo '<p>';
        echo '<a href="' . admin_url('admin.php?page=zoho-sync-core-settings') . '" class="button button-primary">' . __('Configurar Ahora', 'zoho-sync-core') . '</a> ';
        echo '<a href="' . admin_url('admin.php?page=zoho-sync-core') . '" class="button">' . __('Ver Dashboard', 'zoho-sync-core') . '</a>';
        echo '</p>';
        echo '</div>';

        // Add dismiss functionality
        echo '<script>
        jQuery(document).ready(function($) {
            $(document).on("click", "[data-notice=zoho-sync-welcome] .notice-dismiss", function() {
                $.post(ajaxurl, {
                    action: "zoho_sync_dismiss_notice",
                    notice: "welcome",
                    nonce: "' . wp_create_nonce('zoho_sync_dismiss_notice') . '"
                });
            });
        });
        </script>';
    }

    /**
     * Customize admin footer text
     * 
     * @param string $text Current footer text
     * @return string Modified footer text
     */
    public function admin_footer_text($text) {
        $screen = get_current_screen();
        
        if (strpos($screen->id, 'zoho-sync') !== false) {
            $text = sprintf(
                __('Gracias por usar %s. Desarrollado por %s.', 'zoho-sync-core'),
                '<strong>Zoho Sync Core</strong>',
                '<a href="https://bbrion.es" target="_blank">Byron Briones</a>'
            );
        }

        return $text;
    }

    /**
     * Get dashboard data for AJAX requests
     * 
     * @return array Dashboard data
     */
    public function get_dashboard_data() {
        return array(
            'system_status' => $this->get_system_status(),
            'recommendations' => $this->dependency_checker->get_ecosystem_recommendations(),
            'last_updated' => current_time('mysql')
        );
    }

    /**
     * Export system status for support
     * 
     * @return array System status export
     */
    public function export_system_status() {
        $export = array(
            'timestamp' => current_time('mysql'),
            'version' => ZOHO_SYNC_CORE_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'system_status' => $this->get_system_status(),
            'settings' => $this->settings_manager->get_settings(),
            'modules' => $this->get_modules_status()
        );

        // Remove sensitive data
        if (isset($export['settings']['zoho_client_secret'])) {
            $export['settings']['zoho_client_secret'] = '***HIDDEN***';
        }
        if (isset($export['settings']['zoho_refresh_token'])) {
            $export['settings']['zoho_refresh_token'] = '***HIDDEN***';
        }

        return $export;
    }
}
