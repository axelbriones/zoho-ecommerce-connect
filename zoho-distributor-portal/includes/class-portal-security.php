<?php

class ZSDP_Portal_Security {
    
    private $required_capabilities = [
        'view_portal',
        'access_special_pricing',
        'view_distributor_reports'
    ];

    public function __construct() {
        add_action('init', [$this, 'init_security']);
        add_filter('authenticate', [$this, 'check_distributor_access'], 30, 3);
    }

    public function init_security() {
        // Verificar sesión segura
        if (!$this->verify_session_security()) {
            $this->regenerate_session();
        }

        // Aplicar reglas de seguridad
        $this->apply_security_headers();
    }

    public function can_access_portal($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Verificar rol de distribuidor
        if (!in_array('distributor', $user->roles)) {
            return false;
        }

        // Verificar capacidades requeridas
        foreach ($this->required_capabilities as $cap) {
            if (!user_can($user_id, $cap)) {
                return false;
            }
        }

        // Verificar estado activo en Zoho
        if (!$this->verify_zoho_status($user_id)) {
            return false;
        }

        return true;
    }

    private function verify_session_security() {
        if (!isset($_COOKIE[SECURE_AUTH_COOKIE])) {
            return false;
        }

        if (!wp_get_session_token()) {
            return false;
        }

        return true;
    }

    private function regenerate_session() {
        $user_id = get_current_user_id();
        if ($user_id) {
            wp_destroy_current_session();
            wp_clear_auth_cookie();
            wp_set_auth_cookie($user_id, true);
        }
    }

    private function apply_security_headers() {
        if (!$this->is_portal_page()) {
            return;
        }

        // Prevenir clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Habilitar protección XSS en navegadores
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevenir MIME sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Política de seguridad de contenido
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' data: *.zoho.com *.googleapis.com");
    }

    public function check_distributor_access($user, $username, $password) {
        if (!$user || is_wp_error($user)) {
            return $user;
        }

        // Si es distribuidor, verificar acceso en Zoho
        if (in_array('distributor', $user->roles)) {
            if (!$this->verify_zoho_status($user->ID)) {
                return new WP_Error(
                    'distributor_inactive',
                    __('Tu cuenta de distribuidor está inactiva.', 'zoho-distributor-portal')
                );
            }
        }

        return $user;
    }

    private function verify_zoho_status($user_id) {
        try {
            $zoho_id = get_user_meta($user_id, 'zoho_contact_id', true);
            if (!$zoho_id) {
                return false;
            }

            // Verificar estado en Zoho CRM
            $api = ZohoSyncCore::api();
            $contact = $api->get('crm', 'Contacts/' . $zoho_id);

            return isset($contact->data[0]->Distributor_Status) && 
                   $contact->data[0]->Distributor_Status === 'Active';

        } catch (Exception $e) {
            ZohoSyncCore::log(
                'error',
                'Error verificando estado de distribuidor en Zoho: ' . $e->getMessage(),
                ['user_id' => $user_id],
                'distributor_portal'
            );
            return false;
        }
    }

    private function is_portal_page() {
        global $wp_query;
        return isset($wp_query->query_vars['distributor-portal']) || 
               is_page('distributor-portal');
    }
}