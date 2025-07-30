<?php
/**
 * Zoho API Client
 * 
 * Base API client for Zoho services integration
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
 * Zoho API Client Class
 * 
 * Handles all API communications with Zoho services
 */
class Zoho_Sync_Core_API_Client {

    /**
     * Auth Manager instance
     * 
     * @var Zoho_Sync_Core_Auth_Manager
     */
    private $auth_manager;

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
     * Current access token
     * 
     * @var string
     */
    private $access_token;

    /**
     * API base URLs by region
     * 
     * @var array
     */
    private $api_urls = array(
        'com' => 'https://www.zohoapis.com',
        'eu' => 'https://www.zohoapis.eu',
        'in' => 'https://www.zohoapis.in',
        'com.au' => 'https://www.zohoapis.com.au',
        'jp' => 'https://www.zohoapis.jp'
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->auth_manager = new Zoho_Sync_Core_Auth_Manager();
        $this->logger = new Zoho_Sync_Core_Logger();
        $this->settings_manager = new Zoho_Sync_Core_Settings_Manager();
    }

    /**
     * Get valid access token
     * 
     * @return string|false Access token or false on failure
     */
    private function get_access_token() {
        if (!empty($this->access_token)) {
            return $this->access_token;
        }

        $settings = $this->settings_manager->get_settings();
        
        if (empty($settings['zoho_refresh_token'])) {
            $this->logger->log('error', 'No refresh token available for API authentication');
            return false;
        }

        $token_data = $this->auth_manager->refresh_access_token(
            $settings['zoho_refresh_token'],
            $settings['zoho_region'] ?? 'com'
        );

        if ($token_data && isset($token_data['access_token'])) {
            $this->access_token = $token_data['access_token'];
            return $this->access_token;
        }

        $this->logger->log('error', 'Failed to obtain access token');
        return false;
    }

    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array|false API response or false on failure
     */
    public function make_request($endpoint, $method = 'GET', $data = array(), $headers = array()) {
        $access_token = $this->get_access_token();
        
        if (!$access_token) {
            return false;
        }

        $settings = $this->settings_manager->get_settings();
        $region = $settings['zoho_region'] ?? 'com';
        $base_url = $this->api_urls[$region];
        
        $url = $base_url . '/' . ltrim($endpoint, '/');

        $default_headers = array(
            'Authorization' => 'Zoho-oauthtoken ' . $access_token,
            'Content-Type' => 'application/json'
        );

        $headers = array_merge($default_headers, $headers);

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        );

        if (!empty($data)) {
            if ($method === 'GET') {
                $url = add_query_arg($data, $url);
            } else {
                $args['body'] = json_encode($data);
            }
        }

        $this->logger->log('info', sprintf('Making %s request to: %s', $method, $endpoint));

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->logger->log('error', 'API request failed: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        $this->logger->log('info', sprintf('API response code: %d', $response_code));

        if ($response_code >= 400) {
            $this->logger->log('error', sprintf('API error %d: %s', $response_code, $response_body));
            return false;
        }

        $decoded_response = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log('error', 'Failed to decode API response JSON');
            return false;
        }

        return $decoded_response;
    }

    /**
     * Get request (convenience method)
     * 
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|false API response or false on failure
     */
    public function get($endpoint, $params = array()) {
        return $this->make_request($endpoint, 'GET', $params);
    }

    /**
     * Post request (convenience method)
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false API response or false on failure
     */
    public function post($endpoint, $data = array()) {
        return $this->make_request($endpoint, 'POST', $data);
    }

    /**
     * Put request (convenience method)
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false API response or false on failure
     */
    public function put($endpoint, $data = array()) {
        return $this->make_request($endpoint, 'PUT', $data);
    }

    /**
     * Delete request (convenience method)
     * 
     * @param string $endpoint API endpoint
     * @return array|false API response or false on failure
     */
    public function delete($endpoint) {
        return $this->make_request($endpoint, 'DELETE');
    }

    /**
     * Test API connection
     * 
     * @return array Connection test result
     */
    public function test_connection() {
        $this->logger->log('info', 'Testing API connection');

        // Test Zoho Inventory connection
        $inventory_response = $this->get('inventory/v1/organizations');
        
        if ($inventory_response === false) {
            return array(
                'success' => false,
                'message' => __('No se pudo conectar con Zoho Inventory', 'zoho-sync-core'),
                'services' => array()
            );
        }

        $services = array();

        // Check Inventory access
        if (isset($inventory_response['organizations'])) {
            $services['inventory'] = array(
                'status' => 'connected',
                'message' => __('Zoho Inventory conectado correctamente', 'zoho-sync-core'),
                'organizations' => count($inventory_response['organizations'])
            );
        } else {
            $services['inventory'] = array(
                'status' => 'error',
                'message' => __('Error al acceder a Zoho Inventory', 'zoho-sync-core')
            );
        }

        // Test CRM connection
        $crm_response = $this->get('crm/v2/org');
        
        if ($crm_response && !isset($crm_response['code'])) {
            $services['crm'] = array(
                'status' => 'connected',
                'message' => __('Zoho CRM conectado correctamente', 'zoho-sync-core')
            );
        } else {
            $services['crm'] = array(
                'status' => 'limited',
                'message' => __('Acceso limitado a Zoho CRM', 'zoho-sync-core')
            );
        }

        $all_connected = true;
        foreach ($services as $service) {
            if ($service['status'] === 'error') {
                $all_connected = false;
                break;
            }
        }

        return array(
            'success' => $all_connected,
            'message' => $all_connected 
                ? __('Todas las conexiones funcionan correctamente', 'zoho-sync-core')
                : __('Algunas conexiones presentan problemas', 'zoho-sync-core'),
            'services' => $services
        );
    }

    /**
     * Get organization info
     * 
     * @return array|false Organization data or false on failure
     */
    public function get_organization_info() {
        $response = $this->get('inventory/v1/organizations');
        
        if ($response && isset($response['organizations']) && !empty($response['organizations'])) {
            return $response['organizations'][0];
        }

        return false;
    }

    /**
     * Batch request handler
     * 
     * @param array $requests Array of request configurations
     * @return array Batch response results
     */
    public function batch_request($requests) {
        $results = array();
        
        foreach ($requests as $key => $request) {
            $endpoint = $request['endpoint'] ?? '';
            $method = $request['method'] ?? 'GET';
            $data = $request['data'] ?? array();
            
            if (empty($endpoint)) {
                $results[$key] = array(
                    'success' => false,
                    'error' => 'Missing endpoint'
                );
                continue;
            }

            $response = $this->make_request($endpoint, $method, $data);
            
            $results[$key] = array(
                'success' => $response !== false,
                'data' => $response,
                'error' => $response === false ? 'Request failed' : null
            );

            // Add small delay between requests to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }

        return $results;
    }

    /**
     * Handle rate limiting
     * 
     * @param int $retry_after Seconds to wait before retry
     */
    private function handle_rate_limit($retry_after = 60) {
        $this->logger->log('warning', sprintf('Rate limit reached, waiting %d seconds', $retry_after));
        
        // Store rate limit info for dashboard display
        update_option('zoho_sync_core_rate_limit', array(
            'timestamp' => current_time('timestamp'),
            'retry_after' => $retry_after
        ));
    }

    /**
     * Clear access token (force refresh on next request)
     */
    public function clear_token() {
        $this->access_token = null;
    }

    /**
     * Get API usage statistics
     * 
     * @return array Usage statistics
     */
    public function get_usage_stats() {
        $stats = get_option('zoho_sync_core_api_stats', array(
            'requests_today' => 0,
            'requests_total' => 0,
            'last_request' => null,
            'errors_today' => 0,
            'last_reset' => current_time('Y-m-d')
        ));

        // Reset daily counters if it's a new day
        if ($stats['last_reset'] !== current_time('Y-m-d')) {
            $stats['requests_today'] = 0;
            $stats['errors_today'] = 0;
            $stats['last_reset'] = current_time('Y-m-d');
            update_option('zoho_sync_core_api_stats', $stats);
        }

        return $stats;
    }

    /**
     * Update API usage statistics
     * 
     * @param bool $success Whether the request was successful
     */
    private function update_usage_stats($success = true) {
        $stats = $this->get_usage_stats();
        
        $stats['requests_today']++;
        $stats['requests_total']++;
        $stats['last_request'] = current_time('mysql');
        
        if (!$success) {
            $stats['errors_today']++;
        }

        update_option('zoho_sync_core_api_stats', $stats);
    }
}
