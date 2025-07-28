<?php

class ZSZB_Distributor_Info {
    
    public static function get_distributor_data($distributor_id) {
        $user = get_userdata($distributor_id);
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($user->ID, 'distributor_phone', true),
            'address' => get_user_meta($user->ID, 'distributor_address', true),
            'zone_info' => self::get_zone_summary($user->ID),
            'level' => get_user_meta($user->ID, 'distributor_level', true),
            'is_active' => get_user_meta($user->ID, 'distributor_active', true),
            'zoho_id' => get_user_meta($user->ID, 'zoho_contact_id', true)
        ];
    }

    public static function get_zone_summary($distributor_id) {
        $zones = ZSZB_Distributor_Zones::get_distributor_zones($distributor_id);
        $total_codes = 0;
        $exclusive_zones = 0;

        foreach ($zones as $zone) {
            $codes = explode(',', $zone->postal_codes);
            $total_codes += count($codes);
            if ($zone->is_exclusive) {
                $exclusive_zones++;
            }
        }

        return [
            'total_zones' => count($zones),
            'total_postal_codes' => $total_codes,
            'exclusive_zones' => $exclusive_zones
        ];
    }

    public static function render_distributor_info($distributor_id) {
        $data = self::get_distributor_data($distributor_id);
        if (!$data) {
            return '';
        }

        ob_start();
        ?>
        <div class="zszb-distributor-info">
            <h2><?php echo esc_html($data['name']); ?></h2>
            
            <div class="distributor-contact">
                <p><strong><?php _e('Email:', 'zoho-sync-zone-blocker'); ?></strong> <?php echo esc_html($data['email']); ?></p>
                <?php if ($data['phone']): ?>
                    <p><strong><?php _e('Teléfono:', 'zoho-sync-zone-blocker'); ?></strong> <?php echo esc_html($data['phone']); ?></p>
                <?php endif; ?>
            </div>

            <div class="distributor-zones">
                <h3><?php _e('Resumen de Zonas', 'zoho-sync-zone-blocker'); ?></h3>
                <ul>
                    <li><?php printf(__('Zonas asignadas: %d', 'zoho-sync-zone-blocker'), $data['zone_info']['total_zones']); ?></li>
                    <li><?php printf(__('Códigos postales: %d', 'zoho-sync-zone-blocker'), $data['zone_info']['total_postal_codes']); ?></li>
                    <li><?php printf(__('Zonas exclusivas: %d', 'zoho-sync-zone-blocker'), $data['zone_info']['exclusive_zones']); ?></li>
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}