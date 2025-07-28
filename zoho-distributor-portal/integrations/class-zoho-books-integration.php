<?php

class ZSDP_Books_Integration {
    public function __construct() {
        add_filter('zsdp_invoice_data', [$this, 'add_zoho_books_data']);
        add_action('zsdp_after_order_sync', [$this, 'sync_invoice_status']);
    }

    public function add_zoho_books_data($invoice_data) {
        // Integración con datos de facturación de Zoho Books
        return $invoice_data;
    }

    public function sync_invoice_status($order_id) {
        // Sincronización de estado de facturas
    }
}