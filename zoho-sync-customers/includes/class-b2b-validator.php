<?php
/**
 * B2B Validator Class
 *
 * Handles B2B customer validation and approval processes
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZohoSyncCustomers_B2BValidator class
 */
class ZohoSyncCustomers_B2BValidator {
    
    /**
     * Instance of this class
     *
     * @var ZohoSyncCustomers_B2BValidator
     */
    private static $instance = null;
    
    /**
     * Required B2B fields
     *
     * @var array
     */
    private $required_b2b_fields = array(
        'company_name',
        'tax_id',
        'business_type',
        'contact_person',
        'business_phone',
        'business_address'
    );
    
    /**
     * Get instance
     *
     * @return ZohoSyncCustomers_B2BValidator
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Registration validation
        add_filter('woocommerce_process_registration_errors', array($this, 'validate_b2b_registration'), 10, 4);
        add_action('woocommerce_created_customer', array($this, 'process_b2b_registration'), 10, 3);
        
        // Profile update validation
        add_action('personal_options_update', array($this, 'validate_b2b_profile_update'));
        add_action('edit_user_profile_update', array($this, 'validate_b2b_profile_update'));
        
        // Admin approval hooks
        add_action('wp_ajax_approve_b2b_customer', array($this, 'ajax_approve_b2b_customer'));
        add_action('wp_ajax_reject_b2b_customer', array($this, 'ajax_reject_b2b_customer'));
        add_action('wp_ajax_request_b2b_documents', array($this, 'ajax_request_b2b_documents'));
        
        // Document upload hooks
        add_action('wp_ajax_upload_b2b_document', array($this, 'ajax_upload_b2b_document'));
        add_action('wp_ajax_nopriv_upload_b2b_document', array($this, 'ajax_upload_b2b_document'));
        
        // Checkout validation
        add_action('woocommerce_checkout_process', array($this, 'validate_b2b_checkout'));
        add_filter('woocommerce_checkout_fields', array($this, 'add_b2b_checkout_fields'));
        
        // Account restrictions
        add_filter('woocommerce_customer_get_is_paying_customer', array($this, 'check_b2b_approval_status'), 10, 2);
        add_action('template_redirect', array($this, 'restrict_unapproved_b2b_access'));
    }
    
    /**
     * Validate B2B registration data
     *
     * @param WP_Error $errors Existing errors
     * @param string $username Username
     * @param string $password Password
     * @param string $email Email
     * @return WP_Error Modified errors
     */
    public function validate_b2b_registration($errors, $username, $password, $email) {
        // Check if this is a B2B registration
        if (!isset($_POST['register_as_b2b']) || $_POST['register_as_b2b'] !== 'yes') {
            return $errors;
        }
        
        // Validate required B2B fields
        foreach ($this->required_b2b_fields as $field) {
            if (empty($_POST[$field])) {
                $field_label = $this->get_field_label($field);
                $errors->add('b2b_field_required', sprintf(
                    __('El campo %s es requerido para registro B2B', 'zoho-sync-customers'),
                    $field_label
                ));
            }
        }
        
        // Validate tax ID format
        if (!empty($_POST['tax_id'])) {
            if (!$this->validate_tax_id($_POST['tax_id'])) {
                $errors->add('invalid_tax_id', __('El formato del NIT/RUT no es válido', 'zoho-sync-customers'));
            }
            
            // Check if tax ID already exists
            if ($this->tax_id_exists($_POST['tax_id'])) {
                $errors->add('tax_id_exists', __('Este NIT/RUT ya está registrado', 'zoho-sync-customers'));
            }
        }
        
        // Validate business email domain
        if (!empty($_POST['business_email'])) {
            if (!$this->validate_business_email($_POST['business_email'])) {
                $errors->add('invalid_business_email', __('Debe usar un email corporativo válido', 'zoho-sync-customers'));
            }
        }
        
        // Validate phone number
        if (!empty($_POST['business_phone'])) {
            if (!$this->validate_phone_number($_POST['business_phone'])) {
                $errors->add('invalid_phone', __('El formato del teléfono no es válido', 'zoho-sync-customers'));
            }
        }
        
        return $errors;
    }
    
    /**
     * Process B2B registration after user creation
     *
     * @param int $customer_id Customer ID
     * @param array $new_customer_data Customer data
     * @param string $password_generated Whether password was generated
     */
    public function process_b2b_registration($customer_id, $new_customer_data, $password_generated) {
        // Check if this is a B2B registration
        if (!isset($_POST['register_as_b2b']) || $_POST['register_as_b2b'] !== 'yes') {
            return;
        }
        
        // Store B2B data
        $this->store_b2b_data($customer_id, $_POST);
        
        // Set user role to B2B customer
        $user = get_user_by('id', $customer_id);
        if ($user) {
            $user->remove_role('customer');
            $user->add_role('b2b_customer');
        }
        
        // Set approval status
        $approval_required = get_option('zoho_customers_b2b_approval_required', 'yes');
        if ($approval_required === 'yes') {
            update_user_meta($customer_id, 'b2b_approval_status', 'pending');
            update_user_meta($customer_id, 'b2b_registration_date', current_time('mysql'));
            
            // Send notification to admin
            $this->notify_admin_b2b_registration($customer_id);
            
            // Send confirmation to customer
            $this->send_b2b_registration_confirmation($customer_id);
        } else {
            update_user_meta($customer_id, 'b2b_approval_status', 'approved');
            update_user_meta($customer_id, 'b2b_approved_date', current_time('mysql'));
        }
        
        // Log the registration
        ZohoSyncCore::log('info', sprintf(
            'Registro B2B procesado: Usuario %d - %s',
            $customer_id,
            $_POST['company_name'] ?? 'Sin nombre de empresa'
        ), 'customers');
    }
    
    /**
     * Store B2B customer data
     *
     * @param int $user_id User ID
     * @param array $data Form data
     */
    private function store_b2b_data($user_id, $data) {
        $b2b_fields = array(
            'company_name' => 'b2b_company_name',
            'tax_id' => 'b2b_tax_id',
            'business_type' => 'b2b_business_type',
            'contact_person' => 'b2b_contact_person',
            'business_phone' => 'b2b_business_phone',
            'business_email' => 'b2b_business_email',
            'business_address' => 'b2b_business_address',
            'business_city' => 'b2b_business_city',
            'business_state' => 'b2b_business_state',
            'business_country' => 'b2b_business_country',
            'business_postal_code' => 'b2b_business_postal_code',
            'annual_revenue' => 'b2b_annual_revenue',
            'employee_count' => 'b2b_employee_count',
            'industry' => 'b2b_industry',
            'website' => 'b2b_website',
            'additional_info' => 'b2b_additional_info'
        );
        
        foreach ($b2b_fields as $form_field => $meta_key) {
            if (!empty($data[$form_field])) {
                update_user_meta($user_id, $meta_key, sanitize_text_field($data[$form_field]));
            }
        }
        
        // Mark as B2B customer
        update_user_meta($user_id, 'customer_type', 'b2b');
    }
    
    /**
     * Validate B2B profile update
     *
     * @param int $user_id User ID
     */
    public function validate_b2b_profile_update($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('b2b_customer', $user->roles)) {
            return;
        }
        
        // Validate updated B2B fields
        $errors = array();
        
        if (!empty($_POST['b2b_tax_id'])) {
            if (!$this->validate_tax_id($_POST['b2b_tax_id'])) {
                $errors[] = __('El formato del NIT/RUT no es válido', 'zoho-sync-customers');
            }
            
            // Check if tax ID changed and if new one exists
            $current_tax_id = get_user_meta($user_id, 'b2b_tax_id', true);
            if ($_POST['b2b_tax_id'] !== $current_tax_id && $this->tax_id_exists($_POST['b2b_tax_id'])) {
                $errors[] = __('Este NIT/RUT ya está registrado', 'zoho-sync-customers');
            }
        }
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                add_action('admin_notices', function() use ($error) {
                    echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
                });
            }
        }
    }
    
    /**
     * Approve B2B customer
     *
     * @param int $user_id User ID
     * @param string $notes Approval notes
     * @return bool Success status
     */
    public function approve_b2b_customer($user_id, $notes = '') {
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('b2b_customer', $user->roles)) {
            return false;
        }
        
        // Update approval status
        update_user_meta($user_id, 'b2b_approval_status', 'approved');
        update_user_meta($user_id, 'b2b_approved_date', current_time('mysql'));
        update_user_meta($user_id, 'b2b_approval_notes', $notes);
        
        // Send approval notification
        $this->send_b2b_approval_notification($user_id);
        
        // Log the approval
        ZohoSyncCore::log('info', sprintf(
            'Cliente B2B aprobado: Usuario %d - %s',
            $user_id,
            $user->user_email
        ), 'customers');
        
        // Trigger action for other plugins
        do_action('zoho_customers_b2b_approved', $user_id, $notes);
        
        return true;
    }
    
    /**
     * Reject B2B customer
     *
     * @param int $user_id User ID
     * @param string $reason Rejection reason
     * @return bool Success status
     */
    public function reject_b2b_customer($user_id, $reason = '') {
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('b2b_customer', $user->roles)) {
            return false;
        }
        
        // Update approval status
        update_user_meta($user_id, 'b2b_approval_status', 'rejected');
        update_user_meta($user_id, 'b2b_rejected_date', current_time('mysql'));
        update_user_meta($user_id, 'b2b_rejection_reason', $reason);
        
        // Send rejection notification
        $this->send_b2b_rejection_notification($user_id, $reason);
        
        // Log the rejection
        ZohoSyncCore::log('info', sprintf(
            'Cliente B2B rechazado: Usuario %d - %s - Razón: %s',
            $user_id,
            $user->user_email,
            $reason
        ), 'customers');
        
        // Trigger action for other plugins
        do_action('zoho_customers_b2b_rejected', $user_id, $reason);
        
        return true;
    }
    
    /**
     * Get pending B2B customers
     *
     * @return array Pending customers
     */
    public function get_pending_b2b_customers() {
        $users = get_users(array(
            'role' => 'b2b_customer',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'b2b_approval_status',
                    'value' => 'pending',
                    'compare' => '='
                ),
                array(
                    'key' => 'b2b_approval_status',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        $pending_customers = array();
        
        foreach ($users as $user) {
            $pending_customers[] = array(
                'user_id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'company_name' => get_user_meta($user->ID, 'b2b_company_name', true),
                'tax_id' => get_user_meta($user->ID, 'b2b_tax_id', true),
                'registration_date' => get_user_meta($user->ID, 'b2b_registration_date', true),
                'business_type' => get_user_meta($user->ID, 'b2b_business_type', true),
                'documents' => $this->get_user_documents($user->ID)
            );
        }
        
        return $pending_customers;
    }
    
    /**
     * Validate tax ID format
     *
     * @param string $tax_id Tax ID
     * @return bool Valid status
     */
    private function validate_tax_id($tax_id) {
        // Remove any non-alphanumeric characters
        $clean_tax_id = preg_replace('/[^0-9A-Za-z]/', '', $tax_id);
        
        // Colombian NIT validation (8-10 digits + verification digit)
        if (preg_match('/^\d{8,10}-?\d$/', $tax_id)) {
            return $this->validate_colombian_nit($clean_tax_id);
        }
        
        // Generic validation for other countries (6-15 alphanumeric characters)
        return strlen($clean_tax_id) >= 6 && strlen($clean_tax_id) <= 15;
    }
    
    /**
     * Validate Colombian NIT
     *
     * @param string $nit NIT number
     * @return bool Valid status
     */
    private function validate_colombian_nit($nit) {
        $nit = preg_replace('/[^0-9]/', '', $nit);
        
        if (strlen($nit) < 9 || strlen($nit) > 11) {
            return false;
        }
        
        $verification_digit = substr($nit, -1);
        $nit_number = substr($nit, 0, -1);
        
        $factors = array(3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71);
        $sum = 0;
        
        for ($i = 0; $i < strlen($nit_number); $i++) {
            $sum += intval($nit_number[$i]) * $factors[strlen($nit_number) - 1 - $i];
        }
        
        $remainder = $sum % 11;
        $calculated_digit = $remainder < 2 ? $remainder : 11 - $remainder;
        
        return intval($verification_digit) === $calculated_digit;
    }
    
    /**
     * Check if tax ID already exists
     *
     * @param string $tax_id Tax ID
     * @return bool Exists status
     */
    private function tax_id_exists($tax_id) {
        $users = get_users(array(
            'meta_key' => 'b2b_tax_id',
            'meta_value' => $tax_id,
            'count_total' => true
        ));
        
        return count($users) > 0;
    }
    
    /**
     * Validate business email
     *
     * @param string $email Email address
     * @return bool Valid status
     */
    private function validate_business_email($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Check against blacklisted domains (free email providers)
        $blacklisted_domains = array(
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'live.com', 'aol.com', 'icloud.com', 'protonmail.com'
        );
        
        $domain = substr(strrchr($email, '@'), 1);
        
        return !in_array(strtolower($domain), $blacklisted_domains);
    }
    
    /**
     * Validate phone number
     *
     * @param string $phone Phone number
     * @return bool Valid status
     */
    private function validate_phone_number($phone) {
        // Remove all non-numeric characters
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if it's a valid length (7-15 digits)
        return strlen($clean_phone) >= 7 && strlen($clean_phone) <= 15;
    }
    
    /**
     * Add B2B checkout fields
     *
     * @param array $fields Checkout fields
     * @return array Modified fields
     */
    public function add_b2b_checkout_fields($fields) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $fields;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('b2b_customer', $user->roles)) {
            return $fields;
        }
        
        // Add B2B specific fields
        $fields['billing']['billing_tax_id'] = array(
            'label' => __('NIT/RUT', 'zoho-sync-customers'),
            'required' => true,
            'class' => array('form-row-wide'),
            'priority' => 25
        );
        
        $fields['billing']['billing_purchase_order'] = array(
            'label' => __('Número de Orden de Compra', 'zoho-sync-customers'),
            'required' => false,
            'class' => array('form-row-wide'),
            'priority' => 26
        );
        
        return $fields;
    }
    
    /**
     * Validate B2B checkout
     */
    public function validate_b2b_checkout() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('b2b_customer', $user->roles)) {
            return;
        }
        
        // Check if B2B customer is approved
        $approval_status = get_user_meta($user_id, 'b2b_approval_status', true);
        if ($approval_status !== 'approved') {
            wc_add_notice(__('Su cuenta B2B debe ser aprobada antes de realizar pedidos', 'zoho-sync-customers'), 'error');
        }
        
        // Validate tax ID if provided
        if (!empty($_POST['billing_tax_id'])) {
            if (!$this->validate_tax_id($_POST['billing_tax_id'])) {
                wc_add_notice(__('El formato del NIT/RUT no es válido', 'zoho-sync-customers'), 'error');
            }
        }
    }
    
    /**
     * Check B2B approval status for paying customer
     *
     * @param bool $is_paying_customer Current status
     * @param WC_Customer $customer Customer object
     * @return bool Modified status
     */
    public function check_b2b_approval_status($is_paying_customer, $customer) {
        $user = get_user_by('id', $customer->get_id());
        if (!$user || !in_array('b2b_customer', $user->roles)) {
            return $is_paying_customer;
        }
        
        $approval_status = get_user_meta($customer->get_id(), 'b2b_approval_status', true);
        
        // Only approved B2B customers can be paying customers
        return $approval_status === 'approved';
    }
    
    /**
     * Restrict access for unapproved B2B customers
     */
    public function restrict_unapproved_b2b_access() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        if (!$user || !in_array('b2b_customer', $user->roles)) {
            return;
        }
        
        $approval_status = get_user_meta($user_id, 'b2b_approval_status', true);
        
        if ($approval_status !== 'approved') {
            // Restrict access to shop and checkout pages
            if (is_shop() || is_product_category() || is_product_tag() || is_product() || is_checkout()) {
                wp_redirect(wc_get_page_permalink('myaccount'));
                exit;
            }
        }
    }
    
    /**
     * AJAX handler for approving B2B customer
     */
    public function ajax_approve_b2b_customer() {
        check_ajax_referer('zoho_customers_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-customers'));
        }
        
        $user_id = intval($_POST['user_id']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        $result = $this->approve_b2b_customer($user_id, $notes);
        
        wp_send_json(array(
            'success' => $result,
            'message' => $result ? 
                __('Cliente B2B aprobado correctamente', 'zoho-sync-customers') :
                __('Error aprobando cliente B2B', 'zoho-sync-customers')
        ));
    }
    
    /**
     * AJAX handler for rejecting B2B customer
     */
    public function ajax_reject_b2b_customer() {
        check_ajax_referer('zoho_customers_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-customers'));
        }
        
        $user_id = intval($_POST['user_id']);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        $result = $this->reject_b2b_customer($user_id, $reason);
        
        wp_send_json(array(
            'success' => $result,
            'message' => $result ? 
                __('Cliente B2B rechazado', 'zoho-sync-customers') :
                __('Error rechazando cliente B2B', 'zoho-sync-customers')
        ));
    }
    
    /**
     * Get field label for display
     *
     * @param string $field_key Field key
     * @return string Field label
     */
    private function get_field_label($field_key) {
        $labels = array(
            'company_name' => __('Nombre de la Empresa', 'zoho-sync-customers'),
            'tax_id' => __('NIT/RUT', 'zoho-sync-customers'),
            'business_type' => __('Tipo de Negocio', 'zoho-sync-customers'),
            'contact_person' => __('Persona de Contacto', 'zoho-sync-customers'),
            'business_phone' => __('Teléfono Empresarial', 'zoho-sync-customers'),
            'business_address' => __('Dirección Empresarial', 'zoho-sync-customers')
        );
        
        return isset($labels[$field_key]) ? $labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
    }
    
    /**
     * Send B2B registration confirmation
     *
     * @param int $user_id User ID
     */
    private function send_b2b_registration_confirmation($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $subject = __('Registro B2B Recibido - Pendiente de Aprobación', 'zoho-sync-customers');
        $message = sprintf(
            __('Hola %s,\n\nHemos recibido tu solicitud de registro como cliente B2B. Tu cuenta está pendiente de aprobación por nuestro equipo.\n\nTe notificaremos por email una vez que tu cuenta sea aprobada.\n\nGracias por tu interés en nuestros servicios B2B.\n\nSaludos,\nEquipo de Ventas', 'zoho-sync-customers'),
            $user->display_name
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send B2B approval notification
     *
     * @param int $user_id User ID
     */
    private function send_b2b_approval_notification($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $subject = __('Cuenta B2B Aprobada', 'zoho-sync-customers');
        $message = sprintf(
            __('Hola %s,\n\n¡Excelentes noticias! Tu cuenta B2B ha sido aprobada.\n\nYa puedes acceder a:\n- Precios especiales B2B\n- Realizar pedidos empresariales\n- Acceder a tu portal de cliente\n\nInicia sesión en tu cuenta para comenzar.\n\nSaludos,\nEquipo de Ventas', 'zoho-sync-customers'),
            $user->display_name
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send B2B rejection notification
     *
     * @param int $user_id User ID
     * @param string $reason Rejection reason
     */
    private function send_b2b_rejection_notification($user_id, $reason) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $subject = __('Solicitud B2B No Aprobada', 'zoho-sync-customers');
        $message = sprintf(
            __('Hola %s,\n\nLamentamos informarte que tu solicitud de cuenta B2B no ha sido aprobada en este momento.\n\nRazón: %s\n\nSi tienes preguntas o deseas proporcionar información adicional, no dudes en contactarnos.\n\nSaludos,\nEquipo de Ventas', 'zoho-sync-customers'),
            $user->display_name,
            $reason
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Notify admin of B2B registration
     *
     * @param int $user_id User ID
     */
    private function notify_admin_b2b_registration($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $company_name = get_user_meta($user_id, 'b2b_company_name', true);
        $tax_id = get_user_meta($user_id, 'b2b_tax_id', true);
        
        $admin_email = get_option('admin_email');
        $subject = __('Nueva Solicitud de Cliente B2B', 'zoho-sync-customers');
        $message = sprintf(
            __('Se ha registrado una nueva solicitud de cliente B2B:\n\nNombre: %s\nEmail: %s\nEmpresa: %s\nNIT/RUT: %s\nFecha: %s\n\nRevisa la solicitud en el panel de administración para aprobar o rechazar.', 'zoho-sync-customers'),
            $user->display_name,
            $user->user_email,
            $company_name,
            $tax_id,
            current_time('Y-m-d H:i:s')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get user documents
     *
     * @param int $user_id User ID
     * @return array User documents
     */
    private function get_user_documents($user_id) {
        $documents = get_user_meta($user_id, 'b2b_documents', true);
        return is_array($documents) ? $documents : array();
    }
    
    /**
     * Get B2B validation statistics
     *
     * @return array Statistics
     */
    public function get_b2b_stats() {
        $pending_customers = $this->get_pending_b2b_customers();
        
        $stats = array(
            'total_b2b_customers' => $this->get_total_b2b_customers(),
            'pending_approval' => count($pending_customers),
            'approved_customers' => $this->get_approved_b2b_customers_count(),
            'rejected_customers' => $this->get_rejected_b2b_customers_count(),
            'approval_rate' => $this->calculate_approval_rate()
        );
        
        return $stats;
    }
    
    /**
     * Get total B2B customers count
     *
     * @return int Total count
     */
    private function get_total_b2b_customers() {
        $users = get_users(array(
            'role' => 'b2b_customer',
            'count_total' => true,
            'fields' => 'ID'
        ));
        
        return is_array($users) ? count($users) : 0;
    }
    
    /**
     * Get approved B2B customers count
     *
     * @return int Approved count
     */
    private function get_approved_b2b_customers_count() {
        $users = get_users(array(
            'role' => 'b2b_customer',
            'meta_key' => 'b2b_approval_status',
            'meta_value' => 'approved',
            'count_total' => true,
            'fields' => 'ID'
        ));
        
        return is_array($users) ? count($users) : 0;
    }
    
    /**
     * Get rejected B2B customers count
     *
     * @return int Rejected count
     */
    private function get_rejected_b2b_customers_count() {
        $users = get_users(array(
            'role' => 'b2b_customer',
            'meta_key' => 'b2b_approval_status',
            'meta_value' => 'rejected',
            'count_total' => true,
            'fields' => 'ID'
        ));
        
        return is_array($users) ? count($users) : 0;
    }
    
    /**
     * Calculate approval rate
     *
     * @return float Approval rate percentage
     */
    private function calculate_approval_rate() {
        $total = $this->get_total_b2b_customers();
        $approved = $this->get_approved_b2b_customers_count();
        
        if ($total === 0) {
            return 0;
        }
        
        return round(($approved / $total) * 100, 2);
    }
    
    /**
     * Export B2B customers data
     *
     * @param array $args Export arguments
     * @return array Export data
     */
    public function export_b2b_data($args = array()) {
        $defaults = array(
            'status' => 'all', // all, pending, approved, rejected
            'format' => 'csv',
            'include_documents' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $meta_query = array();
        
        if ($args['status'] !== 'all') {
            $meta_query[] = array(
                'key' => 'b2b_approval_status',
                'value' => $args['status'],
                'compare' => '='
            );
        }
        
        $users = get_users(array(
            'role' => 'b2b_customer',
            'meta_query' => $meta_query
        ));
        
        $data = array();
        
        foreach ($users as $user) {
            $user_data = array(
                'user_id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'registration_date' => $user->user_registered,
                'company_name' => get_user_meta($user->ID, 'b2b_company_name', true),
                'tax_id' => get_user_meta($user->ID, 'b2b_tax_id', true),
                'business_type' => get_user_meta($user->ID, 'b2b_business_type', true),
                'approval_status' => get_user_meta($user->ID, 'b2b_approval_status', true),
                'approved_date' => get_user_meta($user->ID, 'b2b_approved_date', true),
                'rejected_date' => get_user_meta($user->ID, 'b2b_rejected_date', true),
                'rejection_reason' => get_user_meta($user->ID, 'b2b_rejection_reason', true)
            );
            
            if ($args['include_documents']) {
                $user_data['documents'] = $this->get_user_documents($user->ID);
            }
            
            $data[] = $user_data;
        }
        
        return $data;
    }
}
     