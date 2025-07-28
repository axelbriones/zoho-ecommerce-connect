<?php

class ZSZB_Notification_Manager {
    
    public static function send_distributor_notification($distributor_id, $type, $data = []) {
        $distributor = ZSZB_Distributor_Info::get_distributor_data($distributor_id);
        
        if (!$distributor || !$distributor['email']) {
            return false;
        }
        
        $template = self::get_email_template($type);
        $content = self::parse_template($template, $data);
        
        return wp_mail(
            $distributor['email'],
            $template['subject'],
            $content,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
    
    private static function get_email_template($type) {
        $templates = [
            'zone_assigned' => [
                'subject' => __('Nueva zona asignada', 'zoho-sync-zone-blocker'),
                'template' => 'emails/zone-assigned.php'
            ]
        ];
        
        return $templates[$type] ?? null;
    }
}