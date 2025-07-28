<?php
/**
 * Distributor Manager Class
 *
 * Manages distributor levels, zones, and privileges
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZohoSyncCustomers_DistributorManager class
 */
class ZohoSyncCustomers_DistributorManager {
    
    /**
     * Instance of this class
     *
     * @var ZohoSyncCustomers_DistributorManager
     */
    private static $instance = null;
    
    /**
     * Default distributor levels
     *
     * @var array
     */
    private $default_levels = array(
        'level_1' => array(
            'name' => 'Nivel 1',
            'discount' => 10,
            'min_order' => 0,
            'credit_limit' => 0,
            'payment_terms' => 30
        ),
        'level_2' => array(
            'name' => 'Nivel 2', 
            'discount' => 25,
            'min_order' => 500000,
            'credit_limit' => 2000000,
            'payment_terms' => 45
        ),
        'level_3' => array(
            'name' => 'Nivel 3',
            'discount' => 40,
            'min_order' => 1000000,
            'credit_limit' => 5000000,
            'payment_terms' => 60
        )
    );
    
    /**
     * Get instance
     *
     * @return ZohoSyncCustomers_DistributorManager
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
        $this->ensure_default_levels();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // User role management
        add_action('user_register', array($this, 'handle_new_user'), 10, 1);
        add_action('profile_update', array($this, 'handle_user_update'), 10, 2);
        
        // Admin hooks
        add_action('wp_ajax_update_distributor_level', array($this, 'ajax_update_distributor_level'));
        add_action('wp_ajax_assign_distributor_zone', array($this, 'ajax_assign_distributor_zone'));
        add_action('wp_ajax_approve_distributor', array($this, 'ajax_approve_distributor'));
        
        // WooCommerce hooks
        add_filter('woocommerce_customer_get_is_paying_customer', array($this, 'override_paying_customer_status'), 10, 2);
    }
    
    /**
     * Ensure default distributor levels exist
     */
    private function ensure_default_levels() {
        $existing_levels = get_option('zoho_customers_pricing_levels', array());
        
        if (empty($existing_levels)) {
            update_option('zoho_customers_pricing_levels', $this->default_levels);
        }
    }
    
    /**
     * Get all distributor levels
     *
     * @return array Distributor levels
     */
    public function get_distributor_levels() {
        $levels = get_option('zoho_customers_pricing_levels', $this->default_levels);
        return apply_filters('zoho_customers_distributor_levels', $levels);
    }
    
    /**
     * Get specific distributor level
     *
     * @param string $level_key Level key
     * @return array|false Level data or false if not found
     */
    public function get_distributor_level($level_key) {
        $levels = $this->get_distributor_levels();
        return isset($levels[$level_key]) ? $levels[$level_key] : false;
    }
    
    /**
     * Create or update distributor level
     *
     * @param string $level_key Level key
     * @param array $level_data Level data
     * @return bool Success status
     */
    public function save_distributor_level($level_key, $level_data) {
        $levels = $this->get_distributor_levels();
        
        // Validate level data
        $validation = $this->validate_level_data($level_data);
        if (!$validation['valid']) {
            return false;
        }
        
        $levels[$level_key] = $level_data;
        
        $result = update_option('zoho_customers_pricing_levels', $levels);
        
        if ($result) {
            ZohoSyncCore::log('info', sprintf(
                'Nivel de distribuidor %s guardado: %s',
                $level_key,
                $level_data['name']
            ), 'customers');
        }
        
        return $result;
    }
    
    /**
     * Delete distributor level
     *
     * @param string $level_key Level key
     * @return bool Success status
     */
    public function delete_distributor_level($level_key) {
        $levels = $this->get_distributor_levels();
        
        if (!isset($levels[$level_key])) {
            return false;
        }
        
        // Check if any users have this level
        $users_with_level = $this->get_users_by_level($level_key);
        if (!empty($users_with_level)) {
            return false; // Cannot delete level with assigned users
        }
        
        unset($levels[$level_key]);
        
        $result = update_option('zoho_customers_pricing_levels', $levels);
        
        if ($result) {
            ZohoSyncCore::log('info', sprintf(
                'Nivel de distribuidor eliminado: %s',
                $level_key
            ), 'customers');
        }
        
        return $result;
    }
    
    /**
     * Assign distributor level to user
     *
     * @param int $user_id User ID
     * @param string $level_key Level key
     * @return bool Success status
     */
    public function assign_distributor_level($user_id, $level_key) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $level = $this->get_distributor_level($level_key);
        if (!$level) {
            return false;
        }
        
        // Update user meta
        update_user_meta($user_id, 'distributor_level', $level_key);
        update_user_meta($user_id, 'distributor_discount', $level['discount']);
        update_user_meta($user_id, 'distributor_min_order', $level['min_order']);
        update_user_meta($user_id, 'distributor_credit_limit', $level['credit_limit']);
        update_user_meta($user_id, 'distributor_payment_terms', $level['payment_terms']);
        
        // Assign distributor role
        $user->remove_role('customer');
        $user->remove_role('b2b_customer');
        $user->add_role('distributor');
        
        // Log the assignment
        ZohoSyncCore::log('info', sprintf(
            'Nivel de distribuidor asignado: Usuario %d -> %s (%s)',
            $user_id,
            $level_key,
            $level['name']
        ), 'customers');
        
        // Trigger action for other plugins
        do_action('zoho_customers_distributor_level_assigned', $user_id, $level_key, $level);
        
        return true;
    }
    
    /**
     * Remove distributor level from user
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function remove_distributor_level($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        // Remove distributor meta
        delete_user_meta($user_id, 'distributor_level');
        delete_user_meta($user_id, 'distributor_discount');
        delete_user_meta($user_id, 'distributor_min_order');
        delete_user_meta($user_id, 'distributor_credit_limit');
        delete_user_meta($user_id, 'distributor_payment_terms');
        
        // Change role back to customer
        $user->remove_role('distributor');
        $user->add_role('customer');
        
        ZohoSyncCore::log('info', sprintf(
            'Nivel de distribuidor removido del usuario %d',
            $user_id
        ), 'customers');
        
        do_action('zoho_customers_distributor_level_removed', $user_id);
        
        return true;
    }
    
    /**
     * Get user's distributor level
     *
     * @param int $user_id User ID
     * @return array|false Level data or false if not a distributor
     */
    public function get_user_distributor_level($user_id) {
        $level_key = get_user_meta($user_id, 'distributor_level', true);
        
        if (empty($level_key)) {
            return false;
        }
        
        return $this->get_distributor_level($level_key);
    }
    
    /**
     * Get user's distributor discount
     *
     * @param int $user_id User ID
     * @return float Discount percentage
     */
    public function get_user_discount($user_id) {
        $discount = get_user_meta($user_id, 'distributor_discount', true);
        return floatval($discount);
    }
    
    /**
     * Check if user is a distributor
     *
     * @param int $user_id User ID
     * @return bool Distributor status
     */
    public function is_distributor($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        return in_array('distributor', $user->roles);
    }
    
    /**
     * Get all distributors
     *
     * @param array $args Query arguments
     * @return array Distributors data
     */
    public function get_distributors($args = array()) {
        $defaults = array(
            'role' => 'distributor',
            'meta_key' => 'distributor_level',
            'orderby' => 'display_name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $users = get_users($args);
        $distributors = array();
        
        foreach ($users as $user) {
            $level_key = get_user_meta($user->ID, 'distributor_level', true);
            $level = $this->get_distributor_level($level_key);
            
            $distributors[] = array(
                'user_id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'level_key' => $level_key,
                'level' => $level,
                'discount' => $this->get_user_discount($user->ID),
                'assigned_zones' => get_user_meta($user->ID, 'assigned_zones', true),
                'approval_status' => get_user_meta($user->ID, 'distributor_approval_status', true),
                'registration_date' => $user->user_registered
            );
        }
        
        return $distributors;
    }
    
    /**
     * Get users by distributor level
     *
     * @param string $level_key Level key
     * @return array Users with the specified level
     */
    public function get_users_by_level($level_key) {
        return get_users(array(
            'meta_key' => 'distributor_level',
            'meta_value' => $level_key
        ));
    }
    
    /**
     * Assign zones to distributor
     *
     * @param int $user_id User ID
     * @param array $zones Array of zone codes
     * @return bool Success status
     */
    public function assign_zones($user_id, $zones) {
        if (!$this->is_distributor($user_id)) {
            return false;
        }
        
        // Validate zones
        $valid_zones = $this->validate_zones($zones);
        if (!$valid_zones['valid']) {
            return false;
        }
        
        // Check for zone conflicts
        $conflicts = $this->check_zone_conflicts($user_id, $zones);
        if (!empty($conflicts)) {
            ZohoSyncCore::log('warning', sprintf(
                'Conflictos de zona detectados para usuario %d: %s',
                $user_id,
                implode(', ', $conflicts)
            ), 'customers');
        }
        
        update_user_meta($user_id, 'assigned_zones', $zones);
        
        ZohoSyncCore::log('info', sprintf(
            'Zonas asignadas al distribuidor %d: %s',
            $user_id,
            implode(', ', $zones)
        ), 'customers');
        
        do_action('zoho_customers_zones_assigned', $user_id, $zones);
        
        return true;
    }
    
    /**
     * Get distributor's assigned zones
     *
     * @param int $user_id User ID
     * @return array Assigned zones
     */
    public function get_user_zones($user_id) {
        $zones = get_user_meta($user_id, 'assigned_zones', true);
        return is_array($zones) ? $zones : array();
    }
    
    /**
     * Check if user has access to zone
     *
     * @param int $user_id User ID
     * @param string $zone_code Zone code
     * @return bool Access status
     */
    public function user_has_zone_access($user_id, $zone_code) {
        if (!$this->is_distributor($user_id)) {
            return false;
        }
        
        $assigned_zones = $this->get_user_zones($user_id);
        return in_array($zone_code, $assigned_zones);
    }
    
    /**
     * Approve distributor
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function approve_distributor($user_id) {
        if (!$this->is_distributor($user_id)) {
            return false;
        }
        
        update_user_meta($user_id, 'distributor_approval_status', 'approved');
        update_user_meta($user_id, 'distributor_approved_date', current_time('mysql'));
        
        // Send approval notification
        $this->send_approval_notification($user_id);
        
        ZohoSyncCore::log('info', sprintf(
            'Distribuidor aprobado: Usuario %d',
            $user_id
        ), 'customers');
        
        do_action('zoho_customers_distributor_approved', $user_id);
        
        return true;
    }
    
    /**
     * Reject distributor
     *
     * @param int $user_id User ID
     * @param string $reason Rejection reason
     * @return bool Success status
     */
    public function reject_distributor($user_id, $reason = '') {
        if (!$this->is_distributor($user_id)) {
            return false;
        }
        
        update_user_meta($user_id, 'distributor_approval_status', 'rejected');
        update_user_meta($user_id, 'distributor_rejection_reason', $reason);
        update_user_meta($user_id, 'distributor_rejected_date', current_time('mysql'));
        
        // Send rejection notification
        $this->send_rejection_notification($user_id, $reason);
        
        ZohoSyncCore::log('info', sprintf(
            'Distribuidor rechazado: Usuario %d - Razón: %s',
            $user_id,
            $reason
        ), 'customers');
        
        do_action('zoho_customers_distributor_rejected', $user_id, $reason);
        
        return true;
    }
    
    /**
     * Get pending distributors for approval
     *
     * @return array Pending distributors
     */
    public function get_pending_distributors() {
        return get_users(array(
            'role' => 'distributor',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'distributor_approval_status',
                    'value' => 'pending',
                    'compare' => '='
                ),
                array(
                    'key' => 'distributor_approval_status',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
    }
    
    /**
     * Handle new user registration
     *
     * @param int $user_id User ID
     */
    public function handle_new_user($user_id) {
        // Check if user should be a distributor based on registration data
        $distributor_request = get_user_meta($user_id, 'distributor_request', true);
        
        if ($distributor_request === 'yes') {
            // Set as pending distributor
            $user = get_user_by('id', $user_id);
            $user->add_role('distributor');
            
            update_user_meta($user_id, 'distributor_approval_status', 'pending');
            
            // Notify admins
            $this->notify_admin_new_distributor($user_id);
        }
    }
    
    /**
     * Handle user profile update
     *
     * @param int $user_id User ID
     * @param WP_User $old_user_data Old user data
     */
    public function handle_user_update($user_id, $old_user_data) {
        // Check if distributor level changed
        $old_level = get_user_meta($user_id, 'distributor_level', true);
        
        // This would be called after the update, so we can compare
        // Implementation depends on how the update is triggered
    }
    
    /**
     * AJAX handler for updating distributor level
     */
    public function ajax_update_distributor_level() {
        check_ajax_referer('zoho_customers_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-customers'));
        }
        
        $user_id = intval($_POST['user_id']);
        $level_key = sanitize_text_field($_POST['level_key']);
        
        $result = $this->assign_distributor_level($user_id, $level_key);
        
        wp_send_json(array(
            'success' => $result,
            'message' => $result ? 
                __('Nivel actualizado correctamente', 'zoho-sync-customers') :
                __('Error actualizando nivel', 'zoho_sync-customers')
        ));
    }
    
    /**
     * AJAX handler for assigning zones
     */
    public function ajax_assign_distributor_zone() {
        check_ajax_referer('zoho_customers_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-customers'));
        }
        
        $user_id = intval($_POST['user_id']);
        $zones = array_map('sanitize_text_field', $_POST['zones']);
        
        $result = $this->assign_zones($user_id, $zones);
        
        wp_send_json(array(
            'success' => $result,
            'message' => $result ? 
                __('Zonas asignadas correctamente', 'zoho-sync-customers') :
                __('Error asignando zonas', 'zoho_sync-customers')
        ));
    }
    
    /**
     * AJAX handler for approving distributor
     */
    public function ajax_approve_distributor() {
        check_ajax_referer('zoho_customers_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permisos insuficientes', 'zoho-sync-customers'));
        }
        
        $user_id = intval($_POST['user_id']);
        $result = $this->approve_distributor($user_id);
        
        wp_send_json(array(
            'success' => $result,
            'message' => $result ? 
                __('Distribuidor aprobado', 'zoho-sync-customers') :
                __('Error aprobando distribuidor', 'zoho_sync-customers')
        ));
    }
    
    /**
     * Validate level data
     *
     * @param array $level_data Level data
     * @return array Validation result
     */
    private function validate_level_data($level_data) {
        $errors = array();
        
        if (empty($level_data['name'])) {
            $errors[] = __('Nombre del nivel es requerido', 'zoho-sync-customers');
        }
        
        if (!isset($level_data['discount']) || !is_numeric($level_data['discount'])) {
            $errors[] = __('Descuento debe ser un número', 'zoho-sync-customers');
        } elseif ($level_data['discount'] < 0 || $level_data['discount'] > 100) {
            $errors[] = __('Descuento debe estar entre 0 y 100', 'zoho-sync-customers');
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Validate zones
     *
     * @param array $zones Zones to validate
     * @return array Validation result
     */
    private function validate_zones($zones) {
        $errors = array();
        
        if (!is_array($zones)) {
            $errors[] = __('Zonas deben ser un array', 'zoho-sync-customers');
        } else {
            foreach ($zones as $zone) {
                if (!$this->is_valid_zone_code($zone)) {
                    $errors[] = sprintf(__('Código de zona inválido: %s', 'zoho-sync-customers'), $zone);
                }
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Check if zone code is valid
     *
     * @param string $zone_code Zone code
     * @return bool Valid status
     */
    private function is_valid_zone_code($zone_code) {
        // Colombian postal code validation (5 digits)
        return preg_match('/^\d{5}$/', $zone_code);
    }
    
    /**
     * Check for zone conflicts
     *
     * @param int $user_id User ID
     * @param array $zones Zones to check
     * @return array Conflicting zones
     */
    private function check_zone_conflicts($user_id, $zones) {
        $conflicts = array();
        
        foreach ($zones as $zone) {
            $existing_users = get_users(array(
                'meta_key' => 'assigned_zones',
                'meta_value' => $zone,
                'meta_compare' => 'LIKE',
                'exclude' => array($user_id)
            ));
            
            if (!empty($existing_users)) {
                $conflicts[] = $zone;
            }
        }
        
        return $conflicts;
    }
    
    /**
     * Send approval notification
     *
     * @param int $user_id User ID
     */
    private function send_approval_notification($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $subject = __('Solicitud de Distribuidor Aprobada', 'zoho-sync-customers');
        $message = sprintf(
            __('Hola %s,\n\nTu solicitud para ser distribuidor ha sido aprobada. Ya puedes acceder a los precios especiales y funciones de distribuidor.\n\nSaludos,\nEquipo de Ventas', 'zoho-sync-customers'),
            $user->display_name
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send rejection notification
     *
     * @param int $user_id User ID
     * @param string $reason Rejection reason
     */
    private function send_rejection_notification($user_id, $reason) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $subject = __('Solicitud de Distribuidor Rechazada', 'zoho-sync-customers');
        $message = sprintf(
            __('Hola %s,\n\nLamentamos informarte que tu solicitud para ser distribuidor ha sido rechazada.\n\nRazón: %s\n\nPuedes contactarnos para más información.\n\nSaludos,\nEquipo de Ventas', 'zoho-sync-customers'),
            $user->display_name,
            $reason
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Notify admin of new distributor request
     *
     * @param int $user_id User ID
     */
    private function notify_admin_new_distributor($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $subject = __('Nueva Solicitud de Distribuidor', 'zoho-sync-customers');
        $message = sprintf(
            __('Se ha registrado una nueva solicitud de distribuidor:\n\nNombre: %s\nEmail: %s\nFecha: %s\n\nRevisa la solicitud en el panel de administración.', 'zoho-sync-customers'),
            $user->display_name,
            $user->user_email,
            $user->user_registered
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Override WooCommerce paying customer status for distributors
     *
     * @param bool $is_paying_customer Current status
     * @param WC_Customer $customer Customer object
     * @return bool Modified status
     */
    public function override_paying_customer_status($is_paying_customer, $customer) {
        if ($this->is_distributor($customer->get_id())) {
            return true; // Distributors are always considered paying customers
        }
        
        return $is_paying_customer;
    }
    
    /**
     * Get distributor statistics
     *
     * @return array Statistics
     */
    public function get_distributor_stats() {
        $distributors = $this->get_distributors();
        
        $stats = array(
            'total' => count($distributors),
            'approved' => 0,
            'pending' => 0,
            'rejected' => 0,
            'by_level' => array()
        );
        
        foreach ($distributors as $distributor) {
            $status = $distributor['approval_status'] ?: 'pending';
            $stats[$status]++;
            
            if (!empty($distributor['level_key'])) {
                if (!isset($stats['by_level'][$distributor['level_key']])) {
                    $stats['by_level'][$distributor['level_key']] = 0;
                }
                $stats['by_level'][$distributor['level_key']]++;
            }
        }
        
        return $stats;
    }
}

class ZSCU_Distributor_Manager {
    
    private $levels;
    
    public function __construct() {
        $this->levels = [
            'bronze' => [
                'discount' => 10,
                'min_orders' => 0
            ],
            'silver' => [
                'discount' => 20,
                'min_orders' => 10
            ],
            'gold' => [
                'discount' => 30,
                'min_orders' => 25
            ],
            'platinum' => [
                'discount' => 40,
                'min_orders' => 50
            ]
        ];

        add_action('init', [$this, 'init']);
        add_action('woocommerce_order_status_completed', [$this, 'check_level_upgrade']);
    }

    public function init() {
        add_filter('woocommerce_product_get_price', [$this, 'apply_distributor_discount'], 10, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'apply_distributor_discount'], 10, 2);
    }

    public function apply_distributor_discount($price, $product) {
        if (!is_user_logged_in()) {
            return $price;
        }

        $user_id = get_current_user_id();
        if (!$this->is_distributor($user_id)) {
            return $price;
        }

        $level = $this->get_distributor_level($user_id);
        if (!$level || !isset($this->levels[$level])) {
            return $price;
        }

        $discount = $this->levels[$level]['discount'];
        return $price - ($price * ($discount / 100));
    }

    public function check_level_upgrade($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();

        if (!$this->is_distributor($user_id)) {
            return;
        }

        $total_orders = wc_get_customer_order_count($user_id);
        $current_level = $this->get_distributor_level($user_id);
        $new_level = $this->calculate_level($total_orders);

        if ($new_level !== $current_level) {
            $this->upgrade_distributor_level($user_id, $new_level);
        }
    }

    public function is_distributor($user_id) {
        $user = get_userdata($user_id);
        return $user && in_array('distributor', $user->roles);
    }

    public function get_distributor_level($user_id) {
        return get_user_meta($user_id, 'distributor_level', true);
    }

    private function calculate_level($total_orders) {
        $level = 'bronze';
        foreach ($this->levels as $name => $data) {
            if ($total_orders >= $data['min_orders']) {
                $level = $name;
            }
        }
        return $level;
    }

    private function upgrade_distributor_level($user_id, $new_level) {
        update_user_meta($user_id, 'distributor_level', $new_level);
        
        // Notificar al distribuidor
        $this->send_level_upgrade_notification($user_id, $new_level);
        
        // Sincronizar con Zoho
        do_action('zoho_sync_data_updated', 'customers', 'distributor_level', [
            'user_id' => $user_id,
            'new_level' => $new_level
        ]);
    }

    private function send_level_upgrade_notification($user_id, $new_level) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $subject = sprintf(
            __('¡Felicitaciones! Tu nivel de distribuidor ha sido actualizado a %s', 'zoho-sync-customers'),
            ucfirst($new_level)
        );

        $message = sprintf(
            __('Estimado %s,

¡Felicitaciones! Debido a tu excelente desempeño, tu nivel de distribuidor ha sido actualizado a %s.

Beneficios de tu nuevo nivel:
- Descuento: %d%%
- Acceso a productos exclusivos
- Soporte prioritario

Gracias por tu preferencia.

Saludos cordiales,
%s', 'zoho-sync-customers'),
            $user->display_name,
            ucfirst($new_level),
            $this->levels[$new_level]['discount'],
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }
}