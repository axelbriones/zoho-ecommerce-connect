<?php
/**
 * Customer Roles Class
 *
 * Manages custom user roles for customers, distributors, and B2B users
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZohoSyncCustomers_CustomerRoles class
 */
class ZohoSyncCustomers_CustomerRoles {
    
    /**
     * Instance of this class
     *
     * @var ZohoSyncCustomers_CustomerRoles
     */
    private static $instance = null;
    
    /**
     * Custom roles configuration
     *
     * @var array
     */
    private $custom_roles = array();
    
    /**
     * Get instance
     *
     * @return ZohoSyncCustomers_CustomerRoles
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
        $this->init_custom_roles();
        $this->init_hooks();
    }
    
    /**
     * Initialize custom roles configuration
     */
    private function init_custom_roles() {
        $this->custom_roles = array(
            'distributor' => array(
                'name' => __('Distribuidor', 'zoho-sync-customers'),
                'capabilities' => array(
                    'read' => true,
                    'edit_posts' => false,
                    'delete_posts' => false,
                    'view_distributor_portal' => true,
                    'access_special_pricing' => true,
                    'place_b2b_orders' => true,
                    'view_assigned_zones' => true,
                    'manage_distributor_customers' => true,
                    'view_distributor_reports' => true,
                    'download_distributor_invoices' => true
                )
            ),
            'b2b_customer' => array(
                'name' => __('Cliente B2B', 'zoho-sync-customers'),
                'capabilities' => array(
                    'read' => true,
                    'edit_posts' => false,
                    'delete_posts' => false,
                    'access_b2b_pricing' => true,
                    'place_b2b_orders' => true,
                    'view_b2b_invoices' => true,
                    'request_credit_limit' => true,
                    'upload_purchase_orders' => true
                )
            ),
            'distributor_admin' => array(
                'name' => __('Administrador de Distribuidor', 'zoho-sync-customers'),
                'capabilities' => array(
                    'read' => true,
                    'edit_posts' => false,
                    'delete_posts' => false,
                    'view_distributor_portal' => true,
                    'access_special_pricing' => true,
                    'place_b2b_orders' => true,
                    'view_assigned_zones' => true,
                    'manage_distributor_customers' => true,
                    'view_distributor_reports' => true,
                    'download_distributor_invoices' => true,
                    'manage_distributor_users' => true,
                    'approve_distributor_orders' => true,
                    'view_distributor_analytics' => true
                )
            )
        );
        
        // Allow customization of roles
        $this->custom_roles = apply_filters('zoho_customers_custom_roles', $this->custom_roles);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Role management hooks
        add_action('init', array($this, 'maybe_create_roles'));
        add_action('wp_roles_init', array($this, 'modify_existing_roles'));
        
        // User role assignment hooks
        add_action('user_register', array($this, 'assign_default_role'), 10, 1);
        add_action('profile_update', array($this, 'handle_role_changes'), 10, 2);
        
        // Capability checks
        add_filter('user_has_cap', array($this, 'check_custom_capabilities'), 10, 4);
        add_filter('map_meta_cap', array($this, 'map_custom_capabilities'), 10, 4);
        
        // Admin interface hooks
        add_filter('editable_roles', array($this, 'filter_editable_roles'));
        add_action('admin_head-user-edit.php', array($this, 'add_role_descriptions'));
        add_action('admin_head-users.php', array($this, 'add_role_descriptions'));
        
        // Role switching hooks (for testing)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_ajax_switch_user_role', array($this, 'ajax_switch_user_role'));
        }
    }
    
    /**
     * Create custom roles if they don't exist
     */
    public function maybe_create_roles() {
        foreach ($this->custom_roles as $role_key => $role_config) {
            if (!get_role($role_key)) {
                add_role($role_key, $role_config['name'], $role_config['capabilities']);
                
                ZohoSyncCore::log('info', sprintf(
                    'Rol personalizado creado: %s (%s)',
                    $role_key,
                    $role_config['name']
                ), 'customers');
            }
        }
    }
    
    /**
     * Modify existing roles to add custom capabilities
     */
    public function modify_existing_roles() {
        // Add custom capabilities to administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = array(
                'manage_distributor_levels',
                'approve_distributors',
                'manage_b2b_customers',
                'view_customer_sync_logs',
                'configure_pricing_levels',
                'manage_customer_zones'
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Add capabilities to shop manager role
        $shop_manager_role = get_role('shop_manager');
        if ($shop_manager_role) {
            $shop_manager_capabilities = array(
                'view_distributor_reports',
                'manage_b2b_customers',
                'view_customer_sync_logs'
            );
            
            foreach ($shop_manager_capabilities as $cap) {
                $shop_manager_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Assign default role to new users
     *
     * @param int $user_id User ID
     */
    public function assign_default_role($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        // Check if user was registered through B2B form
        $b2b_registration = get_user_meta($user_id, 'b2b_registration_request', true);
        if ($b2b_registration === 'yes') {
            $user->remove_role('customer');
            $user->add_role('b2b_customer');
            return;
        }
        
        // Check if user was registered as distributor
        $distributor_registration = get_user_meta($user_id, 'distributor_registration_request', true);
        if ($distributor_registration === 'yes') {
            $user->remove_role('customer');
            $user->add_role('distributor');
            return;
        }
        
        // Default to customer role if not already assigned
        if (empty($user->roles)) {
            $user->add_role('customer');
        }
    }
    
    /**
     * Handle role changes on profile update
     *
     * @param int $user_id User ID
     * @param WP_User $old_user_data Old user data
     */
    public function handle_role_changes($user_id, $old_user_data) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        // Log role changes
        $old_roles = $old_user_data->roles;
        $new_roles = $user->roles;
        
        $added_roles = array_diff($new_roles, $old_roles);
        $removed_roles = array_diff($old_roles, $new_roles);
        
        if (!empty($added_roles) || !empty($removed_roles)) {
            ZohoSyncCore::log('info', sprintf(
                'Roles de usuario cambiados - Usuario %d: Agregados: %s, Removidos: %s',
                $user_id,
                implode(', ', $added_roles),
                implode(', ', $removed_roles)
            ), 'customers');
        }
        
        // Handle specific role transitions
        if (in_array('distributor', $added_roles)) {
            $this->handle_distributor_role_added($user_id);
        }
        
        if (in_array('b2b_customer', $added_roles)) {
            $this->handle_b2b_role_added($user_id);
        }
    }
    
    /**
     * Handle distributor role being added
     *
     * @param int $user_id User ID
     */
    private function handle_distributor_role_added($user_id) {
        // Set default distributor metadata
        if (!get_user_meta($user_id, 'distributor_level', true)) {
            update_user_meta($user_id, 'distributor_level', 'level_1');
        }
        
        if (!get_user_meta($user_id, 'distributor_approval_status', true)) {
            update_user_meta($user_id, 'distributor_approval_status', 'pending');
        }
        
        // Trigger action for other plugins
        do_action('zoho_customers_distributor_role_added', $user_id);
    }
    
    /**
     * Handle B2B customer role being added
     *
     * @param int $user_id User ID
     */
    private function handle_b2b_role_added($user_id) {
        // Set default B2B metadata
        if (!get_user_meta($user_id, 'customer_type', true)) {
            update_user_meta($user_id, 'customer_type', 'b2b');
        }
        
        if (!get_user_meta($user_id, 'b2b_approval_status', true)) {
            update_user_meta($user_id, 'b2b_approval_status', 'pending');
        }
        
        // Trigger action for other plugins
        do_action('zoho_customers_b2b_role_added', $user_id);
    }
    
    /**
     * Check custom capabilities
     *
     * @param array $allcaps All capabilities
     * @param array $caps Required capabilities
     * @param array $args Arguments
     * @param WP_User $user User object
     * @return array Modified capabilities
     */
    public function check_custom_capabilities($allcaps, $caps, $args, $user) {
        // Check distributor-specific capabilities
        if (in_array('distributor', $user->roles)) {
            $allcaps = $this->add_distributor_capabilities($allcaps, $user);
        }
        
        // Check B2B customer capabilities
        if (in_array('b2b_customer', $user->roles)) {
            $allcaps = $this->add_b2b_capabilities($allcaps, $user);
        }
        
        return $allcaps;
    }
    
    /**
     * Add distributor-specific capabilities
     *
     * @param array $allcaps All capabilities
     * @param WP_User $user User object
     * @return array Modified capabilities
     */
    private function add_distributor_capabilities($allcaps, $user) {
        // Check if distributor is approved
        $approval_status = get_user_meta($user->ID, 'distributor_approval_status', true);
        
        if ($approval_status === 'approved') {
            $allcaps['access_special_pricing'] = true;
            $allcaps['place_b2b_orders'] = true;
            $allcaps['view_distributor_portal'] = true;
            $allcaps['view_assigned_zones'] = true;
            $allcaps['download_distributor_invoices'] = true;
        } else {
            // Pending or rejected distributors have limited access
            $allcaps['access_special_pricing'] = false;
            $allcaps['place_b2b_orders'] = false;
        }
        
        return $allcaps;
    }
    
    /**
     * Add B2B customer capabilities
     *
     * @param array $allcaps All capabilities
     * @param WP_User $user User object
     * @return array Modified capabilities
     */
    private function add_b2b_capabilities($allcaps, $user) {
        // Check if B2B customer is approved
        $approval_status = get_user_meta($user->ID, 'b2b_approval_status', true);
        
        if ($approval_status === 'approved') {
            $allcaps['access_b2b_pricing'] = true;
            $allcaps['place_b2b_orders'] = true;
            $allcaps['view_b2b_invoices'] = true;
            $allcaps['upload_purchase_orders'] = true;
        } else {
            // Pending or rejected B2B customers have limited access
            $allcaps['access_b2b_pricing'] = false;
            $allcaps['place_b2b_orders'] = false;
        }
        
        return $allcaps;
    }
    
    /**
     * Map custom capabilities to primitive capabilities
     *
     * @param array $caps Mapped capabilities
     * @param string $cap Capability being checked
     * @param int $user_id User ID
     * @param array $args Arguments
     * @return array Mapped capabilities
     */
    public function map_custom_capabilities($caps, $cap, $user_id, $args) {
        switch ($cap) {
            case 'manage_distributor_levels':
            case 'approve_distributors':
            case 'manage_b2b_customers':
                $caps = array('manage_options');
                break;
                
            case 'view_customer_sync_logs':
                $caps = array('manage_woocommerce');
                break;
                
            case 'access_special_pricing':
            case 'place_b2b_orders':
                // These are handled in check_custom_capabilities
                $caps = array('read');
                break;
        }
        
        return $caps;
    }
    
    /**
     * Filter editable roles in admin
     *
     * @param array $roles Editable roles
     * @return array Filtered roles
     */
    public function filter_editable_roles($roles) {
        // Only show custom roles to administrators
        if (!current_user_can('manage_options')) {
            foreach ($this->custom_roles as $role_key => $role_config) {
                unset($roles[$role_key]);
            }
        }
        
        return $roles;
    }
    
    /**
     * Add role descriptions in admin
     */
    public function add_role_descriptions() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add descriptions for custom roles
            var roleDescriptions = {
                'distributor': '<?php echo esc_js(__('Distribuidor con acceso a precios especiales y portal exclusivo', 'zoho-sync-customers')); ?>',
                'b2b_customer': '<?php echo esc_js(__('Cliente empresarial con precios B2B y funciones especiales', 'zoho-sync-customers')); ?>',
                'distributor_admin': '<?php echo esc_js(__('Administrador de distribuidor con permisos extendidos', 'zoho-sync-customers')); ?>'
            };
            
            $.each(roleDescriptions, function(role, description) {
                $('input[value="' + role + '"]').closest('label').append('<br><small style="color: #666;">' + description + '</small>');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get user's primary customer role
     *
     * @param int $user_id User ID
     * @return string|false Primary role or false
     */
    public function get_user_primary_role($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        // Priority order for customer roles
        $priority_roles = array('distributor_admin', 'distributor', 'b2b_customer', 'customer');
        
        foreach ($priority_roles as $role) {
            if (in_array($role, $user->roles)) {
                return $role;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has customer-related role
     *
     * @param int $user_id User ID
     * @return bool Has customer role
     */
    public function user_has_customer_role($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $customer_roles = array('customer', 'b2b_customer', 'distributor', 'distributor_admin');
        
        return !empty(array_intersect($user->roles, $customer_roles));
    }
    
    /**
     * Get users by customer role
     *
     * @param string $role Role name
     * @param array $args Additional arguments
     * @return array Users
     */
    public function get_users_by_role($role, $args = array()) {
        $defaults = array(
            'role' => $role,
            'orderby' => 'display_name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return get_users($args);
    }
    
    /**
     * Bulk assign role to users
     *
     * @param array $user_ids User IDs
     * @param string $role Role to assign
     * @param bool $remove_existing Remove existing roles
     * @return array Result
     */
    public function bulk_assign_role($user_ids, $role, $remove_existing = false) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        if (!array_key_exists($role, $this->custom_roles) && !get_role($role)) {
            $results['errors'][] = __('Rol no válido', 'zoho-sync-customers');
            return $results;
        }
        
        foreach ($user_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            if (!$user) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Usuario %d no encontrado', 'zoho-sync-customers'), $user_id);
                continue;
            }
            
            try {
                if ($remove_existing) {
                    // Remove all customer-related roles
                    $customer_roles = array('customer', 'b2b_customer', 'distributor', 'distributor_admin');
                    foreach ($customer_roles as $existing_role) {
                        $user->remove_role($existing_role);
                    }
                }
                
                $user->add_role($role);
                $results['success']++;
                
                ZohoSyncCore::log('info', sprintf(
                    'Rol asignado masivamente: Usuario %d -> %s',
                    $user_id,
                    $role
                ), 'customers');
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Error asignando rol a usuario %d: %s', 'zoho-sync-customers'), $user_id, $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * AJAX handler for switching user role (debug only)
     */
    public function ajax_switch_user_role() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            wp_die(__('Función no disponible', 'zoho-sync-customers'));
        }
        
        check_ajax_referer('zoho_customers_debug', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-customers'));
        }
        
        $user_id = intval($_POST['user_id']);
        $new_role = sanitize_text_field($_POST['role']);
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error(__('Usuario no encontrado', 'zoho-sync-customers'));
        }
        
        // Remove existing customer roles
        $customer_roles = array('customer', 'b2b_customer', 'distributor', 'distributor_admin');
        foreach ($customer_roles as $role) {
            $user->remove_role($role);
        }
        
        // Add new role
        $user->add_role($new_role);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Rol cambiado a %s', 'zoho-sync-customers'), $new_role),
            'new_role' => $new_role
        ));
    }
    
    /**
     * Remove custom roles on plugin deactivation
     */
    public function remove_custom_roles() {
        foreach ($this->custom_roles as $role_key => $role_config) {
            remove_role($role_key);
        }
    }
    
    /**
     * Get role statistics
     *
     * @return array Role statistics
     */
    public function get_role_stats() {
        $stats = array();
        
        foreach ($this->custom_roles as $role_key => $role_config) {
            $users = $this->get_users_by_role($role_key, array('fields' => 'ID'));
            $stats[$role_key] = array(
                'name' => $role_config['name'],
                'count' => count($users),
                'capabilities' => count($role_config['capabilities'])
            );
        }
        
        return $stats;
    }
    
    /**
     * Export role data
     *
     * @return array Role export data
     */
    public function export_role_data() {
        $data = array(
            'roles' => $this->custom_roles,
            'statistics' => $this->get_role_stats(),
            'users_by_role' => array()
        );
        
        foreach ($this->custom_roles as $role_key => $role_config) {
            $users = $this->get_users_by_role($role_key);
            $data['users_by_role'][$role_key] = array();
            
            foreach ($users as $user) {
                $data['users_by_role'][$role_key][] = array(
                    'ID' => $user->ID,
                    'display_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'user_registered' => $user->user_registered
                );
            }
        }
        
        return $data;
    }
}