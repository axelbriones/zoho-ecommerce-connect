<?php

class ZSZB_Zone_Blocker {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        require_once ZSZB_PLUGIN_DIR . 'includes/class-zone-manager.php';
        ZSZB_Zone_Manager::install();
    }

    public static function deactivate() {
        // Opcional: limpiar transients, cron, etc.
    }

    private function __construct() {
        // Cargar dependencias principales
        $this->includes();
        // Hooks principales
        add_action('init', [$this, 'init']);
    }

    private function includes() {
        require_once ZSZB_PLUGIN_DIR . 'includes/class-geolocation-detector.php';
        require_once ZSZB_PLUGIN_DIR . 'includes/class-zone-manager.php';
        require_once ZSZB_PLUGIN_DIR . 'includes/class-distributor-zones.php';
        require_once ZSZB_PLUGIN_DIR . 'includes/class-access-controller.php';
        require_once ZSZB_PLUGIN_DIR . 'includes/class-redirect-handler.php';
    }

    public function init() {
        // Inicializar lógica de bloqueo y validación de acceso
        ZSZB_Access_Controller::init();
        ZSZB_Redirect_Handler::init();
    }
}