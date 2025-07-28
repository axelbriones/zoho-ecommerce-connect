<?php

class ZSZB_API_Controller {
    
    public function register_routes() {
        register_rest_route('zoho-sync/v1', '/zones', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_zones'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_zone'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);
    }
    
    public function check_permission() {
        return current_user_can('manage_options');
    }
}