<?php

class ZSDP_Notification_Manager {
    public function __construct() {
        add_action('zsdp_order_status_changed', [$this, 'notify_order_status']);
        add_action('zsdp_stock_level_changed', [$this, 'notify_stock_changes']);
        add_action('zsdp_price_updated', [$this, 'notify_price_changes']);
    }

    public function notify_order_status($order_id) {
        // Notificaciones de cambios en pedidos
    }

    public function notify_stock_changes($product_id) {
        // Notificaciones de cambios en inventario
    }

    public function notify_price_changes($product_id) {
        // Notificaciones de cambios en precios
    }
}