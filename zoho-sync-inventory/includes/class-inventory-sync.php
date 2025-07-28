<?php

class ZSSI_Inventory_Sync {
    
    private $api;
    private $stock_manager;
    private $logger;
    private $last_sync;
    
    public function __construct() {
        $this->api = ZohoSyncCore::api();
        $this->stock_manager = new ZSSI_Stock_Manager();
        $this->logger = new ZSSI_Portal_Logger();
        $this->last_sync = get_option('zssi_last_sync', 0);

        // Hooks principales
        add_action('zssi_stock_sync', [$this, 'sync_all_stock']);
        add_action('woocommerce_product_set_stock', [$this, 'handle_stock_change'], 10, 2);
        add_action('woocommerce_variation_set_stock', [$this, 'handle_stock_change'], 10, 2);
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_completion']);
    }

    public function sync_all_stock() {
        try {
            $batch_size = get_option('zssi_batch_size', 50);
            $page = 1;
            $synced = 0;

            do {
                $products = $this->get_products_batch($page, $batch_size);
                
                if (empty($products)) break;

                foreach ($products as $product) {
                    $this->sync_product_stock($product);
                    $synced++;
                }

                $page++;

            } while (count($products) === $batch_size);

            update_option('zssi_last_sync', time());
            $this->logger->log('info', sprintf(
                __('Sincronización de stock completada. %d productos actualizados.', 'zoho-sync-inventory'),
                $synced
            ));

        } catch (Exception $e) {
            $this->logger->log('error', 
                'Error en sincronización de stock: ' . $e->getMessage()
            );
        }
    }

    private function sync_product_stock($product) {
        $zoho_id = get_post_meta($product->get_id(), 'zoho_item_id', true);
        
        if (!$zoho_id) {
            $this->logger->log('warning', sprintf(
                __('Producto %d sin ID de Zoho', 'zoho-sync-inventory'),
                $product->get_id()
            ));
            return false;
        }

        try {
            // Obtener stock de Zoho
            $zoho_stock = $this->api->get('inventory', "items/{$zoho_id}/stock");
            
            if (!isset($zoho_stock->quantity)) {
                throw new Exception('Datos de stock inválidos de Zoho');
            }

            // Comparar y actualizar si es necesario
            $wc_stock = $product->get_stock_quantity();
            
            if ($wc_stock !== $zoho_stock->quantity) {
                $this->stock_manager->update_stock(
                    $product->get_id(),
                    $zoho_stock->quantity,
                    'zoho'
                );
            }

            return true;

        } catch (Exception $e) {
            $this->logger->log('error', sprintf(
                __('Error sincronizando stock para producto %d: %s', 'zoho-sync-inventory'),
                $product->get_id(),
                $e->getMessage()
            ));
            return false;
        }
    }

    private function get_products_batch($page, $batch_size) {
        return wc_get_products([
            'status' => 'publish',
            'type' => ['simple', 'variation'],
            'limit' => $batch_size,
            'page' => $page,
            'manage_stock' => true
        ]);
    }

    public function handle_stock_change($product_id, $new_stock) {
        $sync_direction = get_option('zssi_sync_direction', 'both');
        
        if ($sync_direction === 'zoho_to_wc') {
            return; // No sincronizar cambios de WC a Zoho
        }

        try {
            $zoho_id = get_post_meta($product_id, 'zoho_item_id', true);
            if (!$zoho_id) return;

            $this->api->put('inventory', "items/{$zoho_id}/stock", [
                'quantity' => $new_stock
            ]);

            $this->logger->log('info', sprintf(
                __('Stock actualizado en Zoho para producto %d: %d', 'zoho-sync-inventory'),
                $product_id,
                $new_stock
            ));

        } catch (Exception $e) {
            $this->logger->log('error', sprintf(
                __('Error actualizando stock en Zoho para producto %d: %s', 'zoho-sync-inventory'),
                $product_id,
                $e->getMessage()
            ));
        }
    }

    public function handle_order_completion($order_id) {
        if (!get_option('zssi_sync_on_order', true)) {
            return;
        }

        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if ($product && $product->managing_stock()) {
                $this->sync_product_stock($product);
            }
        }
    }
}