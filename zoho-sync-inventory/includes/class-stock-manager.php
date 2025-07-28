<?php

class ZSSI_Stock_Manager {
    private $logger;
    private $sync;
    
    public function __construct() {
        $this->logger = new ZSSI_Portal_Logger();
        
        add_action('woocommerce_product_set_stock', [$this, 'handle_stock_update'], 10, 2);
        add_action('woocommerce_variation_set_stock', [$this, 'handle_stock_update'], 10, 2);
        add_action('zssi_after_stock_sync', [$this, 'check_stock_levels']);
    }

    public function update_stock($product_id, $quantity, $source = 'woocommerce') {
        $product = wc_get_product($product_id);
        if (!$product || !$product->managing_stock()) {
            return false;
        }

        try {
            // Registrar cambio anterior
            $old_stock = $product->get_stock_quantity();
            
            // Actualizar stock
            $product->set_stock_quantity($quantity);
            $product->save();

            // Registrar en historial
            $this->log_stock_change($product_id, $old_stock, $quantity, $source);

            // Verificar niveles después del cambio
            $this->check_product_stock_level($product);

            return true;

        } catch (Exception $e) {
            $this->logger->log('error', sprintf(
                __('Error actualizando stock para producto %d: %s', 'zoho-sync-inventory'),
                $product_id,
                $e->getMessage()
            ));
            return false;
        }
    }

    public function handle_stock_update($product_id, $stock_quantity) {
        // Verificar si debemos sincronizar con Zoho
        if (get_option('zssi_sync_on_change', true)) {
            do_action('zssi_trigger_stock_sync', $product_id, $stock_quantity);
        }

        // Verificar niveles de stock
        $this->check_product_stock_level(wc_get_product($product_id));
    }

    private function check_product_stock_level($product) {
        if (!$product || !$product->managing_stock()) {
            return;
        }

        $stock_quantity = $product->get_stock_quantity();
        $threshold = $this->get_product_threshold($product);

        if ($stock_quantity <= $threshold) {
            $this->handle_low_stock($product, $stock_quantity, $threshold);
        }
    }

    private function get_product_threshold($product) {
        // Verificar umbral específico del producto
        $product_threshold = $product->get_meta('_zssi_stock_threshold');
        if ($product_threshold !== '') {
            return (int) $product_threshold;
        }

        // Usar umbral global
        return (int) get_option('zssi_stock_threshold', 5);
    }

    private function handle_low_stock($product, $current_stock, $threshold) {
        // Verificar si ya se notificó recientemente
        $last_notification = get_post_meta($product->get_id(), '_zssi_last_stock_notification', true);
        if ($last_notification && (time() - strtotime($last_notification) < DAY_IN_SECONDS)) {
            return;
        }

        // Registrar alerta
        $alert_data = [
            'product_id' => $product->get_id(),
            'current_stock' => $current_stock,
            'threshold' => $threshold,
            'timestamp' => current_time('mysql')
        ];

        $this->save_stock_alert($alert_data);

        // Notificar
        do_action('zssi_low_stock_alert', $product, $current_stock, $threshold);

        // Actualizar timestamp de última notificación
        update_post_meta($product->get_id(), '_zssi_last_stock_notification', current_time('mysql'));
    }

    private function log_stock_change($product_id, $old_stock, $new_stock, $source) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'zssi_stock_log',
            [
                'product_id' => $product_id,
                'old_stock' => $old_stock,
                'new_stock' => $new_stock,
                'source' => $source,
                'change_date' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s', '%s']
        );
    }

    private function save_stock_alert($alert_data) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'zssi_stock_alerts',
            [
                'product_id' => $alert_data['product_id'],
                'threshold' => $alert_data['threshold'],
                'status' => 'active',
                'last_triggered' => $alert_data['timestamp']
            ],
            ['%d', '%d', '%s', '%s']
        );
    }
}