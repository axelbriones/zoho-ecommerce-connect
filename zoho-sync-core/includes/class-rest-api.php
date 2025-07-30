<?php
/**
 * Zoho REST API
 * 
 * REST API endpoints for Zoho Sync Core
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
 * Zoho REST API Class
 * 
 * Handles REST API endpoints and responses
 */
class Zoho_Sync_Core_REST_API {

    /**
     * API namespace
     * 
     * @var string
     */
    private $namespace = 'zoho-sync-core/v1';

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
     * Dashboard instance
     * 
     * @var Zoho_Sync_Core_Dashboard
     */
    private $dashboard;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Zoho_Sync_Core_Logger();
        $this->settings_manager = new Zoho_Sync_Core_Settings_Manager();
        $this->auth_manager = new Zoho_Sync_Core_Auth_Manager();
        $this->dashboard = new Zoho_Sync_Core_Dashboard();
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // System status endpoints
        register_rest_route($this->namespace, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_system_status'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // Connection test endpoint
        register_rest_route($this->namespace, '/connection/test', array(
            'methods' => 'POST',
            'callback' => array($this, 'test_connection'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // Settings endpoints
        register_rest_route($this->namespace, '/settings', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_settings'),
                'permission_callback' => array($this, 'check_admin_permissions')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'update_settings'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args' => $this->get_settings_schema()
            )
        ));

        // Logs endpoints
        register_rest_route($this->namespace, '/logs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_logs'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ),
                'per_page' => array(
                    'default' => 20,
                    'sanitize_callback' => 'absint'
                ),
                'level' => array(
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'source' => array(
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Clear logs endpoint
        register_rest_route($this->namespace, '/logs/clear', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'clear_logs'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // Dashboard data endpoint
        register_rest_route($this->namespace, '/dashboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_dashboard_data'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // Modules status endpoint
        register_rest_route($this->namespace, '/modules', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_modules_status'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // Token refresh endpoint
        register_rest_route($this->namespace, '/tokens/refresh', array(
            'methods' => 'POST',
            'callback' => array($this, 'refresh_tokens'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // Webhook endpoint (public)
        register_rest_route($this->namespace, '/webhook/(?P<service>[a-zA-Z0-9_-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'check_webhook_permissions'),
            'args' => array(
                'service' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Health check endpoint (public)
        register_rest_route($this->namespace, '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true'
        ));

        // Export system status
        register_rest_route($this->namespace, '/export/system-status', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_system_status'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
    }

    /**
     * Check admin permissions
     * 
     * @param WP_REST_Request $request Request object
     * @return bool True if user has permissions
     */
    public function check_admin_permissions($request) {
        return current_user_can('manage_options');
    }

    /**
     * Check webhook permissions
     * 
     * @param WP_REST_Request $request Request object
     * @return bool True if webhook is authorized
     */
    public function check_webhook_permissions($request) {
        $webhook_secret = $this->settings_manager->get('webhook_secret');
        $provided_secret = $request->get_header('X-Zoho-Webhook-Secret');
        
        if (empty($webhook_secret) || empty($provided_secret)) {
            return false;
        }

        return hash_equals($webhook_secret, $provided_secret);
    }

    /**
     * Get system status
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_system_status($request) {
        try {
            $status = $this->dashboard->get_system_status();
            
            return new WP_REST_Response($status, 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error getting system status', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'error' => __('Error al obtener el estado del sistema', 'zoho-sync-core')
            ), 500);
        }
    }

    /**
     * Test connection
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function test_connection($request) {
        try {
            $result = $this->auth_manager->test_connection();
            
            $status_code = $result['success'] ? 200 : 400;
            
            return new WP_REST_Response($result, $status_code);
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error testing connection', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Error al probar la conexión', 'zoho-sync-core')
            ), 500);
        }
    }

    /**
     * Get settings
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_settings($request) {
        try {
            $settings = $this->settings_manager->get_settings();
            
            // Remove sensitive data
            unset($settings['zoho_client_secret']);
            unset($settings['zoho_refresh_token']);
            unset($settings['webhook_secret']);
            
            return new WP_REST_Response($settings, 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error getting settings', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'error' => __('Error al obtener la configuración', 'zoho-sync-core')
            ), 500);
        }
    }

    /**
     * Update settings
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function update_settings($request) {
        try {
            $params = $request->get_params();
            $updated = array();

            foreach ($params as $key => $value) {
                if ($this->settings_manager->set($key, $value)) {
                    $updated[] = $key;
                }
            }

            $this->logger->log('info', 'Settings updated via REST API', array(
                'updated_settings' => $updated,
                'user_id' => get_current_user_id()
            ));

            return new WP_REST_Response(array(
                'success' => true,
                'message' => __('Configuración actualizada correctamente', 'zoho-sync-core'),
                'updated' => $updated
            ), 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error updating settings', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Error al actualizar la configuración', 'zoho-sync-core')
            ), 500);
        }
    }

    /**
     * Get logs
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_logs($request) {
        try {
            $page = $request->get_param('page');
            $per_page = $request->get_param('per_page');
            $level = $request->get_param('level');
            $source = $request->get_param('source');

            $filters = array();
            if (!empty($level)) {
                $filters['level'] = $level;
            }
            if (!empty($source)) {
                $filters['source'] = $source;
            }

            $logs = $this->logger->get_logs($filters, $per_page, ($page - 1) * $per_page);
            $total = $this->logger->count_logs($filters);

            return new WP_REST_Response(array(
                'logs' => $logs,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total / $per_page)
            ), 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error getting logs', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'error' => __('Error al obtener los logs', 'zoho-sync-core')
            ), 500);
        }
    }

    /**
     * Clear logs
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function clear_logs($request) {
        try {
            $result = $this->logger->clear_logs();
            
            if ($result) {
                $this->logger->log('info', 'Logs cleared via REST API', array(
                    'user_id' => get_current_user_id()
                ));

                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => __('Logs limpiados correctamente', 'zoho-sync-core')
                ), 200);
            } else {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => __('Error al limpiar los logs', 'zoho-sync-core')
                ), 500);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error clearing logs', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Error al limpiar los logs', 'zoho-sync-core')
            ), 500);
        }
    }

    /**
     * Get dashboard data
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_dashboard_data($request) {
        try {
            $data = $this->dashboard->get_dashboard_data();
            
            return new WP_REST_Response($data, 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error getting dashboard data', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'error' => __('Error al obtener los datos del dashboard', 'zoho-sync-core')
            ), 500);
        }
    }

    /**
     * Get modules status
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_modules_status($request) {
        try {
            $dependency_checker = new Zoho_Sync_Core_Dependency_Checker();
            $modules = $dependency_checker->get_modules_status();
            
            return new WP_REST_Response($modules, 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error getting modules status', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'error' => __('Error al obtener el estado de los módulos', 'zoho-sync-core')
            ), 500);
        }
    }

    /**
     * Refresh tokens
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function refresh_tokens($request) {
        try {
            $result = $this->auth_manager->refresh_all_tokens();
            
            $status_code = $result['success'] ? 200 : 400;
            
            return new WP_REST_Response($result, $status_code);
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error refreshing tokens', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Error al renovar los tokens', 'zoho-sync-core')
            ), 500);
        }
    }

    /**
     * Handle webhook
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle_webhook($request) {
        try {
            $service = $request->get_param('service');
            $body = $request->get_body();
            $headers = $request->get_headers();

            $webhook_handler = new Zoho_Sync_Core_Webhook_Handler();
            $result = $webhook_handler->process_webhook($service, $body, $headers);

            if ($result['success']) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Webhook processed successfully'
                ), 200);
            } else {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => $result['message']
                ), 400);
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error handling webhook', array(
                'service' => $service ?? 'unknown',
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Internal server error'
            ), 500);
        }
    }

    /**
     * Health check
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function health_check($request) {
        $health = array(
            'status' => 'ok',
            'timestamp' => current_time('mysql'),
            'version' => ZOHO_SYNC_CORE_VERSION,
            'checks' => array(
                'database' => $this->check_database_health(),
                'files' => $this->check_files_health(),
                'memory' => $this->check_memory_health()
            )
        );

        $overall_healthy = true;
        foreach ($health['checks'] as $check) {
            if (!$check['healthy']) {
                $overall_healthy = false;
                break;
            }
        }

        $health['status'] = $overall_healthy ? 'ok' : 'error';
        $status_code = $overall_healthy ? 200 : 503;

        return new WP_REST_Response($health, $status_code);
    }

    /**
     * Export system status
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function export_system_status($request) {
        try {
            $export = $this->dashboard->export_system_status();
            
            return new WP_REST_Response($export, 200);
        } catch (Exception $e) {
            $this->logger->log('error', 'REST API: Error exporting system status', array(
                'error' => $e->getMessage()
            ));

            return new WP_REST_Response(array(
                'error' => __('Error al exportar el estado del sistema', 'zoho-sync-core')
            ), 500);
        }
    }

    /**
     * Get settings schema for validation
     * 
     * @return array Settings schema
     */
    private function get_settings_schema() {
        return array(
            'zoho_client_id' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'zoho_client_secret' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'zoho_region' => array(
                'type' => 'string',
                'enum' => array('com', 'eu', 'in', 'com.au', 'jp'),
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'log_level' => array(
                'type' => 'string',
                'enum' => array('debug', 'info', 'warning', 'error', 'critical'),
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'log_retention_days' => array(
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 365,
                'sanitize_callback' => 'absint'
            ),
            'enable_webhooks' => array(
                'type' => 'boolean'
            ),
            'sync_frequency' => array(
                'type' => 'string',
                'enum' => array('every_15_minutes', 'every_30_minutes', 'hourly', 'every_2_hours', 'every_6_hours', 'daily'),
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'enable_debug' => array(
                'type' => 'boolean'
            )
        );
    }

    /**
     * Check database health
     * 
     * @return array Health status
     */
    private function check_database_health() {
        global $wpdb;
        
        try {
            $wpdb->get_var("SELECT 1");
            return array(
                'healthy' => true,
                'message' => 'Database connection OK'
            );
        } catch (Exception $e) {
            return array(
                'healthy' => false,
                'message' => 'Database connection failed'
            );
        }
    }

    /**
     * Check files health
     * 
     * @return array Health status
     */
    private function check_files_health() {
        $required_files = array(
            ZOHO_SYNC_CORE_PLUGIN_FILE,
            ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-core.php',
            ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-logger.php',
            ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-auth-manager.php'
        );

        foreach ($required_files as $file) {
            if (!file_exists($file)) {
                return array(
                    'healthy' => false,
                    'message' => 'Required files missing'
                );
            }
        }

        return array(
            'healthy' => true,
            'message' => 'All required files present'
        );
    }

    /**
     * Check memory health
     * 
     * @return array Health status
     */
    private function check_memory_health() {
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        
        if ($memory_limit === '-1') {
            return array(
                'healthy' => true,
                'message' => 'No memory limit'
            );
        }
        
        $memory_limit_bytes = $this->convert_to_bytes($memory_limit);
        $usage_percentage = ($memory_usage / $memory_limit_bytes) * 100;
        
        if ($usage_percentage > 90) {
            return array(
                'healthy' => false,
                'message' => sprintf('High memory usage: %.1f%%', $usage_percentage)
            );
        }
        
        return array(
            'healthy' => true,
            'message' => sprintf('Memory usage: %.1f%%', $usage_percentage)
        );
    }

    /**
     * Convert memory limit to bytes
     * 
     * @param string $memory_limit Memory limit string
     * @return int Bytes
     */
    private function convert_to_bytes($memory_limit) {
        $memory_limit = trim($memory_limit);
        $last = strtolower($memory_limit[strlen($memory_limit) - 1]);
        $memory_limit = (int) $memory_limit;

        switch ($last) {
            case 'g':
                $memory_limit *= 1024;
            case 'm':
                $memory_limit *= 1024;
            case 'k':
                $memory_limit *= 1024;
        }

        return $memory_limit;
    }
}
