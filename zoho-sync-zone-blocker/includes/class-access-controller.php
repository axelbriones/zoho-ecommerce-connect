<?php
<?php

class ZSZB_Access_Controller {

    public static function init() {
        add_action('template_redirect', [__CLASS__, 'check_access']);
        add_action('woocommerce_before_checkout_form', [__CLASS__, 'validate_checkout_access']);
    }

    public static function check_access() {
        // No bloquear admin
        if (is_admin()) {
            return;
        }

        $postal_code = self::get_user_postal_code();
        
        if (!$postal_code) {
            self::maybe_redirect_to_postal_input();
            return;
        }

        if (!self::is_postal_code_allowed($postal_code)) {
            self::handle_blocked_access();
        }
    }

    public static function validate_checkout_access() {
        $postal_code = self::get_user_postal_code();
        
        if (!self::is_postal_code_allowed($postal_code)) {
            wc_add_notice(
                __('Lo sentimos, no realizamos entregas en tu código postal.', 'zoho-sync-zone-blocker'),
                'error'
            );
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }
    }

    private static function get_user_postal_code() {
        // Prioridad: 1. Usuario logueado, 2. Cookie, 3. Sesión
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            return get_user_meta($user_id, 'billing_postcode', true);
        }

        if (isset($_COOKIE['zszb_postal_code'])) {
            return sanitize_text_field($_COOKIE['zszb_postal_code']);
        }

        return WC()->session->get('postal_code');
    }

    private static function is_postal_code_allowed($postal_code) {
        global $wpdb;
        
        $zones = ZSZB_Zone_Manager::get_zones();
        
        foreach ($zones as $zone) {
            if (self::postal_code_matches_zone($postal_code, $zone->postal_codes)) {
                return true;
            }
        }
        
        return false;
    }

    private static function postal_code_matches_zone($postal_code, $zone_codes) {
        $ranges = explode(',', $zone_codes);
        
        foreach ($ranges as $range) {
            if (strpos($range, '-') !== false) {
                list($start, $end) = explode('-', $range);
                if ($postal_code >= $start && $postal_code <= $end) {
                    return true;
                }
            } else {
                if ($postal_code === trim($range)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private static function handle_blocked_access() {
        $redirect_url = get_option('zszb_blocked_redirect');
        
        if ($redirect_url) {
            wp_safe_redirect($redirect_url);
            exit;
        }

        wp_die(
            __('Lo sentimos, no damos servicio en tu zona.', 'zoho-sync-zone-blocker'),
            __('Acceso Restringido', 'zoho-sync-zone-blocker'),
            array('response' => 403)
        );
    }

    private static function maybe_redirect_to_postal_input() {
        if (!is_page('postal-code-input')) {
            wp_safe_redirect(home_url('/postal-code-input'));
            exit;
        }
    }
}