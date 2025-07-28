<?php

class ZSDP_Support_System {
    
    private $settings;
    private $whatsapp_integration;
    
    public function __construct() {
        $this->settings = get_option('zsdp_support_settings', [
            'enable_whatsapp' => true,
            'whatsapp_number' => '',
            'ticket_email' => get_option('admin_email'),
            'auto_response' => true
        ]);

        if ($this->settings['enable_whatsapp']) {
            $this->whatsapp_integration = new ZSDP_WhatsApp_Integration();
        }

        add_action('init', [$this, 'register_support_endpoints']);
        add_action('wp_ajax_zsdp_submit_ticket', [$this, 'handle_ticket_submission']);
        add_action('wp_ajax_zsdp_get_ticket_status', [$this, 'get_ticket_status']);
    }

    public function register_support_endpoints() {
        add_rewrite_endpoint('support', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('my-tickets', EP_ROOT | EP_PAGES);
    }

    public function render_support_dashboard() {
        if (!$this->can_access_support()) {
            return $this->render_access_denied();
        }

        $user_id = get_current_user_id();
        $tickets = $this->get_user_tickets($user_id);
        
        ob_start();
        include ZSDP_PLUGIN_DIR . 'templates/support/dashboard.php';
        return ob_get_clean();
    }

    public function handle_ticket_submission() {
        check_ajax_referer('zsdp_support_nonce', 'nonce');

        if (!$this->can_access_support()) {
            wp_send_json_error(['message' => __('Acceso denegado', 'zoho-distributor-portal')]);
        }

        $ticket_data = $this->validate_ticket_data($_POST);
        if (is_wp_error($ticket_data)) {
            wp_send_json_error(['message' => $ticket_data->get_error_message()]);
        }

        try {
            $ticket_id = $this->create_ticket($ticket_data);
            
            if ($ticket_id) {
                // Notificar al equipo de soporte
                $this->notify_support_team($ticket_id, $ticket_data);
                
                // Respuesta automática si está habilitada
                if ($this->settings['auto_response']) {
                    $this->send_auto_response($ticket_data['email']);
                }

                wp_send_json_success([
                    'message' => __('Ticket creado correctamente', 'zoho-distributor-portal'),
                    'ticket_id' => $ticket_id
                ]);
            }

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Error al crear el ticket', 'zoho-distributor-portal')
            ]);
        }
    }

    private function create_ticket($data) {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'zsdp_support_tickets',
            [
                'user_id' => get_current_user_id(),
                'subject' => $data['subject'],
                'message' => $data['message'],
                'priority' => $data['priority'],
                'status' => 'open',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    private function notify_support_team($ticket_id, $data) {
        $subject = sprintf(
            __('[Ticket #%d] Nuevo ticket de soporte - %s', 'zoho-distributor-portal'),
            $ticket_id,
            $data['subject']
        );

        $message = sprintf(
            __('Se ha recibido un nuevo ticket de soporte:

ID: %d
Distribuidor: %s
Prioridad: %s
Asunto: %s

Mensaje:
%s

Ver ticket: %s', 'zoho-distributor-portal'),
            $ticket_id,
            wp_get_current_user()->display_name,
            $data['priority'],
            $data['subject'],
            $data['message'],
            admin_url('admin.php?page=zsdp-support&ticket=' . $ticket_id)
        );

        wp_mail($this->settings['ticket_email'], $subject, $message);
    }

    private function send_auto_response($email) {
        $subject = __('Hemos recibido tu solicitud de soporte', 'zoho-distributor-portal');
        
        $message = __('Gracias por contactar a nuestro equipo de soporte. 
        
Hemos recibido tu solicitud y la atenderemos lo antes posible.

Este es un mensaje automático, por favor no respondas a este correo.

Saludos cordiales,
Equipo de Soporte', 'zoho-distributor-portal');

        wp_mail($email, $subject, $message);
    }

    public function get_user_tickets($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zsdp_support_tickets 
            WHERE user_id = %d 
            ORDER BY created_at DESC",
            $user_id
        ));
    }

    private function validate_ticket_data($data) {
        $required = ['subject', 'message', 'priority'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error(
                    'missing_field',
                    sprintf(__('El campo %s es obligatorio', 'zoho-distributor-portal'), $field)
                );
            }
        }

        return [
            'subject' => sanitize_text_field($data['subject']),
            'message' => wp_kses_post($data['message']),
            'priority' => sanitize_text_field($data['priority']),
            'email' => wp_get_current_user()->user_email
        ];
    }

    private function can_access_support() {
        return current_user_can('access_distributor_portal') && 
               current_user_can('create_support_tickets');
    }

    private function render_access_denied() {
        ob_start();
        include ZSDP_PLUGIN_DIR . 'templates/support/access-denied.php';
        return ob_get_clean();
    }
}