<?php

class ZSZB_WC_Integration {
    
    public function __construct() {
        add_action('woocommerce_before_checkout_form', [$this, 'validate_zone_access']);
        add_action('woocommerce_add_to_cart_validation', [$this, 'validate_product_zone'], 10, 3);
        add_filter('woocommerce_available_payment_gateways', [$this, 'filter_payment_gateways']);
    }
    
    public function validate_zone_access() {
        $postal_code = WC()->customer->get_shipping_postcode();
        
        if (!ZSZB_Access_Controller::is_postal_code_allowed($postal_code)) {
            wc_add_notice(
                __('Lo sentimos, no realizamos entregas en tu zona.', 'zoho-sync-zone-blocker'),
                'error'
            );
            wp_redirect(wc_get_cart_url());
            exit;
        }
    }
}