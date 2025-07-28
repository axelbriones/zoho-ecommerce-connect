<?php
/**
 * Order Mapper Class
 *
 * Handles mapping between WooCommerce order data and Zoho format
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
 * Order data mapper
 */
class OrderMapper {
    
    /**
     * Default field mapping
     *
     * @var array
     */
    private $default_mapping = array(
        'customer_name' => 'billing_first_name|billing_last_name',
        'customer_email' => 'billing_email',
        'customer_phone' => 'billing_phone',
        'billing_address' => 'billing_address_1|billing_address_2',
        'billing_city' => 'billing_city',
        'billing_state' => 'billing_state',
        'billing_country' => 'billing_country',
        'billing_postcode' => 'billing_postcode',
        'shipping_address' => 'shipping_address_1|shipping_address_2',
        'shipping_city' => 'shipping_city',
        'shipping_state' => 'shipping_state',
        'shipping_country' => 'shipping_country',
        'shipping_postcode' => 'shipping_postcode',
        'order_date' => 'date_created',
        'order_status' => 'status',
        'payment_method' => 'payment_method_title',
        'order_notes' => 'customer_note',
        'currency' => 'currency',
        'total_amount' => 'total',
        'tax_amount' => 'total_tax',
        'shipping_amount' => 'shipping_total',
        'discount_amount' => 'discount_total'
    );
    
    /**
     * Payment method mapping
     *
     * @var array
     */
    private $payment_mapping = array(
        'bacs' => 'Bank Transfer',
        'cheque' => 'Check',
        'cod' => 'Cash on Delivery',
        'paypal' => 'PayPal',
        'stripe' => 'Credit Card',
        'square' => 'Credit Card',
        'woocommerce_payments' => 'Credit Card'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load custom mappings from options
        $this->load_custom_mappings();
    }
    
    /**
     * Load custom field mappings from options
     */
    private function load_custom_mappings() {
        $custom_mapping = get_option('zoho_sync_orders_field_mapping', array());
        if (!empty($custom_mapping)) {
            $this->default_mapping = array_merge($this->default_mapping, $custom_mapping);
        }
        
        $custom_payment_mapping = get_option('zoho_sync_orders_payment_mapping', array());
        if (!empty($custom_payment_mapping)) {
            $this->payment_mapping = array_merge($this->payment_mapping, $custom_payment_mapping);
        }
    }
    
    /**
     * Map WooCommerce order to Zoho format
     *
     * @param \WC_Order $order WooCommerce order
     * @return array Mapped order data
     */
    public function map_order_to_zoho($order) {
        $zoho_data = array();
        
        // Basic order information
        $zoho_data = $this->map_basic_info($order, $zoho_data);
        
        // Customer information
        $zoho_data = $this->map_customer_info($order, $zoho_data);
        
        // Billing and shipping addresses
        $zoho_data = $this->map_addresses($order, $zoho_data);
        
        // Order items
        $zoho_data = $this->map_order_items($order, $zoho_data);
        
        // Totals and taxes
        $zoho_data = $this->map_totals($order, $zoho_data);
        
        // Payment information
        $zoho_data = $this->map_payment_info($order, $zoho_data);
        
        // Custom fields
        $zoho_data = $this->map_custom_fields($order, $zoho_data);
        
        // Apply filters for customization
        $zoho_data = apply_filters('zoho_sync_orders_mapped_data', $zoho_data, $order);
        
        return $zoho_data;
    }
    
    /**
     * Map basic order information
     *
     * @param \WC_Order $order WooCommerce order
     * @param array $zoho_data Zoho data array
     * @return array Updated Zoho data
     */
    private function map_basic_info($order, $zoho_data) {
        // Order reference
        $zoho_data['Subject'] = sprintf(__('Pedido WooCommerce #%s', 'zoho-sync-orders'), $order->get_order_number());
        
        // Order date
        $zoho_data['Quote_Date'] = $order->get_date_created()->format('Y-m-d');
        $zoho_data['Valid_Till'] = $order->get_date_created()->modify('+30 days')->format('Y-m-d');
        
        // Order status
        $zoho_data['Quote_Stage'] = $this->map_order_status($order->get_status());
        
        // Currency
        $zoho_data['Currency'] = $order->get_currency();
        
        // Order notes
        if ($order->get_customer_note()) {
            $zoho_data['Description'] = $order->get_customer_note();
        }
        
        // WooCommerce order ID for reference
        $zoho_data['WooCommerce_Order_ID'] = $order->get_id();
        
        return $zoho_data;
    }
    
    /**
     * Map customer information
     *
     * @param \WC_Order $order WooCommerce order
     * @param array $zoho_data Zoho data array
     * @return array Updated Zoho data
     */
    private function map_customer_info($order, $zoho_data) {
        // Try to find existing Zoho contact
        $customer_id = $order->get_customer_id();
        $zoho_contact_id = null;
        
        if ($customer_id) {
            $zoho_contact_id = get_user_meta($customer_id, 'zoho_contact_id', true);
        }
        
        if ($zoho_contact_id) {
            // Use existing Zoho contact
            $zoho_data['Contact_Name'] = $zoho_contact_id;
        } else {
            // Create contact data
            $contact_data = array(
                'First_Name' => $order->get_billing_first_name(),
                'Last_Name' => $order->get_billing_last_name(),
                'Email' => $order->get_billing_email(),
                'Phone' => $order->get_billing_phone(),
                'Mailing_Street' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
                'Mailing_City' => $order->get_billing_city(),
                'Mailing_State' => $order->get_billing_state(),
                'Mailing_Code' => $order->get_billing_postcode(),
                'Mailing_Country' => $order->get_billing_country()
            );
            
            $zoho_data['Contact_Name'] = $contact_data;
        }
        
        return $zoho_data;
    }
    
    /**
     * Map billing and shipping addresses
     *
     * @param \WC_Order $order WooCommerce order
     * @param array $zoho_data Zoho data array
     * @return array Updated Zoho data
     */
    private function map_addresses($order, $zoho_data) {
        // Billing address
        $zoho_data['Billing_Street'] = trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2());
        $zoho_data['Billing_City'] = $order->get_billing_city();
        $zoho_data['Billing_State'] = $order->get_billing_state();
        $zoho_data['Billing_Code'] = $order->get_billing_postcode();
        $zoho_data['Billing_Country'] = $order->get_billing_country();
        
        // Shipping address (if different from billing)
        if ($order->has_shipping_address()) {
            $zoho_data['Shipping_Street'] = trim($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2());
            $zoho_data['Shipping_City'] = $order->get_shipping_city();
            $zoho_data['Shipping_State'] = $order->get_shipping_state();
            $zoho_data['Shipping_Code'] = $order->get_shipping_postcode();
            $zoho_data['Shipping_Country'] = $order->get_shipping_country();
        } else {
            // Use billing address for shipping
            $zoho_data['Shipping_Street'] = $zoho_data['Billing_Street'];
            $zoho_data['Shipping_City'] = $zoho_data['Billing_City'];
            $zoho_data['Shipping_State'] = $zoho_data['Billing_State'];
            $zoho_data['Shipping_Code'] = $zoho_data['Billing_Code'];
            $zoho_data['Shipping_Country'] = $zoho_data['Billing_Country'];
        }
        
        return $zoho_data;
    }
    
    /**
     * Map order items
     *
     * @param \WC_Order $order WooCommerce order
     * @param array $zoho_data Zoho data array
     * @return array Updated Zoho data
     */
    private function map_order_items($order, $zoho_data) {
        $line_items = array();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            
            if (!$product) {
                continue;
            }
            
            // Try to get Zoho product ID
            $zoho_product_id = get_post_meta($product->get_id(), 'zoho_product_id', true);
            
            $line_item = array(
                'Product_Name' => $zoho_product_id ?: array(
                    'Product_Name' => $item->get_name(),
                    'Product_Code' => $product->get_sku() ?: $product->get_id(),
                    'Unit_Price' => floatval($item->get_subtotal() / $item->get_quantity()),
                    'Description' => $product->get_short_description()
                ),
                'Quantity' => floatval($item->get_quantity()),
                'List_Price' => floatval($item->get_subtotal() / $item->get_quantity()),
                'Unit_Price' => floatval($item->get_subtotal() / $item->get_quantity()),
                'Total' => floatval($item->get_subtotal()),
                'Product_Description' => $item->get_name()
            );
            
            // Add product variations if applicable
            if ($product->is_type('variation')) {
                $variation_data = array();
                foreach ($item->get_meta_data() as $meta) {
                    if (strpos($meta->key, 'pa_') === 0 || strpos($meta->key, 'attribute_') === 0) {
                        $variation_data[] = $meta->key . ': ' . $meta->value;
                    }
                }
                if (!empty($variation_data)) {
                    $line_item['Product_Description'] .= ' (' . implode(', ', $variation_data) . ')';
                }
            }
            
            // Add tax information if applicable
            if (get_option('zoho_sync_orders_include_taxes', 'yes') === 'yes') {
                $tax_amount = floatval($item->get_subtotal_tax());
                if ($tax_amount > 0) {
                    $line_item['Tax'] = $tax_amount;
                    $line_item['Total_After_Discount'] = floatval($item->get_total());
                }
            }
            
            $line_items[] = $line_item;
        }
        
        // Add shipping as line item if enabled
        if (get_option('zoho_sync_orders_include_shipping', 'yes') === 'yes' && $order->get_shipping_total() > 0) {
            $shipping_methods = array();
            foreach ($order->get_shipping_methods() as $shipping_method) {
                $shipping_methods[] = $shipping_method->get_method_title();
            }
            
            $line_items[] = array(
                'Product_Name' => array(
                    'Product_Name' => __('Envío', 'zoho-sync-orders'),
                    'Product_Code' => 'SHIPPING',
                    'Unit_Price' => floatval($order->get_shipping_total()),
                    'Description' => implode(', ', $shipping_methods)
                ),
                'Quantity' => 1,
                'List_Price' => floatval($order->get_shipping_total()),
                'Unit_Price' => floatval($order->get_shipping_total()),
                'Total' => floatval($order->get_shipping_total()),
                'Product_Description' => __('Costo de envío', 'zoho-sync-orders')
            );
        }
        
        $zoho_data['Quoted_Items'] = $line_items;
        
        return $zoho_data;
    }
    
    /**
     * Map order totals and taxes
     *
     * @param \WC_Order $order WooCommerce order
     * @param array $zoho_data Zoho data array
     * @return array Updated Zoho data
     */
    private function map_totals($order, $zoho_data) {
        // Subtotal
        $zoho_data['Sub_Total'] = floatval($order->get_subtotal());
        
        // Discount
        if ($order->get_discount_total() > 0) {
            $zoho_data['Discount'] = floatval($order->get_discount_total());
        }
        
        // Tax
        if (get_option('zoho_sync_orders_include_taxes', 'yes') === 'yes' && $order->get_total_tax() > 0) {
            $zoho_data['Tax'] = floatval($order->get_total_tax());
            
            // Tax details
            $tax_details = array();
            foreach ($order->get_tax_totals() as $tax_code => $tax) {
                $tax_details[] = array(
                    'tax_name' => $tax->label,
                    'tax_amount' => floatval($tax->amount)
                );
            }
            $zoho_data['Tax_Details'] = $tax_details;
        }
        
        // Shipping
        if (get_option('zoho_sync_orders_include_shipping', 'yes') === 'yes' && $order->get_shipping_total() > 0) {
            $zoho_data['Shipping_Charge'] = floatval($order->get_shipping_total());
        }
        
        // Grand total
        $zoho_data['Grand_Total'] = floatval($order->get_total());
        
        return $zoho_data;
    }
    
    /**
     * Map payment information
     *
     * @param \WC_Order $order WooCommerce order
     * @param array $zoho_data Zoho data array
     * @return array Updated Zoho data
     */
    private function map_payment_info($order, $zoho_data) {
        $payment_method = $order->get_payment_method();
        $payment_title = $order->get_payment_method_title();
        
        // Map payment method
        if (isset($this->payment_mapping[$payment_method])) {
            $zoho_data['Payment_Method'] = $this->payment_mapping[$payment_method];
        } else {
            $zoho_data['Payment_Method'] = $payment_title ?: __('Otro', 'zoho-sync-orders');
        }
        
        // Payment status
        if ($order->is_paid()) {
            $zoho_data['Payment_Status'] = 'Paid';
            $zoho_data['Payment_Date'] = $order->get_date_paid() ? $order->get_date_paid()->format('Y-m-d') : null;
        } else {
            $zoho_data['Payment_Status'] = 'Pending';
        }
        
        // Transaction ID if available
        if ($order->get_transaction_id()) {
            $zoho_data['Transaction_ID'] = $order->get_transaction_id();
        }
        
        return $zoho_data;
    }
    
    /**
     * Map custom fields
     *
     * @param \WC_Order $order WooCommerce order
     * @param array $zoho_data Zoho data array
     * @return array Updated Zoho data
     */
    private function map_custom_fields($order, $zoho_data) {
        // Get custom field mappings
        $custom_mappings = get_option('zoho_sync_orders_custom_fields', array());
        
        foreach ($custom_mappings as $zoho_field => $woo_field) {
            $value = null;
            
            // Check if it's a meta field
            if (strpos($woo_field, 'meta:') === 0) {
                $meta_key = substr($woo_field, 5);
                $value = $order->get_meta($meta_key);
            } else {
                // Standard order field
                $method = 'get_' . $woo_field;
                if (method_exists($order, $method)) {
                    $value = $order->$method();
                }
            }
            
            if ($value !== null && $value !== '') {
                $zoho_data[$zoho_field] = $value;
            }
        }
        
        // Add order meta data
        foreach ($order->get_meta_data() as $meta) {
            $key = $meta->key;
            $value = $meta->value;
            
            // Skip private meta fields
            if (strpos($key, '_') === 0) {
                continue;
            }
            
            // Add to custom fields
            $zoho_data['Custom_Fields'][$key] = $value;
        }
        
        return $zoho_data;
    }
    
    /**
     * Map WooCommerce order status to Zoho quote stage
     *
     * @param string $woo_status WooCommerce order status
     * @return string Zoho quote stage
     */
    private function map_order_status($woo_status) {
        $status_mapping = array(
            'pending' => 'Draft',
            'processing' => 'Negotiation',
            'on-hold' => 'Negotiation',
            'completed' => 'Closed Won',
            'cancelled' => 'Closed Lost',
            'refunded' => 'Closed Lost',
            'failed' => 'Closed Lost'
        );
        
        return isset($status_mapping[$woo_status]) ? $status_mapping[$woo_status] : 'Draft';
    }
    
    /**
     * Get field mapping configuration
     *
     * @return array Field mappings
     */
    public function get_field_mappings() {
        return $this->default_mapping;
    }
    
    /**
     * Get payment method mappings
     *
     * @return array Payment mappings
     */
    public function get_payment_mappings() {
        return $this->payment_mapping;
    }
    
    /**
     * Update field mapping
     *
     * @param array $mappings New field mappings
     * @return bool Success status
     */
    public function update_field_mappings($mappings) {
        return update_option('zoho_sync_orders_field_mapping', $mappings);
    }
    
    /**
     * Update payment method mappings
     *
     * @param array $mappings New payment mappings
     * @return bool Success status
     */
    public function update_payment_mappings($mappings) {
        return update_option('zoho_sync_orders_payment_mapping', $mappings);
    }
    
    /**
     * Validate mapped data
     *
     * @param array $zoho_data Mapped data
     * @return array Validation result
     */
    public function validate_mapped_data($zoho_data) {
        $errors = array();
        
        // Required fields validation
        $required_fields = array('Subject', 'Contact_Name', 'Quoted_Items');
        
        foreach ($required_fields as $field) {
            if (empty($zoho_data[$field])) {
                $errors[] = sprintf(__('Campo requerido faltante: %s', 'zoho-sync-orders'), $field);
            }
        }
        
        // Validate line items
        if (isset($zoho_data['Quoted_Items']) && is_array($zoho_data['Quoted_Items'])) {
            foreach ($zoho_data['Quoted_Items'] as $index => $item) {
                if (empty($item['Product_Name'])) {
                    $errors[] = sprintf(__('Producto faltante en línea %d', 'zoho-sync-orders'), $index + 1);
                }
                if (empty($item['Quantity']) || $item['Quantity'] <= 0) {
                    $errors[] = sprintf(__('Cantidad inválida en línea %d', 'zoho-sync-orders'), $index + 1);
                }
            }
        }
        
        // Validate totals
        if (isset($zoho_data['Grand_Total']) && $zoho_data['Grand_Total'] <= 0) {
            $errors[] = __('Total del pedido debe ser mayor a cero', 'zoho-sync-orders');
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
}