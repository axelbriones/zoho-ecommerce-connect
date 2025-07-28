<?php
/**
 * Customers Admin Class
 *
 * Handles the administration interface for the customers plugin
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZohoSyncCustomers_Admin class
 */
class ZohoSyncCustomers_Admin {
    
    /**
     * Instance of this class
     *
     * @var ZohoSyncCustomers_Admin
     */
    private static $instance = null;
    
    /**
     * Plugin slug
     *
     * @var string
     */
    private $plugin_slug = 'zoho-sync-customers';
    
    /**
     * Get instance
     *
     * @return ZohoSyncCustomers_Admin
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // AJAX handlers
        add_action('wp_ajax_zoho_customers_manual_sync', array($this, 'handle_manual_sync'));
        add_action('wp_ajax_zoho_customers_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_zoho_customers_export_data', array($this, 'handle_export_data'));
        
        // User list customization
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_filter('manage_users_custom_column', array($this, 'show_user_column_content'), 10, 3);
        add_filter('users_list_table_query_args', array($this, 'filter_users_by_role'));
        
        // User profile fields
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Zoho Customers', 'zoho-sync-customers'),
            __('Zoho Customers', 'zoho-sync-customers'),
            'manage_options',
            $this->plugin_slug,
            array($this, 'display_dashboard_page'),
            'dashicons-groups',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            $this->plugin_slug,
            __('Dashboard', 'zoho-sync-customers'),
            __('Dashboard', 'zoho-sync-customers'),
            'manage_options',
            $this->plugin_slug,
            array($this, 'display_dashboard_page')
        );
        
        // Sync submenu
        add_submenu_page(
            $this->plugin_slug,
            __('Sincronización', 'zoho-sync-customers'),
            __('Sincronización', 'zoho-sync-customers'),
            'manage_options',
            $this->plugin_slug . '-sync',
            array($this, 'display_sync_page')
        );
        
        // Distributors submenu
        add_submenu_page(
            $this->plugin_slug,
            __('Distribuidores', 'zoho-sync-customers'),
            __('Distribuidores', 'zoho-sync-customers'),
            'manage_options',
            $this->plugin_slug . '-distributors',
            array($this, 'display_distributors_page')
        );
        
        // B2B Customers submenu
        add_submenu_page(
            $this->plugin_slug,
            __('Clientes B2B', 'zoho-sync-customers'),
            __('Clientes B2B', 'zoho-sync-customers'),
            'manage_options',
            $this->plugin_slug . '-b2b',
            array($this, 'display_b2b_page')
        );
        
        // Pricing submenu
        add_submenu_page(
            $this->plugin_slug,
            __('Precios por Nivel', 'zoho-sync-customers'),
            __('Precios', 'zoho-sync-customers'),
            'manage_options',
            $this->plugin_slug . '-pricing',
            array($this, 'display_pricing_page')
        );
        
        // Settings submenu
        add_submenu_page(
            $this->plugin_slug,
            __('Configuración', 'zoho-sync-customers'),
            __('Configuración', 'zoho-sync-customers'),
            'manage_options',
            $this->plugin_slug . '-settings',
            array($this, 'display_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting('zoho_customers_general', 'zoho_customers_sync_enabled');
        register_setting('zoho_customers_general', 'zoho_customers_sync_interval');
        register_setting('zoho_customers_general', 'zoho_customers_auto_create_users');
        register_setting('zoho_customers_general', 'zoho_customers_default_role');
        
        // B2B settings
        register_setting('zoho_customers_b2b', 'zoho_customers_b2b_approval_required');
        register_setting('zoho_customers_b2b', 'zoho_customers_b2b_discount');
        register_setting('zoho_customers_b2b', 'zoho_customers_hide_prices_guests');
        
        // Pricing settings
        register_setting('zoho_customers_pricing', 'zoho_customers_pricing_enabled');
        register_setting('zoho_customers_pricing', 'zoho_customers_show_original_price');
        register_setting('zoho_customers_pricing', 'zoho_customers_pricing_levels');
        
        // Field mapping settings
        register_setting('zoho_customers_mapping', 'zoho_customers_field_mapping');
        register_setting('zoho_customers_mapping', 'zoho_customers_custom_fields');
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, $this->plugin_slug) === false) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'zoho-customers-admin',
            ZOHO_SYNC_CUSTOMERS_PLUGIN_URL . 'admin/assets/css/admin-styles.css',
            array(),
            ZOHO_SYNC_CUSTOMERS_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'zoho-customers-admin',
            ZOHO_SYNC_CUSTOMERS_PLUGIN_URL . 'admin/assets/js/admin-dashboard.js',
            array('jquery', 'wp-util'),
            ZOHO_SYNC_CUSTOMERS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('zoho-customers-admin', 'zohoCustomersAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zoho_customers_admin'),
            'strings' => array(
                'confirmSync' => __('¿Estás seguro de que quieres ejecutar la sincronización manual?', 'zoho-sync-customers'),
                'syncInProgress' => __('Sincronización en progreso...', 'zoho-sync-customers'),
                'syncComplete' => __('Sincronización completada', 'zoho-sync-customers'),
                'syncError' => __('Error en la sincronización', 'zoho-sync-customers'),
                'testingConnection' => __('Probando conexión...', 'zoho-sync-customers'),
                'connectionSuccess' => __('Conexión exitosa', 'zoho-sync-customers'),
                'connectionError' => __('Error de conexión', 'zoho-sync-customers')
            )
        ));
    }
    
    /**
     * Display dashboard page
     */
    public function display_dashboard_page() {
        $sync_stats = ZohoSyncCustomers_CustomersSync::instance()->get_sync_stats();
        $distributor_stats = ZohoSyncCustomers_DistributorManager::instance()->get_distributor_stats();
        $b2b_stats = ZohoSyncCustomers_B2BValidator::instance()->get_b2b_stats();
        
        include ZOHO_SYNC_CUSTOMERS_PLUGIN_DIR . 'admin/partials/dashboard-display.php';
    }
    
    /**
     * Display sync page
     */
    public function display_sync_page() {
        $sync_stats = ZohoSyncCustomers_CustomersSync::instance()->get_sync_stats();
        
        include ZOHO_SYNC_CUSTOMERS_PLUGIN_DIR . 'admin/partials/sync-display.php';
    }
    
    /**
     * Display distributors page
     */
    public function display_distributors_page() {
        $distributors = ZohoSyncCustomers_DistributorManager::instance()->get_distributors();
        $pending_distributors = ZohoSyncCustomers_DistributorManager::instance()->get_pending_distributors();
        $levels = ZohoSyncCustomers_DistributorManager::instance()->get_distributor_levels();
        
        include ZOHO_SYNC_CUSTOMERS_PLUGIN_DIR . 'admin/partials/distributors-display.php';
    }
    
    /**
     * Display B2B customers page
     */
    public function display_b2b_page() {
        $pending_customers = ZohoSyncCustomers_B2BValidator::instance()->get_pending_b2b_customers();
        $b2b_stats = ZohoSyncCustomers_B2BValidator::instance()->get_b2b_stats();
        
        include ZOHO_SYNC_CUSTOMERS_PLUGIN_DIR . 'admin/partials/b2b-display.php';
    }
    
    /**
     * Display pricing page
     */
    public function display_pricing_page() {
        $levels = ZohoSyncCustomers_DistributorManager::instance()->get_distributor_levels();
        $pricing_stats = ZohoSyncCustomers_PricingManager::instance()->get_pricing_stats();
        
        include ZOHO_SYNC_CUSTOMERS_PLUGIN_DIR . 'admin/partials/pricing-display.php';
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        $field_mapping = ZohoSyncCustomers_CustomerMapper::instance()->get_admin_field_mapping();
        
        include ZOHO_SYNC_CUSTOMERS_PLUGIN_DIR . 'admin/partials/settings-display.php';
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if Zoho Sync Core is active
        if (!class_exists('ZohoSyncCore')) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('Zoho Sync Customers requiere que Zoho Sync Core esté activo.', 'zoho-sync-customers'); ?></p>
            </div>
            <?php
        }
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('Zoho Sync Customers requiere que WooCommerce esté activo.', 'zoho-sync-customers'); ?></p>
            </div>
            <?php
        }
        
        // Show sync status notices
        $last_sync = get_option('zoho_customers_last_sync', '');
        if (empty($last_sync)) {
            ?>
            <div class="notice notice-warning">
                <p><?php _e('No se ha ejecutado ninguna sincronización aún. <a href="' . admin_url('admin.php?page=zoho-sync-customers-sync') . '">Ejecutar sincronización</a>', 'zoho-sync-customers'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Handle manual sync AJAX request
     */
    public function handle_manual_sync() {
        check_ajax_referer('zoho_customers_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisos insuficientes', 'zoho-sync-customers'));
        }
        
        $options = array(
            'force_update' => isset($_POST['force_update']) ? true : false,
            'limit' => isset($_POST['limit']) ? intval($_POST['limit']) : 200
        );
        
        $result = ZohoSyncCustomers_CustomersSync::instance()->sync_customers_from_zoho($options);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle test connection AJAX request
     */
    public function handle_test_connection() {
        check_ajax_referer('zoho_customers_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisos insuficientes', 'zoho-sync-customers'));
        }
        
        $result = ZohoSyncCustomers_ZohoCrmApi::instance()->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Handle export data AJAX request
     */
    public function handle_export_data() {
        check_ajax_referer('zoho_customers_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permisos insuficientes', 'zoho-sync-customers'));
        }
        
        $export_type = sanitize_text_field($_POST['export_type']);
        $format = sanitize_text_field($_POST['format']);
        
        switch ($export_type) {
            case 'distributors':
                $data = ZohoSyncCustomers_DistributorManager::instance()->get_distributors();
                break;
            case 'b2b_customers':
                $data = ZohoSyncCustomers_B2BValidator::instance()->export_b2b_data();
                break;
            case 'pricing':
                $data = ZohoSyncCustomers_PricingManager::instance()->export_pricing_data();
                break;
            default:
                wp_send_json_error(__('Tipo de exportación no válido', 'zoho-sync-customers'));
        }
        
        // Generate export file
        $filename = $export_type . '_' . date('Y-m-d_H-i-s') . '.' . $format;
        $file_path = $this->generate_export_file($data, $format, $filename);
        
        if ($file_path) {
            wp_send_json_success(array(
                'download_url' => wp_upload_dir()['baseurl'] . '/zoho-exports/' . $filename,
                'filename' => $filename
            ));
        } else {
            wp_send_json_error(__('Error generando archivo de exportación', 'zoho-sync-customers'));
        }
    }
    
    /**
     * Add custom columns to users list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_user_columns($columns) {
        $columns['customer_type'] = __('Tipo de Cliente', 'zoho-sync-customers');
        $columns['distributor_level'] = __('Nivel Distribuidor', 'zoho-sync-customers');
        $columns['approval_status'] = __('Estado Aprobación', 'zoho-sync-customers');
        $columns['zoho_sync'] = __('Sincronizado', 'zoho-sync-customers');
        
        return $columns;
    }
    
    /**
     * Show custom column content
     *
     * @param string $value Column value
     * @param string $column_name Column name
     * @param int $user_id User ID
     * @return string Column content
     */
    public function show_user_column_content($value, $column_name, $user_id) {
        switch ($column_name) {
            case 'customer_type':
                $user = get_user_by('id', $user_id);
                if (in_array('distributor', $user->roles)) {
                    return '<span class="customer-type distributor">' . __('Distribuidor', 'zoho-sync-customers') . '</span>';
                } elseif (in_array('b2b_customer', $user->roles)) {
                    return '<span class="customer-type b2b">' . __('B2B', 'zoho-sync-customers') . '</span>';
                } elseif (in_array('customer', $user->roles)) {
                    return '<span class="customer-type regular">' . __('Regular', 'zoho-sync-customers') . '</span>';
                }
                return '-';
                
            case 'distributor_level':
                $level = get_user_meta($user_id, 'distributor_level', true);
                if ($level) {
                    $levels = ZohoSyncCustomers_DistributorManager::instance()->get_distributor_levels();
                    return isset($levels[$level]) ? $levels[$level]['name'] : $level;
                }
                return '-';
                
            case 'approval_status':
                $distributor_status = get_user_meta($user_id, 'distributor_approval_status', true);
                $b2b_status = get_user_meta($user_id, 'b2b_approval_status', true);
                
                if ($distributor_status) {
                    return $this->get_status_badge($distributor_status);
                } elseif ($b2b_status) {
                    return $this->get_status_badge($b2b_status);
                }
                return '-';
                
            case 'zoho_sync':
                $zoho_id = get_user_meta($user_id, 'zoho_contact_id', true);
                if ($zoho_id) {
                    return '<span class="dashicons dashicons-yes-alt" style="color: green;" title="' . __('Sincronizado', 'zoho-sync-customers') . '"></span>';
                } else {
                    return '<span class="dashicons dashicons-minus" style="color: #ccc;" title="' . __('No sincronizado', 'zoho-sync-customers') . '"></span>';
                }
        }
        
        return $value;
    }
    
    /**
     * Filter users by role
     *
     * @param array $args Query arguments
     * @return array Modified arguments
     */
    public function filter_users_by_role($args) {
        if (isset($_GET['customer_type'])) {
            $customer_type = sanitize_text_field($_GET['customer_type']);
            
            switch ($customer_type) {
                case 'distributor':
                    $args['role'] = 'distributor';
                    break;
                case 'b2b':
                    $args['role'] = 'b2b_customer';
                    break;
                case 'regular':
                    $args['role'] = 'customer';
                    break;
            }
        }
        
        return $args;
    }
    
    /**
     * Add user profile fields
     *
     * @param WP_User $user User object
     */
    public function add_user_profile_fields($user) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $customer_type = get_user_meta($user->ID, 'customer_type', true);
        $zoho_contact_id = get_user_meta($user->ID, 'zoho_contact_id', true);
        $distributor_level = get_user_meta($user->ID, 'distributor_level', true);
        $approval_status = get_user_meta($user->ID, 'distributor_approval_status', true) ?: get_user_meta($user->ID, 'b2b_approval_status', true);
        
        ?>
        <h3><?php _e('Información de Cliente Zoho', 'zoho-sync-customers'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="customer_type"><?php _e('Tipo de Cliente', 'zoho-sync-customers'); ?></label></th>
                <td>
                    <select name="customer_type" id="customer_type">
                        <option value="b2c" <?php selected($customer_type, 'b2c'); ?>><?php _e('B2C (Regular)', 'zoho-sync-customers'); ?></option>
                        <option value="b2b" <?php selected($customer_type, 'b2b'); ?>><?php _e('B2B (Empresarial)', 'zoho-sync-customers'); ?></option>
                        <option value="distributor" <?php selected($customer_type, 'distributor'); ?>><?php _e('Distribuidor', 'zoho-sync-customers'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="zoho_contact_id"><?php _e('ID de Contacto Zoho', 'zoho-sync-customers'); ?></label></th>
                <td>
                    <input type="text" name="zoho_contact_id" id="zoho_contact_id" value="<?php echo esc_attr($zoho_contact_id); ?>" class="regular-text" readonly />
                    <p class="description"><?php _e('ID del contacto en Zoho CRM (solo lectura)', 'zoho-sync-customers'); ?></p>
                </td>
            </tr>
            <?php if (in_array('distributor', $user->roles)): ?>
            <tr>
                <th><label for="distributor_level"><?php _e('Nivel de Distribuidor', 'zoho-sync-customers'); ?></label></th>
                <td>
                    <select name="distributor_level" id="distributor_level">
                        <?php
                        $levels = ZohoSyncCustomers_DistributorManager::instance()->get_distributor_levels();
                        foreach ($levels as $level_key => $level_data) {
                            echo '<option value="' . esc_attr($level_key) . '" ' . selected($distributor_level, $level_key, false) . '>';
                            echo esc_html($level_data['name']) . ' (' . $level_data['discount'] . '% descuento)';
                            echo '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label for="approval_status"><?php _e('Estado de Aprobación', 'zoho-sync-customers'); ?></label></th>
                <td>
                    <select name="approval_status" id="approval_status">
                        <option value="pending" <?php selected($approval_status, 'pending'); ?>><?php _e('Pendiente', 'zoho-sync-customers'); ?></option>
                        <option value="approved" <?php selected($approval_status, 'approved'); ?>><?php _e('Aprobado', 'zoho-sync-customers'); ?></option>
                        <option value="rejected" <?php selected($approval_status, 'rejected'); ?>><?php _e('Rechazado', 'zoho-sync-customers'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save user profile fields
     *
     * @param int $user_id User ID
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_POST['customer_type'])) {
            update_user_meta($user_id, 'customer_type', sanitize_text_field($_POST['customer_type']));
        }
        
        if (isset($_POST['distributor_level'])) {
            update_user_meta($user_id, 'distributor_level', sanitize_text_field($_POST['distributor_level']));
        }
        
        if (isset($_POST['approval_status'])) {
            $status = sanitize_text_field($_POST['approval_status']);
            $user = get_user_by('id', $user_id);
            
            if (in_array('distributor', $user->roles)) {
                update_user_meta($user_id, 'distributor_approval_status', $status);
            } elseif (in_array('b2b_customer', $user->roles)) {
                update_user_meta($user_id, 'b2b_approval_status', $status);
            }
        }
    }
    
    /**
     * Get status badge HTML
     *
     * @param string $status Status
     * @return string Badge HTML
     */
    private function get_status_badge($status) {
        $badges = array(
            'pending' => '<span class="status-badge pending">' . __('Pendiente', 'zoho-sync-customers') . '</span>',
            'approved' => '<span class="status-badge approved">' . __('Aprobado', 'zoho-sync-customers') . '</span>',
            'rejected' => '<span class="status-badge rejected">' . __('Rechazado', 'zoho-sync-customers') . '</span>'
        );
        
        return isset($badges[$status]) ? $badges[$status] : $status;
    }
    
    /**
     * Generate export file
     *
     * @param array $data Data to export
     * @param string $format Export format
     * @param string $filename Filename
     * @return string|false File path or false on failure
     */
    private function generate_export_file($data, $format, $filename) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/zoho-exports';
        
        // Create export directory if it doesn't exist
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        $file_path = $export_dir . '/' . $filename;
        
        switch ($format) {
            case 'csv':
                return $this->generate_csv_file($data, $file_path);
            case 'json':
                return $this->generate_json_file($data, $file_path);
            default:
                return false;
        }
    }
    
    /**
     * Generate CSV file
     *
     * @param array $data Data to export
     * @param string $file_path File path
     * @return string|false File path or false on failure
     */
    private function generate_csv_file($data, $file_path) {
        $file = fopen($file_path, 'w');
        
        if (!$file) {
            return false;
        }
        
        // Write headers
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            fputcsv($file, $headers);
            
            // Write data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
        }
        
        fclose($file);
        
        return $file_path;
    }
    
    /**
     * Generate JSON file
     *
     * @param array $data Data to export
     * @param string $file_path File path
     * @return string|false File path or false on failure
     */
    private function generate_json_file($data, $file_path) {
        $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($file_path, $json_data) !== false) {
            return $file_path;
        }
        
        return false;
    }
}