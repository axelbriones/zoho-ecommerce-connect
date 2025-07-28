<?php

class ZSCORE_Ecosystem_Validator {
    private $core;
    private $required_modules = ['core'];
    private $optional_modules = [
        'orders',
        'customers',
        'products',
        'inventory',
        'reports',
        'zone_blocker'
    ];

    public function __construct() {
        $this->core = ZohoSyncCore::instance();
    }

    public function validate_ecosystem() {
        $validation = [
            'status' => true,
            'messages' => [],
            'modules' => []
        ];

        // Validar núcleo
        $core_validation = $this->validate_core();
        if (!$core_validation['status']) {
            $validation['status'] = false;
            $validation['messages'] = array_merge(
                $validation['messages'], 
                $core_validation['messages']
            );
        }

        // Validar módulos activos
        foreach ($this->core->get_modules() as $module_id => $module) {
            $module_validation = $this->validate_module($module_id);
            $validation['modules'][$module_id] = $module_validation;
            
            if (!$module_validation['status']) {
                $validation['status'] = false;
            }
        }

        // Validar integraciones
        $integration_validation = $this->validate_integrations();
        if (!$integration_validation['status']) {
            $validation['status'] = false;
            $validation['messages'] = array_merge(
                $validation['messages'], 
                $integration_validation['messages']
            );
        }

        return $validation;
    }

    private function validate_core() {
        $validation = [
            'status' => true,
            'messages' => []
        ];

        // Verificar tablas de base de datos
        if (!$this->verify_database_tables()) {
            $validation['status'] = false;
            $validation['messages'][] = __('Tablas de base de datos faltantes o corruptas', 'zoho-sync-core');
        }

        // Verificar configuración de Zoho
        if (!$this->verify_zoho_configuration()) {
            $validation['status'] = false;
            $validation['messages'][] = __('Configuración de Zoho incompleta', 'zoho-sync-core');
        }

        // Verificar permisos de directorio
        if (!$this->verify_directory_permissions()) {
            $validation['status'] = false;
            $validation['messages'][] = __('Permisos de directorio insuficientes', 'zoho-sync-core');
        }

        return $validation;
    }

    private function validate_module($module_id) {
        $validation = [
            'status' => true,
            'messages' => []
        ];

        $module = $this->core->get_module_status($module_id);
        
        // Verificar existencia del módulo
        if (!$module) {
            return [
                'status' => false,
                'messages' => [__('Módulo no encontrado', 'zoho-sync-core')]
            ];
        }

        // Verificar dependencias
        $dependencies = $this->get_module_dependencies($module_id);
        foreach ($dependencies as $dependency) {
            if (!$this->core->is_module_active($dependency)) {
                $validation['status'] = false;
                $validation['messages'][] = sprintf(
                    __('Dependencia faltante: %s', 'zoho-sync-core'),
                    $dependency
                );
            }
        }

        // Verificar hooks requeridos
        if (!$this->verify_module_hooks($module_id)) {
            $validation['status'] = false;
            $validation['messages'][] = __('Hooks requeridos no registrados', 'zoho-sync-core');
        }

        return $validation;
    }

    private function validate_integrations() {
        $validation = [
            'status' => true,
            'messages' => []
        ];

        // Verificar integración con WooCommerce
        if (!$this->verify_woocommerce_integration()) {
            $validation['status'] = false;
            $validation['messages'][] = __('Integración con WooCommerce incompleta', 'zoho-sync-core');
        }

        // Verificar integración con Zoho
        if (!$this->verify_zoho_integration()) {
            $validation['status'] = false;
            $validation['messages'][] = __('Integración con Zoho incompleta', 'zoho-sync-core');
        }

        return $validation;
    }

    private function verify_database_tables() {
        global $wpdb;
        $required_tables = [
            $wpdb->prefix . 'zscore_logs',
            $wpdb->prefix . 'zscore_sync_queue',
            $wpdb->prefix . 'zscore_modules'
        ];

        foreach ($required_tables as $table) {
            if (!$this->table_exists($table)) {
                return false;
            }
        }

        return true;
    }

    private function table_exists($table) {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    }

    private function verify_zoho_configuration() {
        $required_settings = [
            'zoho_client_id',
            'zoho_client_secret',
            'zoho_refresh_token'
        ];

        foreach ($required_settings as $setting) {
            if (empty(get_option($setting))) {
                return false;
            }
        }

        return true;
    }

    private function verify_directory_permissions() {
        $required_directories = [
            WP_CONTENT_DIR . '/uploads/zoho-sync',
            WP_CONTENT_DIR . '/uploads/zoho-sync/logs',
            WP_CONTENT_DIR . '/uploads/zoho-sync/temp'
        ];

        foreach ($required_directories as $dir) {
            if (!is_dir($dir) && !wp_mkdir_p($dir)) {
                return false;
            }

            if (!is_writable($dir)) {
                return false;
            }
        }

        return true;
    }

    private function verify_module_hooks($module_id) {
        $required_hooks = $this->get_required_hooks($module_id);
        
        foreach ($required_hooks as $hook) {
            if (!has_action($hook) && !has_filter($hook)) {
                return false;
            }
        }

        return true;
    }

    private function get_required_hooks($module_id) {
        $common_hooks = [
            "zscore_{$module_id}_init",
            "zscore_{$module_id}_sync",
            "zscore_{$module_id}_error"
        ];

        $specific_hooks = [];
        
        switch ($module_id) {
            case 'orders':
                $specific_hooks = ['woocommerce_order_status_changed'];
                break;
            case 'customers':
                $specific_hooks = ['user_register', 'profile_update'];
                break;
            case 'products':
                $specific_hooks = ['woocommerce_update_product'];
                break;
        }

        return array_merge($common_hooks, $specific_hooks);
    }

    private function verify_woocommerce_integration() {
        return class_exists('WooCommerce') && 
               version_compare(WC_VERSION, '6.0', '>=');
    }

    private function verify_zoho_integration() {
        try {
            $api = $this->core->api();
            $test = $api->test_connection();
            return $test === true;
        } catch (Exception $e) {
            return false;
        }
    }
}