<?php
/**
 * Gestor de Configuraciones para Zoho Sync Core
 * 
 * @package ZohoSyncCore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Settings Manager para el ecosistema Zoho Sync
 * Proporciona gestión centralizada de configuraciones para todos los plugins
 */
class Zoho_Sync_Core_Settings_Manager {
    
    /**
     * Instancia de wpdb
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Nombre de la tabla de configuraciones
     * @var string
     */
    private $table_name;
    
    /**
     * Cache de configuraciones
     * @var array
     */
    private $cache = array();
    
    /**
     * Configuraciones por defecto
     * @var array
     */
    private $defaults = array();
    
    /**
     * Clave de encriptación
     * @var string
     */
    private $encryption_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . ZOHO_SYNC_CORE_SETTINGS_TABLE;
        
        // Generar o recuperar clave de encriptación
        $this->encryption_key = $this->get_encryption_key();
        
        // Definir configuraciones por defecto
        $this->set_defaults();
        
        // Cargar configuraciones con autoload
        $this->load_autoload_settings();
        
        // Hooks para limpiar cache
        add_action('zoho_sync_setting_updated', array($this, 'clear_cache'));
    }
    
    /**
     * Definir configuraciones por defecto
     */
    private function set_defaults() {
        $this->defaults = array(
            // Configuraciones generales
            'zoho_region' => 'com',
            'api_timeout' => ZOHO_SYNC_CORE_API_TIMEOUT,
            'max_retry_attempts' => ZOHO_SYNC_CORE_MAX_RETRY_ATTEMPTS,
            'log_level' => 'info',
            'log_to_file' => false,
            'log_to_email' => false,
            'log_retention_days' => ZOHO_SYNC_CORE_LOG_RETENTION_DAYS,
            
            // Configuraciones de autenticación
            'zoho_client_id' => '',
            'zoho_client_secret' => '',
            'zoho_refresh_token' => '',
            'zoho_access_token' => '',
            'token_expires_at' => '',
            
            // Configuraciones de sincronización
            'sync_enabled' => true,
            'sync_interval' => 'hourly',
            'batch_size' => 50,
            'webhook_enabled' => false,
            'webhook_secret' => '',
            
            // Configuraciones de notificaciones
            'email_notifications' => true,
            'notification_email' => get_option('admin_email', ''),
            'slack_webhook_url' => '',
            
            // Configuraciones de seguridad
            'enable_ip_whitelist' => false,
            'allowed_ips' => array(),
            'enable_rate_limiting' => true,
            'rate_limit_requests' => 100,
            'rate_limit_window' => 3600, // 1 hora
        );
    }
    
    /**
     * Obtener una configuración
     * @param string $key Clave de la configuración
     * @param mixed $default Valor por defecto
     * @param string $module Módulo (opcional)
     * @return mixed Valor de la configuración
     */
    public function get($key, $default = null, $module = 'core') {
        $cache_key = $module . '.' . $key;
        
        // Verificar cache primero
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        // Buscar en base de datos
        $full_key = $module === 'core' ? $key : $module . '_' . $key;
        
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT setting_value, is_encrypted FROM {$this->table_name} WHERE setting_key = %s",
                $full_key
            )
        );
        
        if ($result) {
            $value = $result->setting_value;
            
            // Desencriptar si es necesario
            if ($result->is_encrypted) {
                $value = $this->decrypt($value);
            }
            
            // Intentar deserializar
            $unserialized = maybe_unserialize($value);
            $value = $unserialized !== false ? $unserialized : $value;
            
            // Guardar en cache
            $this->cache[$cache_key] = $value;
            
            return $value;
        }
        
        // Usar valor por defecto
        $default_value = $default !== null ? $default : ($this->defaults[$key] ?? null);
        
        // Guardar en cache
        $this->cache[$cache_key] = $default_value;
        
        return $default_value;
    }
    
    /**
     * Establecer una configuración
     * @param string $key Clave de la configuración
     * @param mixed $value Valor
     * @param string $module Módulo (opcional)
     * @param bool $encrypt Encriptar valor (opcional)
     * @param bool $autoload Cargar automáticamente (opcional)
     * @return bool Éxito de la operación
     */
    public function set($key, $value, $module = 'core', $encrypt = false, $autoload = true) {
        $full_key = $module === 'core' ? $key : $module . '_' . $key;
        $cache_key = $module . '.' . $key;
        
        // Serializar valor si es necesario
        $serialized_value = maybe_serialize($value);
        
        // Encriptar si es necesario
        if ($encrypt) {
            $serialized_value = $this->encrypt($serialized_value);
        }
        
        // Preparar datos
        $data = array(
            'setting_key' => $full_key,
            'setting_value' => $serialized_value,
            'module' => $module,
            'is_encrypted' => $encrypt ? 1 : 0,
            'autoload' => $autoload ? 1 : 0,
            'updated_at' => current_time('mysql')
        );
        
        // Verificar si ya existe
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE setting_key = %s",
                $full_key
            )
        );
        
        if ($exists) {
            // Actualizar
            $result = $this->wpdb->update(
                $this->table_name,
                $data,
                array('setting_key' => $full_key),
                array('%s', '%s', '%s', '%d', '%d', '%s'),
                array('%s')
            );
        } else {
            // Insertar
            $data['created_at'] = current_time('mysql');
            $result = $this->wpdb->insert(
                $this->table_name,
                $data,
                array('%s', '%s', '%s', '%d', '%d', '%s', '%s')
            );
        }
        
        if ($result !== false) {
            // Actualizar cache
            $this->cache[$cache_key] = $value;
            
            // Hook personalizado
            do_action('zoho_sync_setting_updated', $key, $value, $module);
            
            // Log de cambio
            ZohoSyncCore::log('info', 'Configuración actualizada', array(
                'key' => $full_key,
                'module' => $module,
                'encrypted' => $encrypt
            ));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Eliminar una configuración
     * @param string $key Clave de la configuración
     * @param string $module Módulo (opcional)
     * @return bool Éxito de la operación
     */
    public function delete($key, $module = 'core') {
        $full_key = $module === 'core' ? $key : $module . '_' . $key;
        $cache_key = $module . '.' . $key;
        
        $result = $this->wpdb->delete(
            $this->table_name,
            array('setting_key' => $full_key),
            array('%s')
        );
        
        if ($result !== false) {
            // Limpiar cache
            unset($this->cache[$cache_key]);
            
            // Hook personalizado
            do_action('zoho_sync_setting_deleted', $key, $module);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener múltiples configuraciones
     * @param array $keys Array de claves
     * @param string $module Módulo (opcional)
     * @return array Configuraciones encontradas
     */
    public function get_multiple($keys, $module = 'core') {
        $results = array();
        
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, null, $module);
        }
        
        return $results;
    }
    
    /**
     * Establecer múltiples configuraciones
     * @param array $settings Array de configuraciones (key => value)
     * @param string $module Módulo (opcional)
     * @param array $encrypt_keys Claves a encriptar (opcional)
     * @return bool Éxito de la operación
     */
    public function set_multiple($settings, $module = 'core', $encrypt_keys = array()) {
        $success = true;
        
        foreach ($settings as $key => $value) {
            $encrypt = in_array($key, $encrypt_keys);
            if (!$this->set($key, $value, $module, $encrypt)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Obtener todas las configuraciones de un módulo
     * @param string $module Módulo
     * @return array Configuraciones del módulo
     */
    public function get_module_settings($module = 'core') {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT setting_key, setting_value, is_encrypted FROM {$this->table_name} WHERE module = %s",
                $module
            )
        );
        
        $settings = array();
        
        foreach ($results as $result) {
            $key = $result->setting_key;
            $value = $result->setting_value;
            
            // Remover prefijo del módulo si existe
            if ($module !== 'core' && strpos($key, $module . '_') === 0) {
                $key = substr($key, strlen($module . '_'));
            }
            
            // Desencriptar si es necesario
            if ($result->is_encrypted) {
                $value = $this->decrypt($value);
            }
            
            // Deserializar
            $value = maybe_unserialize($value);
            
            $settings[$key] = $value;
        }
        
        return $settings;
    }
    
    /**
     * Validar configuración
     * @param string $key Clave
     * @param mixed $value Valor
     * @param string $module Módulo
     * @return array Resultado de validación
     */
    public function validate_setting($key, $value, $module = 'core') {
        $validation = array(
            'valid' => true,
            'message' => '',
            'sanitized_value' => $value
        );
        
        // Aplicar filtro de validación personalizada
        $validation = apply_filters('zoho_sync_validate_setting', $validation, $key, $value, $module);
        
        // Validaciones específicas
        switch ($key) {
            case 'zoho_region':
                $allowed_regions = array('com', 'eu', 'in', 'com.au', 'jp');
                if (!in_array($value, $allowed_regions)) {
                    $validation['valid'] = false;
                    $validation['message'] = __('Región de Zoho no válida', 'zoho-sync-core');
                }
                break;
                
            case 'api_timeout':
                $value = intval($value);
                if ($value < 5 || $value > 300) {
                    $validation['valid'] = false;
                    $validation['message'] = __('El timeout debe estar entre 5 y 300 segundos', 'zoho-sync-core');
                } else {
                    $validation['sanitized_value'] = $value;
                }
                break;
                
            case 'batch_size':
                $value = intval($value);
                if ($value < 1 || $value > 200) {
                    $validation['valid'] = false;
                    $validation['message'] = __('El tamaño de lote debe estar entre 1 y 200', 'zoho-sync-core');
                } else {
                    $validation['sanitized_value'] = $value;
                }
                break;
                
            case 'notification_email':
                if (!empty($value) && !is_email($value)) {
                    $validation['valid'] = false;
                    $validation['message'] = __('Email de notificación no válido', 'zoho-sync-core');
                } else {
                    $validation['sanitized_value'] = sanitize_email($value);
                }
                break;
                
            case 'allowed_ips':
                if (is_array($value)) {
                    $sanitized_ips = array();
                    foreach ($value as $ip) {
                        if (filter_var($ip, FILTER_VALIDATE_IP)) {
                            $sanitized_ips[] = $ip;
                        }
                    }
                    $validation['sanitized_value'] = $sanitized_ips;
                }
                break;
        }
        
        return $validation;
    }
    
    /**
     * Exportar configuraciones
     * @param string $module Módulo (opcional)
     * @param bool $include_sensitive Incluir datos sensibles
     * @return array Configuraciones exportadas
     */
    public function export_settings($module = null, $include_sensitive = false) {
        $where_clause = $module ? "WHERE module = %s" : "";
        $query = "SELECT setting_key, setting_value, module, is_encrypted FROM {$this->table_name} $where_clause";
        
        if ($module) {
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($query, $module)
            );
        } else {
            $results = $this->wpdb->get_results($query);
        }
        
        $export = array();
        $sensitive_keys = array('zoho_client_secret', 'zoho_refresh_token', 'zoho_access_token', 'webhook_secret');
        
        foreach ($results as $result) {
            // Omitir datos sensibles si no se solicitan
            if (!$include_sensitive && in_array($result->setting_key, $sensitive_keys)) {
                continue;
            }
            
            $value = $result->setting_value;
            
            // No desencriptar para exportación
            if (!$result->is_encrypted) {
                $value = maybe_unserialize($value);
            }
            
            $export[] = array(
                'key' => $result->setting_key,
                'value' => $value,
                'module' => $result->module,
                'encrypted' => (bool) $result->is_encrypted
            );
        }
        
        return $export;
    }
    
    /**
     * Importar configuraciones
     * @param array $settings Configuraciones a importar
     * @param bool $overwrite Sobrescribir existentes
     * @return array Resultado de la importación
     */
    public function import_settings($settings, $overwrite = false) {
        $results = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        foreach ($settings as $setting) {
            if (!isset($setting['key']) || !isset($setting['value'])) {
                $results['errors'][] = __('Configuración inválida: falta clave o valor', 'zoho-sync-core');
                continue;
            }
            
            $key = $setting['key'];
            $value = $setting['value'];
            $module = $setting['module'] ?? 'core';
            $encrypted = $setting['encrypted'] ?? false;
            
            // Verificar si existe
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->table_name} WHERE setting_key = %s",
                    $key
                )
            );
            
            if ($exists && !$overwrite) {
                $results['skipped']++;
                continue;
            }
            
            // Validar configuración
            $validation = $this->validate_setting($key, $value, $module);
            if (!$validation['valid']) {
                $results['errors'][] = sprintf(
                    __('Error validando %s: %s', 'zoho-sync-core'),
                    $key,
                    $validation['message']
                );
                continue;
            }
            
            // Importar configuración
            if ($this->set($key, $validation['sanitized_value'], $module, $encrypted)) {
                $results['imported']++;
            } else {
                $results['errors'][] = sprintf(
                    __('Error importando configuración: %s', 'zoho-sync-core'),
                    $key
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Cargar configuraciones con autoload
     */
    private function load_autoload_settings() {
        $results = $this->wpdb->get_results(
            "SELECT setting_key, setting_value, module, is_encrypted FROM {$this->table_name} WHERE autoload = 1"
        );
        
        foreach ($results as $result) {
            $key = $result->setting_key;
            $module = $result->module;
            $value = $result->setting_value;
            
            // Desencriptar si es necesario
            if ($result->is_encrypted) {
                $value = $this->decrypt($value);
            }
            
            // Deserializar
            $value = maybe_unserialize($value);
            
            // Guardar en cache
            $cache_key = $module . '.' . ($module === 'core' ? $key : str_replace($module . '_', '', $key));
            $this->cache[$cache_key] = $value;
        }
    }
    
    /**
     * Limpiar cache
     */
    public function clear_cache() {
        $this->cache = array();
    }
    
    /**
     * Obtener clave de encriptación
     * @return string
     */
    private function get_encryption_key() {
        $key = get_option('zoho_sync_core_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, false);
            update_option('zoho_sync_core_encryption_key', $key);
        }
        
        return $key;
    }
    
    /**
     * Encriptar valor
     * @param string $value Valor a encriptar
     * @return string Valor encriptado
     */
    private function encrypt($value) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($value); // Fallback básico
        }
        
        $method = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        $encrypted = openssl_encrypt($value, $method, $this->encryption_key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Desencriptar valor
     * @param string $encrypted_value Valor encriptado
     * @return string Valor desencriptado
     */
    private function decrypt($encrypted_value) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_value); // Fallback básico
        }
        
        $data = base64_decode($encrypted_value);
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, $method, $this->encryption_key, 0, $iv);
    }
    
    /**
     * Resetear configuraciones a valores por defecto
     * @param string $module Módulo (opcional)
     */
    public function reset_to_defaults($module = 'core') {
        if ($module === 'core') {
            foreach ($this->defaults as $key => $value) {
                $this->set($key, $value, $module);
            }
        } else {
            // Eliminar todas las configuraciones del módulo
            $this->wpdb->delete(
                $this->table_name,
                array('module' => $module),
                array('%s')
            );
        }
        
        $this->clear_cache();
        
        ZohoSyncCore::log('info', 'Configuraciones reseteadas a valores por defecto', array(
            'module' => $module
        ));
    }
}
