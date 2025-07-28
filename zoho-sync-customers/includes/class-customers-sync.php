<?php
/**
 * Customers Sync Class
 *
 * Handles the main synchronization logic between Zoho CRM and WooCommerce customers
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZohoSyncCustomers_CustomersSync class
 */
class ZohoSyncCustomers_CustomersSync {
    
    /**
     * Instance of this class
     *
     * @var ZohoSyncCustomers_CustomersSync
     */
    private static $instance = null;
    
    /**
     * Zoho CRM API instance
     *
     * @var ZohoSyncCustomers_ZohoCrmApi
     */
    private $zoho_api;
    
    /**
     * Customer mapper instance
     *
     * @var ZohoSyncCustomers_CustomerMapper
     */
    private $mapper;
    
    /**
     * Get instance
     *
     * @return ZohoSyncCustomers_CustomersSync
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
        $this->init_dependencies();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Cron hooks
        add_action('zoho_customers_sync_cron', array($this, 'run_scheduled_sync'));
        add_action('zoho_customers_cleanup_cron', array($this, 'cleanup_old_data'));
        
        // Manual sync hooks
        add_action('wp_ajax_zoho_customers_manual_sync', array($this, 'handle_manual_sync'));
        
        // User registration hooks
        add_action('user_register', array($this, 'sync_new_user_to_zoho'), 10, 1);
        add_action('profile_update', array($this, 'sync_user_update_to_zoho'), 10, 2);
        
        // WooCommerce customer hooks
        add_action('woocommerce_customer_save_address', array($this, 'sync_customer_address_update'), 10, 2);
        add_action('woocommerce_created_customer', array($this, 'sync_new_woo_customer'), 10, 3);
    }
    
    /**
     * Initialize dependencies
     */
    private function init_dependencies() {
        $this->zoho_api = new ZohoSyncCustomers_ZohoCrmApi();
        $this->mapper = new ZohoSyncCustomers_CustomerMapper();
    }
    
    /**
     * Run scheduled synchronization
     */
    public function run_scheduled_sync() {
        if (!$this->is_sync_enabled()) {
            return;
        }
        
        ZohoSyncCore::log('info', 'Iniciando sincronización programada de clientes', 'customers');
        
        try {
            $result = $this->sync_customers_from_zoho();
            
            if ($result['success']) {
                ZohoSyncCore::log('info', sprintf(
                    'Sincronización completada: %d clientes procesados, %d creados, %d actualizados',
                    $result['processed'],
                    $result['created'],
                    $result['updated']
                ), 'customers');
            } else {
                ZohoSyncCore::log('error', 'Error en sincronización: ' . $result['message'], 'customers');
            }
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 'Excepción en sincronización: ' . $e->getMessage(), 'customers');
        }
        
        // Update last sync time
        update_option('zoho_customers_last_sync', current_time('mysql'));
    }
    
    /**
     * Sync customers from Zoho CRM to WooCommerce
     *
     * @param array $options Sync options
     * @return array Result array
     */
    public function sync_customers_from_zoho($options = array()) {
        $defaults = array(
            'limit' => 200,
            'offset' => 0,
            'force_update' => false,
            'sync_all' => false
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $result = array(
            'success' => false,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'message' => ''
        );
        
        try {
            // Get customers from Zoho CRM
            $zoho_customers = $this->zoho_api->get_contacts($options);
            
            if (!$zoho_customers || !isset($zoho_customers['data'])) {
                throw new Exception(__('No se pudieron obtener clientes de Zoho CRM', 'zoho-sync-customers'));
            }
            
            foreach ($zoho_customers['data'] as $zoho_customer) {
                $result['processed']++;
                
                try {
                    $sync_result = $this->process_zoho_customer($zoho_customer, $options);
                    
                    if ($sync_result['created']) {
                        $result['created']++;
                    } elseif ($sync_result['updated']) {
                        $result['updated']++;
                    }
                    
                } catch (Exception $e) {
                    $result['errors']++;
                    ZohoSyncCore::log('error', sprintf(
                        'Error procesando cliente %s: %s',
                        $zoho_customer['Email'] ?? 'Sin email',
                        $e->getMessage()
                    ), 'customers');
                }
            }
            
            $result['success'] = true;
            $result['message'] = sprintf(
                __('Sincronización completada: %d procesados, %d creados, %d actualizados, %d errores', 'zoho-sync-customers'),
                $result['processed'],
                $result['created'],
                $result['updated'],
                $result['errors']
            );
            
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            ZohoSyncCore::log('error', 'Error en sync_customers_from_zoho: ' . $e->getMessage(), 'customers');
        }
        
        return $result;
    }
    
    /**
     * Process individual Zoho customer
     *
     * @param array $zoho_customer Zoho customer data
     * @param array $options Processing options
     * @return array Result array
     */
    private function process_zoho_customer($zoho_customer, $options = array()) {
        $result = array(
            'created' => false,
            'updated' => false,
            'user_id' => 0
        );
        
        // Validate required fields
        if (empty($zoho_customer['Email'])) {
            throw new Exception(__('Cliente sin email válido', 'zoho-sync-customers'));
        }
        
        // Check if user already exists
        $existing_user = get_user_by('email', $zoho_customer['Email']);
        
        if ($existing_user) {
            // Update existing user
            if ($options['force_update'] || $this->should_update_user($existing_user, $zoho_customer)) {
                $this->update_wordpress_user($existing_user, $zoho_customer);
                $result['updated'] = true;
                $result['user_id'] = $existing_user->ID;
            }
        } else {
            // Create new user
            if ($this->should_create_user($zoho_customer)) {
                $user_id = $this->create_wordpress_user($zoho_customer);
                if ($user_id) {
                    $result['created'] = true;
                    $result['user_id'] = $user_id;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Create WordPress user from Zoho customer data
     *
     * @param array $zoho_customer Zoho customer data
     * @return int|false User ID on success, false on failure
     */
    private function create_wordpress_user($zoho_customer) {
        try {
            // Map Zoho data to WordPress user data
            $user_data = $this->mapper->map_zoho_to_wordpress($zoho_customer);
            
            // Create user
            $user_id = wp_insert_user($user_data);
            
            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }
            
            // Store Zoho metadata
            $this->store_zoho_metadata($user_id, $zoho_customer);
            
            // Assign distributor role if applicable
            $this->assign_user_role($user_id, $zoho_customer);
            
            // Create WooCommerce customer if needed
            $this->create_woocommerce_customer($user_id, $zoho_customer);
            
            ZohoSyncCore::log('info', sprintf(
                'Cliente creado: %s (ID: %d)',
                $zoho_customer['Email'],
                $user_id
            ), 'customers');
            
            return $user_id;
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', sprintf(
                'Error creando usuario para %s: %s',
                $zoho_customer['Email'],
                $e->getMessage()
            ), 'customers');
            return false;
        }
    }
    
    /**
     * Update WordPress user with Zoho customer data
     *
     * @param WP_User $user WordPress user object
     * @param array $zoho_customer Zoho customer data
     * @return bool Success status
     */
    private function update_wordpress_user($user, $zoho_customer) {
        try {
            // Map Zoho data to WordPress user data
            $user_data = $this->mapper->map_zoho_to_wordpress($zoho_customer);
            $user_data['ID'] = $user->ID;
            
            // Update user
            $result = wp_update_user($user_data);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Update Zoho metadata
            $this->store_zoho_metadata($user->ID, $zoho_customer);
            
            // Update user role if needed
            $this->assign_user_role($user->ID, $zoho_customer);
            
            // Update WooCommerce customer data
            $this->update_woocommerce_customer($user->ID, $zoho_customer);
            
            ZohoSyncCore::log('info', sprintf(
                'Cliente actualizado: %s (ID: %d)',
                $zoho_customer['Email'],
                $user->ID
            ), 'customers');
            
            return true;
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', sprintf(
                'Error actualizando usuario %s: %s',
                $zoho_customer['Email'],
                $e->getMessage()
            ), 'customers');
            return false;
        }
    }
    
    /**
     * Store Zoho metadata for user
     *
     * @param int $user_id WordPress user ID
     * @param array $zoho_customer Zoho customer data
     */
    private function store_zoho_metadata($user_id, $zoho_customer) {
        // Store Zoho ID
        if (!empty($zoho_customer['id'])) {
            update_user_meta($user_id, 'zoho_contact_id', $zoho_customer['id']);
        }
        
        // Store distributor level
        if (!empty($zoho_customer['Distributor_Level'])) {
            update_user_meta($user_id, 'distributor_level', $zoho_customer['Distributor_Level']);
        }
        
        // Store customer type (B2B/B2C)
        $customer_type = $this->determine_customer_type($zoho_customer);
        update_user_meta($user_id, 'customer_type', $customer_type);
        
        // Store assigned zones
        if (!empty($zoho_customer['Assigned_Zones'])) {
            update_user_meta($user_id, 'assigned_zones', $zoho_customer['Assigned_Zones']);
        }
        
        // Store last sync time
        update_user_meta($user_id, 'zoho_last_sync', current_time('mysql'));
    }
    
    /**
     * Assign appropriate user role based on Zoho data
     *
     * @param int $user_id WordPress user ID
     * @param array $zoho_customer Zoho customer data
     */
    private function assign_user_role($user_id, $zoho_customer) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $customer_type = $this->determine_customer_type($zoho_customer);
        
        // Remove existing roles
        $user->remove_role('customer');
        $user->remove_role('b2b_customer');
        $user->remove_role('distributor');
        
        // Assign new role based on customer type
        switch ($customer_type) {
            case 'distributor':
                $user->add_role('distributor');
                break;
            case 'b2b':
                $user->add_role('b2b_customer');
                break;
            default:
                $user->add_role('customer');
                break;
        }
    }
    
    /**
     * Determine customer type from Zoho data
     *
     * @param array $zoho_customer Zoho customer data
     * @return string Customer type
     */
    private function determine_customer_type($zoho_customer) {
        // Check if it's a distributor
        if (!empty($zoho_customer['Distributor_Level']) || 
            !empty($zoho_customer['Is_Distributor']) ||
            !empty($zoho_customer['Assigned_Zones'])) {
            return 'distributor';
        }
        
        // Check if it's B2B customer
        if (!empty($zoho_customer['Customer_Type']) && 
            strtolower($zoho_customer['Customer_Type']) === 'b2b') {
            return 'b2b';
        }
        
        // Check for business indicators
        if (!empty($zoho_customer['Account_Name']) || 
            !empty($zoho_customer['Company']) ||
            !empty($zoho_customer['Tax_ID'])) {
            return 'b2b';
        }
        
        return 'b2c';
    }
    
    /**
     * Create WooCommerce customer data
     *
     * @param int $user_id WordPress user ID
     * @param array $zoho_customer Zoho customer data
     */
    private function create_woocommerce_customer($user_id, $zoho_customer) {
        if (!class_exists('WC_Customer')) {
            return;
        }
        
        $customer = new WC_Customer($user_id);
        $this->update_woocommerce_customer_data($customer, $zoho_customer);
        $customer->save();
    }
    
    /**
     * Update WooCommerce customer data
     *
     * @param int $user_id WordPress user ID
     * @param array $zoho_customer Zoho customer data
     */
    private function update_woocommerce_customer($user_id, $zoho_customer) {
        if (!class_exists('WC_Customer')) {
            return;
        }
        
        $customer = new WC_Customer($user_id);
        $this->update_woocommerce_customer_data($customer, $zoho_customer);
        $customer->save();
    }
    
    /**
     * Update WooCommerce customer data object
     *
     * @param WC_Customer $customer WooCommerce customer object
     * @param array $zoho_customer Zoho customer data
     */
    private function update_woocommerce_customer_data($customer, $zoho_customer) {
        // Billing address
        if (!empty($zoho_customer['Mailing_Street'])) {
            $customer->set_billing_address_1($zoho_customer['Mailing_Street']);
        }
        if (!empty($zoho_customer['Mailing_City'])) {
            $customer->set_billing_city($zoho_customer['Mailing_City']);
        }
        if (!empty($zoho_customer['Mailing_State'])) {
            $customer->set_billing_state($zoho_customer['Mailing_State']);
        }
        if (!empty($zoho_customer['Mailing_Zip'])) {
            $customer->set_billing_postcode($zoho_customer['Mailing_Zip']);
        }
        if (!empty($zoho_customer['Mailing_Country'])) {
            $customer->set_billing_country($zoho_customer['Mailing_Country']);
        }
        
        // Phone
        if (!empty($zoho_customer['Phone'])) {
            $customer->set_billing_phone($zoho_customer['Phone']);
        }
        
        // Company
        if (!empty($zoho_customer['Account_Name'])) {
            $customer->set_billing_company($zoho_customer['Account_Name']);
        }
    }
    
    /**
     * Check if user should be updated
     *
     * @param WP_User $user WordPress user
     * @param array $zoho_customer Zoho customer data
     * @return bool
     */
    private function should_update_user($user, $zoho_customer) {
        $last_sync = get_user_meta($user->ID, 'zoho_last_sync', true);
        
        if (empty($last_sync)) {
            return true;
        }
        
        // Check if Zoho record was modified after last sync
        if (!empty($zoho_customer['Modified_Time'])) {
            $zoho_modified = strtotime($zoho_customer['Modified_Time']);
            $last_sync_time = strtotime($last_sync);
            
            return $zoho_modified > $last_sync_time;
        }
        
        return false;
    }
    
    /**
     * Check if user should be created
     *
     * @param array $zoho_customer Zoho customer data
     * @return bool
     */
    private function should_create_user($zoho_customer) {
        $auto_create = get_option('zoho_customers_auto_create_users', 'yes');
        
        if ($auto_create !== 'yes') {
            return false;
        }
        
        // Additional validation can be added here
        return true;
    }
    
    /**
     * Sync new WordPress user to Zoho
     *
     * @param int $user_id User ID
     */
    public function sync_new_user_to_zoho($user_id) {
        if (!$this->is_sync_enabled()) {
            return;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        // Skip if user already has Zoho ID
        if (get_user_meta($user_id, 'zoho_contact_id', true)) {
            return;
        }
        
        try {
            $zoho_data = $this->mapper->map_wordpress_to_zoho($user);
            $result = $this->zoho_api->create_contact($zoho_data);
            
            if ($result && !empty($result['data'][0]['details']['id'])) {
                update_user_meta($user_id, 'zoho_contact_id', $result['data'][0]['details']['id']);
                
                ZohoSyncCore::log('info', sprintf(
                    'Usuario sincronizado a Zoho: %s (ID: %d)',
                    $user->user_email,
                    $user_id
                ), 'customers');
            }
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', sprintf(
                'Error sincronizando usuario a Zoho %s: %s',
                $user->user_email,
                $e->getMessage()
            ), 'customers');
        }
    }
    
    /**
     * Sync user update to Zoho
     *
     * @param int $user_id User ID
     * @param WP_User $old_user_data Old user data
     */
    public function sync_user_update_to_zoho($user_id, $old_user_data) {
        if (!$this->is_sync_enabled()) {
            return;
        }
        
        $zoho_contact_id = get_user_meta($user_id, 'zoho_contact_id', true);
        if (!$zoho_contact_id) {
            return;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        try {
            $zoho_data = $this->mapper->map_wordpress_to_zoho($user);
            $result = $this->zoho_api->update_contact($zoho_contact_id, $zoho_data);
            
            if ($result) {
                ZohoSyncCore::log('info', sprintf(
                    'Usuario actualizado en Zoho: %s (ID: %d)',
                    $user->user_email,
                    $user_id
                ), 'customers');
            }
            
        } catch (Exception $e) {
            ZohoSyncCore::log('error', sprintf(
                'Error actualizando usuario en Zoho %s: %s',
                $user->user_email,
                $e->getMessage()
            ), 'customers');
        }
    }
    
    /**
     * Handle manual sync request
     */
    public function handle_manual_sync() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'zoho_customers_manual_sync')) {
            wp_die(__('Error de seguridad', 'zoho-sync-customers'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-customers'));
        }
        
        $options = array(
            'force_update' => isset($_POST['force_update']) ? true : false,
            'limit' => isset($_POST['limit']) ? intval($_POST['limit']) : 200
        );
        
        $result = $this->sync_customers_from_zoho($options);
        
        wp_send_json($result);
    }
    
    /**
     * Cleanup old data
     */
    public function cleanup_old_data() {
        // Clean up old sync logs (older than 30 days)
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // This would be implemented based on your logging system
        ZohoSyncCore::log('info', 'Limpieza de datos antiguos completada', 'customers');
    }
    
    /**
     * Check if sync is enabled
     *
     * @return bool
     */
    private function is_sync_enabled() {
        return get_option('zoho_customers_sync_enabled', 'yes') === 'yes';
    }
    
    /**
     * Get sync statistics
     *
     * @return array
     */
    public function get_sync_stats() {
        return array(
            'last_sync' => get_option('zoho_customers_last_sync', ''),
            'total_customers' => $this->get_total_customers(),
            'distributor_count' => $this->get_distributor_count(),
            'b2b_customer_count' => $this->get_b2b_customer_count(),
            'sync_enabled' => $this->is_sync_enabled()
        );
    }
    
    /**
     * Get total customers count
     *
     * @return int
     */
    private function get_total_customers() {
        $users = get_users(array(
            'meta_key' => 'zoho_contact_id',
            'count_total' => true,
            'fields' => 'ID'
        ));
        
        return is_array($users) ? count($users) : 0;
    }
    
    /**
     * Get distributor count
     *
     * @return int
     */
    private function get_distributor_count() {
        $users = get_users(array(
            'role' => 'distributor',
            'count_total' => true,
            'fields' => 'ID'
        ));
        
        return is_array($users) ? count($users) : 0;
    }
    
    /**
     * Get B2B customer count
     *
     * @return int
     */
    private function get_b2b_customer_count() {
        $users = get_users(array(
            'role' => 'b2b_customer',
            'count_total' => true,
            'fields' => 'ID'
        ));
        
        return is_array($users) ? count($users) : 0;
    }
}

class ZSCU_Customers_Sync {
    private $api;
    private $mapper;
    private $last_sync;

    public function __construct() {
        $this->api = ZohoSyncCore::api();
        $this->mapper = new ZSCU_Customer_Mapper();
        $this->last_sync = get_option('zscu_last_sync_timestamp', 0);

        // Hooks para sincronización
        add_action('user_register', [$this, 'sync_new_customer'], 10, 1);
        add_action('profile_update', [$this, 'sync_customer_update'], 10, 2);
        add_action('woocommerce_customer_save_address', [$this, 'sync_address_update'], 10, 2);
        add_action('zscu_scheduled_sync', [$this, 'sync_from_zoho']);
    }

    public static function sync_new_customer($user_id) {
        $user = get_userdata($user_id);
        if (!$user || !in_array('customer', $user->roles)) {
            return;
        }

        try {
            $instance = new self();
            $zoho_data = $instance->prepare_customer_data($user);
            $response = $instance->api->post('crm', 'Contacts', $zoho_data);

            if ($response && isset($response->data[0]->id)) {
                update_user_meta($user_id, 'zoho_contact_id', $response->data[0]->id);
                
                ZohoSyncCore::log('info', 
                    sprintf(__('Nuevo cliente sincronizado con Zoho: %s', 'zoho-sync-customers'), 
                        $user->user_email
                    ),
                    ['user_id' => $user_id],
                    'customers'
                );
            }
        } catch (Exception $e) {
            ZohoSyncCore::log('error', 
                'Error sincronizando nuevo cliente: ' . $e->getMessage(),
                ['user_id' => $user_id],
                'customers'
            );
        }
    }

    public function sync_from_zoho() {
        try {
            $page = 1;
            $processed = 0;
            $modified_time = date('c', $this->last_sync);

            do {
                $contacts = $this->api->get('crm', 'Contacts', [
                    'modified_time' => $modified_time,
                    'page' => $page,
                    'per_page' => 100
                ]);

                if (empty($contacts->data)) {
                    break;
                }

                foreach ($contacts->data as $contact) {
                    $this->process_zoho_contact($contact);
                    $processed++;
                }

                $page++;

            } while (!empty($contacts->data));

            update_option('zscu_last_sync_timestamp', time());

            ZohoSyncCore::log('info', 
                sprintf(__('Sincronización desde Zoho completada. %d contactos procesados.', 'zoho-sync-customers'), 
                    $processed
                ),
                [],
                'customers'
            );

        } catch (Exception $e) {
            ZohoSyncCore::log('error', 
                'Error en sincronización desde Zoho: ' . $e->getMessage(),
                [],
                'customers'
            );
        }
    }

    private function process_zoho_contact($contact) {
        $user_id = $this->get_user_by_zoho_id($contact->id);

        if ($user_id) {
            // Actualizar usuario existente
            $this->update_existing_user($user_id, $contact);
        } else {
            // Crear nuevo usuario
            $this->create_new_user($contact);
        }
    }

    private function prepare_customer_data($user) {
        $customer = new WC_Customer($user->ID);
        
        return $this->mapper->to_zoho([
            'user' => $user,
            'customer' => $customer,
            'billing' => [
                'first_name' => $customer->get_billing_first_name(),
                'last_name' => $customer->get_billing_last_name(),
                'company' => $customer->get_billing_company(),
                'address_1' => $customer->get_billing_address_1(),
                'address_2' => $customer->get_billing_address_2(),
                'city' => $customer->get_billing_city(),
                'state' => $customer->get_billing_state(),
                'postcode' => $customer->get_billing_postcode(),
                'country' => $customer->get_billing_country(),
                'email' => $customer->get_billing_email(),
                'phone' => $customer->get_billing_phone()
            ]
        ]);
    }

    private function get_user_by_zoho_id($zoho_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'zoho_contact_id' AND meta_value = %s",
            $zoho_id
        ));
    }
}