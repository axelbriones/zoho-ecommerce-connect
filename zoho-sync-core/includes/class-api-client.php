<?php
/**
 * Cliente API Base para Zoho Sync Core
 * 
 * @package ZohoSyncCore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase API Client para el ecosistema Zoho Sync
 * Proporciona comunicación estandarizada con las APIs de Zoho
 */
class Zoho_Sync_Core_Api_Client {
    
    /**
     * Instancia del Auth Manager
     * @var Zoho_Sync_Core_Auth_Manager
     */
    private $auth_manager;
    
    /**
     * Configuración del cliente
     * @var array
     */
    private $config;
    
    /**
     * Rate limiting data
     * @var array
     */
    private $rate_limits = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->auth_manager = ZohoSyncCore::auth();
        
        $this->config = array(
            'timeout' => ZohoSyncCore::settings()->get('api_timeout', ZOHO_SYNC_CORE_API_TIMEOUT),
            'max_retries' => ZohoSyncCore::settings()->get('max_retry_attempts', ZOHO_SYNC_CORE_MAX_RETRY_ATTEMPTS),
            'retry_delay' => 1, // segundos
            'user_agent' => 'Zoho Sync Core/' . ZOHO_SYNC_CORE_VERSION . ' (WordPress)',
            'rate_limit_enabled' => ZohoSyncCore::settings()->get('enable_rate_limiting', true)
        );
    }
    
    /**
     * Realizar request GET
     * @param string $service Servicio de Zoho
     * @param string $endpoint Endpoint de la API
     * @param array $params Parámetros de consulta
     * @param string $region Región de Zoho
     * @return array|WP_Error Respuesta de la API
     */
    public function get($service, $endpoint, $params = array(), $region = null) {
        return $this->request('GET', $service, $endpoint, $params, array(), $region);
    }
    
    /**
     * Realizar request POST
     * @param string $service Servicio de Zoho
     * @param string $endpoint Endpoint de la API
     * @param array $data Datos a enviar
     * @param array $params Parámetros de consulta
     * @param string $region Región de Zoho
     * @return array|WP_Error Respuesta de la API
     */
    public function post($service, $endpoint, $data = array(), $params = array(), $region = null) {
        return $this->request('POST', $service, $endpoint, $params, $data, $region);
    }
    
    /**
     * Realizar request PUT
     * @param string $service Servicio de Zoho
     * @param string $endpoint Endpoint de la API
     * @param array $data Datos a enviar
     * @param array $params Parámetros de consulta
     * @param string $region Región de Zoho
     * @return array|WP_Error Respuesta de la API
     */
    public function put($service, $endpoint, $data = array(), $params = array(), $region = null) {
        return $this->request('PUT', $service, $endpoint, $params, $data, $region);
    }
    
    /**
     * Realizar request DELETE
     * @param string $service Servicio de Zoho
     * @param string $endpoint Endpoint de la API
     * @param array $params Parámetros de consulta
     * @param string $region Región de Zoho
     * @return array|WP_Error Respuesta de la API
     */
    public function delete($service, $endpoint, $params = array(), $region = null) {
        return $this->request('DELETE', $service, $endpoint, $params, array(), $region);
    }
    
    /**
     * Realizar request PATCH
     * @param string $service Servicio de Zoho
     * @param string $endpoint Endpoint de la API
     * @param array $data Datos a enviar
     * @param array $params Parámetros de consulta
     * @param string $region Región de Zoho
     * @return array|WP_Error Respuesta de la API
     */
    public function patch($service, $endpoint, $data = array(), $params = array(), $region = null) {
        return $this->request('PATCH', $service, $endpoint, $params, $data, $region);
    }
    
    /**
     * Método principal para realizar requests
     * @param string $method Método HTTP
     * @param string $service Servicio de Zoho
     * @param string $endpoint Endpoint de la API
     * @param array $params Parámetros de consulta
     * @param array $data Datos del cuerpo
     * @param string $region Región de Zoho
     * @return array|WP_Error Respuesta de la API
     */
    private function request($method, $service, $endpoint, $params = array(), $data = array(), $region = null) {
        // Obtener región por defecto si no se especifica
        if ($region === null) {
            $region = ZohoSyncCore::settings()->get('zoho_region', 'com');
        }
        
        // Verificar autenticación
        $access_token = $this->auth_manager->get_access_token($service, $region);
        if (!$access_token) {
            return new WP_Error(
                'zoho_auth_error',
                __('No hay token de acceso válido para el servicio', 'zoho-sync-core'),
                array('service' => $service, 'region' => $region)
            );
        }
        
        // Verificar rate limiting
        if ($this->config['rate_limit_enabled'] && !$this->check_rate_limit($service)) {
            return new WP_Error(
                'zoho_rate_limit',
                __('Límite de rate excedido para el servicio', 'zoho-sync-core'),
                array('service' => $service)
            );
        }
        
        // Construir URL
        $url = $this->build_url($service, $endpoint, $params, $region);
        
        // Preparar headers
        $headers = $this->prepare_headers($access_token, $method, $data);
        
        // Preparar argumentos del request
        $args = $this->prepare_request_args($method, $headers, $data);
        
        // Aplicar filtro a los argumentos
        $args = apply_filters('zoho_sync_api_request_args', $args, $service, $endpoint, $method);
        
        // Log del request
        $this->log_request($method, $url, $args, $service);
        
        // Realizar request con reintentos
        $response = $this->execute_request_with_retries($method, $url, $args, $service);
        
        // Actualizar rate limiting
        if ($this->config['rate_limit_enabled']) {
            $this->update_rate_limit($service, $response);
        }
        
        return $response;
    }
    
    /**
     * Construir URL completa
     * @param string $service Servicio
     * @param string $endpoint Endpoint
     * @param array $params Parámetros
     * @param string $region Región
     * @return string URL completa
     */
    private function build_url($service, $endpoint, $params, $region) {
        $base_url = $this->auth_manager->get_api_url($region);
        
        // Mapeo de servicios a rutas base
        $service_paths = array(
            'crm' => '/crm/v2',
            'inventory' => '/inventory/v1',
            'books' => '/books/v3',
            'creator' => '/creator/v2'
        );
        
        $service_path = $service_paths[$service] ?? '';
        $url = rtrim($base_url . $service_path, '/') . '/' . ltrim($endpoint, '/');
        
        // Agregar parámetros de consulta
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        return $url;
    }
    
    /**
     * Preparar headers del request
     * @param string $access_token Token de acceso
     * @param string $method Método HTTP
     * @param array $data Datos del cuerpo
     * @return array Headers
     */
    private function prepare_headers($access_token, $method, $data) {
        $headers = array(
            'Authorization' => 'Zoho-oauthtoken ' . $access_token,
            'User-Agent' => $this->config['user_agent'],
            'Accept' => 'application/json'
        );
        
        // Agregar Content-Type para requests con cuerpo
        if (in_array($method, array('POST', 'PUT', 'PATCH')) && !empty($data)) {
            $headers['Content-Type'] = 'application/json';
        }
        
        return $headers;
    }
    
    /**
     * Preparar argumentos del request
     * @param string $method Método HTTP
     * @param array $headers Headers
     * @param array $data Datos del cuerpo
     * @return array Argumentos
     */
    private function prepare_request_args($method, $headers, $data) {
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => $this->config['timeout'],
            'redirection' => 0,
            'httpversion' => '1.1',
            'blocking' => true,
            'cookies' => array(),
            'sslverify' => true
        );
        
        // Agregar cuerpo para métodos que lo requieren
        if (in_array($method, array('POST', 'PUT', 'PATCH')) && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }
        
        return $args;
    }
    
    /**
     * Ejecutar request con reintentos
     * @param string $method Método HTTP
     * @param string $url URL
     * @param array $args Argumentos
     * @param string $service Servicio
     * @return array|WP_Error Respuesta
     */
    private function execute_request_with_retries($method, $url, $args, $service) {
        $attempts = 0;
        $max_attempts = $this->config['max_retries'] + 1;
        
        while ($attempts < $max_attempts) {
            $attempts++;
            
            // Realizar request
            $response = wp_remote_request($url, $args);
            
            // Log del intento
            $this->log_response($response, $attempts, $service);
            
            // Verificar si es un error de WordPress
            if (is_wp_error($response)) {
                if ($attempts >= $max_attempts) {
                    return $response;
                }
                
                // Esperar antes del siguiente intento
                sleep($this->config['retry_delay'] * $attempts);
                continue;
            }
            
            // Obtener código de respuesta
            $response_code = wp_remote_retrieve_response_code($response);
            
            // Procesar respuesta exitosa
            if ($response_code >= 200 && $response_code < 300) {
                return $this->process_successful_response($response);
            }
            
            // Manejar errores específicos
            $error_response = $this->handle_error_response($response, $response_code, $service);
            
            // Verificar si debe reintentar
            if (!$this->should_retry($response_code, $attempts, $max_attempts)) {
                return $error_response;
            }
            
            // Calcular delay con backoff exponencial
            $delay = $this->calculate_retry_delay($attempts, $response_code);
            sleep($delay);
        }
        
        return new WP_Error(
            'zoho_max_retries',
            __('Se alcanzó el máximo número de reintentos', 'zoho-sync-core'),
            array('attempts' => $attempts, 'service' => $service)
        );
    }
    
    /**
     * Procesar respuesta exitosa
     * @param array $response Respuesta de WordPress
     * @return array Datos procesados
     */
    private function process_successful_response($response) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'zoho_json_error',
                __('Error al decodificar respuesta JSON', 'zoho-sync-core'),
                array('json_error' => json_last_error_msg(), 'body' => $body)
            );
        }
        
        return $data;
    }
    
    /**
     * Manejar respuesta de error
     * @param array $response Respuesta de WordPress
     * @param int $response_code Código de respuesta
     * @param string $service Servicio
     * @return WP_Error Error procesado
     */
    private function handle_error_response($response, $response_code, $service) {
        $body = wp_remote_retrieve_body($response);
        $error_data = json_decode($body, true);
        
        // Extraer mensaje de error de Zoho
        $error_message = $this->extract_error_message($error_data, $response_code);
        
        // Determinar tipo de error
        $error_code = $this->determine_error_code($response_code);
        
        return new WP_Error(
            $error_code,
            $error_message,
            array(
                'response_code' => $response_code,
                'service' => $service,
                'error_data' => $error_data,
                'body' => $body
            )
        );
    }
    
    /**
     * Extraer mensaje de error de la respuesta
     * @param array $error_data Datos de error
     * @param int $response_code Código de respuesta
     * @return string Mensaje de error
     */
    private function extract_error_message($error_data, $response_code) {
        // Intentar extraer mensaje de diferentes estructuras de Zoho
        if (isset($error_data['message'])) {
            return $error_data['message'];
        }
        
        if (isset($error_data['error']['message'])) {
            return $error_data['error']['message'];
        }
        
        if (isset($error_data['details']['message'])) {
            return $error_data['details']['message'];
        }
        
        // Mensajes por defecto según código de respuesta
        $default_messages = array(
            400 => __('Solicitud incorrecta', 'zoho-sync-core'),
            401 => __('No autorizado - Token inválido', 'zoho-sync-core'),
            403 => __('Prohibido - Sin permisos suficientes', 'zoho-sync-core'),
            404 => __('Recurso no encontrado', 'zoho-sync-core'),
            429 => __('Demasiadas solicitudes - Rate limit excedido', 'zoho-sync-core'),
            500 => __('Error interno del servidor de Zoho', 'zoho-sync-core'),
            502 => __('Bad Gateway', 'zoho-sync-core'),
            503 => __('Servicio no disponible', 'zoho-sync-core'),
            504 => __('Timeout del gateway', 'zoho-sync-core')
        );
        
        return $default_messages[$response_code] ?? sprintf(
            __('Error HTTP %d', 'zoho-sync-core'),
            $response_code
        );
    }
    
    /**
     * Determinar código de error
     * @param int $response_code Código de respuesta HTTP
     * @return string Código de error
     */
    private function determine_error_code($response_code) {
        $error_codes = array(
            400 => 'zoho_bad_request',
            401 => 'zoho_unauthorized',
            403 => 'zoho_forbidden',
            404 => 'zoho_not_found',
            429 => 'zoho_rate_limit',
            500 => 'zoho_server_error',
            502 => 'zoho_bad_gateway',
            503 => 'zoho_service_unavailable',
            504 => 'zoho_gateway_timeout'
        );
        
        return $error_codes[$response_code] ?? 'zoho_http_error';
    }
    
    /**
     * Verificar si debe reintentar
     * @param int $response_code Código de respuesta
     * @param int $attempt Intento actual
     * @param int $max_attempts Máximo de intentos
     * @return bool True si debe reintentar
     */
    private function should_retry($response_code, $attempt, $max_attempts) {
        if ($attempt >= $max_attempts) {
            return false;
        }
        
        // Códigos que permiten reintento
        $retryable_codes = array(429, 500, 502, 503, 504);
        
        return in_array($response_code, $retryable_codes);
    }
    
    /**
     * Calcular delay para reintento
     * @param int $attempt Número de intento
     * @param int $response_code Código de respuesta
     * @return int Segundos de delay
     */
    private function calculate_retry_delay($attempt, $response_code) {
        // Para rate limiting, usar delay más largo
        if ($response_code === 429) {
            return min(60, pow(2, $attempt) * 5); // Máximo 60 segundos
        }
        
        // Backoff exponencial normal
        return min(30, pow(2, $attempt - 1) * $this->config['retry_delay']);
    }
    
    /**
     * Verificar rate limiting
     * @param string $service Servicio
     * @return bool True si puede hacer request
     */
    private function check_rate_limit($service) {
        $now = time();
        $window = ZohoSyncCore::settings()->get('rate_limit_window', 3600);
        $max_requests = ZohoSyncCore::settings()->get('rate_limit_requests', 100);
        
        if (!isset($this->rate_limits[$service])) {
            $this->rate_limits[$service] = array(
                'requests' => array(),
                'window_start' => $now
            );
        }
        
        $rate_data = &$this->rate_limits[$service];
        
        // Limpiar requests antiguos
        $rate_data['requests'] = array_filter($rate_data['requests'], function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        // Verificar si excede el límite
        return count($rate_data['requests']) < $max_requests;
    }
    
    /**
     * Actualizar rate limiting
     * @param string $service Servicio
     * @param array|WP_Error $response Respuesta
     */
    private function update_rate_limit($service, $response) {
        $now = time();
        
        if (!isset($this->rate_limits[$service])) {
            $this->rate_limits[$service] = array(
                'requests' => array(),
                'window_start' => $now
            );
        }
        
        // Agregar timestamp del request actual
        $this->rate_limits[$service]['requests'][] = $now;
        
        // Si la respuesta incluye headers de rate limiting, usarlos
        if (!is_wp_error($response) && is_array($response)) {
            $headers = wp_remote_retrieve_headers($response);
            
            if (isset($headers['x-ratelimit-remaining'])) {
                // Actualizar límites basados en headers de Zoho
                $remaining = intval($headers['x-ratelimit-remaining']);
                $limit = intval($headers['x-ratelimit-limit'] ?? 100);
                
                // Ajustar nuestro tracking interno
                $used = $limit - $remaining;
                $this->rate_limits[$service]['requests'] = array_fill(0, $used, $now);
            }
        }
    }
    
    /**
     * Log del request
     * @param string $method Método
     * @param string $url URL
     * @param array $args Argumentos
     * @param string $service Servicio
     */
    private function log_request($method, $url, $args, $service) {
        // Solo log en modo debug
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_data = array(
            'method' => $method,
            'url' => $url,
            'service' => $service,
            'headers' => $args['headers'] ?? array(),
            'has_body' => !empty($args['body'])
        );
        
        // No logear el token completo por seguridad
        if (isset($log_data['headers']['Authorization'])) {
            $log_data['headers']['Authorization'] = 'Zoho-oauthtoken ***';
        }
        
        ZohoSyncCore::log('debug', 'API Request iniciado', $log_data, 'api');
    }
    
    /**
     * Log de la respuesta
     * @param array|WP_Error $response Respuesta
     * @param int $attempt Número de intento
     * @param string $service Servicio
     */
    private function log_response($response, $attempt, $service) {
        if (is_wp_error($response)) {
            ZohoSyncCore::log('error', 'API Request falló', array(
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message(),
                'attempt' => $attempt,
                'service' => $service
            ), 'api');
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $level = ($response_code >= 200 && $response_code < 300) ? 'info' : 'warning';
            
            ZohoSyncCore::log($level, 'API Response recibida', array(
                'response_code' => $response_code,
                'attempt' => $attempt,
                'service' => $service
            ), 'api');
        }
    }
    
    /**
     * Realizar request en lote
     * @param string $service Servicio
     * @param array $requests Array de requests
     * @param string $region Región
     * @return array Respuestas
     */
    public function batch_request($service, $requests, $region = null) {
        $responses = array();
        
        foreach ($requests as $key => $request) {
            $method = $request['method'] ?? 'GET';
            $endpoint = $request['endpoint'];
            $params = $request['params'] ?? array();
            $data = $request['data'] ?? array();
            
            $response = $this->request($method, $service, $endpoint, $params, $data, $region);
            $responses[$key] = $response;
            
            // Pequeña pausa entre requests para evitar rate limiting
            usleep(100000); // 0.1 segundos
        }
        
        return $responses;
    }
    
    /**
     * Obtener información de rate limiting
     * @param string $service Servicio
     * @return array Información de rate limiting
     */
    public function get_rate_limit_info($service) {
        if (!isset($this->rate_limits[$service])) {
            return array(
                'requests_made' => 0,
                'requests_remaining' => ZohoSyncCore::settings()->get('rate_limit_requests', 100),
                'window_start' => time(),
                'window_end' => time() + ZohoSyncCore::settings()->get('rate_limit_window', 3600)
            );
        }
        
        $rate_data = $this->rate_limits[$service];
        $max_requests = ZohoSyncCore::settings()->get('rate_limit_requests', 100);
        $window = ZohoSyncCore::settings()->get('rate_limit_window', 3600);
        
        return array(
            'requests_made' => count($rate_data['requests']),
            'requests_remaining' => max(0, $max_requests - count($rate_data['requests'])),
            'window_start' => $rate_data['window_start'],
            'window_end' => $rate_data['window_start'] + $window
        );
    }
    
    /**
     * Limpiar rate limiting para un servicio
     * @param string $service Servicio
     */
    public function clear_rate_limit($service) {
        unset($this->rate_limits[$service]);
    }
    
    /**
     * Obtener estadísticas de API
     * @return array Estadísticas
     */
    public function get_api_stats() {
        $stats = array();
        
        foreach ($this->rate_limits as $service => $data) {
            $stats[$service] = array(
                'requests_in_window' => count($data['requests']),
                'last_request' => !empty($data['requests']) ? max($data['requests']) : null,
                'window_start' => $data['window_start']
            );
        }
        
        return $stats;
    }
}
