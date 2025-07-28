<?php

class ZSSI_Low_Stock_Monitor {
    
    private $logger;
    private $stock_manager;
    private $notification_manager;
    
    public function __construct() {
        $this->logger = new ZSSI_Portal_Logger();
        $this->stock_manager = new ZSSI_Stock_Manager();
        $this->notification_manager = new ZSSI_Notification_Manager();

        add_action('zssi_check_stock_levels', [$this, 'monitor_stock_levels']);
        add_action('woocommerce_low_stock', [$this, 'handle_low_stock']);
        add_action('woocommerce_no_stock', [$this, 'handle_out_of_stock']);
    }

    public function monitor_stock_levels() {
        $products = $this->get_managed_products();
        
        foreach ($products as $product) {
            $this->check_product_stock($product);
        }
    }

    private function check_product_stock($product) {
        $stock = $product->get_stock_quantity();
        $threshold = $this->get_product_threshold($product);
        
        if ($stock <= $threshold) {
            $this->process_low_stock_alert($product, $stock, $threshold);
        }
        
        if ($stock <= 0) {
            $this->process_out_of_stock_alert($product);
        }
    }

    private function process_low_stock_alert($product, $current_stock, $threshold) {
        $alert_data = [
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'current_stock' => $current_stock,
            'threshold' => $threshold,
            'alert_type' => 'low_stock'
        ];

        // Registrar alerta
        $this->register_stock_alert($alert_data);

        // Notificar
        $this->notify_low_stock($alert_data);

        $this->logger->log('warning', sprintf(
            __('Stock bajo detectado para %s (SKU: %s). Stock actual: %d', 'zoho-sync-inventory'),
            $product->get_name(),
            $product->get_sku(),
            $current_stock
        ));
    }

    private function process_out_of_stock_alert($product) {
        $alert_data = [
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'alert_type' => 'out_of_stock'
        ];

        // Registrar alerta
        $this->register_stock_alert($alert_data);

        // Notificar
        $this->notify_out_of_stock($alert_data);

        $this->logger->log('error', sprintf(
            __('Producto agotado: %s (SKU: %s)', 'zoho-sync-inventory'),
            $product->get_name(),
            $product->get_sku()
        ));
    }

    private function get_managed_products() {
        return wc_get_products([
            'status' => 'publish',
            'type' => ['simple', 'variation'],
            'manage_stock' => true,
            'limit' => -1
        ]);
    }

    private function get_product_threshold($product) {
        $custom_threshold = get_post_meta($product->get_id(), '_stock_threshold', true);
        return $custom_threshold ? (int)$custom_threshold : get_option('zssi_default_threshold', 5);
    }

    private function register_stock_alert($alert_data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'zssi_stock_alerts',
            [
                'product_id' => $alert_data['product_id'],
                'alert_type' => $alert_data['alert_type'],
                'stock_level' => $alert_data['current_stock'] ?? 0,
                'threshold' => $alert_data['threshold'] ?? 0,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%d', '%s']
        );

        return $wpdb->insert_id;
    }

    private function notify_low_stock($alert_data) {
        $recipients = $this->get_notification_recipients();
        
        foreach ($recipients as $recipient) {
            $this->notification_manager->send_notification(
                'low_stock',
                $recipient,
                [
                    'product_name' => $alert_data['product_name'],
                    'sku' => $alert_data['sku'],
                    'current_stock' => $alert_data['current_stock'],
                    'threshold' => $alert_data['threshold']
                ]
            );
        }
    }

    private function notify_out_of_stock($alert_data) {
        $recipients = $this->get_notification_recipients();
        
        foreach ($recipients as $recipient) {
            $this->notification_manager->send_notification(
                'out_of_stock',
                $recipient,
                [
                    'product_name' => $alert_data['product_name'],
                    'sku' => $alert_data['sku']
                ]
            );
        }
    }

    private function get_notification_recipients() {
        $recipients = [];

        // Administradores
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $recipients[] = $admin_email;
        }

        // Emails adicionales configurados
        $additional_emails = get_option('zssi_notification_emails', '');
        if ($additional_emails) {
            $emails = array_map('trim', explode(',', $additional_emails));
            $recipients = array_merge($recipients, $emails);
        }

        return array_unique($recipients);
    }
}