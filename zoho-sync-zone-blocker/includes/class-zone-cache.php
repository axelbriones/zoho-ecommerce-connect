<?php

class ZSZB_Zone_Cache {
    const CACHE_GROUP = 'zszb_zones';
    const CACHE_TIME = 3600; // 1 hora
    
    public static function get_cached_zones() {
        $zones = wp_cache_get('all_zones', self::CACHE_GROUP);
        
        if (false === $zones) {
            $zones = ZSZB_Zone_Manager::get_zones();
            wp_cache_set('all_zones', $zones, self::CACHE_GROUP, self::CACHE_TIME);
        }
        
        return $zones;
    }
    
    public static function clear_cache() {
        wp_cache_delete('all_zones', self::CACHE_GROUP);
    }
    
    public static function is_postal_code_allowed($postal_code) {
        $cache_key = 'postal_' . $postal_code;
        $allowed = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if (false === $allowed) {
            $allowed = ZSZB_Access_Controller::check_postal_code($postal_code);
            wp_cache_set($cache_key, $allowed, self::CACHE_GROUP, self::CACHE_TIME);
        }
        
        return $allowed;
    }
}