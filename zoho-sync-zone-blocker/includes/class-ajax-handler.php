<?php

class ZSZB_Ajax_Handler {
    
    public static function init() {
        add_action('wp_ajax_zszb_check_postal', [__CLASS__, 'handle_postal_check']);
        add_action('wp_ajax_nopriv_zszb_check_postal', [__CLASS__, 'handle_postal_check']);
    }
    
    public static function handle_postal_check() {
        check_ajax_referer('zszb_check_postal', 'zszb_nonce');
        
        $postal_code = sanitize_text_field($_POST['postal_code']);
        
        if (empty($postal_code)) {
            wp_send_json_error([
                'message' => __('Por favor ingresa un código postal válido.', 'zoho-sync-zone-blocker')
            ]);
        }
        
        // Verificar formato del código postal (España: 5 dígitos)
        if (!preg_match('/^[0-9]{5}$/', $postal_code)) {
            wp_send_json_error([
                'message' => __('El código postal debe tener 5 dígitos.', 'zoho-sync-zone-blocker')
            ]);
        }
        
        // Verificar si el código está permitido
        if (ZSZB_Access_Controller::is_postal_code_allowed($postal_code)) {
            // Guardar el código postal
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), 'billing_postcode', $postal_code);
            }
            
            setcookie('zszb_postal_code', $postal_code, time() + DAY_IN_SECONDS * 30, COOKIEPATH, COOKIE_DOMAIN);
            WC()->session->set('postal_code', $postal_code);
            
            wp_send_json_success([
                'redirect' => home_url(),
                'message' => __('¡Genial! Damos servicio en tu zona.', 'zoho-sync-zone-blocker')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Lo sentimos, actualmente no damos servicio en tu zona.', 'zoho-sync-zone-blocker')
            ]);
        }
    }
}