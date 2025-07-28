<?php
/**
 * Zoho Orders API Class
 *
 * Handles communication with Zoho CRM and Books APIs for order synchronization
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
 * Zoho Orders API handler
 */
class ZohoOrdersApi {
    
    /**
     * API client instance
     *
     * @var \ZohoSyncCore\ApiClient
     */
    private $api_client;
    
    /**
     * Auth manager instance
     *
     * @var \ZohoSyncCore\AuthManager
     */
    private $auth_manager;
    
    /**
     * API endpoints
     *
     * @var array
     */
    private $endpoints = array(
        'crm' => array(
            'quotes' => '/crm/v2/Quotes',
            'salesorders' => '/crm/v2/Sales_Orders',
            'contacts' => '/crm/v2/Contacts',
            'products' => '/crm/v2/Products'
        ),
        'books' => array(
            'estimates' => '/books/v3/estimates',
            'salesorders' => '/books/v3/salesorders',
            'contacts' => '/books/v3/contacts',
            'items' => '/books/v3/items'
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new \ZohoSyncCore\ApiClient();
        $this->auth_manager = new \ZohoSyncCore\AuthManager();
    }
    
    /**
     * Create quote in Zoho CRM
     *
     * @param array $quote_data Quote data
     * @return array API response
     */
    public function create_quote($quote_data) {
        try {
            // Prepare quote data for CRM
            $crm_data = $this->prepare_crm_quote_data($quote_data);
            
            // Make API request
            $response = $this->api_client->post(
                $this->endpoints['crm']['quotes'],
                array('data' => array($crm_data)),
                $this->get_auth_headers()
            );
            
            if ($response['success'] && isset($response['data']['data'][0]['details']['id'])) {
                $zoho_id = $response['data']['data'][0]['details']['id'];
                
                // Log success
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Cotización creada en Zoho CRM con ID: %s', 'zoho-sync-orders'), $zoho_id),
                    'info',
                    'orders'
                );
                
                return array(
                    'success' => true,
                    'zoho_id' => $zoho_id,
                    'response' => $response['data']
                );
            } else {
                throw new \Exception($this->extract_error_message($response));
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error creando cotización en Zoho CRM: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
            
            throw $e;
        }
    }
    
    /**
     * Update quote in Zoho CRM
     *
     * @param string $quote_id Zoho quote ID
     * @param array $quote_data Updated quote data
     * @return array API response
     */
    public function update_quote($quote_id, $quote_data) {
        try {
            // Prepare quote data for CRM
            $crm_data = $this->prepare_crm_quote_data($quote_data);
            
            // Make API request
            $response = $this->api_client->put(
                $this->endpoints['crm']['quotes'] . '/' . $quote_id,
                array('data' => array($crm_data)),
                $this->get_auth_headers()
            );
            
            if ($response['success']) {
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Cotización actualizada en Zoho CRM ID: %s', 'zoho-sync-orders'), $quote_id),
                    'info',
                    'orders'
                );
                
                return array(
                    'success' => true,
                    'zoho_id' => $quote_id,
                    'response' => $response['data']
                );
            } else {
                throw new \Exception($this->extract_error_message($response));
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error actualizando cotización en Zoho CRM: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
            
            throw $e;
        }
    }
    
    /**
     * Create sales order in Zoho CRM
     *
     * @param array $order_data Order data
     * @return array API response
     */
    public function create_sales_order($order_data) {
        try {
            // Prepare order data for CRM
            $crm_data = $this->prepare_crm_order_data($order_data);
            
            // Make API request
            $response = $this->api_client->post(
                $this->endpoints['crm']['salesorders'],
                array('data' => array($crm_data)),
                $this->get_auth_headers()
            );
            
            if ($response['success'] && isset($response['data']['data'][0]['details']['id'])) {
                $zoho_id = $response['data']['data'][0]['details']['id'];
                
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Orden de venta creada en Zoho CRM con ID: %s', 'zoho-sync-orders'), $zoho_id),
                    'info',
                    'orders'
                );
                
                return array(
                    'success' => true,
                    'zoho_id' => $zoho_id,
                    'response' => $response['data']
                );
            } else {
                throw new \Exception($this->extract_error_message($response));
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error creando orden de venta en Zoho CRM: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
            
            throw $e;
        }
    }
    
    /**
     * Update sales order in Zoho CRM
     *
     * @param string $order_id Zoho order ID
     * @param array $order_data Updated order data
     * @return array API response
     */
    public function update_sales_order($order_id, $order_data) {
        try {
            // Prepare order data for CRM
            $crm_data = $this->prepare_crm_order_data($order_data);
            
            // Make API request
            $response = $this->api_client->put(
                $this->endpoints['crm']['salesorders'] . '/' . $order_id,
                array('data' => array($crm_data)),
                $this->get_auth_headers()
            );
            
            if ($response['success']) {
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Orden de venta actualizada en Zoho CRM ID: %s', 'zoho-sync-orders'), $order_id),
                    'info',
                    'orders'
                );
                
                return array(
                    'success' => true,
                    'zoho_id' => $order_id,
                    'response' => $response['data']
                );
            } else {
                throw new \Exception($this->extract_error_message($response));
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error actualizando orden de venta en Zoho CRM: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
            
            throw $e;
        }
    }
    
    /**
     * Create estimate in Zoho Books
     *
     * @param array $estimate_data Estimate data
     * @return array API response
     */
    public function create_estimate($estimate_data) {
        try {
            // Get organization ID for Books
            $organization_id = get_option('zoho_sync_books_organization_id');
            if (!$organization_id) {
                throw new \Exception(__('ID de organización de Zoho Books no configurado', 'zoho-sync-orders'));
            }
            
            // Prepare estimate data for Books
            $books_data = $this->prepare_books_estimate_data($estimate_data);
            
            // Make API request
            $response = $this->api_client->post(
                $this->endpoints['books']['estimates'] . '?organization_id=' . $organization_id,
                $books_data,
                $this->get_auth_headers()
            );
            
            if ($response['success'] && isset($response['data']['estimate']['estimate_id'])) {
                $zoho_id = $response['data']['estimate']['estimate_id'];
                
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Presupuesto creado en Zoho Books con ID: %s', 'zoho-sync-orders'), $zoho_id),
                    'info',
                    'orders'
                );
                
                return array(
                    'success' => true,
                    'zoho_id' => $zoho_id,
                    'response' => $response['data']
                );
            } else {
                throw new \Exception($this->extract_error_message($response));
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error creando presupuesto en Zoho Books: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
            
            throw $e;
        }
    }
    
    /**
     * Find or create contact in Zoho CRM
     *
     * @param array $contact_data Contact data
     * @return string Contact ID
     */
    public function find_or_create_contact($contact_data) {
        try {
            // Search for existing contact by email
            $search_response = $this->api_client->get(
                $this->endpoints['crm']['contacts'] . '/search?email=' . urlencode($contact_data['Email']),
                array(),
                $this->get_auth_headers()
            );
            
            if ($search_response['success'] && isset($search_response['data']['data'][0]['id'])) {
                // Contact found
                return $search_response['data']['data'][0]['id'];
            }
            
            // Contact not found, create new one
            $create_response = $this->api_client->post(
                $this->endpoints['crm']['contacts'],
                array('data' => array($contact_data)),
                $this->get_auth_headers()
            );
            
            if ($create_response['success'] && isset($create_response['data']['data'][0]['details']['id'])) {
                $contact_id = $create_response['data']['data'][0]['details']['id'];
                
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Contacto creado en Zoho CRM con ID: %s', 'zoho-sync-orders'), $contact_id),
                    'info',
                    'orders'
                );
                
                return $contact_id;
            } else {
                throw new \Exception($this->extract_error_message($create_response));
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error gestionando contacto en Zoho CRM: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
            
            throw $e;
        }
    }
    
    /**
     * Find or create product in Zoho CRM
     *
     * @param array $product_data Product data
     * @return string Product ID
     */
    public function find_or_create_product($product_data) {
        try {
            // Search for existing product by code
            $search_response = $this->api_client->get(
                $this->endpoints['crm']['products'] . '/search?criteria=Product_Code:equals:' . urlencode($product_data['Product_Code']),
                array(),
                $this->get_auth_headers()
            );
            
            if ($search_response['success'] && isset($search_response['data']['data'][0]['id'])) {
                // Product found
                return $search_response['data']['data'][0]['id'];
            }
            
            // Product not found, create new one
            $create_response = $this->api_client->post(
                $this->endpoints['crm']['products'],
                array('data' => array($product_data)),
                $this->get_auth_headers()
            );
            
            if ($create_response['success'] && isset($create_response['data']['data'][0]['details']['id'])) {
                $product_id = $create_response['data']['data'][0]['details']['id'];
                
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Producto creado en Zoho CRM con ID: %s', 'zoho-sync-orders'), $product_id),
                    'info',
                    'orders'
                );
                
                return $product_id;
            } else {
                throw new \Exception($this->extract_error_message($create_response));
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error gestionando producto en Zoho CRM: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
            
            throw $e;
        }
    }
    
    /**
     * Get quote from Zoho CRM
     *
     * @param string $quote_id Quote ID
     * @return array Quote data
     */
    public function get_quote($quote_id) {
        try {
            $response = $this->api_client->get(
                $this->endpoints['crm']['quotes'] . '/' . $quote_id,
                array(),
                $this->get_auth_headers()
            );
            
            if ($response['success'] && isset($response['data']['data'][0])) {
                return array(
                    'success' => true,
                    'data' => $response['data']['data'][0]
                );
            } else {
                throw new \Exception($this->extract_error_message($response));
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error obteniendo cotización de Zoho CRM: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
            
            throw $e;
        }
    }
    
    /**
     * Delete quote from Zoho CRM
     *
     * @param string $quote_id Quote ID
     * @return array API response
     */
    public function delete_quote($quote_id) {
        try {
            $response = $this->api_client->delete(
                $this->endpoints['crm']['quotes'] . '/' . $quote_id,
                array(),
                $this->get_auth_headers()
            );
            
            if ($response['success']) {
                \ZohoSyncCore\Logger::log(
                    sprintf(__('Cotización eliminada de Zoho CRM ID: %s', 'zoho-sync-orders'), $quote_id),
                    'info',
                    'orders'
                );
                
                return array(
                    'success' => true,
                    'response' => $response['data']
                );
            } else {
                throw new \Exception($this->extract_error_message($response));
            }
            
        } catch (\Exception $e) {
            \ZohoSyncCore\Logger::log(
                sprintf(__('Error eliminando cotización de Zoho CRM: %s', 'zoho-sync-orders'), $e->getMessage()),
                'error',
                'orders'
            );
            
            throw $e;
        }
    }
    
    /**
     * Prepare quote data for Zoho CRM
     *
     * @param array $quote_data Raw quote data
     * @return array Formatted CRM data
     */
    private function prepare_crm_quote_data($quote_data) {
        $crm_data = array();
        
        // Basic quote information
        $crm_data['Subject'] = $quote_data['Subject'];
        $crm_data['Quote_Stage'] = $quote_data['Quote_Stage'] ?? 'Draft';
        $crm_data['Valid_Till'] = $quote_data['Valid_Till'];
        
        // Handle contact
        if (is_array($quote_data['Contact_Name'])) {
            // Create contact if data is provided
            $contact_id = $this->find_or_create_contact($quote_data['Contact_Name']);
            $crm_data['Contact_Name'] = $contact_id;
        } else {
            // Use existing contact ID
            $crm_data['Contact_Name'] = $quote_data['Contact_Name'];
        }
        
        // Address information
        $crm_data['Billing_Street'] = $quote_data['Billing_Street'] ?? '';
        $crm_data['Billing_City'] = $quote_data['Billing_City'] ?? '';
        $crm_data['Billing_State'] = $quote_data['Billing_State'] ?? '';
        $crm_data['Billing_Code'] = $quote_data['Billing_Code'] ?? '';
        $crm_data['Billing_Country'] = $quote_data['Billing_Country'] ?? '';
        
        $crm_data['Shipping_Street'] = $quote_data['Shipping_Street'] ?? '';
        $crm_data['Shipping_City'] = $quote_data['Shipping_City'] ?? '';
        $crm_data['Shipping_State'] = $quote_data['Shipping_State'] ?? '';
        $crm_data['Shipping_Code'] = $quote_data['Shipping_Code'] ?? '';
        $crm_data['Shipping_Country'] = $quote_data['Shipping_Country'] ?? '';
        
        // Line items
        $quoted_items = array();
        foreach ($quote_data['Quoted_Items'] as $item) {
            $line_item = array();
            
            // Handle product
            if (is_array($item['Product_Name'])) {
                // Create product if data is provided
                $product_id = $this->find_or_create_product($item['Product_Name']);
                $line_item['Product_Name'] = $product_id;
            } else {
                // Use existing product ID
                $line_item['Product_Name'] = $item['Product_Name'];
            }
            
            $line_item['Quantity'] = $item['Quantity'];
            $line_item['List_Price'] = $item['List_Price'];
            $line_item['Unit_Price'] = $item['Unit_Price'];
            $line_item['Total'] = $item['Total'];
            
            if (isset($item['Product_Description'])) {
                $line_item['Product_Description'] = $item['Product_Description'];
            }
            
            $quoted_items[] = $line_item;
        }
        $crm_data['Quoted_Items'] = $quoted_items;
        
        // Totals
        $crm_data['Sub_Total'] = $quote_data['Sub_Total'] ?? 0;
        $crm_data['Discount'] = $quote_data['Discount'] ?? 0;
        $crm_data['Tax'] = $quote_data['Tax'] ?? 0;
        $crm_data['Grand_Total'] = $quote_data['Grand_Total'] ?? 0;
        
        // Additional fields
        if (isset($quote_data['Description'])) {
            $crm_data['Description'] = $quote_data['Description'];
        }
        
        if (isset($quote_data['WooCommerce_Order_ID'])) {
            $crm_data['WooCommerce_Order_ID'] = $quote_data['WooCommerce_Order_ID'];
        }
        
        return $crm_data;
    }
    
    /**
     * Prepare order data for Zoho CRM
     *
     * @param array $order_data Raw order data
     * @return array Formatted CRM data
     */
    private function prepare_crm_order_data($order_data) {
        // Sales orders have similar structure to quotes
        $crm_data = $this->prepare_crm_quote_data($order_data);
        
        // Change quoted items to ordered items
        if (isset($crm_data['Quoted_Items'])) {
            $crm_data['Ordered_Items'] = $crm_data['Quoted_Items'];
            unset($crm_data['Quoted_Items']);
        }
        
        // Add order-specific fields
        $crm_data['Status'] = $order_data['Status'] ?? 'Created';
        
        if (isset($order_data['PO_Date'])) {
            $crm_data['PO_Date'] = $order_data['PO_Date'];
        }
        
        if (isset($order_data['PO_Number'])) {
            $crm_data['PO_Number'] = $order_data['PO_Number'];
        }
        
        return $crm_data;
    }
    
    /**
     * Prepare estimate data for Zoho Books
     *
     * @param array $estimate_data Raw estimate data
     * @return array Formatted Books data
     */
    private function prepare_books_estimate_data($estimate_data) {
        $books_data = array();
        
        // Customer information
        $books_data['customer_name'] = $estimate_data['customer_name'] ?? '';
        $books_data['customer_email'] = $estimate_data['customer_email'] ?? '';
        
        // Estimate details
        $books_data['estimate_number'] = $estimate_data['estimate_number'] ?? '';
        $books_data['reference_number'] = $estimate_data['WooCommerce_Order_ID'] ?? '';
        $books_data['date'] = $estimate_data['date'] ?? date('Y-m-d');
        $books_data['expiry_date'] = $estimate_data['expiry_date'] ?? date('Y-m-d', strtotime('+30 days'));
        
        // Line items
        $line_items = array();
        foreach ($estimate_data['line_items'] as $item) {
            $line_items[] = array(
                'name' => $item['name'],
                'description' => $item['description'] ?? '',
                'rate' => $item['rate'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'qty'
            );
        }
        $books_data['line_items'] = $line_items;
        
        // Totals
        $books_data['discount'] = $estimate_data['discount'] ?? 0;
        $books_data['is_discount_before_tax'] = true;
        $books_data['discount_type'] = 'entity_level';
        
        return $books_data;
    }
    
    /**
     * Get authentication headers
     *
     * @return array Auth headers
     */
    private function get_auth_headers() {
        $access_token = $this->auth_manager->get_access_token();
        
        return array(
            'Authorization' => 'Zoho-oauthtoken ' . $access_token,
            'Content-Type' => 'application/json'
        );
    }
    
    /**
     * Extract error message from API response
     *
     * @param array $response API response
     * @return string Error message
     */
    private function extract_error_message($response) {
        if (isset($response['data']['data'][0]['message'])) {
            return $response['data']['data'][0]['message'];
        }
        
        if (isset($response['data']['message'])) {
            return $response['data']['message'];
        }
        
        if (isset($response['error'])) {
            return $response['error'];
        }
        
        return __('Error desconocido en la API de Zoho', 'zoho-sync-orders');
    }
    
    /**
     * Test API connection
     *
     * @return array Test result
     */
    public function test_connection() {
        try {
            // Test CRM connection
            $crm_response = $this->api_client->get(
                '/crm/v2/org',
                array(),
                $this->get_auth_headers()
            );
            
            if (!$crm_response['success']) {
                throw new \Exception(__('Error conectando con Zoho CRM', 'zoho-sync-orders'));
            }
            
            return array(
                'success' => true,
                'message' => __('Conexión con Zoho exitosa', 'zoho-sync-orders'),
                'crm_org' => $crm_response['data']['org'][0]['company_name'] ?? 'N/A'
            );
            
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get API usage statistics
     *
     * @return array Usage stats
     */
    public function get_api_usage() {
        try {
            // This would typically come from Zoho's API usage endpoints
            // For now, we'll return basic info
            return array(
                'success' => true,
                'daily_limit' => 15000,
                'used_today' => 0, // Would be calculated from actual usage
                'remaining' => 15000
            );
            
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}