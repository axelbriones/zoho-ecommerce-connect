<?php

class ZSCORE_Module_Manager {
    private $core;
    private $active_modules = [];
    private $module_paths = [];
    private $validator;

    public function __construct() {
        $this->core = ZohoSyncCore::instance();
        $this->validator = new ZSCORE_Ecosystem_Validator();
        
        add_action('plugins_loaded', [$this, 'load_modules'], 5);
        add_action('admin_init', [$this, 'check_modules_health']);
        add_filter('plugin_action_links', [$this, 'modify_plugin_links'], 10, 2);
    }

    public function load_modules() {
        // Detectar módulos instalados
        $this->detect_modules();

        // Cargar módulos en orden de dependencias
        $load_order = $this->calculate_load_order();
        foreach ($load_order as $module_id) {
            $this->load_module($module_id);
        }

        // Validar ecosistema después de cargar
        $validation = $this->validator->validate_ecosystem();
        if (!$validation['status']) {
            $this->handle_validation_errors($validation);
        }
    }

    private function detect_modules() {
        $plugin_dir = WP_PLUGIN_DIR;
        $pattern = '/^zoho-sync-([a-z-]+)$/';
        
        if ($handle = opendir($plugin_dir)) {
            while (false !== ($entry = readdir($handle))) {
                if (preg_match($pattern, $entry, $matches)) {
                    $module_id = str_replace('-', '_', $matches[1]);
                    $this->module_paths[$module_id] = $plugin_dir . '/' . $entry;
                }
            }
            closedir($handle);
        }
    }

    private function calculate_load_order() {
        $dependencies = [];
        $load_order = [];

        // Recopilar dependencias
        foreach ($this->module_paths as $module_id => $path) {
            $deps = $this->get_module_dependencies($module_id);
            $dependencies[$module_id] = $deps;
        }

        // Ordenar basado en dependencias
        while (count($dependencies) > 0) {
            $loaded_something = false;
            
            foreach ($dependencies as $module => $deps) {
                if (empty($deps) || array_diff($deps, $load_order) === []) {
                    $load_order[] = $module;
                    unset($dependencies[$module]);
                    $loaded_something = true;
                }
            }

            if (!$loaded_something) {
                // Detectar dependencias circulares
                $this->handle_circular_dependencies($dependencies);
                break;
            }
        }

        return $load_order;
    }

    private function get_module_dependencies($module_id) {
        $main_file = $this->module_paths[$module_id] . "/zoho-sync-{$module_id}.php";
        if (!file_exists($main_file)) {
            return [];
        }

        // Leer encabezado del plugin
        $data = get_file_data($main_file, [
            'Dependencies' => 'Dependencies'
        ]);

        return array_filter(
            array_map('trim', explode(',', $data['Dependencies']))
        );
    }

    private function load_module($module_id) {
        $main_file = $this->module_paths[$module_id] . "/zoho-sync-{$module_id}.php";
        
        if (file_exists($main_file)) {
            require_once $main_file;
            $this->active_modules[$module_id] = true;
            
            do_action("zscore_module_{$module_id}_loaded");
        }
    }

    private function handle_validation_errors($validation) {
        // Registrar errores
        foreach ($validation['messages'] as $message) {
            $this->core->log('error', $message);
        }

        // Mostrar notificaciones admin
        add_action('admin_notices', function() use ($validation) {
            echo '<div class="error">';
            echo '<p><strong>' . __('Zoho Sync: Errores de Validación', 'zoho-sync-core') . '</strong></p>';
            echo '<ul>';
            foreach ($validation['messages'] as $message) {
                echo '<li>' . esc_html($message) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        });
    }

    private function handle_circular_dependencies($dependencies) {
        $message = __('Se detectaron dependencias circulares entre módulos:', 'zoho-sync-core');
        $message .= "\n" . print_r($dependencies, true);
        
        $this->core->log('error', $message);
    }

    public function check_modules_health() {
        foreach ($this->active_modules as $module_id => $active) {
            if (!$active) continue;

            $status = $this->core->get_module_status($module_id);
            if (!empty($status['errors'])) {
                $this->handle_module_errors($module_id, $status['errors']);
            }
        }
    }

    private function handle_module_errors($module_id, $errors) {
        $formatted_errors = array_map(function($error) {
            return [
                'time' => $error['timestamp'],
                'message' => $error['message']
            ];
        }, $errors);

        update_option("zscore_{$module_id}_health_status", [
            'status' => 'error',
            'last_check' => current_time('mysql'),
            'errors' => $formatted_errors
        ]);
    }

    public function modify_plugin_links($links, $plugin_file) {
        foreach ($this->module_paths as $module_id => $path) {
            if (plugin_basename($path) === $plugin_file) {
                $settings_url = admin_url("admin.php?page=zoho-sync-{$module_id}");
                array_unshift($links, sprintf(
                    '<a href="%s">%s</a>',
                    esc_url($settings_url),
                    __('Configuración', 'zoho-sync-core')
                ));
            }
        }
        return $links;
    }
}