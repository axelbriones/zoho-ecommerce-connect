<?php
/**
 * Manejador de Webhooks para Zoho Sync Core
 * 
 * @package ZohoSyncCore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Webhook Handler para el ecosistema Zoho Sync
 * Maneja todos los webhooks entrantes de Zoho
 */
class Zoho_Sync_Core_Webhook_Handler {
    
    /**
     * Endpoint base para webhooks
     * @var string
     */
    private $webhook_endpoint = 'zoho-sync-webhook';
    
    /**
     * Handlers registrados por módulo
     * @var array
     */
    private $registered_handlers = array();
    
    /**
     * Configuración de seguridad
     * @var array
     */
    private $security_config = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_security_config();
        $this->init_hooks();
        $this->register_core_handlers();
    }
    
    /**
     * Inicializar configuración de seguridad
     */
    private function init_security_config() {
        $this->security_config = array(
            'verify_signature' => ZohoSyncCore::settings()->get('webhook_verify_signature', true),
            'secret_key' => ZohoSyncCore::settings()->get('webhook_secret_key', ''),
            'allowed_ips' => ZohoSyncCore::settings()->get('webhook_allowed_ips', array()),
            'rate_limit' => ZohoSyncCore::settings()->get('webhook_rate_limit', 100),
            'rate_window' => ZohoSyncCore::settings()->get('webhook_rate_window', 3600)
        );
    }
    
    /**
     * Inicializar hooks de WordPress
     */
    private function init_hooks() {
        // Registrar endpoint personalizado
        add_action('init', array($this, 'add_webhook_endpoint'));
        add_action('template_redirect', array($this, 'handle_webhook_request'));
        
        // Agregar reglas de rewrite
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Hook para limpiar logs de webhooks
        add_action('zoho_sync_core_daily_cleanup', array($this, 'cleanup_webhook_logs'));
    }
    
    /**
     * Registrar handlers del core
     */
    private function register_core_handlers() {
        // Handler para eventos de autenticación
        $this->register_handler('auth', array(
            'callback' => array($this, 'handle_auth_webhook'),
            'events' => array('token_refresh', 'auth_error'),
            'description' => __('Eventos de autenticación', 'zoho-sync-core')
        ));
        
        // Handler para eventos del sistema
        $this->register_handler('system', array(
            'callback' => array($this, 'handle_system_webhook'),
            'events' => array('health_check', 'maintenance'),
            'description' => __('Eventos del sistema', 'zoho-sync-core')
        ));
    }
    
    /**
     * Agregar endpoint de webhook
     */
    public function add_webhook_endpoint() {
        add_rewrite_endpoint($this->webhook_endpoint, EP_ROOT);
    }
    
    /**
     * Agregar reglas de rewrite
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^' . $this->webhook_endpoint . '/([^/]+)/?$',
            'index.php?' . $this->webhook_endpoint . '=1&webhook_module=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^' . $this->webhook_endpoint . '/([^/]+)/([^/]+)/?$',
            'index.php?' . $this->webhook_endpoint . '=1&webhook_module=$matches[1]&webhook_event=$matches[2]',
            'top'
        );
    }
    
    /**
     * Agregar variables de consulta
     * @param array $vars Variables existentes
     * @return array Variables actualizadas
     */
    public function add_query_vars($vars) {
        $vars[] = $this->webhook_endpoint;
        $vars[] = 'webhook_module';
        $vars[] = 'webhook_event';
        return $vars;
    }
    
    /**
     * Manejar request de webhook
     */
    public function handle_webhook_request() {
        // Verificar si es un request de webhook
        if (!get_query_var($this->webhook_endpoint)) {
            return;
        }
        
        // Obtener parámetros
        $module = get_query_var('webhook_module');
        $event = get_query_var('webhook_event');
        
        // Log del request entrante
        $this->log_webhook_request($module, $event);
        
        try {
            // Verificar seguridad
            $security_check = $this->verify_webhook_security();
            if (!$security_check['valid']) {
                $this->send_webhook_response(403, array(
                    'error' => 'Security verification failed',
                    'message' => $security_check['message']
                ));
                return;
            }
            
            // Obtener datos del webhook
            $webhook_data = $this->parse_webhook_data();
            
            // Procesar webhook
            $result = $this->process_webhook($module, $event, $webhook_data);
            
            if ($result['success']) {
                $this->send_webhook_response(200, array(
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result['data'] ?? null
                ));
            } else {
                $this->send_webhook_response(400, array(
                    'status' => 'error',
                    'message' => $result['message'],
                    'error_code' => $result['error_code'] ?? 'processing_error'
                ));
            }
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 'Error procesando webhook', array(
                'module' => $module,
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ), 'webhook');
            
            $this->send_webhook_response(500, array(
                'status' => 'error',
                'message' => 'Internal server error',
                'error_code' => 'internal_error'
            ));
        }
    }
    
    /**
     * Verificar seguridad del webhook
     * @return array Resultado de verificación
     */
    private function verify_webhook_security() {
        $result = array(
            'valid' => true,
            'message' => ''
        );
        
        // Verificar método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $result['valid'] = false;
            $result['message'] = 'Only POST method allowed';
            return $result;
        }
        
        // Verificar IP si está configurado
        if (!empty($this->security_config['allowed_ips'])) {
            $client_ip = $this->get_client_ip();
            if (!in_array($client_ip, $this->security_config['allowed_ips'])) {
                $result['valid'] = false;
                $result['message'] = 'IP not allowed';
                
                ZohoSyncCore::log('warning', 'Webhook desde IP no autorizada', array(
                    'client_ip' => $client_ip,
                    'allowed_ips' => $this->security_config['allowed_ips']
                ), 'webhook');
                
                return $result;
            }
        }
        
        // Verificar rate limiting
        if (!$this->check_rate_limit()) {
            $result['valid'] = false;
            $result['message'] = 'Rate limit exceeded';
            return $result;
        }
        
        // Verificar firma si está habilitado
        if ($this->security_config['verify_signature'] && !empty($this->security_config['secret_key'])) {
            if (!$this->verify_webhook_signature()) {
                $result['valid'] = false;
                $result['message'] = 'Invalid signature';
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Verificar firma del webhook
     * @return bool True si la firma es válida
     */
    private function verify_webhook_signature() {
        $signature_header = $_SERVER['HTTP_X_ZOHO_WEBHOOK_SIGNATURE'] ?? '';
        
        if (empty($signature_header)) {
            return false;
        }
        
        $payload = file_get_contents('php://input');
        $expected_signature = hash_hmac('sha256', $payload, $this->security_config['secret_key']);
        
        return hash_equals($expected_signature, $signature_header);
    }
    
    /**
     * Verificar rate limiting
     * @return bool True si está dentro del límite
     */
    private function check_rate_limit() {
        $client_ip = $this->get_client_ip();
        $cache_key = 'zoho_webhook_rate_limit_' . md5($client_ip);
        
        $current_count = get_transient($cache_key) ?: 0;
        
        if ($current_count >= $this->security_config['rate_limit']) {
            return false;
        }
        
        set_transient($cache_key, $current_count + 1, $this->security_config['rate_window']);
        
        return true;
    }
    
    /**
     * Obtener IP del cliente
     * @return string IP del cliente
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Parsear datos del webhook
     * @return array Datos parseados
     */
    private function parse_webhook_data() {
        $raw_data = file_get_contents('php://input');
        
        // Intentar decodificar JSON
        $json_data = json_decode($raw_data, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json_data;
        }
        
        // Si no es JSON, intentar parsear como form data
        parse_str($raw_data, $form_data);
        if (!empty($form_data)) {
            return $form_data;
        }
        
        // Devolver datos raw si no se puede parsear
        return array('raw_data' => $raw_data);
    }
    
    /**
     * Procesar webhook
     * @param string $module Módulo destino
     * @param string $event Evento específico
     * @param array $data Datos del webhook
     * @return array Resultado del procesamiento
     */
    private function process_webhook($module, $event, $data) {
        // Verificar si hay handler registrado para el módulo
        if (!isset($this->registered_handlers[$module])) {
            return array(
                'success' => false,
                'message' => 'No handler registered for module: ' . $module,
                'error_code' => 'no_handler'
            );
        }
        
        $handler = $this->registered_handlers[$module];
        
        // Verificar si el evento está permitido
        if (!empty($handler['events']) && !in_array($event, $handler['events'])) {
            return array(
                'success' => false,
                'message' => 'Event not allowed for module: ' . $event,
                'error_code' => 'event_not_allowed'
            );
        }
        
        // Verificar si el callback es válido
        if (!is_callable($handler['callback'])) {
            return array(
                'success' => false,
                'message' => 'Invalid callback for module: ' . $module,
                'error_code' => 'invalid_callback'
            );
        }
        
        // Ejecutar callback
        try {
            $result = call_user_func($handler['callback'], $event, $data, $module);
            
            // Normalizar resultado
            if (!is_array($result)) {
                $result = array(
                    'success' => (bool) $result,
                    'message' => $result ? 'Webhook processed successfully' : 'Webhook processing failed'
                );
            }
            
            // Log del resultado
            ZohoSyncCore::log('info', 'Webhook procesado', array(
                'module' => $module,
                'event' => $event,
                'success' => $result['success'],
                'message' => $result['message'] ?? ''
            ), 'webhook');
            
            // Hook personalizado
            do_action('zoho_sync_webhook_processed', $module, $event, $data, $result);
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Callback execution failed: ' . $e->getMessage(),
                'error_code' => 'callback_error'
            );
        }
    }
    
    /**
     * Enviar respuesta del webhook
     * @param int $status_code Código de estado HTTP
     * @param array $data Datos de respuesta
     */
    private function send_webhook_response($status_code, $data) {
        // Establecer código de estado
        http_response_code($status_code);
        
        // Establecer headers
        header('Content-Type: application/json');
        header('X-Powered-By: Zoho Sync Core/' . ZOHO_SYNC_CORE_VERSION);
        
        // Agregar timestamp
        $data['timestamp'] = current_time('c');
        $data['request_id'] = uniqid('webhook_', true);
        
        // Enviar respuesta
        echo wp_json_encode($data);
        
        // Log de respuesta
        ZohoSyncCore::log('debug', 'Respuesta de webhook enviada', array(
            'status_code' => $status_code,
            'response_data' => $data
        ), 'webhook');
        
        exit;
    }
    
    /**
     * Registrar handler de webhook
     * @param string $module Nombre del módulo
     * @param array $config Configuración del handler
     * @return bool Éxito del registro
     */
    public function register_handler($module, $config) {
        // Validar configuración
        if (!isset($config['callback']) || !is_callable($config['callback'])) {
            ZohoSyncCore::log('error', 'Error registrando webhook handler: callback inválido', array(
                'module' => $module
            ), 'webhook');
            return false;
        }
        
        // Configuración por defecto
        $default_config = array(
            'callback' => null,
            'events' => array(),
            'description' => '',
            'priority' => 10
        );
        
        $config = array_merge($default_config, $config);
        
        // Registrar handler
        $this->registered_handlers[$module] = $config;
        
        ZohoSyncCore::log('info', 'Webhook handler registrado', array(
            'module' => $module,
            'events' => $config['events'],
            'description' => $config['description']
        ), 'webhook');
        
        return true;
    }
    
    /**
     * Desregistrar handler de webhook
     * @param string $module Nombre del módulo
     * @return bool Éxito de la operación
     */
    public function unregister_handler($module) {
        if (isset($this->registered_handlers[$module])) {
            unset($this->registered_handlers[$module]);
            
            ZohoSyncCore::log('info', 'Webhook handler desregistrado', array(
                'module' => $module
            ), 'webhook');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener handlers registrados
     * @return array Handlers registrados
     */
    public function get_registered_handlers() {
        return $this->registered_handlers;
    }
    
    /**
     * Generar URL de webhook para un módulo
     * @param string $module Nombre del módulo
     * @param string $event Evento específico (opcional)
     * @return string URL del webhook
     */
    public function get_webhook_url($module, $event = '') {
        $base_url = home_url($this->webhook_endpoint . '/' . $module);
        
        if (!empty($event)) {
            $base_url .= '/' . $event;
        }
        
        return $base_url;
    }
    
    /**
     * Obtener configuración de webhook para Zoho
     * @param string $module Módulo
     * @param array $events Eventos a configurar
     * @return array Configuración para Zoho
     */
    public function get_zoho_webhook_config($module, $events = array()) {
        $config = array(
            'url' => $this->get_webhook_url($module),
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        );
        
        // Agregar firma si está habilitada
        if ($this->security_config['verify_signature'] && !empty($this->security_config['secret_key'])) {
            $config['headers']['X-Zoho-Webhook-Signature'] = 'Required';
            $config['signature_method'] = 'HMAC-SHA256';
        }
        
        // Agregar eventos específicos
        if (!empty($events)) {
            $config['events'] = $events;
        }
        
        return $config;
    }
    
    /**
     * Handler para webhooks de autenticación
     * @param string $event Evento
     * @param array $data Datos
     * @param string $module Módulo
     * @return array Resultado
     */
    public function handle_auth_webhook($event, $data, $module) {
        switch ($event) {
            case 'token_refresh':
                return $this->handle_token_refresh_webhook($data);
                
            case 'auth_error':
                return $this->handle_auth_error_webhook($data);
                
            default:
                return array(
                    'success' => false,
                    'message' => 'Unknown auth event: ' . $event
                );
        }
    }
    
    /**
     * Handler para webhooks del sistema
     * @param string $event Evento
     * @param array $data Datos
     * @param string $module Módulo
     * @return array Resultado
     */
    public function handle_system_webhook($event, $data, $module) {
        switch ($event) {
            case 'health_check':
                return $this->handle_health_check_webhook($data);
                
            case 'maintenance':
                return $this->handle_maintenance_webhook($data);
                
            default:
                return array(
                    'success' => false,
                    'message' => 'Unknown system event: ' . $event
                );
        }
    }
    
    /**
     * Manejar webhook de refresco de token
     * @param array $data Datos del webhook
     * @return array Resultado
     */
    private function handle_token_refresh_webhook($data) {
        $service = $data['service'] ?? '';
        $region = $data['region'] ?? 'com';
        
        if (empty($service)) {
            return array(
                'success' => false,
                'message' => 'Service parameter required'
            );
        }
        
        $auth_manager = ZohoSyncCore::auth();
        $result = $auth_manager->refresh_access_token($service, $region);
        
        if ($result) {
            return array(
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => array(
                    'service' => $service,
                    'region' => $region,
                    'expires_at' => $result['expires_in'] ?? null
                )
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to refresh token'
            );
        }
    }
    
    /**
     * Manejar webhook de error de autenticación
     * @param array $data Datos del webhook
     * @return array Resultado
     */
    private function handle_auth_error_webhook($data) {
        $service = $data['service'] ?? '';
        $error_message = $data['error'] ?? 'Unknown auth error';
        
        ZohoSyncCore::log('error', 'Error de autenticación reportado por webhook', array(
            'service' => $service,
            'error' => $error_message,
            'webhook_data' => $data
        ), 'webhook');
        
        // Desactivar token si es necesario
        if (!empty($service)) {
            $auth_manager = ZohoSyncCore::auth();
            $auth_manager->deactivate_token($service);
        }
        
        return array(
            'success' => true,
            'message' => 'Auth error processed'
        );
    }
    
    /**
     * Manejar webhook de verificación de salud
     * @param array $data Datos del webhook
     * @return array Resultado
     */
    private function handle_health_check_webhook($data) {
        $core = ZohoSyncCore::instance()->core;
        $health_data = $core->check_system_health();
        
        return array(
            'success' => true,
            'message' => 'Health check completed',
            'data' => array(
                'status' => $health_data['overall_status'],
                'timestamp' => $health_data['timestamp']
            )
        );
    }
    
    /**
     * Manejar webhook de mantenimiento
     * @param array $data Datos del webhook
     * @return array Resultado
     */
    private function handle_maintenance_webhook($data) {
        $action = $data['action'] ?? '';
        
        switch ($action) {
            case 'cleanup':
                do_action('zoho_sync_core_daily_cleanup');
                return array(
                    'success' => true,
                    'message' => 'Cleanup executed'
                );
                
            case 'health_check':
                return $this->handle_health_check_webhook($data);
                
            default:
                return array(
                    'success' => false,
                    'message' => 'Unknown maintenance action: ' . $action
                );
        }
    }
    
    /**
     * Log del request de webhook
     * @param string $module Módulo
     * @param string $event Evento
     */
    private function log_webhook_request($module, $event) {
        $log_data = array(
            'module' => $module,
            'event' => $event,
            'client_ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 0
        );
        
        ZohoSyncCore::log('info', 'Webhook request recibido', $log_data, 'webhook');
    }
    
    /**
     * Limpiar logs de webhooks antiguos
     */
    public function cleanup_webhook_logs() {
        // Esta función se ejecuta como parte de la limpieza diaria
        ZohoSyncCore::log('info', 'Limpieza de logs de webhooks completada', array(), 'webhook');
    }
    
    /**
     * Obtener estadísticas de webhooks
     * @return array Estadísticas
     */
    public function get_webhook_stats() {
        // Obtener estadísticas de logs de webhooks
        $logger = ZohoSyncCore::logger();
        
        $stats = array(
            'total_handlers' => count($this->registered_handlers),
            'security_enabled' => $this->security_config['verify_signature'],
            'rate_limit' => $this->security_config['rate_limit'],
            'allowed_ips_count' => count($this->security_config['allowed_ips'])
        );
        
        if ($logger) {
            $webhook_logs = $logger->get_logs(array(
                'module' => 'webhook',
                'limit' => 1000
            ));
            
            $stats['total_requests'] = count($webhook_logs);
            $stats['recent_requests'] = count($logger->get_logs(array(
                'module' => 'webhook',
                'date_from' => date('Y-m-d H:i:s', strtotime('-24 hours')),
                'limit' => 1000
            )));
        }
        
        return $stats;
    }
    
    /**
     * Probar webhook
     * @param string $module Módulo
     * @param string $event Evento
     * @param array $test_data Datos de prueba
     * @return array Resultado de la prueba
     */
    public function test_webhook($module, $event, $test_data = array()) {
        if (!isset($this->registered_handlers[$module])) {
            return array(
                'success' => false,
                'message' => 'Handler not found for module: ' . $module
            );
        }
        
        $default_test_data = array(
            'test' => true,
            'timestamp' => current_time('c'),
            'source' => 'webhook_test'
        );
        
        $test_data = array_merge($default_test_data, $test_data);
        
        try {
            $result = $this->process_webhook($module, $event, $test_data);
            
            ZohoSyncCore::log('info', 'Prueba de webhook ejecutada', array(
                'module' => $module,
                'event' => $event,
                'result' => $result
            ), 'webhook');
            
            return $result;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'error_code' => 'test_error'
            );
        }
    }
}
