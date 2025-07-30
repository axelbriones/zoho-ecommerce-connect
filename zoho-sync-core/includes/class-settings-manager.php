<?php
/**
 * Settings Manager Class - Simplified Version
 * 
 * Centralized settings management for all Zoho Sync plugins
 * 
 * @package ZohoSyncCore
 * @subpackage Settings
 * @since 8.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Zoho Sync Core Settings Manager Class - Simplified
 * 
 * @class Zoho_Sync_Core_Settings_Manager
 * @version 8.0.0
 * @since 8.0.0
 */
class Zoho_Sync_Core_Settings_Manager {

    /**
     * Settings cache
     * 
     * @var array
     */
    private $settings_cache = array();

    /**
     * Default settings
     * 
     * @var array
     */
    private $default_settings = array(
        // Core settings
        'zoho_region' => 'com',
        'zoho_client_id' => '',
        'zoho_client_secret' => '',
        'zoho_refresh_token' => '',
        'zoho_access_token' => '',
        'token_expires_at' => '',
        
        // Logging settings
        'log_level' => 'info',
        'log_retention_days' => 30,
        'enable_debug' => false,
        
        // Webhook settings
        'enable_webhooks' => true,
        'webhook_secret' => '',
        'webhook_url' => '',
        
        // Sync settings
        'sync_frequency' => 'hourly',
        'batch_size' => 50,
        'sync_timeout' => 300,
        'enable_auto_sync' => true,
        
        // API settings
        'api_rate_limit' => 100,
        'api_timeout' => 30,
        'api_retry_attempts' => 3,
        'api_retry_delay' => 5,
        
        // Security settings
        'enable_ssl_verify' => true,
        'allowed_ips' => array(),
        'enable_ip_whitelist' => false,
        
        // Performance settings
        'enable_caching' => true,
        'cache_duration' => 3600,
        'enable_compression' => true,
        
        // Notification settings
        'enable_email_notifications' => true,
        'notification_email' => '',
        'notification_events' => array('error', 'sync_complete'),
        
        // Module settings
        'active_modules' => array(),
        'module_priorities' => array(),
        
        // Advanced settings
        'custom_fields_mapping' => array(),
        'data_transformation_rules' => array(),
        'error_handling_rules' => array()
    );

    /**
     * Constructor - Simplified to prevent recursion
     */
    public function __construct() {
        // Load settings without any hooks to prevent infinite loops
        $this->load_settings_simple();
    }

    /**
     * Load settings from database - Simplified version
     */
    private function load_settings_simple() {
        // Start with defaults
        $this->settings_cache = $this->default_settings;
        
        // Try to load from WordPress options, but don't trigger any hooks
        if (function_exists('get_option')) {
            $wp_settings = get_option('zoho_sync_core_settings', array());
            if (is_array($wp_settings)) {
                foreach ($wp_settings as $key => $value) {
                    $this->settings_cache[$key] = $value;
                }
            }
        }
    }

    /**
     * Get setting value
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get($key, $default = null) {
        if (isset($this->settings_cache[$key])) {
            return $this->settings_cache[$key];
        }

        if (isset($this->default_settings[$key])) {
            return $this->default_settings[$key];
        }

        return $default;
    }

    /**
     * Set setting value - Simplified version
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param bool $autoload Whether to autoload
     * @return bool Success status
     */
    public function set($key, $value, $autoload = true) {
        // Update cache immediately
        $this->settings_cache[$key] = $value;

        // Try to save to WordPress options without triggering hooks
        if (function_exists('update_option')) {
            // Get current settings
            $all_settings = get_option('zoho_sync_core_settings', array());
            if (!is_array($all_settings)) {
                $all_settings = array();
            }
            
            // Update the specific key
            $all_settings[$key] = $value;
            
            // Save back to database
            return update_option('zoho_sync_core_settings', $all_settings, $autoload);
        }

        return true; // Return true if WordPress functions aren't available
    }

    /**
     * Get all settings
     * 
     * @param string $module Filter by module (ignored in simplified version)
     * @return array Settings array
     */
    public function get_all($module = null) {
        return $this->settings_cache;
    }

    /**
     * Update multiple settings
     * 
     * @param array $settings Settings array
     * @return bool Success status
     */
    public function update_multiple($settings) {
        if (!is_array($settings)) {
            return false;
        }

        $success = true;
        foreach ($settings as $key => $value) {
            if (!$this->set($key, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Reset settings to defaults
     * 
     * @param array $keys Specific keys to reset (optional)
     * @return bool Success status
     */
    public function reset_to_defaults($keys = null) {
        if ($keys === null) {
            $keys = array_keys($this->default_settings);
        }

        $success = true;
        foreach ($keys as $key) {
            if (isset($this->default_settings[$key])) {
                if (!$this->set($key, $this->default_settings[$key])) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Delete setting - Simplified version
     * 
     * @param string $key Setting key
     * @return bool Success status
     */
    public function delete($key) {
        // Remove from cache
        unset($this->settings_cache[$key]);

        // Remove from WordPress options
        if (function_exists('get_option') && function_exists('update_option')) {
            $all_settings = get_option('zoho_sync_core_settings', array());
            if (is_array($all_settings) && isset($all_settings[$key])) {
                unset($all_settings[$key]);
                return update_option('zoho_sync_core_settings', $all_settings);
            }
        }

        return true;
    }

    /**
     * Get default settings
     * 
     * @return array Default settings
     */
    public function get_default_settings() {
        return $this->default_settings;
    }

    /**
     * Basic validation for setting values
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Valid status
     */
    public function validate_setting($key, $value) {
        // Basic validation - just check if it's not null for required fields
        $required_fields = array('zoho_region', 'log_level', 'sync_frequency');
        
        if (in_array($key, $required_fields) && (is_null($value) || $value === '')) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return mixed Sanitized value
     */
    public function sanitize_setting($key, $value) {
        // Basic sanitization
        if (is_string($value)) {
            return sanitize_text_field($value);
        }
        
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        if (is_bool($value)) {
            return (bool) $value;
        }
        
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }

        return $value;
    }

    /**
     * Export settings - Simplified version
     * 
     * @param bool $include_sensitive Include sensitive data
     * @return array Settings export
     */
    public function export_settings($include_sensitive = false) {
        $settings = $this->get_all();

        if (!$include_sensitive) {
            $sensitive_keys = array('zoho_client_secret', 'zoho_refresh_token', 'zoho_access_token', 'webhook_secret');
            foreach ($sensitive_keys as $key) {
                if (isset($settings[$key])) {
                    $settings[$key] = '[HIDDEN]';
                }
            }
        }

        return array(
            'version' => defined('ZOHO_SYNC_CORE_VERSION') ? ZOHO_SYNC_CORE_VERSION : '8.0.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'settings' => $settings
        );
    }

    /**
     * Import settings - Simplified version
     * 
     * @param array $import_data Import data
     * @return bool Success status
     */
    public function import_settings($import_data) {
        if (!isset($import_data['settings']) || !is_array($import_data['settings'])) {
            return false;
        }

        $success = true;
        foreach ($import_data['settings'] as $key => $value) {
            if ($value !== '[HIDDEN]') {
                if (!$this->set($key, $value)) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    // Placeholder methods to maintain compatibility
    public function register_settings() { return true; }
    public function validate_settings() { return true; }
    public function before_update_settings($new_value, $old_value) { return $new_value; }
    public function after_update_settings($old_value, $new_value) { return true; }
    public function get_validation_rules() { return array(); }
    public function sanitize_settings($settings) { return $settings; }
}
