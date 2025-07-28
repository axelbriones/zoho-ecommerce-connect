<?php

class ZSZB_Distributor_Zones {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'zszb_distributor_zones';
        
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        // Registrar endpoints para la API REST
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            distributor_id bigint(20) NOT NULL,
            zone_name varchar(100) NOT NULL,
            postal_codes text NOT NULL,
            is_exclusive tinyint(1) DEFAULT 0,
            priority int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY distributor_id (distributor_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function assign_zone($distributor_id, $data) {
        global $wpdb;
        
        // Validar datos
        if (empty($data['zone_name']) || empty($data['postal_codes'])) {
            return new WP_Error('invalid_data', __('Datos de zona incompletos', 'zoho-sync-zone-blocker'));
        }

        // Verificar superposición de zonas
        if ($this->check_zone_overlap($data['postal_codes'], $distributor_id)) {
            return new WP_Error('zone_overlap', __('La zona se superpone con otras existentes', 'zoho-sync-zone-blocker'));
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                'distributor_id' => $distributor_id,
                'zone_name' => $data['zone_name'],
                'postal_codes' => is_array($data['postal_codes']) ? implode(',', $data['postal_codes']) : $data['postal_codes'],
                'is_exclusive' => isset($data['is_exclusive']) ? 1 : 0,
                'priority' => isset($data['priority']) ? intval($data['priority']) : 0
            ],
            ['%d', '%s', '%s', '%d', '%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Error al guardar la zona', 'zoho-sync-zone-blocker'));
        }

        // Limpiar caché
        ZSZB_Zone_Cache::clear_cache();
        
        // Notificar al core
        do_action('zoho_sync_data_updated', 'zone_blocker', 'zone_assigned', $wpdb->insert_id);

        return $wpdb->insert_id;
    }

    public function get_distributor_zones($distributor_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE distributor_id = %d 
            ORDER BY priority DESC, created_at DESC",
            $distributor_id
        ));
    }

    public function get_zone_info($postal_code) {
        global $wpdb;
        
        $zones = $wpdb->get_results($wpdb->prepare(
            "SELECT z.*, u.display_name as distributor_name, u.user_email 
            FROM {$this->table_name} z 
            LEFT JOIN {$wpdb->users} u ON z.distributor_id = u.ID 
            WHERE FIND_IN_SET(%s, z.postal_codes) > 0 
            ORDER BY z.priority DESC, z.is_exclusive DESC 
            LIMIT 1",
            $postal_code
        ));

        return !empty($zones) ? $zones[0] : null;
    }

    private function check_zone_overlap($postal_codes, $exclude_distributor_id = 0) {
        global $wpdb;
        
        $postal_array = is_array($postal_codes) ? $postal_codes : explode(',', $postal_codes);
        
        foreach ($postal_array as $code) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                WHERE distributor_id != %d 
                AND FIND_IN_SET(%s, postal_codes) > 0 
                AND is_exclusive = 1",
                $exclude_distributor_id,
                trim($code)
            ));
            
            if ($exists > 0) {
                return true;
            }
        }
        
        return false;
    }

    public function register_rest_routes() {
        register_rest_route('zoho-sync/v1', '/zones/distributor/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_distributor_zones'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function api_get_distributor_zones($request) {
        $distributor_id = $request->get_param('id');
        $zones = $this->get_distributor_zones($distributor_id);
        
        return new WP_REST_Response($zones, 200);
    }
}