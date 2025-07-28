<?php

class ZSSI_Notification_Manager {
    
    private $logger;
    private $settings;
    private $notification_queue = [];
    
    public function __construct() {
        $this->logger = new ZSSI_Portal_Logger();
        $this->settings = get_option('zssi_notification_settings', [
            'email_notifications' => true,
            'admin_notifications' => true,
            'distributor_notifications' => true,
            'notification_frequency' => 'immediately',
            'batch_notifications' => false
        ]);

        add_action('init', [$this, 'init_hooks']);
        add_action('zssi_process_notification_queue', [$this, 'process_queued_notifications']);
    }

    public function init_hooks() {
        add_action('zssi_stock_below_threshold', [$this, 'notify_low_stock'], 10, 2);
        add_action('zssi_stock_out', [$this, 'notify_out_of_stock'], 10, 1);
        add_action('zssi_stock_replenished', [$this, 'notify_stock_replenished'], 10, 2);
        add_action('zssi_sync_failed', [$this, 'notify_sync_failure'], 10, 2);
    }

    public function send_notification($type, $recipient, $data) {
        if (!$this->should_send_notification($type)) {
            return false;
        }

        if ($this->settings['batch_notifications']) {
            $this->queue_notification($type, $recipient, $data);
            return true;
        }

        return $this->process_notification($type, $recipient, $data);
    }

    private function process_notification($type, $recipient, $data) {
        $template = $this->get_notification_template($type);
        
        if (!$template) {
            $this->logger->log('error', sprintf(
                __('Plantilla de notificación no encontrada: %s', 'zoho-sync-inventory'),
                $type
            ));
            return false;
        }

        $content = $this->prepare_notification_content($template, $data);
        $subject = $this->get_notification_subject($type, $data);

        return wp_mail($recipient, $subject, $content, [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
        ]);
    }

    private function queue_notification($type, $recipient, $data) {
        $this->notification_queue[] = [
            'type' => $type,
            'recipient' => $recipient,
            'data' => $data,
            'timestamp' => time()
        ];

        if (!wp_next_scheduled('zssi_process_notification_queue')) {
            wp_schedule_single_event(
                time() + (5 * MINUTE_IN_SECONDS), 
                'zssi_process_notification_queue'
            );
        }
    }

    public function process_queued_notifications() {
        if (empty($this->notification_queue)) {
            return;
        }

        $notifications_by_recipient = [];

        // Agrupar notificaciones por destinatario
        foreach ($this->notification_queue as $notification) {
            $recipient = $notification['recipient'];
            if (!isset($notifications_by_recipient[$recipient])) {
                $notifications_by_recipient[$recipient] = [];
            }
            $notifications_by_recipient[$recipient][] = $notification;
        }

        // Enviar notificaciones agrupadas
        foreach ($notifications_by_recipient as $recipient => $notifications) {
            $this->send_batch_notification($recipient, $notifications);
        }

        // Limpiar cola
        $this->notification_queue = [];
    }

    private function send_batch_notification($recipient, $notifications) {
        $content = $this->prepare_batch_content($notifications);
        $subject = sprintf(
            __('Resumen de Actualizaciones de Inventario (%d)', 'zoho-sync-inventory'),
            count($notifications)
        );

        return wp_mail($recipient, $subject, $content, [
            'Content-Type: text/html; charset=UTF-8'
        ]);
    }

    private function prepare_batch_content($notifications) {
        ob_start();
        include ZSSI_PLUGIN_DIR . 'templates/email/batch-notification.php';
        return ob_get_clean();
    }

    private function get_notification_template($type) {
        $template_path = ZSSI_PLUGIN_DIR . "templates/email/{$type}.php";
        
        if (!file_exists($template_path)) {
            return false;
        }

        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    private function get_notification_subject($type, $data) {
        $subjects = [
            'low_stock' => __('⚠️ Stock Bajo: %s', 'zoho-sync-inventory'),
            'out_of_stock' => __('❌ Producto Agotado: %s', 'zoho-sync-inventory'),
            'stock_replenished' => __('✅ Stock Reabastecido: %s', 'zoho-sync-inventory'),
            'sync_failed' => __('🔄 Error de Sincronización: %s', 'zoho-sync-inventory')
        ];

        return sprintf(
            $subjects[$type] ?? __('Notificación de Inventario', 'zoho-sync-inventory'),
            $data['product_name'] ?? ''
        );
    }

    private function should_send_notification($type) {
        if (!$this->settings['email_notifications']) {
            return false;
        }

        $notification_types = [
            'low_stock' => true,
            'out_of_stock' => true,
            'stock_replenished' => $this->settings['admin_notifications'],
            'sync_failed' => $this->settings['admin_notifications']
        ];

        return $notification_types[$type] ?? false;
    }

    private function prepare_notification_content($template, $data) {
        return strtr($template, [
            '{product_name}' => $data['product_name'] ?? '',
            '{current_stock}' => $data['current_stock'] ?? '0',
            '{threshold}' => $data['threshold'] ?? '0',
            '{store_name}' => get_bloginfo('name'),
            '{site_url}' => get_site_url(),
            '{admin_url}' => admin_url('admin.php?page=zssi-inventory')
        ]);
    }
}