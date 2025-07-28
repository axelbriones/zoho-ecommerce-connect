<?php

class ZSDP_Registration_Handler {
    
    private $zoho_api;
    private $validator;
    
    public function __construct() {
        $this->zoho_api = ZohoSyncCore::api();
        
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_ajax_nopriv_zsdp_register_distributor', [$this, 'handle_registration']);
        add_action('template_redirect', [$this, 'check_registration_access']);
    }

    public function register_shortcodes() {
        add_shortcode('distributor_registration', [$this, 'render_registration_form']);
    }

    public function render_registration_form() {
        if (is_user_logged_in()) {
            return sprintf(
                '<p class="zsdp-notice">%s</p>',
                __('Ya has iniciado sesión.', 'zoho-distributor-portal')
            );
        }

        ob_start();
        include ZSDP_PLUGIN_DIR . 'templates/registration/registration-form.php';
        return ob_get_clean();
    }

    public function handle_registration() {
        check_ajax_referer('zsdp_registration', 'nonce');

        $data = $this->validate_registration_data($_POST);
        if (is_wp_error($data)) {
            wp_send_json_error([
                'message' => $data->get_error_message()
            ]);
        }

        try {
            // Crear usuario en WordPress
            $user_id = $this->create_wordpress_user($data);
            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }

            // Crear contacto en Zoho CRM
            $zoho_id = $this->create_zoho_contact($data, $user_id);
            if (!$zoho_id) {
                throw new Exception(__('Error al crear contacto en Zoho', 'zoho-distributor-portal'));
            }

            // Vincular IDs
            update_user_meta($user_id, 'zoho_contact_id', $zoho_id);

            // Notificar al administrador
            $this->notify_admin_new_distributor($user_id, $data);

            wp_send_json_success([
                'message' => __('Registro completado. En breve revisaremos tu solicitud.', 'zoho-distributor-portal'),
                'redirect' => wp_login_url()
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    private function validate_registration_data($data) {
        $required = ['business_name', 'tax_id', 'email', 'phone', 'address', 'postal_code'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error(
                    'required_field',
                    sprintf(__('El campo %s es obligatorio', 'zoho-distributor-portal'), $field)
                );
            }
        }

        // Validar email
        if (!is_email($data['email'])) {
            return new WP_Error('invalid_email', __('Email inválido', 'zoho-distributor-portal'));
        }

        // Validar que el email no exista
        if (email_exists($data['email'])) {
            return new WP_Error('email_exists', __('Este email ya está registrado', 'zoho-distributor-portal'));
        }

        return $data;
    }

    private function create_wordpress_user($data) {
        $userdata = [
            'user_login' => $data['email'],
            'user_email' => $data['email'],
            'user_pass' => wp_generate_password(),
            'role' => 'pending_distributor',
            'show_admin_bar_front' => false
        ];

        $user_id = wp_insert_user($userdata);
        if (!is_wp_error($user_id)) {
            // Guardar meta datos
            update_user_meta($user_id, 'business_name', $data['business_name']);
            update_user_meta($user_id, 'tax_id', $data['tax_id']);
            update_user_meta($user_id, 'phone', $data['phone']);
            update_user_meta($user_id, 'address', $data['address']);
            update_user_meta($user_id, 'postal_code', $data['postal_code']);
            update_user_meta($user_id, 'registration_status', 'pending');
        }

        return $user_id;
    }

    private function create_zoho_contact($data, $user_id) {
        $contact_data = [
            'First_Name' => $data['business_name'],
            'Email' => $data['email'],
            'Phone' => $data['phone'],
            'Tax_ID' => $data['tax_id'],
            'Mailing_Street' => $data['address'],
            'Mailing_Zip' => $data['postal_code'],
            'Account_Type' => 'Distributor',
            'Status' => 'Pending_Approval',
            'WordPress_ID' => $user_id
        ];

        $response = $this->zoho_api->post('crm', 'Contacts', $contact_data);
        return $response->data[0]->id ?? false;
    }

    private function notify_admin_new_distributor($user_id, $data) {
        $admin_email = get_option('admin_email');
        $subject = sprintf(
            __('[%s] Nueva solicitud de distribuidor', 'zoho-distributor-portal'),
            get_bloginfo('name')
        );

        $message = sprintf(
            __('Nueva solicitud de distribuidor recibida:

Empresa: %s
RFC/Tax ID: %s
Email: %s
Teléfono: %s
Dirección: %s
Código Postal: %s

Revisar solicitud: %s', 'zoho-distributor-portal'),
            $data['business_name'],
            $data['tax_id'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['postal_code'],
            admin_url('user-edit.php?user_id=' . $user_id)
        );

        wp_mail($admin_email, $subject, $message);
    }
}