<?php
/**
 * Auth Manager Class
 * 
 * @package ZohoSyncCore
 * @subpackage Auth
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class Zoho_Sync_Core_Auth_Manager {

    private $zoho_urls = array(
        'com' => array(
            'accounts' => 'https://accounts.zoho.com',
            'api' => 'https://www.zohoapis.com'
        ),
        'eu' => array(
            'accounts' => 'https://accounts.zoho.eu',
            'api' => 'https://www.zohoapis.eu'
        ),
        'in' => array(
            'accounts' => 'https://accounts.zoho.in',
            'api' => 'https://www.zohoapis.in'
        ),
        'com.au' => array(
            'accounts' => 'https://accounts.zoho.com.au',
            'api' => 'https://www.zohoapis.com.au'
        ),
        'jp' => array(
            'accounts' => 'https://accounts.zoho.jp',
            'api' => 'https://www.zohoapis.jp'
        )
    );

    public function get_authorization_url($service, $region = 'com', $redirect_uri = '') {
        $settings = get_option('zoho_sync_core_settings');
        $client_id = isset($settings['zoho_client_id']) ? $settings['zoho_client_id'] : '';

        if (empty($client_id)) {
            return false;
        }

        if (empty($redirect_uri)) {
            $redirect_uri = admin_url('admin.php?page=zoho-sync-core');
        }

        $scopes = 'ZohoInventory.FullAccess.all,ZohoCRM.modules.ALL';

        $params = array(
            'scope' => $scopes,
            'client_id' => $client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'access_type' => 'offline',
        );

        $base_url = $this->zoho_urls[$region]['accounts'] . '/oauth/v2/auth';
        return $base_url . '?' . http_build_query($params);
    }

    public function exchange_code_for_tokens($code, $region = 'com', $redirect_uri = '') {
        $settings = get_option('zoho_sync_core_settings');
        $client_id = isset($settings['zoho_client_id']) ? $settings['zoho_client_id'] : '';
        $client_secret = isset($settings['zoho_client_secret']) ? $settings['zoho_client_secret'] : '';

        if (empty($client_id) || empty($client_secret)) {
            return false;
        }

        if (empty($redirect_uri)) {
            $redirect_uri = admin_url('admin.php?page=zoho-sync-core');
        }

        $token_url = $this->zoho_urls[$region]['accounts'] . '/oauth/v2/token';

        $params = array(
            'grant_type' => 'authorization_code',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'code' => $code
        );

        $response = wp_remote_post($token_url, array('body' => $params));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['refresh_token'])) {
            $settings['zoho_refresh_token'] = $data['refresh_token'];
            update_option('zoho_sync_core_settings', $settings);
        }

        return $data;
    }

    public function validate_credentials($client_id, $client_secret, $refresh_token, $region = 'com') {
        $token_url = $this->zoho_urls[$region]['accounts'] . '/oauth/v2/token';
        $params = array(
            'grant_type' => 'refresh_token',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token
        );
        $response = wp_remote_post($token_url, array('body' => $params));
        if (is_wp_error($response)) {
            return array('valid' => false, 'message' => $response->get_error_message());
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data['error'])) {
            return array('valid' => false, 'message' => $data['error_description'] ?? $data['error']);
        }
        if (isset($data['access_token'])) {
            return array('valid' => true, 'message' => __('Connection successful', 'zoho-sync-core'));
        }
        return array('valid' => false, 'message' => __('Unexpected response from Zoho', 'zoho-sync-core'));
    }

    /**
     * Get available regions
     * 
     * @return array Available regions
     */
    public function get_available_regions() {
        return array(
            'com' => __('Estados Unidos (.com)', 'zoho-sync-core'),
            'eu' => __('Europa (.eu)', 'zoho-sync-core'),
            'in' => __('India (.in)', 'zoho-sync-core'),
            'com.au' => __('Australia (.com.au)', 'zoho-sync-core'),
            'jp' => __('Japón (.jp)', 'zoho-sync-core')
        );
    }

    /**
     * Get authentication status
     * 
     * @return array Authentication status
     */
    public function get_auth_status() {
        $settings = get_option('zoho_sync_core_settings', array());
        $client_id = isset($settings['zoho_client_id']) ? $settings['zoho_client_id'] : '';
        $client_secret = isset($settings['zoho_client_secret']) ? $settings['zoho_client_secret'] : '';
        $refresh_token = isset($settings['zoho_refresh_token']) ? $settings['zoho_refresh_token'] : '';
        
        $tokens_available = !empty($client_id) && !empty($client_secret) && !empty($refresh_token);
        $token_valid = false;
        
        if ($tokens_available) {
            $validation = $this->validate_credentials($client_id, $client_secret, $refresh_token);
            $token_valid = $validation['valid'];
        }
        
        return array(
            'tokens_available' => $tokens_available,
            'token_valid' => $token_valid,
            'client_id_set' => !empty($client_id),
            'client_secret_set' => !empty($client_secret),
            'refresh_token_set' => !empty($refresh_token)
        );
    }
}
