<?php
/**
 * Gestor de Autenticación para Zoho Sync Core
 * 
 * @package ZohoSyncCore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Auth Manager para el ecosistema Zoho Sync
 * Gestiona la autenticación OAuth2 con los servicios de Zoho
 */
class Zoho_Sync_Core_Auth_Manager {
    
    /**
     * Instancia de wpdb
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Nombre de la tabla de tokens
     * @var string
     */
    private $table_name;
    
    /**
     * URLs base de Zoho por región
     * @var array
     */
    private $zoho_urls = array(
        'com' => array(
            'accounts' => 'https://accounts.zoho.com',
            'api' => 'https://www.zohoapis.com'
        ),
        'eu' => array(
            'accounts' => 'https://accounts.zoho.eu',
            'api' => 'https://www.zohoapis.eu'
        ),
        'in' => array(
            'accounts' => 'https://accounts.zoho.in',
            'api' => 'https://www.zohoapis.in'
        ),
        'com.au' => array(
            'accounts' => 'https://accounts.zoho.com.au',
            'api' => 'https://www.zohoapis.com.au'
        ),
        'jp' => array(
            'accounts' => 'https://accounts.zoho.jp',
            'api' => 'https://www.zohoapis.jp'
        )
    );
    
    /**
     * Servicios de Zoho disponibles
     * @var array
     */
    private $zoho_services = array(
        'crm' => array(
            'name' => 'Zoho CRM',
            'scopes' => array('ZohoCRM.modules.ALL', 'ZohoCRM.settings.ALL')
        ),
        'inventory' => array(
            'name' => 'Zoho Inventory',
            'scopes' => array('ZohoInventory.FullAccess.all')
        ),
        'books' => array(
            'name' => 'Zoho Books',
            'scopes' => array('ZohoBooks.FullAccess.all')
        ),
        'creator' => array(
            'name' => 'Zoho Creator',
            'scopes' => array('ZohoCreator.form.CREATE', 'ZohoCreator.report.READ')
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . ZOHO_SYNC_CORE_TOKENS_TABLE;
        
        // Programar verificación de tokens
        add_action('zoho_sync_core_check_tokens', array($this, 'check_token_expiration'));
        if (!wp_next_scheduled('zoho_sync_core_check_tokens')) {
            wp_schedule_event(time(), 'hourly', 'zoho_sync_core_check_tokens');
        }
        
        // Hook para refrescar tokens automáticamente
        add_action('zoho_sync_core_refresh_token', array($this, 'refresh_token_by_service'));
    }
    
    /**
     * Generar URL de autorización para Zoho
     * @param string $service Servicio de Zoho
     * @param string $region Región de Zoho
     * @param string $redirect_uri URI de redirección
     * @return string URL de autorización
     */
    public function get_authorization_url($service, $region = 'com', $redirect_uri = '') {
        $client_id = ZohoSyncCore::settings()->get('zoho_client_id');
        
        if (empty($client_id)) {
            ZohoSyncCore::log('error', 'Client ID de Zoho no configurado', array(), 'auth');
            return false;
        }
        
        if (empty($redirect_uri)) {
            $redirect_uri = admin_url('admin.php?page=zoho-sync-core-auth&action=callback');
        }
        
        $scopes = $this->get_service_scopes($service);
        $state = wp_create_nonce('zoho_auth_' . $service . '_' . $region);
        
        $params = array(
            'scope' => implode(',', $scopes),
            'client_id' => $client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'access_type' => 'offline',
            'state' => $state
        );
        
        $base_url = $this->zoho_urls[$region]['accounts'] . '/oauth/v2/auth';
        
        return $base_url . '?' . http_build_query($params);
    }
    
    /**
     * Intercambiar código de autorización por tokens
     * @param string $code Código de autorización
     * @param string $service Servicio de Zoho
     * @param string $region Región de Zoho
     * @param string $redirect_uri URI de redirección
     * @return array|false Resultado del intercambio
     */
    public function exchange_code_for_tokens($code, $service, $region = 'com', $redirect_uri = '') {
        $client_id = ZohoSyncCore::settings()->get('zoho_client_id');
        $client_secret = ZohoSyncCore::settings()->get('zoho_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            ZohoSyncCore::log('error', 'Credenciales de Zoho no configuradas', array(), 'auth');
            return false;
        }
        
        if (empty($redirect_uri)) {
            $redirect_uri = admin_url('admin.php?page=zoho-sync-core-auth&action=callback');
        }
        
        $token_url = $this->zoho_urls[$region]['accounts'] . '/oauth/v2/token';
        
        $params = array(
            'grant_type' => 'authorization_code',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'code' => $code
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            ZohoSyncCore::log('error', 'Error en request de tokens', array(
                'error' => $response->get_error_message(),
                'service' => $service,
                'region' => $region
            ), 'auth');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            ZohoSyncCore::log('error', 'Error de Zoho al obtener tokens', array(
                'error' => $data['error'],
                'error_description' => $data['error_description'] ?? '',
                'service' => $service,
                'region' => $region
            ), 'auth');
            return false;
        }
        
        if (isset($data['access_token'])) {
            // Guardar tokens
            $saved = $this->save_tokens($service, $region, $data);
            
            if ($saved) {
                ZohoSyncCore::log('info', 'Tokens de Zoho obtenidos exitosamente', array(
                    'service' => $service,
                    'region' => $region,
                    'expires_in' => $data['expires_in'] ?? 'unknown'
                ), 'auth');
                
                do_action('zoho_sync_auth_tokens_obtained', $service, $region, $data);
                
                return $data;
            }
        }
        
        return false;
    }
    
    /**
     * Refrescar token de acceso
     * @param string $service Servicio de Zoho
     * @param string $region Región de Zoho
     * @return array|false Nuevos tokens o false si falla
     */
    public function refresh_access_token($service, $region = 'com') {
        $token_data = $this->get_token_data($service, $region);
        
        if (!$token_data || empty($token_data->refresh_token)) {
            ZohoSyncCore::log('error', 'No hay refresh token disponible', array(
                'service' => $service,
                'region' => $region
            ), 'auth');
            return false;
        }
        
        $client_id = ZohoSyncCore::settings()->get('zoho_client_id');
        $client_secret = ZohoSyncCore::settings()->get('zoho_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            ZohoSyncCore::log('error', 'Credenciales de Zoho no configuradas para refresh', array(), 'auth');
            return false;
        }
        
        $token_url = $this->zoho_urls[$region]['accounts'] . '/oauth/v2/token';
        
        $params = array(
            'grant_type' => 'refresh_token',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $token_data->refresh_token
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            ZohoSyncCore::log('error', 'Error en request de refresh token', array(
                'error' => $response->get_error_message(),
                'service' => $service,
                'region' => $region
            ), 'auth');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            ZohoSyncCore::log('error', 'Error de Zoho al refrescar token', array(
                'error' => $data['error'],
                'error_description' => $data['error_description'] ?? '',
                'service' => $service,
                'region' => $region
            ), 'auth');
            
            // Si el refresh token es inválido, marcar como inactivo
            if ($data['error'] === 'invalid_grant') {
                $this->deactivate_token($service, $region);
            }
            
            return false;
        }
        
        if (isset($data['access_token'])) {
            // Mantener el refresh token existente si no se proporciona uno nuevo
            if (!isset($data['refresh_token'])) {
                $data['refresh_token'] = $token_data->refresh_token;
            }
            
            // Actualizar tokens
            $updated = $this->save_tokens($service, $region, $data);
            
            if ($updated) {
                ZohoSyncCore::log('info', 'Token de Zoho refrescado exitosamente', array(
                    'service' => $service,
                    'region' => $region,
                    'expires_in' => $data['expires_in'] ?? 'unknown'
                ), 'auth');
                
                do_action('zoho_sync_auth_token_refreshed', $service, $region, $data);
                
                return $data;
            }
        }
        
        return false;
    }
    
    /**
     * Obtener token de acceso válido
     * @param string $service Servicio de Zoho
     * @param string $region Región de Zoho
     * @return string|false Token de acceso o false si no está disponible
     */
    public function get_access_token($service, $region = 'com') {
        $token_data = $this->get_token_data($service, $region);
        
        if (!$token_data || !$token_data->is_active) {
            return false;
        }
        
        // Verificar si el token ha expirado
        if ($this->is_token_expired($token_data)) {
            // Intentar refrescar el token
            $refreshed = $this->refresh_access_token($service, $region);
            
            if ($refreshed && isset($refreshed['access_token'])) {
                return $refreshed['access_token'];
            }
            
            return false;
        }
        
        return $token_data->access_token;
    }
    
    /**
     * Verificar si un token está expirado
     * @param object $token_data Datos del token
     * @return bool True si está expirado
     */
    private function is_token_expired($token_data) {
        if (!$token_data->expires_at) {
            return false; // Si no hay fecha de expiración, asumir que no expira
        }
        
        $expires_at = strtotime($token_data->expires_at);
        $now = time();
        
        // Considerar expirado si faltan menos de 5 minutos
        return ($expires_at - $now) < 300;
    }
    
    /**
     * Guardar tokens en la base de datos
     * @param string $service Servicio de Zoho
     * @param string $region Región de Zoho
     * @param array $token_data Datos del token
     * @return bool Éxito de la operación
     */
    private function save_tokens($service, $region, $token_data) {
        $expires_at = null;
        if (isset($token_data['expires_in'])) {
            $expires_at = date('Y-m-d H:i:s', time() + intval($token_data['expires_in']));
        }
        
        $data = array(
            'service' => $service,
            'region' => $region,
            'access_token' => $token_data['access_token'],
            'refresh_token' => $token_data['refresh_token'] ?? '',
            'token_type' => $token_data['token_type'] ?? 'Bearer',
            'expires_at' => $expires_at,
            'scope' => $token_data['scope'] ?? '',
            'is_active' => 1,
            'updated_at' => current_time('mysql')
        );
        
        // Verificar si ya existe
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE service = %s AND region = %s",
                $service,
                $region
            )
        );
        
        if ($exists) {
            // Actualizar
            $result = $this->wpdb->update(
                $this->table_name,
                $data,
                array('service' => $service, 'region' => $region),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%s', '%s')
            );
        } else {
            // Insertar
            $data['created_at'] = current_time('mysql');
            $result = $this->wpdb->insert(
                $this->table_name,
                $data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Obtener datos del token
     * @param string $service Servicio de Zoho
     * @param string $region Región de Zoho
     * @return object|null Datos del token
     */
    private function get_token_data($service, $region = 'com') {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE service = %s AND region = %s",
                $service,
                $region
            )
        );
    }
    
    /**
     * Desactivar token
     * @param string $service Servicio de Zoho
     * @param string $region Región de Zoho
     * @return bool Éxito de la operación
     */
    public function deactivate_token($service, $region = 'com') {
        $result = $this->wpdb->update(
            $this->table_name,
            array('is_active' => 0, 'updated_at' => current_time('mysql')),
            array('service' => $service, 'region' => $region),
            array('%d', '%s'),
            array('%s', '%s')
        );
        
        if ($result !== false) {
            ZohoSyncCore::log('info', 'Token desactivado', array(
                'service' => $service,
                'region' => $region
            ), 'auth');
            
            do_action('zoho_sync_auth_token_deactivated', $service, $region);
        }
        
        return $result !== false;
    }
    
    /**
     * Eliminar token
     * @param string $service Servicio de Zoho
     * @param string $region Región de Zoho
     * @return bool Éxito de la operación
     */
    public function delete_token($service, $region = 'com') {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('service' => $service, 'region' => $region),
            array('%s', '%s')
        );
        
        if ($result !== false) {
            ZohoSyncCore::log('info', 'Token eliminado', array(
                'service' => $service,
                'region' => $region
            ), 'auth');
            
            do_action('zoho_sync_auth_token_deleted', $service, $region);
        }
        
        return $result !== false;
    }
    
    /**
     * Verificar si un servicio está autenticado
     * @param string $service Servicio de Zoho
     * @param string $region Región de Zoho
     * @return bool True si está autenticado
     */
    public function is_authenticated($service, $region = 'com') {
        $token = $this->get_access_token($service, $region);
        return !empty($token);
    }
    
    /**
     * Obtener todos los tokens activos
     * @return array Tokens activos
     */
    public function get_active_tokens() {
        return $this->wpdb->get_results(
            "SELECT service, region, expires_at, created_at, updated_at 
             FROM {$this->table_name} 
             WHERE is_active = 1 
             ORDER BY service, region"
        );
    }
    
    /**
     * Obtener estado de autenticación para todos los servicios
     * @return array Estado de autenticación
     */
    public function get_authentication_status() {
        $status = array();
        $region = ZohoSyncCore::settings()->get('zoho_region', 'com');
        
        foreach ($this->zoho_services as $service => $config) {
            $token_data = $this->get_token_data($service, $region);
            
            $status[$service] = array(
                'name' => $config['name'],
                'authenticated' => $this->is_authenticated($service, $region),
                'expires_at' => $token_data ? $token_data->expires_at : null,
                'expires_soon' => $token_data ? $this->is_token_expired($token_data) : false,
                'last_updated' => $token_data ? $token_data->updated_at : null
            );
        }
        
        return $status;
    }
    
    /**
     * Verificar expiración de tokens programadamente
     */
    public function check_token_expiration() {
        $tokens = $this->wpdb->get_results(
            "SELECT service, region, expires_at FROM {$this->table_name} 
             WHERE is_active = 1 AND expires_at IS NOT NULL"
        );
        
        foreach ($tokens as $token) {
            if ($this->is_token_expired($token)) {
                ZohoSyncCore::log('warning', 'Token próximo a expirar', array(
                    'service' => $token->service,
                    'region' => $token->region,
                    'expires_at' => $token->expires_at
                ), 'auth');
                
                // Intentar refrescar automáticamente
                $this->refresh_access_token($token->service, $token->region);
            }
        }
    }
    
    /**
     * Refrescar token por servicio (hook)
     * @param string $service Servicio
     * @param string $region Región
     */
    public function refresh_token_by_service($service, $region = 'com') {
        $this->refresh_access_token($service, $region);
    }
    
    /**
     * Obtener scopes para un servicio
     * @param string $service Servicio
     * @return array Scopes
     */
    private function get_service_scopes($service) {
        return $this->zoho_services[$service]['scopes'] ?? array();
    }
    
    /**
     * Validar credenciales de Zoho
     * @param string $client_id Client ID
     * @param string $client_secret Client Secret
     * @param string $region Región
     * @return array Resultado de validación
     */
    public function validate_credentials($client_id, $client_secret, $refresh_token, $region = 'com') {
        $validation = array(
            'valid' => false,
            'message' => '',
            'details' => array()
        );
        
        // Validaciones básicas
        if (empty($client_id)) {
            $validation['message'] = __('Client ID es requerido', 'zoho-sync-core');
            return $validation;
        }
        
        if (empty($client_secret)) {
            $validation['message'] = __('Client Secret es requerido', 'zoho-sync-core');
            return $validation;
        }

        if (empty($refresh_token)) {
            $validation['message'] = __('Refresh Token es requerido', 'zoho-sync-core');
            return $validation;
        }
        
        if (!isset($this->zoho_urls[$region])) {
            $validation['message'] = __('Región no válida', 'zoho-sync-core');
            return $validation;
        }
        
        $token_url = $this->zoho_urls[$region]['accounts'] . '/oauth/v2/token';

        $params = array(
            'grant_type' => 'refresh_token',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token
        );

        $response = wp_remote_post($token_url, array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            $validation['message'] = $response->get_error_message();
            return $validation;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            $validation['message'] = $data['error_description'] ?? $data['error'];
            ZohoSyncCore::log('error', 'Error de validación de credenciales de Zoho', $data);
            return $validation;
        }
        
        if (isset($data['access_token'])) {
            $validation['valid'] = true;
            $validation['message'] = __('Conexión exitosa', 'zoho-sync-core');
            ZohoSyncCore::log('info', 'Validación de credenciales de Zoho exitosa', $data);
        } else {
            $validation['message'] = __('Respuesta inesperada de Zoho', 'zoho-sync-core');
            ZohoSyncCore::log('error', 'Respuesta inesperada de Zoho durante la validación de credenciales', $data);
        }
        
        return $validation;
    }
    
    /**
     * Obtener URL de API para una región
     * @param string $region Región
     * @return string URL de API
     */
    public function get_api_url($region = 'com') {
        return $this->zoho_urls[$region]['api'] ?? $this->zoho_urls['com']['api'];
    }
    
    /**
     * Obtener URL de accounts para una región
     * @param string $region Región
     * @return string URL de accounts
     */
    public function get_accounts_url($region = 'com') {
        return $this->zoho_urls[$region]['accounts'] ?? $this->zoho_urls['com']['accounts'];
    }
    
    /**
     * Obtener servicios disponibles
     * @return array Servicios
     */
    public function get_available_services() {
        return $this->zoho_services;
    }
    
    /**
     * Revocar token en Zoho
     * @param string $service Servicio
     * @param string $region Región
     * @return bool Éxito de la operación
     */
    public function revoke_token($service, $region = 'com') {
        $token_data = $this->get_token_data($service, $region);
        
        if (!$token_data || empty($token_data->refresh_token)) {
            return false;
        }
        
        $revoke_url = $this->zoho_urls[$region]['accounts'] . '/oauth/v2/token/revoke';
        
        $response = wp_remote_post($revoke_url, array(
            'body' => array('token' => $token_data->refresh_token),
            'timeout' => 30
        ));
        
        if (!is_wp_error($response)) {
            // Eliminar token de la base de datos
            $this->delete_token($service, $region);
            
            ZohoSyncCore::log('info', 'Token revocado en Zoho', array(
                'service' => $service,
                'region' => $region
            ), 'auth');
            
            return true;
        }
        
        return false;
    }
}
