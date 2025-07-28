<?php

class ZSZB_Zone_Manager {
    
    private static $table_name;
    
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'zszb_zones';
    }
    
    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS " . self::$table_name . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            distributor_id bigint(20) NOT NULL,
            postal_codes text NOT NULL,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY distributor_id (distributor_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function add_zone($distributor_id, $postal_codes) {
        global $wpdb;
        
        return $wpdb->insert(
            self::$table_name,
            array(
                'distributor_id' => $distributor_id,
                'postal_codes' => is_array($postal_codes) ? implode(',', $postal_codes) : $postal_codes
            ),
            array('%d', '%s')
        );
    }

    public static function update_zone($zone_id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            self::$table_name,
            $data,
            array('id' => $zone_id),
            array('%d', '%s'),
            array('%d')
        );
    }

    public static function delete_zone($zone_id) {
        global $wpdb;
        
        return $wpdb->delete(
            self::$table_name,
            array('id' => $zone_id),
            array('%d')
        );
    }

    public static function get_zone($zone_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_name . " WHERE id = %d",
                $zone_id
            )
        );
    }

    public static function get_zones() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT z.*, u.display_name as distributor_name 
             FROM " . self::$table_name . " z
             LEFT JOIN {$wpdb->users} u ON z.distributor_id = u.ID
             ORDER BY z.created_at DESC"
        );
    }
}