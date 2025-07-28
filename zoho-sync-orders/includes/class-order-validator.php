<?php
/**
 * Order Validator Class
 *
 * Validates order data before synchronization with Zoho
 *
 * @package ZohoSyncOrders
 * @subpackage Includes
 * @since 1.0.0
 */

namespace ZohoSyncOrders;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order data validator
 */
class OrderValidator {
    
    /**
     * Required fields for Zoho synchronization
     *
     * @var array
     */
    private $required_fields = array(
        'customer_email',
        'order_total',
        'order_items'
    );
    
    /**
     * Validation rules
     *
     * @var array
     */
    private $validation_rules = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_validation_rules();
    }
    
    /**
     * Initialize validation rules
     */
    private function init_validation_rules() {
        $this->validation_rules = array(
            'email' => array(
                'pattern' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
                'message' => __('Formato de email inválido', 'zoho-sync-orders')
            ),
            'phone' => array(
                'pattern' => '/^[\+]?[0-9\s\-\(\)]{7,20}$/',
                'message' => __('Formato de teléfono inválido', 'zoho-sync-orders')
            ),
            'currency' => array(
                'pattern' => '/^[A-Z]{3}$/',
                'message' => __('Código de moneda inválido', 'zoho-sync-orders')
            ),
            'amount' => array(
                'min' => 0,
                'message' => __('El monto debe ser mayor o igual a cero', 'zoho-sync-orders')
            ),
            'quantity' => array(
                'min' => 1,
                'message' => __('La cantidad debe ser mayor a cero', 'zoho-sync-orders')
            )
        );
    }
    
    /**
     * Validate WooCommerce order
     *
     * @param \WC_Order $order WooCommerce order
     * @return array Validation result
     */
    public function validate_order($order) {
        $errors = array();
        
        // Basic order validation
        $errors = array_merge($errors, $this->validate_basic_info($order));
        
        // Customer validation
        $errors = array_merge($errors, $this->validate_customer_info($order));
        
        // Items validation
        $errors = array_merge($errors, $this->validate_order_items($order));
        
        // Totals validation
        $errors = array_merge($errors, $this->validate_totals($order));
        
        // Address validation
        $errors = array_merge($errors, $this->validate_addresses($order));
        
        // Payment validation
        $errors = array_merge($errors, $this->validate_payment_info($order));
        
        // Custom validation
        $errors = array_merge($errors, $this->validate_custom_fields($order));
        
        // Apply custom validation filters
        $errors = apply_filters('zoho_sync_orders_validation_errors', $errors, $order);
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'message' => empty($errors) ? __('Validación exitosa', 'zoho-sync-orders') : implode('; ', $errors)
        );
    }
    
    /**
     * Validate basic order information
     *
     * @param \WC_Order $order WooCommerce order
     * @return array Validation errors
     */
    private function validate_basic_info($order) {
        $errors = array();
        
        // Order ID validation
        if (!$order->get_id()) {
            $errors[] = __('ID de pedido inválido', 'zoho-sync-orders');
        }
        
        // Order status validation
        $valid_statuses = array('pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed');
        if (!in_array($order->get_status(), $valid_statuses)) {
            $errors[] = __('Estado de pedido inválido', 'zoho-sync-orders');
        }
        
        // Currency validation
        $currency = $order->get_currency();
        if (!$this->validate_field($currency, 'currency')) {
            $errors[] = $this->validation_rules['currency']['message'];
        }
        
        // Date validation
        if (!$order->get_date_created()) {
            $errors[] = __('Fecha de creación del pedido faltante', 'zoho-sync-orders');
        }
        
        return $errors;
    }
    
    /**
     * Validate customer information
     *
     * @param \WC_Order $order WooCommerce order
     * @return array Validation errors
     */
    private function validate_customer_info($order) {
        $errors = array();
        
        // Email validation (required)
        $email = $order->get_billing_email();
        if (empty($email)) {
            $errors[] = __('Email del cliente es requerido', 'zoho-sync-orders');
        } elseif (!$this->validate_field($email, 'email')) {
            $errors[] = $this->validation_rules['email']['message'];
        }
        
        // Name validation
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        if (empty($first_name) && empty($last_name)) {
            $errors[] = __('Nombre del cliente es requerido', 'zoho-sync-orders');
        }
        
        // Phone validation (if provided)
        $phone = $order->get_billing_phone();
        if (!empty($phone) && !$this->validate_field($phone, 'phone')) {
            $errors[] = $this->validation_rules['phone']['message'];
        }
        
        // Customer ID validation (if registered user)
        $customer_id = $order->get_customer_id();
        if ($customer_id && !get_user_by('id', $customer_id)) {
            $errors[] = __('ID de cliente inválido', 'zoho-sync-orders');
        }
        
        return $errors;
    }
    
    /**
     * Validate order items
     *
     * @param \WC_Order $order WooCommerce order
     * @return array Validation errors
     */
    private function validate_order_items($order) {
        $errors = array();
        
        $items = $order->get_items();
        
        // Check if order has items
        if (empty($items)) {
            $errors[] = __('El pedido debe tener al menos un producto', 'zoho-sync-orders');
            return $errors;
        }
        
        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            $item_name = $item->get_name();
            
            // Product existence validation
            if (!$product) {
                $errors[] = sprintf(__('Producto "%s" no encontrado', 'zoho-sync-orders'), $item_name);
                continue;
            }
            
            // Quantity validation
            $quantity = $item->get_quantity();
            if (!$this->validate_field($quantity, 'quantity')) {
                $errors[] = sprintf(__('Cantidad inválida para producto "%s"', 'zoho-sync-orders'), $item_name);
            }
            
            // Price validation
            $price = $item->get_subtotal();
            if (!$this->validate_field($price, 'amount')) {
                $errors[] = sprintf(__('Precio inválido para producto "%s"', 'zoho-sync-orders'), $item_name);
            }
            
            // Product name validation
            if (empty($item_name)) {
                $errors[] = __('Nombre de producto faltante', 'zoho-sync-orders');
            }
            
            // Stock validation (if manage stock is enabled)
            if ($product->managing_stock() && !$product->is_in_stock()) {
                $errors[] = sprintf(__('Producto "%s" fuera de stock', 'zoho-sync-orders'), $item_name);
            }
            
            // Variation validation (for variable products)
            if ($product->is_type('variation')) {
                $parent_product = wc_get_product($product->get_parent_id());
                if (!$parent_product) {
                    $errors[] = sprintf(__('Producto padre no encontrado para variación "%s"', 'zoho-sync-orders'), $item_name);
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate order totals
     *
     * @param \WC_Order $order WooCommerce order
     * @return array Validation errors
     */
    private function validate_totals($order) {
        $errors = array();
        
        // Total validation
        $total = $order->get_total();
        if (!$this->validate_field($total, 'amount')) {
            $errors[] = __('Total del pedido inválido', 'zoho-sync-orders');
        }
        
        // Subtotal validation
        $subtotal = $order->get_subtotal();
        if (!$this->validate_field($subtotal, 'amount')) {
            $errors[] = __('Subtotal del pedido inválido', 'zoho-sync-orders');
        }
        
        // Tax validation
        $tax_total = $order->get_total_tax();
        if ($tax_total < 0) {
            $errors[] = __('Total de impuestos no puede ser negativo', 'zoho-sync-orders');
        }
        
        // Shipping validation
        $shipping_total = $order->get_shipping_total();
        if ($shipping_total < 0) {
            $errors[] = __('Total de envío no puede ser negativo', 'zoho-sync-orders');
        }
        
        // Discount validation
        $discount_total = $order->get_discount_total();
        if ($discount_total < 0) {
            $errors[] = __('Total de descuento no puede ser negativo', 'zoho-sync-orders');
        }
        
        // Total consistency validation
        $calculated_total = $subtotal + $tax_total + $shipping_total - $discount_total;
        if (abs($calculated_total - $total) > 0.01) { // Allow for small rounding differences
            $errors[] = __('Inconsistencia en el cálculo del total del pedido', 'zoho-sync-orders');
        }
        
        return $errors;
    }
    
    /**
     * Validate addresses
     *
     * @param \WC_Order $order WooCommerce order
     * @return array Validation errors
     */
    private function validate_addresses($order) {
        $errors = array();
        
        // Billing address validation
        $billing_country = $order->get_billing_country();
        if (empty($billing_country)) {
            $errors[] = __('País de facturación es requerido', 'zoho-sync-orders');
        } elseif (!array_key_exists($billing_country, WC()->countries->get_countries())) {
            $errors[] = __('País de facturación inválido', 'zoho-sync-orders');
        }
        
        // Billing city validation
        if (empty($order->get_billing_city())) {
            $errors[] = __('Ciudad de facturación es requerida', 'zoho-sync-orders');
        }
        
        // Billing address validation
        if (empty($order->get_billing_address_1())) {
            $errors[] = __('Dirección de facturación es requerida', 'zoho-sync-orders');
        }
        
        // Shipping address validation (if different from billing)
        if ($order->has_shipping_address()) {
            $shipping_country = $order->get_shipping_country();
            if (!empty($shipping_country) && !array_key_exists($shipping_country, WC()->countries->get_countries())) {
                $errors[] = __('País de envío inválido', 'zoho-sync-orders');
            }
        }
        
        // Postal code validation (basic format check)
        $billing_postcode = $order->get_billing_postcode();
        if (!empty($billing_postcode) && !preg_match('/^[A-Za-z0-9\s\-]{3,10}$/', $billing_postcode)) {
            $errors[] = __('Código postal de facturación inválido', 'zoho-sync-orders');
        }
        
        return $errors;
    }
    
    /**
     * Validate payment information
     *
     * @param \WC_Order $order WooCommerce order
     * @return array Validation errors
     */
    private function validate_payment_info($order) {
        $errors = array();
        
        // Payment method validation
        $payment_method = $order->get_payment_method();
        if (empty($payment_method)) {
            $errors[] = __('Método de pago es requerido', 'zoho-sync-orders');
        }
        
        // Payment method title validation
        $payment_title = $order->get_payment_method_title();
        if (empty($payment_title)) {
            $errors[] = __('Título del método de pago es requerido', 'zoho-sync-orders');
        }
        
        // Transaction ID validation (if order is paid)
        if ($order->is_paid()) {
            $transaction_id = $order->get_transaction_id();
            if (empty($transaction_id) && in_array($payment_method, array('stripe', 'paypal', 'square'))) {
                // Some payment methods should have transaction IDs
                $errors[] = __('ID de transacción faltante para método de pago electrónico', 'zoho-sync-orders');
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate custom fields
     *
     * @param \WC_Order $order WooCommerce order
     * @return array Validation errors
     */
    private function validate_custom_fields($order) {
        $errors = array();
        
        // Get custom field validation rules
        $custom_validations = get_option('zoho_sync_orders_custom_validations', array());
        
        foreach ($custom_validations as $field => $rules) {
            $value = $order->get_meta($field);
            
            // Required field validation
            if (isset($rules['required']) && $rules['required'] && empty($value)) {
                $errors[] = sprintf(__('Campo personalizado requerido: %s', 'zoho-sync-orders'), $field);
                continue;
            }
            
            // Skip validation if field is empty and not required
            if (empty($value)) {
                continue;
            }
            
            // Pattern validation
            if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
                $message = isset($rules['message']) ? $rules['message'] : sprintf(__('Formato inválido para campo: %s', 'zoho-sync-orders'), $field);
                $errors[] = $message;
            }
            
            // Length validation
            if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                $errors[] = sprintf(__('Campo %s excede la longitud máxima de %d caracteres', 'zoho-sync-orders'), $field, $rules['max_length']);
            }
            
            if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                $errors[] = sprintf(__('Campo %s debe tener al menos %d caracteres', 'zoho-sync-orders'), $field, $rules['min_length']);
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate individual field
     *
     * @param mixed $value Field value
     * @param string $type Validation type
     * @return bool Validation result
     */
    private function validate_field($value, $type) {
        if (!isset($this->validation_rules[$type])) {
            return true;
        }
        
        $rule = $this->validation_rules[$type];
        
        // Pattern validation
        if (isset($rule['pattern'])) {
            return preg_match($rule['pattern'], $value);
        }
        
        // Minimum value validation
        if (isset($rule['min'])) {
            return is_numeric($value) && floatval($value) >= $rule['min'];
        }
        
        // Maximum value validation
        if (isset($rule['max'])) {
            return is_numeric($value) && floatval($value) <= $rule['max'];
        }
        
        return true;
    }
    
    /**
     * Validate order before sync
     *
     * @param int $order_id Order ID
     * @return array Validation result
     */
    public function validate_order_for_sync($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'valid' => false,
                'errors' => array(__('Pedido no encontrado', 'zoho-sync-orders')),
                'message' => __('Pedido no encontrado', 'zoho-sync-orders')
            );
        }
        
        return $this->validate_order($order);
    }
    
    /**
     * Check if order meets sync criteria
     *
     * @param \WC_Order $order WooCommerce order
     * @return array Check result
     */
    public function check_sync_criteria($order) {
        $errors = array();
        
        // Check order status
        $sync_statuses = get_option('zoho_sync_orders_sync_status', array('processing', 'completed'));
        if (!in_array($order->get_status(), $sync_statuses)) {
            $errors[] = sprintf(__('Estado del pedido (%s) no está configurado para sincronización', 'zoho-sync-orders'), $order->get_status());
        }
        
        // Check minimum order amount
        $min_amount = get_option('zoho_sync_orders_min_amount', 0);
        if ($min_amount > 0 && $order->get_total() < $min_amount) {
            $errors[] = sprintf(__('Total del pedido (%s) es menor al mínimo requerido (%s)', 'zoho-sync-orders'), 
                wc_price($order->get_total()), wc_price($min_amount));
        }
        
        // Check excluded payment methods
        $excluded_methods = get_option('zoho_sync_orders_excluded_payment_methods', array());
        if (!empty($excluded_methods) && in_array($order->get_payment_method(), $excluded_methods)) {
            $errors[] = sprintf(__('Método de pago (%s) está excluido de la sincronización', 'zoho-sync-orders'), 
                $order->get_payment_method_title());
        }
        
        // Check customer type restrictions
        $customer_id = $order->get_customer_id();
        if ($customer_id) {
            $user = get_user_by('id', $customer_id);
            if ($user) {
                $excluded_roles = get_option('zoho_sync_orders_excluded_user_roles', array());
                $user_roles = $user->roles;
                
                if (!empty($excluded_roles) && !empty(array_intersect($user_roles, $excluded_roles))) {
                    $errors[] = __('Tipo de usuario excluido de la sincronización', 'zoho-sync-orders');
                }
            }
        }
        
        return array(
            'meets_criteria' => empty($errors),
            'errors' => $errors,
            'message' => empty($errors) ? __('Pedido cumple criterios de sincronización', 'zoho-sync-orders') : implode('; ', $errors)
        );
    }
    
    /**
     * Get validation rules
     *
     * @return array Validation rules
     */
    public function get_validation_rules() {
        return $this->validation_rules;
    }
    
    /**
     * Update validation rules
     *
     * @param array $rules New validation rules
     * @return bool Success status
     */
    public function update_validation_rules($rules) {
        $this->validation_rules = array_merge($this->validation_rules, $rules);
        return update_option('zoho_sync_orders_validation_rules', $this->validation_rules);
    }
    
    /**
     * Add custom validation rule
     *
     * @param string $field Field name
     * @param array $rule Validation rule
     * @return bool Success status
     */
    public function add_custom_validation($field, $rule) {
        $custom_validations = get_option('zoho_sync_orders_custom_validations', array());
        $custom_validations[$field] = $rule;
        return update_option('zoho_sync_orders_custom_validations', $custom_validations);
    }
    
    /**
     * Remove custom validation rule
     *
     * @param string $field Field name
     * @return bool Success status
     */
    public function remove_custom_validation($field) {
        $custom_validations = get_option('zoho_sync_orders_custom_validations', array());
        unset($custom_validations[$field]);
        return update_option('zoho_sync_orders_custom_validations', $custom_validations);
    }
}