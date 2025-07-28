<?php

abstract class ZSCORE_Module_Activator {
    
    protected $module_id;
    protected $core;
    protected $required_capabilities = [];
    protected $custom_tables = [];

    public function __construct($module_id) {
        $this->module_id = $module_id;
        $this->core = ZohoSyncCore::instance();
        
        register_activation_hook(
            $this->get_plugin_file(),
            [$this, 'activate']
        );
        register_deactivation_hook(
            $this->get_plugin_file(),
            [$this, 'deactivate']
        );
    }

    public function activate() {
        if (!$this->check_dependencies()) {
            $this->deactivate_with_message();
            return;
        }

        $this->create_custom_tables();
        $this->register_capabilities();
        $this->set_default_options();
        $this->register_with_core();

        $this->custom_activation();
        
        flush_rewrite_rules();
    }

    public function deactivate() {
        $this->unregister_from_core();
        $this->remove_capabilities();
        $this->custom_deactivation();
        
        flush_rewrite_rules();
    }

    protected function check_dependencies() {
        // Verificar que el core esté activo
        if (!class_exists('ZohoSyncCore')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>';
                printf(
                    __('El módulo %s requiere que Zoho Sync Core esté instalado y activado.', 'zoho-sync-core'),
                    $this->module_id
                );
                echo '</p></div>';
            });
            return false;
        }

        return true;
    }

    protected function create_custom_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($this->custom_tables as $table => $schema) {
            $table_name = $wpdb->prefix . $table;
            dbDelta($schema);
        }
    }

    protected function register_capabilities() {
        $admin_role = get_role('administrator');
        foreach ($this->required_capabilities as $cap) {
            $admin_role->add_cap($cap);
        }
    }

    protected function remove_capabilities() {
        $admin_role = get_role('administrator');
        foreach ($this->required_capabilities as $cap) {
            $admin_role->remove_cap($cap);
        }
    }

    protected function register_with_core() {
        $module_data = $this->get_module_data();
        $this->core->register_module($this->module_id, $module_data);
    }

    protected function unregister_from_core() {
        // Notificar al core que el módulo se está desactivando
        do_action('zscore_module_deactivating', $this->module_id);
    }

    protected function get_plugin_file() {
        return WP_PLUGIN_DIR . "/{$this->module_id}/{$this->module_id}.php";
    }

    protected function get_module_data() {
        $default_headers = [
            'Name' => 'Plugin Name',
            'Version' => 'Version',
            'Dependencies' => 'Dependencies'
        ];

        $plugin_data = get_file_data(
            $this->get_plugin_file(),
            $default_headers
        );

        return [
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
            'dependencies' => array_map('trim', explode(',', $plugin_data['Dependencies'])),
            'status' => 'active'
        ];
    }

    protected function deactivate_with_message() {
        deactivate_plugins(plugin_basename($this->get_plugin_file()));
        
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }

    // Métodos abstractos que cada módulo debe implementar
    abstract protected function set_default_options();
    abstract protected function custom_activation();
    abstract protected function custom_deactivation();
}