<?php

class Zoho_Sync_Core_Autoloader {

    public function register() {
        spl_autoload_register(array($this, 'load_class'));
    }

    public function load_class($class_name) {
        if (strpos($class_name, 'Zoho_Sync_Core_') !== 0) {
            return;
        }

        $file_path = str_replace(
            array('Zoho_Sync_Core_', '_'),
            array('', '-'),
            strtolower($class_name)
        );

        $file = ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-' . $file_path . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }

        $file = ZOHO_SYNC_CORE_ADMIN_DIR . 'class-' . $file_path . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
}
