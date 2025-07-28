<?php

class ZSZB_Zoho_Sync {
    private $api;
    
    public function __construct() {
        // Usar el cliente API del core
        $this->api = ZohoSyncCore::api();
        
        add_action('zszb_sync_zones', [$this, 'sync_zones_with_zoho']);
        add_action('zszb_hourly_sync', [$this, 'schedule_sync']);
    }
    
    public function schedule_sync() {
        if (!wp_next_scheduled('zszb_sync_zones')) {
            wp_schedule_event(time(), 'hourly', 'zszb_sync_zones');
        }
    }
    
    public function sync_zones_with_zoho() {
        try {
            // Obtener zonas de Zoho CRM
            $zoho_zones = $this->api->get('crm', 'Territories', [
                'fields' => 'Territory_Name,Postal_Codes,Distributor'
            ]);
            
            if (!empty($zoho_zones->data)) {
                foreach ($zoho_zones->data as $zone) {
                    $this->update_local_zone($zone);
                }
                
                ZohoSyncCore::log('info', 
                    sprintf('Sincronizadas %d zonas desde Zoho', count($zoho_zones->data)),
                    [], 
                    'zone_blocker'
                );
            }
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 
                'Error sincronizando zonas: ' . $e->getMessage(),
                [], 
                'zone_blocker'
            );
        }
    }
    
    private function update_local_zone($zoho_zone) {
        global $wpdb;
        
        $postal_codes = $this->format_postal_codes($zoho_zone->Postal_Codes);
        $distributor_id = $this->get_distributor_user_id($zoho_zone->Distributor);
        
        if (!$distributor_id) {
            return false;
        }
        
        return ZSZB_Zone_Manager::update_or_create_zone([
            'distributor_id' => $distributor_id,
            'postal_codes' => $postal_codes,
            'zoho_territory_id' => $zoho_zone->id
        ]);
    }
    
    private function format_postal_codes($postal_codes) {
        // Convertir formato de Zoho al formato local
        return preg_replace('/\s+/', '', $postal_codes);
    }
    
    private function get_distributor_user_id($zoho_distributor_id) {
        return get_user_meta($zoho_distributor_id, 'zoho_contact_id', true);
    }
}