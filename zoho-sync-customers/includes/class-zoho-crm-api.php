<?php
/**
 * Zoho CRM API Class
 *
 * Handles Zoho CRM API operations specific to customers
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZohoSyncCustomers_ZohoCrmApi class
 */
class ZohoSyncCustomers_ZohoCrmApi {
    
    /**
     * Instance of this class
     *
     * @var ZohoSyncCustomers_ZohoCrmApi
     */
    private static $instance = null;
    
    /**
     * Zoho API client instance
     *
     * @var ZohoSyncCore_ApiClient
     */
    private $api_client;
    
    /**
     * Module name in Zoho CRM
     *
     * @var string
     */
    private $module_name = 'Contacts';
    
    /**
     * Get instance
     *
     * @return ZohoSyncCustomers_ZohoCrmApi
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
        $this->init_api_client();
    }
    
    /**
     * Initialize API client
     */
    private function init_api_client() {
        if (class_exists('ZohoSyncCore')) {
            $this->api_client = ZohoSyncCore::api();
        } else {
            throw new Exception(__('Zoho Sync Core no está disponible', 'zoho-sync-customers'));
        }
    }
    
    /**
     * Get contacts from Zoho CRM
     *
     * @param array $options Query options
     * @return array|false Contacts data or false on failure
     */
    public function get_contacts($options = array()) {
        $defaults = array(
            'page' => 1,
            'per_page' => 200,
            'sort_order' => 'asc',
            'sort_by' => 'Modified_Time',
            'fields' => $this->get_default_fields(),
            'modified_since' => null,
            'approved' => 'both'
        );
        
        $options = wp_parse_args($options, $defaults);
        
        try {
            $params = array(
                'page' => $options['page'],
                'per_page' => $options['per_page'],
                'sort_order' => $options['sort_order'],
                'sort_by' => $options['sort_by'],
                'fields' => implode(',', $options['fields'])
            );
            
            // Add modified since filter
            if (!empty($options['modified_since'])) {
                $params['If-Modified-Since'] = $options['modified_since'];
            }
            
            // Add approval filter
            if ($options['approved'] !== 'both') {
                $params['approved'] = $options['approved'];
            }
            
            $endpoint = "crm/v2/{$this->module_name}";
            $response = $this->api_client->get($endpoint, $params);
            
            if ($response && isset($response['data'])) {
                ZohoSyncCore::log('info', sprintf(
                    'Obtenidos %d contactos de Zoho CRM',
                    count($response['data'])
                ), 'customers');
                
                return $response;
            }
            
            return false;
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 'Error obteniendo contactos: ' . $e->getMessage(), 'customers');
            return false;
        }
    }
    
    /**
     * Get single contact by ID
     *
     * @param string $contact_id Zoho contact ID
     * @return array|false Contact data or false on failure
     */
    public function get_contact($contact_id) {
        try {
            $endpoint = "crm/v2/{$this->module_name}/{$contact_id}";
            $params = array(
                'fields' => implode(',', $this->get_default_fields())
            );
            
            $response = $this->api_client->get($endpoint, $params);
            
            if ($response && isset($response['data'][0])) {
                return $response['data'][0];
            }
            
            return false;
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', sprintf(
                'Error obteniendo contacto %s: %s',
                $contact_id,
                $e->getMessage()
            ), 'customers');
            return false;
        }
    }
    
    /**
     * Search contacts by criteria
     *
     * @param array $criteria Search criteria
     * @return array|false Search results or false on failure
     */
    public function search_contacts($criteria) {
        try {
            $search_query = $this->build_search_query($criteria);
            
            $endpoint = "crm/v2/{$this->module_name}/search";
            $params = array(
                'criteria' => $search_query,
                'fields' => implode(',', $this->get_default_fields())
            );
            
            $response = $this->api_client->get($endpoint, $params);
            
            if ($response && isset($response['data'])) {
                return $response['data'];
            }
            
            return false;
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 'Error buscando contactos: ' . $e->getMessage(), 'customers');
            return false;
        }
    }
    
    /**
     * Create new contact in Zoho CRM
     *
     * @param array $contact_data Contact data
     * @return array|false Response data or false on failure
     */
    public function create_contact($contact_data) {
        try {
            // Validate required fields
            $validation = $this->validate_contact_data($contact_data);
            if (!$validation['valid']) {
                throw new Exception('Datos de contacto inválidos: ' . implode(', ', $validation['errors']));
            }
            
            $endpoint = "crm/v2/{$this->module_name}";
            $data = array(
                'data' => array($contact_data)
            );
            
            $response = $this->api_client->post($endpoint, $data);
            
            if ($response && isset($response['data'][0]['status']) && $response['data'][0]['status'] === 'success') {
                ZohoSyncCore::log('info', sprintf(
                    'Contacto creado en Zoho: %s',
                    $contact_data['Email'] ?? 'Sin email'
                ), 'customers');
                
                return $response;
            }
            
            throw new Exception('Error creando contacto en Zoho CRM');
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 'Error creando contacto: ' . $e->getMessage(), 'customers');
            return false;
        }
    }
    
    /**
     * Update existing contact in Zoho CRM
     *
     * @param string $contact_id Zoho contact ID
     * @param array $contact_data Updated contact data
     * @return array|false Response data or false on failure
     */
    public function update_contact($contact_id, $contact_data) {
        try {
            $endpoint = "crm/v2/{$this->module_name}/{$contact_id}";
            $data = array(
                'data' => array($contact_data)
            );
            
            $response = $this->api_client->put($endpoint, $data);
            
            if ($response && isset($response['data'][0]['status']) && $response['data'][0]['status'] === 'success') {
                ZohoSyncCore::log('info', sprintf(
                    'Contacto actualizado en Zoho: %s',
                    $contact_id
                ), 'customers');
                
                return $response;
            }
            
            throw new Exception('Error actualizando contacto en Zoho CRM');
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', sprintf(
                'Error actualizando contacto %s: %s',
                $contact_id,
                $e->getMessage()
            ), 'customers');
            return false;
        }
    }
    
    /**
     * Delete contact from Zoho CRM
     *
     * @param string $contact_id Zoho contact ID
     * @return bool Success status
     */
    public function delete_contact($contact_id) {
        try {
            $endpoint = "crm/v2/{$this->module_name}/{$contact_id}";
            $response = $this->api_client->delete($endpoint);
            
            if ($response && isset($response['data'][0]['status']) && $response['data'][0]['status'] === 'success') {
                ZohoSyncCore::log('info', sprintf(
                    'Contacto eliminado de Zoho: %s',
                    $contact_id
                ), 'customers');
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', sprintf(
                'Error eliminando contacto %s: %s',
                $contact_id,
                $e->getMessage()
            ), 'customers');
            return false;
        }
    }
    
    /**
     * Get contacts by distributor level
     *
     * @param string $level Distributor level
     * @return array|false Contacts data or false on failure
     */
    public function get_distributors_by_level($level) {
        $criteria = array(
            'Distributor_Level' => $level
        );
        
        return $this->search_contacts($criteria);
    }
    
    /**
     * Get B2B customers
     *
     * @return array|false Contacts data or false on failure
     */
    public function get_b2b_customers() {
        $criteria = array(
            'Customer_Type' => 'B2B'
        );
        
        return $this->search_contacts($criteria);
    }
    
    /**
     * Get contacts by assigned zone
     *
     * @param string $zone Zone identifier
     * @return array|false Contacts data or false on failure
     */
    public function get_contacts_by_zone($zone) {
        $criteria = array(
            'Assigned_Zones' => $zone
        );
        
        return $this->search_contacts($criteria);
    }
    
    /**
     * Bulk update contacts
     *
     * @param array $contacts Array of contact data with IDs
     * @return array|false Response data or false on failure
     */
    public function bulk_update_contacts($contacts) {
        try {
            if (empty($contacts) || count($contacts) > 100) {
                throw new Exception('Número de contactos inválido para actualización masiva');
            }
            
            $endpoint = "crm/v2/{$this->module_name}";
            $data = array(
                'data' => $contacts
            );
            
            $response = $this->api_client->put($endpoint, $data);
            
            if ($response && isset($response['data'])) {
                $success_count = 0;
                foreach ($response['data'] as $result) {
                    if (isset($result['status']) && $result['status'] === 'success') {
                        $success_count++;
                    }
                }
                
                ZohoSyncCore::log('info', sprintf(
                    'Actualización masiva completada: %d de %d contactos actualizados',
                    $success_count,
                    count($contacts)
                ), 'customers');
                
                return $response;
            }
            
            return false;
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 'Error en actualización masiva: ' . $e->getMessage(), 'customers');
            return false;
        }
    }
    
    /**
     * Get default fields for contact queries
     *
     * @return array Default fields
     */
    private function get_default_fields() {
        $fields = array(
            'id',
            'First_Name',
            'Last_Name',
            'Email',
            'Phone',
            'Mobile',
            'Account_Name',
            'Mailing_Street',
            'Mailing_City',
            'Mailing_State',
            'Mailing_Zip',
            'Mailing_Country',
            'Customer_Type',
            'Distributor_Level',
            'Is_Distributor',
            'Assigned_Zones',
            'Tax_ID',
            'Created_Time',
            'Modified_Time',
            'Owner'
        );
        
        // Allow customization of fields
        return apply_filters('zoho_customers_api_fields', $fields);
    }
    
    /**
     * Build search query from criteria
     *
     * @param array $criteria Search criteria
     * @return string Search query
     */
    private function build_search_query($criteria) {
        $query_parts = array();
        
        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                // Handle array values (OR conditions)
                $or_parts = array();
                foreach ($value as $v) {
                    $or_parts[] = "({$field}:equals:{$v})";
                }
                $query_parts[] = '(' . implode(' or ', $or_parts) . ')';
            } else {
                // Handle single values
                $query_parts[] = "({$field}:equals:{$value})";
            }
        }
        
        return implode(' and ', $query_parts);
    }
    
    /**
     * Validate contact data
     *
     * @param array $contact_data Contact data to validate
     * @return array Validation result
     */
    private function validate_contact_data($contact_data) {
        $errors = array();
        
        // Check required fields
        if (empty($contact_data['Email'])) {
            $errors[] = __('Email es requerido', 'zoho-sync-customers');
        } elseif (!filter_var($contact_data['Email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('Email no es válido', 'zoho-sync-customers');
        }
        
        // Validate phone number format
        if (!empty($contact_data['Phone'])) {
            if (!$this->validate_phone_number($contact_data['Phone'])) {
                $errors[] = __('Formato de teléfono no válido', 'zoho-sync-customers');
            }
        }
        
        // Validate distributor level
        if (!empty($contact_data['Distributor_Level'])) {
            $valid_levels = $this->get_valid_distributor_levels();
            if (!in_array($contact_data['Distributor_Level'], $valid_levels)) {
                $errors[] = __('Nivel de distribuidor no válido', 'zoho-sync-customers');
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Validate phone number format
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
     * Get valid distributor levels
     *
     * @return array Valid levels
     */
    private function get_valid_distributor_levels() {
        $levels = get_option('zoho_customers_pricing_levels', array());
        return array_keys($levels);
    }
    
    /**
     * Get contact by email
     *
     * @param string $email Email address
     * @return array|false Contact data or false if not found
     */
    public function get_contact_by_email($email) {
        $criteria = array(
            'Email' => $email
        );
        
        $results = $this->search_contacts($criteria);
        
        if ($results && !empty($results)) {
            return $results[0];
        }
        
        return false;
    }
    
    /**
     * Check if contact exists by email
     *
     * @param string $email Email address
     * @return bool Exists status
     */
    public function contact_exists($email) {
        return $this->get_contact_by_email($email) !== false;
    }
    
    /**
     * Get contacts modified since date
     *
     * @param string $since_date Date in Y-m-d H:i:s format
     * @param array $options Additional options
     * @return array|false Contacts data or false on failure
     */
    public function get_contacts_modified_since($since_date, $options = array()) {
        $options['modified_since'] = date('c', strtotime($since_date));
        return $this->get_contacts($options);
    }
    
    /**
     * Get API usage statistics
     *
     * @return array API usage stats
     */
    public function get_api_usage() {
        try {
            $endpoint = 'crm/v2/org';
            $response = $this->api_client->get($endpoint);
            
            if ($response && isset($response['org'][0])) {
                return array(
                    'daily_limit' => $response['org'][0]['max_per_day'] ?? 0,
                    'used_today' => $response['org'][0]['used_today'] ?? 0,
                    'remaining' => ($response['org'][0]['max_per_day'] ?? 0) - ($response['org'][0]['used_today'] ?? 0)
                );
            }
            
            return array(
                'daily_limit' => 0,
                'used_today' => 0,
                'remaining' => 0
            );
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 'Error obteniendo estadísticas de API: ' . $e->getMessage(), 'customers');
            return array(
                'daily_limit' => 0,
                'used_today' => 0,
                'remaining' => 0
            );
        }
    }
    
    /**
     * Test API connection
     *
     * @return array Test result
     */
    public function test_connection() {
        try {
            $endpoint = 'crm/v2/org';
            $response = $this->api_client->get($endpoint);
            
            if ($response && isset($response['org'][0])) {
                return array(
                    'success' => true,
                    'message' => __('Conexión exitosa con Zoho CRM', 'zoho-sync-customers'),
                    'org_name' => $response['org'][0]['company_name'] ?? 'N/A'
                );
            }
            
            return array(
                'success' => false,
                'message' => __('Error conectando con Zoho CRM', 'zoho-sync-customers')
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get module metadata
     *
     * @return array|false Module metadata or false on failure
     */
    public function get_module_metadata() {
        try {
            $endpoint = "crm/v2/settings/modules/{$this->module_name}";
            $response = $this->api_client->get($endpoint);
            
            if ($response && isset($response['modules'][0])) {
                return $response['modules'][0];
            }
            
            return false;
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 'Error obteniendo metadata del módulo: ' . $e->getMessage(), 'customers');
            return false;
        }
    }
    
    /**
     * Get custom fields for the module
     *
     * @return array Custom fields
     */
    public function get_custom_fields() {
        $metadata = $this->get_module_metadata();
        
        if (!$metadata || !isset($metadata['fields'])) {
            return array();
        }
        
        $custom_fields = array();
        
        foreach ($metadata['fields'] as $field) {
            if (isset($field['custom_field']) && $field['custom_field'] === true) {
                $custom_fields[] = array(
                    'api_name' => $field['api_name'],
                    'field_label' => $field['field_label'],
                    'data_type' => $field['data_type'],
                    'required' => $field['required'] ?? false
                );
            }
        }
        
        return $custom_fields;
    }
}