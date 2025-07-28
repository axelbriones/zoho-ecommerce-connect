<?php
/**
 * Pricing Manager Class
 *
 * Manages level-based pricing for distributors and B2B customers
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZohoSyncCustomers_PricingManager class
 */
class ZohoSyncCustomers_PricingManager {
    
    /**
     * Instance of this class
     *
     * @var ZohoSyncCustomers_PricingManager
     */
    private static $instance = null;
    
    /**
     * Distributor manager instance
     *
     * @var ZohoSyncCustomers_DistributorManager
     */
    private $distributor_manager;
    
    /**
     * Price calculation cache
     *
     * @var array
     */
    private $price_cache = array();
    
    /**
     * Cache group for pricing
     *
     * @var string
     */
    private $cache_group = 'zscu_pricing';
    
    /**
     * Get instance
     *
     * @return ZohoSyncCustomers_PricingManager
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
        $this->distributor_manager = ZohoSyncCustomers_DistributorManager::instance();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // WooCommerce price filters
        add_filter('woocommerce_product_get_price', array($this, 'get_customer_price'), 10, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'get_customer_regular_price'), 10, 2);
        add_filter('woocommerce_product_get_sale_price', array($this, 'get_customer_sale_price'), 10, 2);
        
        // Variable product price filters
        add_filter('woocommerce_product_variation_get_price', array($this, 'get_customer_price'), 10, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'get_customer_regular_price'), 10, 2);
        add_filter('woocommerce_product_variation_get_sale_price', array($this, 'get_customer_sale_price'), 10, 2);
        
        // Cart and checkout price filters
        add_filter('woocommerce_cart_item_price', array($this, 'cart_item_price'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'cart_item_subtotal'), 10, 3);
        
        // Price display filters
        add_filter('woocommerce_get_price_html', array($this, 'get_price_html'), 10, 2);
        add_filter('woocommerce_format_price_range', array($this, 'format_price_range'), 10, 3);
        
        // Admin price display
        add_action('woocommerce_product_options_pricing', array($this, 'add_distributor_price_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_distributor_price_fields'));
        
        // AJAX handlers
        add_action('wp_ajax_get_distributor_price', array($this, 'ajax_get_distributor_price'));
        add_action('wp_ajax_nopriv_get_distributor_price', array($this, 'ajax_get_distributor_price'));
        
        // Cache management
        add_action('woocommerce_product_object_updated_props', array($this, 'clear_product_price_cache'), 10, 2);
        
        // Custom pricing hooks
        add_filter('woocommerce_product_get_price', [$this, 'apply_special_pricing'], 20, 2);
        add_filter('woocommerce_get_price_html', [$this, 'modify_price_display'], 20, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'apply_cart_pricing']);
    }
    
    /**
     * Get customer-specific price for product
     *
     * @param float $price Original price
     * @param WC_Product $product Product object
     * @return float Modified price
     */
    public function get_customer_price($price, $product) {
        if (!$this->should_apply_custom_pricing()) {
            return $price;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $price;
        }
        
        $custom_price = $this->calculate_customer_price($price, $product, $user_id);
        return $custom_price !== false ? $custom_price : $price;
    }
    
    /**
     * Get customer-specific regular price
     *
     * @param float $price Original regular price
     * @param WC_Product $product Product object
     * @return float Modified price
     */
    public function get_customer_regular_price($price, $product) {
        if (!$this->should_apply_custom_pricing()) {
            return $price;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $price;
        }
        
        $custom_price = $this->calculate_customer_price($price, $product, $user_id, 'regular');
        return $custom_price !== false ? $custom_price : $price;
    }
    
    /**
     * Get customer-specific sale price
     *
     * @param float $price Original sale price
     * @param WC_Product $product Product object
     * @return float Modified price
     */
    public function get_customer_sale_price($price, $product) {
        if (!$this->should_apply_custom_pricing() || empty($price)) {
            return $price;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $price;
        }
        
        $custom_price = $this->calculate_customer_price($price, $product, $user_id, 'sale');
        return $custom_price !== false ? $custom_price : $price;
    }
    
    /**
     * Calculate customer-specific price
     *
     * @param float $original_price Original price
     * @param WC_Product $product Product object
     * @param int $user_id User ID
     * @param string $price_type Price type (regular, sale, or current)
     * @return float|false Custom price or false if no custom pricing
     */
    private function calculate_customer_price($original_price, $product, $user_id, $price_type = 'current') {
        if (empty($original_price) || $original_price <= 0) {
            return false;
        }
        
        // Check cache first
        $cache_key = $this->get_price_cache_key($product->get_id(), $user_id, $price_type);
        if (isset($this->price_cache[$cache_key])) {
            return $this->price_cache[$cache_key];
        }
        
        $custom_price = false;
        
        // Check for product-specific distributor prices
        $product_specific_price = $this->get_product_specific_price($product, $user_id, $price_type);
        if ($product_specific_price !== false) {
            $custom_price = $product_specific_price;
        } else {
            // Apply level-based discount
            $discount = $this->get_user_discount($user_id);
            if ($discount > 0) {
                $custom_price = $original_price * (1 - ($discount / 100));
            }
        }
        
        // Apply additional filters
        $custom_price = apply_filters('zoho_customers_calculated_price', $custom_price, $original_price, $product, $user_id, $price_type);
        
        // Cache the result
        $this->price_cache[$cache_key] = $custom_price;
        
        return $custom_price;
    }
    
    /**
     * Get product-specific distributor price
     *
     * @param WC_Product $product Product object
     * @param int $user_id User ID
     * @param string $price_type Price type
     * @return float|false Product-specific price or false
     */
    private function get_product_specific_price($product, $user_id, $price_type) {
        $level_key = get_user_meta($user_id, 'distributor_level', true);
        if (empty($level_key)) {
            return false;
        }
        
        $meta_key = "distributor_price_{$level_key}";
        if ($price_type === 'regular') {
            $meta_key = "distributor_regular_price_{$level_key}";
        } elseif ($price_type === 'sale') {
            $meta_key = "distributor_sale_price_{$level_key}";
        }
        
        $specific_price = $product->get_meta($meta_key);
        
        if (!empty($specific_price) && is_numeric($specific_price)) {
            return floatval($specific_price);
        }
        
        return false;
    }
    
    /**
     * Get user's discount percentage
     *
     * @param int $user_id User ID
     * @return float Discount percentage
     */
    private function get_user_discount($user_id) {
        // Check if user is distributor
        if ($this->distributor_manager->is_distributor($user_id)) {
            return $this->distributor_manager->get_user_discount($user_id);
        }
        
        // Check if user is B2B customer
        $user = get_user_by('id', $user_id);
        if ($user && in_array('b2b_customer', $user->roles)) {
            $b2b_discount = get_option('zoho_customers_b2b_discount', 5);
            return floatval($b2b_discount);
        }
        
        return 0;
    }
    
    /**
     * Modify cart item price display
     *
     * @param string $price_html Price HTML
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified price HTML
     */
    public function cart_item_price($price_html, $cart_item, $cart_item_key) {
        if (!$this->should_apply_custom_pricing()) {
            return $price_html;
        }
        
        $product = $cart_item['data'];
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return $price_html;
        }
        
        $custom_price = $this->calculate_customer_price($product->get_price(), $product, $user_id);
        
        if ($custom_price !== false && $custom_price != $product->get_price()) {
            return wc_price($custom_price);
        }
        
        return $price_html;
    }
    
    /**
     * Modify cart item subtotal display
     *
     * @param string $subtotal_html Subtotal HTML
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified subtotal HTML
     */
    public function cart_item_subtotal($subtotal_html, $cart_item, $cart_item_key) {
        if (!$this->should_apply_custom_pricing()) {
            return $subtotal_html;
        }
        
        $product = $cart_item['data'];
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return $subtotal_html;
        }
        
        $custom_price = $this->calculate_customer_price($product->get_price(), $product, $user_id);
        
        if ($custom_price !== false && $custom_price != $product->get_price()) {
            $subtotal = $custom_price * $cart_item['quantity'];
            return wc_price($subtotal);
        }
        
        return $subtotal_html;
    }
    
    /**
     * Modify price HTML display
     *
     * @param string $price_html Price HTML
     * @param WC_Product $product Product object
     * @return string Modified price HTML
     */
    public function get_price_html($price_html, $product) {
        if (!$this->should_apply_custom_pricing()) {
            return $price_html;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $this->get_guest_price_html($price_html, $product);
        }
        
        $original_price = $product->get_price();
        $custom_price = $this->calculate_customer_price($original_price, $product, $user_id);
        
        if ($custom_price !== false && $custom_price != $original_price) {
            $discount_percentage = $this->get_user_discount($user_id);
            
            if ($discount_percentage > 0) {
                $price_html = $this->format_distributor_price_html($original_price, $custom_price, $discount_percentage);
            } else {
                $price_html = wc_price($custom_price);
            }
        }
        
        return $price_html;
    }
    
    /**
     * Get price HTML for guest users
     *
     * @param string $price_html Original price HTML
     * @param WC_Product $product Product object
     * @return string Modified price HTML
     */
    private function get_guest_price_html($price_html, $product) {
        $hide_prices = get_option('zoho_customers_hide_prices_guests', 'no');
        
        if ($hide_prices === 'yes') {
            return '<span class="price-hidden">' . __('Inicia sesión para ver precios', 'zoho-sync-customers') . '</span>';
        }
        
        return $price_html;
    }
    
    /**
     * Format distributor price HTML with discount info
     *
     * @param float $original_price Original price
     * @param float $custom_price Custom price
     * @param float $discount_percentage Discount percentage
     * @return string Formatted price HTML
     */
    private function format_distributor_price_html($original_price, $custom_price, $discount_percentage) {
        $show_original = get_option('zoho_customers_show_original_price', 'yes');
        
        if ($show_original === 'yes') {
            $html = '<span class="price">';
            $html .= '<del class="original-price">' . wc_price($original_price) . '</del> ';
            $html .= '<ins class="distributor-price">' . wc_price($custom_price) . '</ins>';
            $html .= ' <span class="discount-badge">-' . round($discount_percentage) . '%</span>';
            $html .= '</span>';
        } else {
            $html = '<span class="price distributor-price">' . wc_price($custom_price) . '</span>';
        }
        
        return $html;
    }
    
    /**
     * Format price range for variable products
     *
     * @param string $price_html Price range HTML
     * @param float $min_price Minimum price
     * @param float $max_price Maximum price
     * @return string Modified price range HTML
     */
    public function format_price_range($price_html, $min_price, $max_price) {
        if (!$this->should_apply_custom_pricing()) {
            return $price_html;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $price_html;
        }
        
        $discount = $this->get_user_discount($user_id);
        if ($discount > 0) {
            $custom_min = $min_price * (1 - ($discount / 100));
            $custom_max = $max_price * (1 - ($discount / 100));
            
            return wc_price($custom_min) . ' - ' . wc_price($custom_max);
        }
        
        return $price_html;
    }
    
    /**
     * Add distributor price fields to product admin
     */
    public function add_distributor_price_fields() {
        global $post;
        
        $levels = $this->distributor_manager->get_distributor_levels();
        
        echo '<div class="options_group distributor-pricing">';
        echo '<h4>' . __('Precios por Nivel de Distribuidor', 'zoho-sync-customers') . '</h4>';
        
        foreach ($levels as $level_key => $level) {
            $regular_price = get_post_meta($post->ID, "distributor_regular_price_{$level_key}", true);
            $sale_price = get_post_meta($post->ID, "distributor_sale_price_{$level_key}", true);
            
            echo '<div class="distributor-level-prices">';
            echo '<h5>' . esc_html($level['name']) . ' (' . $level['discount'] . '% descuento)</h5>';
            
            woocommerce_wp_text_input(array(
                'id' => "distributor_regular_price_{$level_key}",
                'label' => __('Precio Regular', 'zoho-sync-customers') . ' (' . get_woocommerce_currency_symbol() . ')',
                'placeholder' => __('Automático', 'zoho-sync-customers'),
                'value' => $regular_price,
                'data_type' => 'price'
            ));
            
            woocommerce_wp_text_input(array(
                'id' => "distributor_sale_price_{$level_key}",
                'label' => __('Precio de Oferta', 'zoho-sync-customers') . ' (' . get_woocommerce_currency_symbol() . ')',
                'placeholder' => __('Automático', 'zoho_sync_customers'),
                'value' => $sale_price,
                'data_type' => 'price'
            ));
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Save distributor price fields
     *
     * @param int $post_id Product ID
     */
    public function save_distributor_price_fields($post_id) {
        $levels = $this->distributor_manager->get_distributor_levels();
        
        foreach ($levels as $level_key => $level) {
            $regular_price_key = "distributor_regular_price_{$level_key}";
            $sale_price_key = "distributor_sale_price_{$level_key}";
            
            if (isset($_POST[$regular_price_key])) {
                $regular_price = wc_format_decimal($_POST[$regular_price_key]);
                update_post_meta($post_id, $regular_price_key, $regular_price);
            }
            
            if (isset($_POST[$sale_price_key])) {
                $sale_price = wc_format_decimal($_POST[$sale_price_key]);
                update_post_meta($post_id, $sale_price_key, $sale_price);
            }
        }
        
        // Clear price cache for this product
        $this->clear_product_price_cache($post_id);
    }
    
    /**
     * AJAX handler for getting distributor price
     */
    public function ajax_get_distributor_price() {
        check_ajax_referer('zoho_customers_pricing', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$product_id) {
            wp_send_json_error(__('Datos inválidos', 'zoho-sync-customers'));
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Producto no encontrado', 'zoho-sync-customers'));
        }
        
        $original_price = $product->get_price();
        $custom_price = $this->calculate_customer_price($original_price, $product, $user_id);
        
        $response = array(
            'original_price' => $original_price,
            'custom_price' => $custom_price,
            'discount' => $this->get_user_discount($user_id),
            'price_html' => $this->get_price_html($product->get_price_html(), $product)
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * Check if custom pricing should be applied
     *
     * @return bool
     */
    private function should_apply_custom_pricing() {
        // Don't apply in admin unless specifically requested
        if (is_admin() && !wp_doing_ajax()) {
            return false;
        }
        
        // Check if pricing is enabled
        $pricing_enabled = get_option('zoho_customers_pricing_enabled', 'yes');
        if ($pricing_enabled !== 'yes') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get price cache key
     *
     * @param int $product_id Product ID
     * @param int $user_id User ID
     * @param string $price_type Price type
     * @return string Cache key
     */
    private function get_price_cache_key($product_id, $user_id, $price_type) {
        return "price_{$product_id}_{$user_id}_{$price_type}";
    }
    
    /**
     * Clear product price cache
     *
     * @param int $product_id Product ID
     * @param array $updated_props Updated properties
     */
    public function clear_product_price_cache($product_id, $updated_props = array()) {
        // Clear all cached prices for this product
        foreach ($this->price_cache as $key => $value) {
            if (strpos($key, "price_{$product_id}_") === 0) {
                unset($this->price_cache[$key]);
            }
        }
    }
    
    /**
     * Get pricing rules for user
     *
     * @param int $user_id User ID
     * @return array Pricing rules
     */
    public function get_user_pricing_rules($user_id) {
        $rules = array(
            'is_distributor' => $this->distributor_manager->is_distributor($user_id),
            'is_b2b' => false,
            'discount' => 0,
            'level' => null,
            'min_order' => 0,
            'credit_limit' => 0
        );
        
        if ($rules['is_distributor']) {
            $level = $this->distributor_manager->get_user_distributor_level($user_id);
            if ($level) {
                $rules['level'] = $level;
                $rules['discount'] = $level['discount'];
                $rules['min_order'] = $level['min_order'];
                $rules['credit_limit'] = $level['credit_limit'];
            }
        } else {
            $user = get_user_by('id', $user_id);
            if ($user && in_array('b2b_customer', $user->roles)) {
                $rules['is_b2b'] = true;
                $rules['discount'] = get_option('zoho_customers_b2b_discount', 5);
            }
        }
        
        return $rules;
    }
    
    /**
     * Check if user meets minimum order requirement
     *
     * @param int $user_id User ID
     * @param float $order_total Order total
     * @return bool Meets requirement
     */
    public function user_meets_min_order($user_id, $order_total) {
        $rules = $this->get_user_pricing_rules($user_id);
        
        if ($rules['min_order'] > 0) {
            return $order_total >= $rules['min_order'];
        }
        
        return true;
    }
    
    /**
     * Get user's available credit
     *
     * @param int $user_id User ID
     * @return float Available credit
     */
    public function get_user_available_credit($user_id) {
        $rules = $this->get_user_pricing_rules($user_id);
        
        if ($rules['credit_limit'] > 0) {
            $used_credit = $this->get_user_used_credit($user_id);
            return max(0, $rules['credit_limit'] - $used_credit);
        }
        
        return 0;
    }
    
    /**
     * Get user's used credit
     *
     * @param int $user_id User ID
     * @return float Used credit
     */
    private function get_user_used_credit($user_id) {
        // This would calculate based on pending orders, unpaid invoices, etc.
        // Implementation depends on business logic
        return 0;
    }
    
    /**
     * Get pricing statistics
     *
     * @return array Statistics
     */
    public function get_pricing_stats() {
        $stats = array(
            'total_products_with_custom_prices' => 0,
            'average_discount_applied' => 0,
            'total_savings_this_month' => 0
        );
        
        // This would be implemented based on actual usage data
        return $stats;
    }
    
    /**
     * Export pricing data
     *
     * @param array $args Export arguments
     * @return array Export data
     */
    public function export_pricing_data($args = array()) {
        $defaults = array(
            'format' => 'csv',
            'include_products' => true,
            'include_users' => true,
            'date_range' => 'all'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $data = array();
        
        if ($args['include_products']) {
            $data['products'] = $this->get_products_pricing_data();
        }
        
        if ($args['include_users']) {
            $data['users'] = $this->get_users_pricing_data();
        }
        
        return $data;
    }
    
    /**
     * Get products pricing data
     *
     * @return array Products data
     */
    private function get_products_pricing_data() {
        $products = wc_get_products(array(
            'limit' => -1,
            'status' => 'publish'
        ));
        
        $data = array();
        $levels = $this->distributor_manager->get_distributor_levels();
        
        foreach ($products as $product) {
            $product_data = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price()
            );
            
            foreach ($levels as $level_key => $level) {
                $product_data["distributor_regular_price_{$level_key}"] = $product->get_meta("distributor_regular_price_{$level_key}");
                $product_data["distributor_sale_price_{$level_key}"] = $product->get_meta("distributor_sale_price_{$level_key}");
            }
            
            $data[] = $product_data;
        }
        
        return $data;
    }
    
    /**
     * Get users pricing data
     *
     * @return array Users data
     */
    private function get_users_pricing_data() {
        $distributors = $this->distributor_manager->get_distributors();
        
        $data = array();
        
        foreach ($distributors as $distributor) {
            $data[] = array(
                'user_id' => $distributor['user_id'],
                'display_name' => $distributor['display_name'],
                'email' => $distributor['email'],
                'level' => $distributor['level_key'],
                'discount' => $distributor['discount'],
                'approval_status' => $distributor['approval_status']
            );
        }
        
        return $data;
    }
    
    /**
     * Get special price for a product
     *
     * @param int $product_id Product ID
     * @param int|null $user_id User ID
     * @return float|null Special price or null
     */
    public function get_special_price($product_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Verificar caché primero
        $cache_key = "special_price_{$product_id}_{$user_id}";
        $cached_price = wp_cache_get($cache_key, $this->cache_group);
        
        if (false !== $cached_price) {
            return $cached_price;
        }

        $price = $this->calculate_special_price($product_id, $user_id);
        
        // Cachear el resultado
        wp_cache_set($cache_key, $price, $this->cache_group, HOUR_IN_SECONDS);
        
        return $price;
    }

    /**
     * Calculate special price for a product
     *
     * @param int $product_id Product ID
     * @param int $user_id User ID
     * @return float|false Calculated special price or false on failure
     */
    private function calculate_special_price($product_id, $user_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }

        $base_price = $product->get_regular_price();
        
        // Obtener descuento del distribuidor
        $discount = $this->get_user_discount($user_id);
        
        // Aplicar descuentos adicionales (volumen, temporada, etc.)
        $additional_discounts = $this->get_additional_discounts($product_id, $user_id);
        
        $final_discount = $discount + $additional_discounts;
        
        // Limitar descuento máximo
        $final_discount = min($final_discount, 100);
        
        return $base_price - ($base_price * ($final_discount / 100));
    }

    /**
     * Get additional discounts for a user on a product
     *
     * @param int $product_id Product ID
     * @param int $user_id User ID
     * @return float Additional discount amount
     */
    private function get_additional_discounts($product_id, $user_id) {
        $additional = 0;
        
        // Descuento por volumen
        $volume_discount = $this->get_volume_discount($product_id, $user_id);
        $additional += $volume_discount;
        
        // Descuento por temporada
        $seasonal_discount = $this->get_seasonal_discount($product_id);
        $additional += $seasonal_discount;
        
        // Descuentos personalizados
        $custom_discount = apply_filters('zscu_custom_discount', 0, $product_id, $user_id);
        $additional += $custom_discount;
        
        return $additional;
    }

    /**
     * Get volume discount based on purchase history
     *
     * @param int $product_id Product ID
     * @param int $user_id User ID
     * @return float Volume discount percentage
     */
    private function get_volume_discount($product_id, $user_id) {
        // Obtener historial de compras del producto
        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => 'completed',
            'date_created' => '>' . date('Y-m-d', strtotime('-1 year'))
        ]);

        $total_quantity = 0;
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $product_id) {
                    $total_quantity += $item->get_quantity();
                }
            }
        }

        // Aplicar descuento según cantidad
        if ($total_quantity > 1000) return 10;
        if ($total_quantity > 500) return 7;
        if ($total_quantity > 100) return 5;
        
        return 0;
    }

    /**
     * Get seasonal discount for a product
     *
     * @param int $product_id Product ID
     * @return float Seasonal discount percentage
     */
    private function get_seasonal_discount($product_id) {
        $seasonal_discounts = get_option('zscu_seasonal_discounts', []);
        $current_month = date('n');
        
        foreach ($seasonal_discounts as $discount) {
            if ($discount['month'] == $current_month && 
                in_array($product_id, $discount['products'])) {
                return floatval($discount['amount']);
            }
        }
        
        return 0;
    }

    /**
     * Apply special pricing to product
     *
     * @param float $price Original price
     * @param WC_Product $product Product object
     * @return float Modified price
     */
    public function apply_special_pricing($price, $product) {
        if (!is_user_logged_in()) {
            return $price;
        }

        $user_id = get_current_user_id();
        $special_price = $this->get_special_price($product->get_id(), $user_id);
        
        return $special_price ?: $price;
    }

    /**
     * Modify price display HTML
     *
     * @param string $price_html Price HTML
     * @param WC_Product $product Product object
     * @return string Modified price HTML
     */
    public function modify_price_display($price_html, $product) {
        if (!is_user_logged_in() || !$this->distributor_manager->is_distributor(get_current_user_id())) {
            return $price_html;
        }

        $special_price = $this->get_special_price($product->get_id());
        if (!$special_price) {
            return $price_html;
        }

        $regular_price = $product->get_regular_price();
        $savings = $regular_price - $special_price;
        $savings_percent = ($savings / $regular_price) * 100;

        return sprintf(
            '<del>%s</del> <ins>%s</ins><br><span class="savings">%s</span>',
            wc_price($regular_price),
            wc_price($special_price),
            sprintf(__('Ahorras: %s (%d%%)', 'zoho-sync-customers'), 
                wc_price($savings),
                round($savings_percent)
            )
        );
    }

    /**
     * Apply special pricing to cart items
     *
     * @param WC_Cart $cart Cart object
     */
    public function apply_cart_pricing($cart) {
        if (!is_user_logged_in() || !$this->distributor_manager->is_distributor(get_current_user_id())) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            $special_price = $this->get_special_price($cart_item['product_id']);
            if ($special_price) {
                $cart_item['data']->set_price($special_price);
            }
        }
    }
}