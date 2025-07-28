<?php
/**
 * Customer Mapper Class
 *
 * Handles data mapping between Zoho CRM and WooCommerce customers
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZohoSyncCustomers_CustomerMapper class
 */
class ZohoSyncCustomers_CustomerMapper {
    
    /**
     * Instance of this class
     *
     * @var ZohoSyncCustomers_CustomerMapper
     */
    private static $instance = null;
    
    /**
     * Field mapping configuration
     *
     * @var array
     */
    private $field_mapping;
    
    /**
     * Get instance
     *
     * @return ZohoSyncCustomers_CustomerMapper
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
        $this->init_field_mapping();
    }
    
    /**
     * Initialize field mapping configuration
     */
    private function init_field_mapping() {
        $this->field_mapping = array(
            // Basic fields
            'first_name' => array(
                'zoho_field' => 'First_Name',
                'wp_field' => 'first_name',
                'required' => false,
                'type' => 'string'
            ),
            'last_name' => array(
                'zoho_field' => 'Last_Name',
                'wp_field' => 'last_name',
                'required' => false,
                'type' => 'string'
            ),
            'email' => array(
                'zoho_field' => 'Email',
                'wp_field' => 'user_email',
                'required' => true,
                'type' => 'email'
            ),
            'phone' => array(
                'zoho_field' => 'Phone',
                'wp_field' => 'billing_phone',
                'required' => false,
                'type' => 'string'
            ),
            
            // Address fields
            'billing_address_1' => array(
                'zoho_field' => 'Mailing_Street',
                'wp_field' => 'billing_address_1',
                'required' => false,
                'type' => 'string'
            ),
            'billing_city' => array(
                'zoho_field' => 'Mailing_City',
                'wp_field' => 'billing_city',
                'required' => false,
                'type' => 'string'
            ),
            'billing_state' => array(
                'zoho_field' => 'Mailing_State',
                'wp_field' => 'billing_state',
                'required' => false,
                'type' => 'string'
            ),
            'billing_postcode' => array(
                'zoho_field' => 'Mailing_Zip',
                'wp_field' => 'billing_postcode',
                'required' => false,
                'type' => 'string'
            ),
            'billing_country' => array(
                'zoho_field' => 'Mailing_Country',
                'wp_field' => 'billing_country',
                'required' => false,
                'type' => 'string'
            ),
            
            // Company fields
            'company' => array(
                'zoho_field' => 'Account_Name',
                'wp_field' => 'billing_company',
                'required' => false,
                'type' => 'string'
            ),
            
            // Custom fields
            'distributor_level' => array(
                'zoho_field' => 'Distributor_Level',
                'wp_field' => 'distributor_level',
                'required' => false,
                'type' => 'string'
            ),
            'customer_type' => array(
                'zoho_field' => 'Customer_Type',
                'wp_field' => 'customer_type',
                'required' => false,
                'type' => 'string'
            ),
            'tax_id' => array(
                'zoho_field' => 'Tax_ID',
                'wp_field' => 'tax_id',
                'required' => false,
                'type' => 'string'
            ),
            'assigned_zones' => array(
                'zoho_field' => 'Assigned_Zones',
                'wp_field' => 'assigned_zones',
                'required' => false,
                'type' => 'array'
            )
        );
        
        // Allow customization of field mapping
        $this->field_mapping = apply_filters('zoho_customers_field_mapping', $this->field_mapping);
    }
    
    /**
     * Map Zoho customer data to WordPress user data
     *
     * @param array $zoho_customer Zoho customer data
     * @return array WordPress user data
     */
    public function map_zoho_to_wordpress($zoho_customer) {
        $user_data = array();
        
        // Map basic user fields
        foreach ($this->field_mapping as $field_key => $mapping) {
            if (isset($zoho_customer[$mapping['zoho_field']])) {
                $value = $zoho_customer[$mapping['zoho_field']];
                $value = $this->sanitize_field_value($value, $mapping['type']);
                
                // Handle special WordPress fields
                switch ($mapping['wp_field']) {
                    case 'user_email':
                        $user_data['user_email'] = $value;
                        break;
                    case 'first_name':
                        $user_data['first_name'] = $value;
                        break;
                    case 'last_name':
                        $user_data['last_name'] = $value;
                        break;
                    default:
                        // Store as meta data
                        $user_data['meta_input'][$mapping['wp_field']] = $value;
                        break;
                }
            }
        }
        
        // Generate username if not provided
        if (empty($user_data['user_login']) && !empty($user_data['user_email'])) {
            $user_data['user_login'] = $this->generate_username($user_data['user_email']);
        }
        
        // Set display name
        $display_name = '';
        if (!empty($user_data['first_name'])) {
            $display_name = $user_data['first_name'];
        }
        if (!empty($user_data['last_name'])) {
            $display_name .= (!empty($display_name) ? ' ' : '') . $user_data['last_name'];
        }
        if (empty($display_name) && !empty($user_data['user_email'])) {
            $display_name = $user_data['user_email'];
        }
        $user_data['display_name'] = $display_name;
        
        // Set default role
        $user_data['role'] = $this->determine_user_role($zoho_customer);
        
        // Generate random password for new users
        if (!isset($user_data['user_pass'])) {
            $user_data['user_pass'] = wp_generate_password(12, false);
        }
        
        return apply_filters('zoho_customers_mapped_user_data', $user_data, $zoho_customer);
    }
    
    /**
     * Map WordPress user data to Zoho customer data
     *
     * @param WP_User $user WordPress user object
     * @return array Zoho customer data
     */
    public function map_wordpress_to_zoho($user) {
        $zoho_data = array();
        
        // Map basic fields
        foreach ($this->field_mapping as $field_key => $mapping) {
            $value = null;
            
            // Get value from user object or meta
            switch ($mapping['wp_field']) {
                case 'user_email':
                    $value = $user->user_email;
                    break;
                case 'first_name':
                    $value = $user->first_name;
                    break;
                case 'last_name':
                    $value = $user->last_name;
                    break;
                default:
                    $value = get_user_meta($user->ID, $mapping['wp_field'], true);
                    break;
            }
            
            // Add to Zoho data if value exists
            if (!empty($value)) {
                $zoho_data[$mapping['zoho_field']] = $this->format_for_zoho($value, $mapping['type']);
            }
        }
        
        // Add WooCommerce customer data if available
        if (class_exists('WC_Customer')) {
            $customer = new WC_Customer($user->ID);
            $zoho_data = $this->add_woocommerce_data($zoho_data, $customer);
        }
        
        return apply_filters('zoho_customers_mapped_zoho_data', $zoho_data, $user);
    }
    
    /**
     * Add WooCommerce customer data to Zoho mapping
     *
     * @param array $zoho_data Existing Zoho data
     * @param WC_Customer $customer WooCommerce customer
     * @return array Updated Zoho data
     */
    private function add_woocommerce_data($zoho_data, $customer) {
        // Billing address
        if ($customer->get_billing_address_1()) {
            $zoho_data['Mailing_Street'] = $customer->get_billing_address_1();
            if ($customer->get_billing_address_2()) {
                $zoho_data['Mailing_Street'] .= ', ' . $customer->get_billing_address_2();
            }
        }
        
        if ($customer->get_billing_city()) {
            $zoho_data['Mailing_City'] = $customer->get_billing_city();
        }
        
        if ($customer->get_billing_state()) {
            $zoho_data['Mailing_State'] = $customer->get_billing_state();
        }
        
        if ($customer->get_billing_postcode()) {
            $zoho_data['Mailing_Zip'] = $customer->get_billing_postcode();
        }
        
        if ($customer->get_billing_country()) {
            $zoho_data['Mailing_Country'] = $customer->get_billing_country();
        }
        
        if ($customer->get_billing_phone()) {
            $zoho_data['Phone'] = $customer->get_billing_phone();
        }
        
        if ($customer->get_billing_company()) {
            $zoho_data['Account_Name'] = $customer->get_billing_company();
        }
        
        return $zoho_data;
    }
    
    /**
     * Sanitize field value based on type
     *
     * @param mixed $value Field value
     * @param string $type Field type
     * @return mixed Sanitized value
     */
    private function sanitize_field_value($value, $type) {
        switch ($type) {
            case 'email':
                return sanitize_email($value);
            case 'string':
                return sanitize_text_field($value);
            case 'array':
                return is_array($value) ? $value : explode(',', $value);
            case 'int':
                return intval($value);
            case 'float':
                return floatval($value);
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Format value for Zoho API
     *
     * @param mixed $value Field value
     * @param string $type Field type
     * @return mixed Formatted value
     */
    private function format_for_zoho($value, $type) {
        switch ($type) {
            case 'array':
                return is_array($value) ? implode(',', $value) : $value;
            case 'int':
                return intval($value);
            case 'float':
                return floatval($value);
            default:
                return strval($value);
        }
    }
    
    /**
     * Generate unique username from email
     *
     * @param string $email Email address
     * @return string Unique username
     */
    private function generate_username($email) {
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        
        // Ensure username is unique
        $original_username = $username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Determine user role based on Zoho customer data
     *
     * @param array $zoho_customer Zoho customer data
     * @return string User role
     */
    private function determine_user_role($zoho_customer) {
        // Check if it's a distributor
        if (!empty($zoho_customer['Distributor_Level']) || 
            !empty($zoho_customer['Is_Distributor']) ||
            !empty($zoho_customer['Assigned_Zones'])) {
            return 'distributor';
        }
        
        // Check if it's B2B customer
        if (!empty($zoho_customer['Customer_Type']) && 
            strtolower($zoho_customer['Customer_Type']) === 'b2b') {
            return 'b2b_customer';
        }
        
        // Check for business indicators
        if (!empty($zoho_customer['Account_Name']) || 
            !empty($zoho_customer['Company']) ||
            !empty($zoho_customer['Tax_ID'])) {
            return 'b2b_customer';
        }
        
        // Default to regular customer
        return get_option('zoho_customers_default_role', 'customer');
    }
    
    /**
     * Validate required fields
     *
     * @param array $data Data to validate
     * @param string $direction Mapping direction ('zoho_to_wp' or 'wp_to_zoho')
     * @return array Validation result
     */
    public function validate_required_fields($data, $direction = 'zoho_to_wp') {
        $errors = array();
        
        foreach ($this->field_mapping as $field_key => $mapping) {
            if ($mapping['required']) {
                $field_name = ($direction === 'zoho_to_wp') ? $mapping['zoho_field'] : $mapping['wp_field'];
                
                if (empty($data[$field_name])) {
                    $errors[] = sprintf(
                        __('Campo requerido faltante: %s', 'zoho-sync-customers'),
                        $field_name
                    );
                }
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Get field mapping configuration
     *
     * @return array Field mapping
     */
    public function get_field_mapping() {
        return $this->field_mapping;
    }
    
    /**
     * Update field mapping configuration
     *
     * @param array $mapping New mapping configuration
     */
    public function update_field_mapping($mapping) {
        $this->field_mapping = $mapping;
        update_option('zoho_customers_field_mapping', $mapping);
    }
    
    /**
     * Reset field mapping to defaults
     */
    public function reset_field_mapping() {
        delete_option('zoho_customers_field_mapping');
        $this->init_field_mapping();
    }
    
    /**
     * Map custom fields
     *
     * @param array $zoho_customer Zoho customer data
     * @param int $user_id WordPress user ID
     */
    public function map_custom_fields($zoho_customer, $user_id) {
        $custom_fields = get_option('zoho_customers_custom_fields', array());
        
        foreach ($custom_fields as $custom_field) {
            if (isset($zoho_customer[$custom_field['zoho_field']])) {
                $value = $zoho_customer[$custom_field['zoho_field']];
                $value = $this->sanitize_field_value($value, $custom_field['type']);
                
                update_user_meta($user_id, $custom_field['wp_field'], $value);
            }
        }
    }
    
    /**
     * Get mapped field value
     *
     * @param array $data Source data
     * @param string $field_key Field key from mapping
     * @param string $direction Mapping direction
     * @return mixed Field value
     */
    public function get_mapped_field_value($data, $field_key, $direction = 'zoho_to_wp') {
        if (!isset($this->field_mapping[$field_key])) {
            return null;
        }
        
        $mapping = $this->field_mapping[$field_key];
        $field_name = ($direction === 'zoho_to_wp') ? $mapping['zoho_field'] : $mapping['wp_field'];
        
        return isset($data[$field_name]) ? $data[$field_name] : null;
    }
    
    /**
     * Transform data for specific regions
     *
     * @param array $data Data to transform
     * @param string $region Target region
     * @return array Transformed data
     */
    public function transform_for_region($data, $region = 'default') {
        switch ($region) {
            case 'colombia':
                return $this->transform_for_colombia($data);
            case 'mexico':
                return $this->transform_for_mexico($data);
            default:
                return $data;
        }
    }
    
    /**
     * Transform data for Colombia
     *
     * @param array $data Data to transform
     * @return array Transformed data
     */
    private function transform_for_colombia($data) {
        // Colombian specific transformations
        if (isset($data['Mailing_Country']) && empty($data['Mailing_Country'])) {
            $data['Mailing_Country'] = 'CO';
        }
        
        // Format Colombian phone numbers
        if (isset($data['Phone'])) {
            $data['Phone'] = $this->format_colombian_phone($data['Phone']);
        }
        
        return $data;
    }
    
    /**
     * Transform data for Mexico
     *
     * @param array $data Data to transform
     * @return array Transformed data
     */
    private function transform_for_mexico($data) {
        // Mexican specific transformations
        if (isset($data['Mailing_Country']) && empty($data['Mailing_Country'])) {
            $data['Mailing_Country'] = 'MX';
        }
        
        return $data;
    }
    
    /**
     * Format Colombian phone number
     *
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    private function format_colombian_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '3') {
            $phone = '57' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Get field mapping for admin interface
     *
     * @return array Formatted field mapping for admin
     */
    public function get_admin_field_mapping() {
        $admin_mapping = array();
        
        foreach ($this->field_mapping as $field_key => $mapping) {
            $admin_mapping[] = array(
                'key' => $field_key,
                'zoho_field' => $mapping['zoho_field'],
                'wp_field' => $mapping['wp_field'],
                'required' => $mapping['required'],
                'type' => $mapping['type'],
                'label' => $this->get_field_label($field_key)
            );
        }
        
        return $admin_mapping;
    }
    
    /**
     * Get human-readable field label
     *
     * @param string $field_key Field key
     * @return string Field label
     */
    private function get_field_label($field_key) {
        $labels = array(
            'first_name' => __('Nombre', 'zoho-sync-customers'),
            'last_name' => __('Apellido', 'zoho-sync-customers'),
            'email' => __('Email', 'zoho_sync_customers'),
            'phone' => __('Teléfono', 'zoho-sync-customers'),
            'billing_address_1' => __('Dirección', 'zoho-sync-customers'),
            'billing_city' => __('Ciudad', 'zoho-sync-customers'),
            'billing_state' => __('Estado/Provincia', 'zoho-sync-customers'),
            'billing_postcode' => __('Código Postal', 'zoho-sync-customers'),
            'billing_country' => __('País', 'zoho-sync-customers'),
            'company' => __('Empresa', 'zoho-sync-customers'),
            'distributor_level' => __('Nivel Distribuidor', 'zoho-sync-customers'),
            'customer_type' => __('Tipo de Cliente', 'zoho-sync-customers'),
            'tax_id' => __('NIT/RUT', 'zoho-sync-customers'),
            'assigned_zones' => __('Zonas Asignadas', 'zoho-sync-customers')
        );
        
        return isset($labels[$field_key]) ? $labels[$field_key] : ucfirst(str_replace('_', ' ', $field_key));
    }
}