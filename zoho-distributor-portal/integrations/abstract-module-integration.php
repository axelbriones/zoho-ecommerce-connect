<?php

abstract class ZSDP_Module_Integration {
    
    protected $module_name;
    protected $required_version;
    protected $module_status = false;

    public function __construct($module_name, $required_version = '1.0') {
        $this->module_name = $module_name;
        $this->required_version = $required_version;
        
        // Verificar estado del módulo
        $this->check_module_status();
        
        if ($this->module_status) {
            $this->init();
        }
    }

    abstract protected function init();
    abstract public function get_dashboard_widget();
    abstract public function get_menu_items();
    
    protected function check_module_status() {
        $core = ZohoSyncCore::instance();
        $this->module_status = $core->is_module_active($this->module_name);
        return $this->module_status;
    }

    protected function log($message, $type = 'info', $context = []) {
        ZohoSyncCore::log(
            $type,
            $message,
            $context,
            'distributor_portal'
        );
    }
}