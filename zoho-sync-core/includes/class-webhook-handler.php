<?php
/**
 * Zoho Webhook Handler
 * 
 * Handles incoming webhooks from Zoho services
 * 
 * @package ZohoSyncCore
 * @subpackage Includes
 * @since 1.0.0
 * @author Byron Briones <bbrion.es>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Zoho Webhook Handler Class
 * 
 * Processes webhooks from Zoho services for real-time synchronization
 */
class Zoho_Sync_Core_Webhook_Handler {

    /**
     * Logger instance
     * 
     * @var Zoho_Sync_Core_Logger
     */
    private $logger;

    /**
     * Settings Manager instance
     * 
     * @var Zoho_Sync_Core_Settings_Manager
     */
    private $settings_manager;

    /**
     * Supported webhook events
     * 
     * @var array
     */
    private $supported_events = array(
        'inventory.item.create',
        'inventory.item.update',
        'inventory.item.delete',
        'crm.contact.create',
        'crm.contact.update',
        'crm.contact.delete',
        'books.invoice.create',
        'books.invoice.update',
        'books.invoice.delete'
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Zoho_Sync_Core_Logger();
        $this->settings_manager = new Zoho_Sync_Core_Settings_Manager();
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'add_webhook_endpoint'));
        add_action('template_redirect', array($this, 'handle_webhook_request'));
        add_action('wp_ajax_nopriv_zoho_webhook', array($this, 'process_webhook'));
        add_action('wp_ajax_zoho_webhook', array($this, 'process_webhook'));
    }

    /**
     * Add webhook endpoint to WordPress
     */
    public function add_webhook_endpoint() {
        add_rewrite_rule(
            '^zoho-webhook/?$',
            'index.php?zoho_webhook=1',
            'top'
        );
        
        add_rewrite_tag('%zoho_webhook%', '([^&]+)');
    }

    /**
     * Handle incoming webhook requests
     */
    public function handle_webhook_request() {
        if (!get_query_var('zoho_webhook')) {
            return;
        }

        $this->process_webhook();
    }

    /**
     * Process incoming webhook
     */
    public function process_webhook() {
        // Verify request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send_response(405, array('error' => 'Method not allowed'));
            return;
        }

        // Get raw POST data
        $raw_data = file_get_contents('php://input');
        
        if (empty($raw_data)) {
            $this->logger->log('warning', 'Received empty webhook payload');
            $this->send_response(400, array('error' => 'Empty payload'));
            return;
        }

        // Parse JSON data
        $webhook_data = json_decode($raw_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log('error', 'Invalid JSON in webhook payload: ' . json_last_error_msg());
            $this->send_response(400, array('error' => 'Invalid JSON'));
            return;
        }

        // Verify webhook signature if configured
        if (!$this->verify_webhook_signature($raw_data)) {
            $this->logger->log('error', 'Webhook signature verification failed');
            $this->send_response(401, array('error' => 'Unauthorized'));
            return;
        }

        // Log incoming webhook
        $this->logger->log('info', 'Received webhook', array(
            'event' => $webhook_data['event'] ?? 'unknown',
            'service' => $webhook_data['service'] ?? 'unknown',
            'data_size' => strlen($raw_data)
        ));

        // Process webhook based on event type
        $result = $this->process_webhook_event($webhook_data);

        if ($result['success']) {
            $this->send_response(200, array('message' => 'Webhook processed successfully'));
        } else {
            $this->send_response(500, array('error' => $result['message']));
        }
    }

    /**
     * Verify webhook signature
     * 
     * @param string $payload Raw webhook payload
     * @return bool True if signature is valid
     */
    private function verify_webhook_signature($payload) {
        $settings = $this->settings_manager->get_settings();
        $webhook_secret = $settings['webhook_secret'] ?? '';

        // If no secret is configured, skip verification
        if (empty($webhook_secret)) {
            return true;
        }

        $signature_header = $_SERVER['HTTP_X_ZOHO_SIGNATURE'] ?? '';
        
        if (empty($signature_header)) {
            return false;
        }

        $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);
        
        return hash_equals($expected_signature, $signature_header);
    }

    /**
     * Process webhook event
     * 
     * @param array $webhook_data Webhook payload data
     * @return array Processing result
     */
    private function process_webhook_event($webhook_data) {
        $event = $webhook_data['event'] ?? '';
        $service = $webhook_data['service'] ?? '';
        $data = $webhook_data['data'] ?? array();

        // Check if event is supported
        if (!in_array($event, $this->supported_events)) {
            $this->logger->log('warning', 'Unsupported webhook event: ' . $event);
            return array(
                'success' => false,
                'message' => 'Unsupported event type'
            );
        }

        try {
            switch ($service) {
                case 'inventory':
                    return $this->process_inventory_webhook($event, $data);
                
                case 'crm':
                    return $this->process_crm_webhook($event, $data);
                
                case 'books':
                    return $this->process_books_webhook($event, $data);
                
                default:
                    return array(
                        'success' => false,
                        'message' => 'Unknown service: ' . $service
                    );
            }
        } catch (Exception $e) {
            $this->logger->log('error', 'Webhook processing error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Processing error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Process inventory webhook events
     * 
     * @param string $event Event type
     * @param array $data Event data
     * @return array Processing result
     */
    private function process_inventory_webhook($event, $data) {
        switch ($event) {
            case 'inventory.item.create':
            case 'inventory.item.update':
                return $this->sync_inventory_item($data);
            
            case 'inventory.item.delete':
                return $this->delete_inventory_item($data);
            
            default:
                return array(
                    'success' => false,
                    'message' => 'Unknown inventory event: ' . $event
                );
        }
    }

    /**
     * Process CRM webhook events
     * 
     * @param string $event Event type
     * @param array $data Event data
     * @return array Processing result
     */
    private function process_crm_webhook($event, $data) {
        switch ($event) {
            case 'crm.contact.create':
            case 'crm.contact.update':
                return $this->sync_crm_contact($data);
            
            case 'crm.contact.delete':
                return $this->delete_crm_contact($data);
            
            default:
                return array(
                    'success' => false,
                    'message' => 'Unknown CRM event: ' . $event
                );
        }
    }

    /**
     * Process Books webhook events
     * 
     * @param string $event Event type
     * @param array $data Event data
     * @return array Processing result
     */
    private function process_books_webhook($event, $data) {
        switch ($event) {
            case 'books.invoice.create':
            case 'books.invoice.update':
                return $this->sync_books_invoice($data);
            
            case 'books.invoice.delete':
                return $this->delete_books_invoice($data);
            
            default:
                return array(
                    'success' => false,
                    'message' => 'Unknown Books event: ' . $event
                );
        }
    }

    /**
     * Sync inventory item from webhook
     * 
     * @param array $data Item data
     * @return array Processing result
     */
    private function sync_inventory_item($data) {
        // Trigger action for other plugins to handle
        do_action('zoho_sync_inventory_item_webhook', $data);
        
        $this->logger->log('info', 'Inventory item webhook processed', array(
            'item_id' => $data['item_id'] ?? 'unknown'
        ));

        return array(
            'success' => true,
            'message' => 'Inventory item processed'
        );
    }

    /**
     * Delete inventory item from webhook
     * 
     * @param array $data Item data
     * @return array Processing result
     */
    private function delete_inventory_item($data) {
        // Trigger action for other plugins to handle
        do_action('zoho_sync_inventory_item_delete_webhook', $data);
        
        $this->logger->log('info', 'Inventory item deletion webhook processed', array(
            'item_id' => $data['item_id'] ?? 'unknown'
        ));

        return array(
            'success' => true,
            'message' => 'Inventory item deletion processed'
        );
    }

    /**
     * Sync CRM contact from webhook
     * 
     * @param array $data Contact data
     * @return array Processing result
     */
    private function sync_crm_contact($data) {
        // Trigger action for other plugins to handle
        do_action('zoho_sync_crm_contact_webhook', $data);
        
        $this->logger->log('info', 'CRM contact webhook processed', array(
            'contact_id' => $data['contact_id'] ?? 'unknown'
        ));

        return array(
            'success' => true,
            'message' => 'CRM contact processed'
        );
    }

    /**
     * Delete CRM contact from webhook
     * 
     * @param array $data Contact data
     * @return array Processing result
     */
    private function delete_crm_contact($data) {
        // Trigger action for other plugins to handle
        do_action('zoho_sync_crm_contact_delete_webhook', $data);
        
        $this->logger->log('info', 'CRM contact deletion webhook processed', array(
            'contact_id' => $data['contact_id'] ?? 'unknown'
        ));

        return array(
            'success' => true,
            'message' => 'CRM contact deletion processed'
        );
    }

    /**
     * Sync Books invoice from webhook
     * 
     * @param array $data Invoice data
     * @return array Processing result
     */
    private function sync_books_invoice($data) {
        // Trigger action for other plugins to handle
        do_action('zoho_sync_books_invoice_webhook', $data);
        
        $this->logger->log('info', 'Books invoice webhook processed', array(
            'invoice_id' => $data['invoice_id'] ?? 'unknown'
        ));

        return array(
            'success' => true,
            'message' => 'Books invoice processed'
        );
    }

    /**
     * Delete Books invoice from webhook
     * 
     * @param array $data Invoice data
     * @return array Processing result
     */
    private function delete_books_invoice($data) {
        // Trigger action for other plugins to handle
        do_action('zoho_sync_books_invoice_delete_webhook', $data);
        
        $this->logger->log('info', 'Books invoice deletion webhook processed', array(
            'invoice_id' => $data['invoice_id'] ?? 'unknown'
        ));

        return array(
            'success' => true,
            'message' => 'Books invoice deletion processed'
        );
    }

    /**
     * Send HTTP response
     * 
     * @param int $status_code HTTP status code
     * @param array $data Response data
     */
    private function send_response($status_code, $data) {
        status_header($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Get webhook URL
     * 
     * @return string Webhook URL
     */
    public function get_webhook_url() {
        return home_url('/zoho-webhook/');
    }

    /**
     * Register webhook with Zoho service
     * 
     * @param string $service Service name (inventory, crm, books)
     * @param array $events Events to subscribe to
     * @return array Registration result
     */
    public function register_webhook($service, $events = array()) {
        $api_client = new Zoho_Sync_Core_API_Client();
        $webhook_url = $this->get_webhook_url();

        $webhook_data = array(
            'webhook_url' => $webhook_url,
            'events' => $events,
            'name' => 'WordPress Zoho Sync Webhook'
        );

        $endpoint = $service . '/v1/webhooks';
        $response = $api_client->post($endpoint, $webhook_data);

        if ($response) {
            $this->logger->log('info', 'Webhook registered successfully', array(
                'service' => $service,
                'webhook_id' => $response['webhook']['webhook_id'] ?? 'unknown'
            ));

            return array(
                'success' => true,
                'webhook_id' => $response['webhook']['webhook_id'] ?? null,
                'message' => __('Webhook registrado correctamente', 'zoho-sync-core')
            );
        } else {
            $this->logger->log('error', 'Failed to register webhook', array(
                'service' => $service
            ));

            return array(
                'success' => false,
                'message' => __('Error al registrar webhook', 'zoho-sync-core')
            );
        }
    }

    /**
     * Unregister webhook from Zoho service
     * 
     * @param string $service Service name
     * @param string $webhook_id Webhook ID
     * @return array Unregistration result
     */
    public function unregister_webhook($service, $webhook_id) {
        $api_client = new Zoho_Sync_Core_API_Client();
        
        $endpoint = $service . '/v1/webhooks/' . $webhook_id;
        $response = $api_client->delete($endpoint);

        if ($response !== false) {
            $this->logger->log('info', 'Webhook unregistered successfully', array(
                'service' => $service,
                'webhook_id' => $webhook_id
            ));

            return array(
                'success' => true,
                'message' => __('Webhook eliminado correctamente', 'zoho-sync-core')
            );
        } else {
            $this->logger->log('error', 'Failed to unregister webhook', array(
                'service' => $service,
                'webhook_id' => $webhook_id
            ));

            return array(
                'success' => false,
                'message' => __('Error al eliminar webhook', 'zoho-sync-core')
            );
        }
    }

    /**
     * Get webhook statistics
     * 
     * @return array Webhook statistics
     */
    public function get_webhook_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zoho_sync_logs';
        
        $stats = array(
            'total_webhooks' => 0,
            'successful_webhooks' => 0,
            'failed_webhooks' => 0,
            'last_webhook' => null
        );

        // Get webhook statistics from logs
        $webhook_logs = $wpdb->get_results($wpdb->prepare("
            SELECT level, COUNT(*) as count, MAX(timestamp) as last_webhook
            FROM {$table_name}
            WHERE message LIKE %s
            GROUP BY level
        ", '%webhook%'));

        foreach ($webhook_logs as $log) {
            $stats['total_webhooks'] += $log->count;
            
            if ($log->level === 'info') {
                $stats['successful_webhooks'] += $log->count;
            } else {
                $stats['failed_webhooks'] += $log->count;
            }
            
            if (!$stats['last_webhook'] || $log->last_webhook > $stats['last_webhook']) {
                $stats['last_webhook'] = $log->last_webhook;
            }
        }

        return $stats;
    }

    /**
     * Test webhook endpoint
     * 
     * @return array Test result
     */
    public function test_webhook_endpoint() {
        $webhook_url = $this->get_webhook_url();
        
        $test_data = array(
            'event' => 'test.webhook',
            'service' => 'test',
            'data' => array(
                'test' => true,
                'timestamp' => current_time('mysql')
            )
        );

        $response = wp_remote_post($webhook_url, array(
            'body' => json_encode($test_data),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('Error al probar webhook: ', 'zoho-sync-core') . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => __('Webhook endpoint funcionando correctamente', 'zoho-sync-core')
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Webhook endpoint retornó código: ', 'zoho-sync-core') . $response_code
            );
        }
    }
}
