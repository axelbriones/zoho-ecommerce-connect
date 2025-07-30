<?php
/**
 * Zoho Admin Notices
 * 
 * Manages admin notices and notifications
 * 
 * @package ZohoSyncCore
 * @subpackage Admin
 * @since 1.0.0
 * @author Byron Briones <bbrion.es>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Zoho Admin Notices Class
 * 
 * Handles admin notices, alerts and notifications
 */
class Zoho_Sync_Core_Admin_Notices {

    /**
     * Notices storage
     * 
     * @var array
     */
    private $notices = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_notices();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_notices', array($this, 'display_notices'));
        add_action('wp_ajax_zoho_sync_dismiss_notice', array($this, 'ajax_dismiss_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Load stored notices
     */
    private function load_notices() {
        $this->notices = get_option('zoho_sync_core_admin_notices', array());
    }

    /**
     * Save notices to database
     */
    private function save_notices() {
        update_option('zoho_sync_core_admin_notices', $this->notices);
    }

    /**
     * Add a notice
     * 
     * @param string $type Notice type (success, error, warning, info)
     * @param string $message Notice message
     * @param bool $dismissible Whether notice is dismissible
     * @param string $id Unique notice ID
     */
    public function add_notice($type, $message, $dismissible = true, $id = null) {
        if (!$id) {
            $id = md5($message . time());
        }

        $this->notices[$id] = array(
            'type' => $type,
            'message' => $message,
            'dismissible' => $dismissible,
            'created' => current_time('mysql'),
            'dismissed' => false
        );

        $this->save_notices();
    }

    /**
     * Add success notice
     * 
     * @param string $message Notice message
     * @param bool $dismissible Whether notice is dismissible
     * @param string $id Unique notice ID
     */
    public function add_success($message, $dismissible = true, $id = null) {
        $this->add_notice('success', $message, $dismissible, $id);
    }

    /**
     * Add error notice
     * 
     * @param string $message Notice message
     * @param bool $dismissible Whether notice is dismissible
     * @param string $id Unique notice ID
     */
    public function add_error($message, $dismissible = true, $id = null) {
        $this->add_notice('error', $message, $dismissible, $id);
    }

    /**
     * Add warning notice
     * 
     * @param string $message Notice message
     * @param bool $dismissible Whether notice is dismissible
     * @param string $id Unique notice ID
     */
    public function add_warning($message, $dismissible = true, $id = null) {
        $this->add_notice('warning', $message, $dismissible, $id);
    }

    /**
     * Add info notice
     * 
     * @param string $message Notice message
     * @param bool $dismissible Whether notice is dismissible
     * @param string $id Unique notice ID
     */
    public function add_info($message, $dismissible = true, $id = null) {
        $this->add_notice('info', $message, $dismissible, $id);
    }

    /**
     * Remove a notice
     * 
     * @param string $id Notice ID
     */
    public function remove_notice($id) {
        if (isset($this->notices[$id])) {
            unset($this->notices[$id]);
            $this->save_notices();
        }
    }

    /**
     * Dismiss a notice
     * 
     * @param string $id Notice ID
     */
    public function dismiss_notice($id) {
        if (isset($this->notices[$id])) {
            $this->notices[$id]['dismissed'] = true;
            $this->save_notices();
        }
    }

    /**
     * Clear all notices
     */
    public function clear_notices() {
        $this->notices = array();
        $this->save_notices();
    }

    /**
     * Display admin notices
     */
    public function display_notices() {
        if (empty($this->notices)) {
            return;
        }

        foreach ($this->notices as $id => $notice) {
            if ($notice['dismissed']) {
                continue;
            }

            $this->render_notice($id, $notice);
        }

        // Clean up old notices (older than 7 days)
        $this->cleanup_old_notices();
    }

    /**
     * Render a single notice
     * 
     * @param string $id Notice ID
     * @param array $notice Notice data
     */
    private function render_notice($id, $notice) {
        $type = $notice['type'];
        $message = $notice['message'];
        $dismissible = $notice['dismissible'];

        $classes = array('notice', 'notice-' . $type);
        
        if ($dismissible) {
            $classes[] = 'is-dismissible';
        }

        echo '<div class="' . implode(' ', $classes) . '" data-notice-id="' . esc_attr($id) . '">';
        echo '<p>' . wp_kses_post($message) . '</p>';
        echo '</div>';
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        
        $script = "
        jQuery(document).ready(function($) {
            $(document).on('click', '.notice[data-notice-id] .notice-dismiss', function() {
                var noticeId = $(this).closest('.notice').data('notice-id');
                if (noticeId) {
                    $.post(ajaxurl, {
                        action: 'zoho_sync_dismiss_notice',
                        notice_id: noticeId,
                        nonce: '" . wp_create_nonce('zoho_sync_dismiss_notice') . "'
                    });
                }
            });
        });
        ";
        
        wp_add_inline_script('jquery', $script);
    }

    /**
     * AJAX handler for dismissing notices
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer('zoho_sync_dismiss_notice', 'nonce');

        $notice_id = sanitize_text_field($_POST['notice_id'] ?? '');
        
        if ($notice_id) {
            $this->dismiss_notice($notice_id);
            wp_send_json_success();
        } else {
            wp_send_json_error(__('ID de notificación inválido', 'zoho-sync-core'));
        }
    }

    /**
     * Clean up old notices
     */
    private function cleanup_old_notices() {
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        $cleaned = false;

        foreach ($this->notices as $id => $notice) {
            if ($notice['created'] < $cutoff_date && $notice['dismissed']) {
                unset($this->notices[$id]);
                $cleaned = true;
            }
        }

        if ($cleaned) {
            $this->save_notices();
        }
    }

    /**
     * Add system status notices
     */
    public function add_system_notices() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            $this->add_warning(
                sprintf(
                    __('WooCommerce no está activo. %s requiere WooCommerce para funcionar correctamente. %s', 'zoho-sync-core'),
                    '<strong>Zoho Sync Core</strong>',
                    '<a href="' . admin_url('plugins.php') . '">' . __('Activar WooCommerce', 'zoho-sync-core') . '</a>'
                ),
                true,
                'woocommerce_inactive'
            );
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $this->add_error(
                sprintf(
                    __('Tu versión de PHP (%s) es muy antigua. Zoho Sync Core requiere PHP 7.4 o superior. Contacta a tu proveedor de hosting para actualizar.', 'zoho-sync-core'),
                    PHP_VERSION
                ),
                false,
                'php_version_old'
            );
        }

        // Check if Zoho credentials are configured
        $settings = get_option('zoho_sync_core_settings', array());
        if (empty($settings['zoho_client_id']) || empty($settings['zoho_client_secret'])) {
            $this->add_info(
                sprintf(
                    __('Zoho Sync Core está instalado pero no configurado. %s para comenzar.', 'zoho-sync-core'),
                    '<a href="' . admin_url('admin.php?page=zoho-sync-core-settings') . '">' . __('Configura tu conexión con Zoho', 'zoho-sync-core') . '</a>'
                ),
                true,
                'zoho_not_configured'
            );
        }

        // Check connection status
        $connection_status = get_option('zoho_sync_core_connection_status', array());
        if (!empty($connection_status) && !$connection_status['success']) {
            $this->add_error(
                sprintf(
                    __('Error de conexión con Zoho: %s. %s', 'zoho-sync-core'),
                    $connection_status['message'],
                    '<a href="' . admin_url('admin.php?page=zoho-sync-core-settings') . '">' . __('Revisar configuración', 'zoho-sync-core') . '</a>'
                ),
                true,
                'zoho_connection_error'
            );
        }

        // Check for recent critical errors
        global $wpdb;
        $logs_table = ZOHO_SYNC_LOGS_TABLE;
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table) {
            $critical_errors = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $logs_table 
                 WHERE level = 'critical' AND created_at > %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            ));

            if ($critical_errors > 0) {
                $this->add_error(
                    sprintf(
                        _n(
                            'Se ha detectado %d error crítico en las últimas 24 horas.',
                            'Se han detectado %d errores críticos en las últimas 24 horas.',
                            $critical_errors,
                            'zoho-sync-core'
                        ),
                        $critical_errors
                    ) . ' <a href="' . admin_url('admin.php?page=zoho-sync-core-logs') . '">' . __('Ver logs', 'zoho-sync-core') . '</a>',
                    true,
                    'critical_errors_detected'
                );
            }
        }
    }

    /**
     * Add welcome notice for new installations
     */
    public function add_welcome_notice() {
        $activation_time = get_option('zoho_sync_core_activation_time');
        $dismissed = get_option('zoho_sync_core_welcome_dismissed', false);

        if (!$dismissed && $activation_time && (current_time('timestamp') - $activation_time) < 604800) {
            $message = '<h3>' . __('¡Bienvenido a Zoho Sync Core!', 'zoho-sync-core') . '</h3>';
            $message .= '<p>' . __('Gracias por instalar Zoho Sync Core, el núcleo del ecosistema de sincronización con Zoho.', 'zoho-sync-core') . '</p>';
            $message .= '<p>';
            $message .= '<a href="' . admin_url('admin.php?page=zoho-sync-core-settings') . '" class="button button-primary">' . __('Configurar Conexión', 'zoho-sync-core') . '</a> ';
            $message .= '<a href="' . admin_url('admin.php?page=zoho-sync-core') . '" class="button">' . __('Ver Dashboard', 'zoho-sync-core') . '</a> ';
            $message .= '<a href="https://bbrion.es/zoho-sync-docs" target="_blank" class="button">' . __('Documentación', 'zoho-sync-core') . '</a>';
            $message .= '</p>';

            $this->add_info($message, true, 'welcome_notice');
        }
    }

    /**
     * Add update notice
     * 
     * @param string $old_version Previous version
     * @param string $new_version New version
     */
    public function add_update_notice($old_version, $new_version) {
        $message = sprintf(
            __('Zoho Sync Core se ha actualizado de la versión %s a %s.', 'zoho-sync-core'),
            $old_version,
            $new_version
        );

        $message .= ' <a href="' . admin_url('admin.php?page=zoho-sync-core') . '">' . __('Ver cambios', 'zoho-sync-core') . '</a>';

        $this->add_success($message, true, 'plugin_updated');
    }

    /**
     * Add maintenance notice
     */
    public function add_maintenance_notice() {
        $this->add_warning(
            __('Zoho Sync Core está realizando tareas de mantenimiento. Algunas funciones pueden estar temporalmente limitadas.', 'zoho-sync-core'),
            false,
            'maintenance_mode'
        );
    }

    /**
     * Add rate limiting notice
     */
    public function add_rate_limit_notice() {
        $this->add_warning(
            sprintf(
                __('Se ha alcanzado el límite de solicitudes a la API de Zoho. Las sincronizaciones se reanudarán automáticamente. %s', 'zoho-sync-core'),
                '<a href="' . admin_url('admin.php?page=zoho-sync-core-logs') . '">' . __('Ver detalles', 'zoho-sync-core') . '</a>'
            ),
            true,
            'api_rate_limit'
        );
    }

    /**
     * Add token expiration notice
     */
    public function add_token_expiration_notice() {
        $this->add_warning(
            sprintf(
                __('Algunos tokens de acceso están próximos a expirar. %s para renovarlos automáticamente.', 'zoho-sync-core'),
                '<a href="' . admin_url('admin.php?page=zoho-sync-core-settings') . '">' . __('Revisar configuración', 'zoho-sync-core') . '</a>'
            ),
            true,
            'tokens_expiring'
        );
    }

    /**
     * Get all notices
     * 
     * @return array All notices
     */
    public function get_notices() {
        return $this->notices;
    }

    /**
     * Get notices by type
     * 
     * @param string $type Notice type
     * @return array Filtered notices
     */
    public function get_notices_by_type($type) {
        return array_filter($this->notices, function($notice) use ($type) {
            return $notice['type'] === $type && !$notice['dismissed'];
        });
    }

    /**
     * Count notices by type
     * 
     * @param string $type Notice type
     * @return int Notice count
     */
    public function count_notices_by_type($type) {
        return count($this->get_notices_by_type($type));
    }

    /**
     * Check if there are any error notices
     * 
     * @return bool True if there are error notices
     */
    public function has_errors() {
        return $this->count_notices_by_type('error') > 0;
    }

    /**
     * Check if there are any warning notices
     * 
     * @return bool True if there are warning notices
     */
    public function has_warnings() {
        return $this->count_notices_by_type('warning') > 0;
    }

    /**
     * Get notice summary for dashboard
     * 
     * @return array Notice summary
     */
    public function get_notice_summary() {
        return array(
            'total' => count($this->notices),
            'errors' => $this->count_notices_by_type('error'),
            'warnings' => $this->count_notices_by_type('warning'),
            'info' => $this->count_notices_by_type('info'),
            'success' => $this->count_notices_by_type('success')
        );
    }
}
