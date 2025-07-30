<?php
/**
 * Zoho Dependency Checker
 * 
 * Checks and manages plugin dependencies
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
 * Zoho Dependency Checker Class
 * 
 * Manages plugin dependencies and ecosystem status
 */
class Zoho_Sync_Core_Dependency_Checker {

    /**
     * Logger instance
     * 
     * @var Zoho_Sync_Core_Logger
     */
    private $logger;

    /**
     * Required plugins in the ecosystem
     * 
     * @var array
     */
    private $ecosystem_plugins = array(
        'zoho-sync-orders' => array(
            'name' => 'Zoho Sync Orders',
            'file' => 'zoho-sync-orders/zoho-sync-orders.php',
            'required' => false,
            'description' => 'Sincronización de pedidos con Zoho'
        ),
        'zoho-sync-customers' => array(
            'name' => 'Zoho Sync Customers',
            'file' => 'zoho-sync-customers/zoho-sync-customers.php',
            'required' => false,
            'description' => 'Sincronización de clientes con Zoho CRM'
        ),
        'zoho-sync-products' => array(
            'name' => 'Zoho Sync Products',
            'file' => 'zoho-sync-products/zoho-sync-products.php',
            'required' => false,
            'description' => 'Sincronización de productos con Zoho Inventory'
        ),
        'zoho-sync-inventory' => array(
            'name' => 'Zoho Sync Inventory',
            'file' => 'zoho-sync-inventory/zoho-sync-inventory.php',
            'required' => false,
            'description' => 'Sincronización de inventario'
        ),
        'zoho-sync-reports' => array(
            'name' => 'Zoho Sync Reports',
            'file' => 'zoho-sync-reports/zoho-sync-reports.php',
            'required' => false,
            'description' => 'Reportes de ventas B2B/B2C'
        ),
        'zoho-sync-zone-blocker' => array(
            'name' => 'Zoho Zone Blocker',
            'file' => 'zoho-sync-zone-blocker/zoho-sync-zone-blocker.php',
            'required' => false,
            'description' => 'Bloqueo por zona postal'
        ),
        'zoho-distributor-portal' => array(
            'name' => 'Zoho Distributor Portal',
            'file' => 'zoho-distributor-portal/zoho-distributor-portal.php',
            'required' => false,
            'description' => 'Portal para distribuidores B2B'
        )
    );

    /**
     * System requirements
     * 
     * @var array
     */
    private $system_requirements = array(
        'php_version' => '7.4',
        'wp_version' => '5.0',
        'wc_version' => '4.0',
        'mysql_version' => '5.6',
        'memory_limit' => '128M',
        'max_execution_time' => 30
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Zoho_Sync_Core_Logger();
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'check_dependencies'));
        add_action('admin_notices', array($this, 'display_dependency_notices'));
        add_filter('plugin_action_links_' . ZOHO_SYNC_CORE_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }

    /**
     * Check all dependencies
     */
    public function check_dependencies() {
        $this->check_system_requirements();
        $this->check_plugin_dependencies();
        $this->check_ecosystem_status();
    }

    /**
     * Check system requirements
     * 
     * @return array Requirements status
     */
    public function check_system_requirements() {
        $requirements_status = array();

        // PHP Version
        $requirements_status['php_version'] = array(
            'required' => $this->system_requirements['php_version'],
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, $this->system_requirements['php_version'], '>='),
            'message' => version_compare(PHP_VERSION, $this->system_requirements['php_version'], '>=') 
                ? __('PHP versión compatible', 'zoho-sync-core')
                : sprintf(__('PHP %s requerido, versión actual: %s', 'zoho-sync-core'), $this->system_requirements['php_version'], PHP_VERSION)
        );

        // WordPress Version
        global $wp_version;
        $requirements_status['wp_version'] = array(
            'required' => $this->system_requirements['wp_version'],
            'current' => $wp_version,
            'status' => version_compare($wp_version, $this->system_requirements['wp_version'], '>='),
            'message' => version_compare($wp_version, $this->system_requirements['wp_version'], '>=')
                ? __('WordPress versión compatible', 'zoho-sync-core')
                : sprintf(__('WordPress %s requerido, versión actual: %s', 'zoho-sync-core'), $this->system_requirements['wp_version'], $wp_version)
        );

        // WooCommerce Version
        if (class_exists('WooCommerce')) {
            $wc_version = WC()->version;
            $requirements_status['wc_version'] = array(
                'required' => $this->system_requirements['wc_version'],
                'current' => $wc_version,
                'status' => version_compare($wc_version, $this->system_requirements['wc_version'], '>='),
                'message' => version_compare($wc_version, $this->system_requirements['wc_version'], '>=')
                    ? __('WooCommerce versión compatible', 'zoho-sync-core')
                    : sprintf(__('WooCommerce %s requerido, versión actual: %s', 'zoho-sync-core'), $this->system_requirements['wc_version'], $wc_version)
            );
        } else {
            $requirements_status['wc_version'] = array(
                'required' => $this->system_requirements['wc_version'],
                'current' => 'No instalado',
                'status' => false,
                'message' => __('WooCommerce no está instalado', 'zoho-sync-core')
            );
        }

        // Memory Limit
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->convert_to_bytes($memory_limit);
        $required_memory_bytes = $this->convert_to_bytes($this->system_requirements['memory_limit']);
        
        $requirements_status['memory_limit'] = array(
            'required' => $this->system_requirements['memory_limit'],
            'current' => $memory_limit,
            'status' => $memory_limit === '-1' || $memory_limit_bytes >= $required_memory_bytes,
            'message' => $memory_limit === '-1' || $memory_limit_bytes >= $required_memory_bytes
                ? __('Límite de memoria suficiente', 'zoho-sync-core')
                : sprintf(__('Memoria %s requerida, actual: %s', 'zoho-sync-core'), $this->system_requirements['memory_limit'], $memory_limit)
        );

        // Max Execution Time
        $max_execution_time = ini_get('max_execution_time');
        $requirements_status['max_execution_time'] = array(
            'required' => $this->system_requirements['max_execution_time'],
            'current' => $max_execution_time,
            'status' => $max_execution_time == 0 || $max_execution_time >= $this->system_requirements['max_execution_time'],
            'message' => $max_execution_time == 0 || $max_execution_time >= $this->system_requirements['max_execution_time']
                ? __('Tiempo de ejecución suficiente', 'zoho-sync-core')
                : sprintf(__('Tiempo de ejecución %ds requerido, actual: %ds', 'zoho-sync-core'), $this->system_requirements['max_execution_time'], $max_execution_time)
        );

        // MySQL Version
        global $wpdb;
        $mysql_version = $wpdb->get_var("SELECT VERSION()");
        $requirements_status['mysql_version'] = array(
            'required' => $this->system_requirements['mysql_version'],
            'current' => $mysql_version,
            'status' => version_compare($mysql_version, $this->system_requirements['mysql_version'], '>='),
            'message' => version_compare($mysql_version, $this->system_requirements['mysql_version'], '>=')
                ? __('MySQL versión compatible', 'zoho-sync-core')
                : sprintf(__('MySQL %s requerido, versión actual: %s', 'zoho-sync-core'), $this->system_requirements['mysql_version'], $mysql_version)
        );

        // Store results
        update_option('zoho_sync_core_system_requirements', $requirements_status);

        return $requirements_status;
    }

    /**
     * Check plugin dependencies
     * 
     * @return array Plugin dependencies status
     */
    public function check_plugin_dependencies() {
        $dependencies_status = array();

        // Check if required plugins are active
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // WooCommerce (required)
        $dependencies_status['woocommerce'] = array(
            'name' => 'WooCommerce',
            'required' => true,
            'status' => is_plugin_active('woocommerce/woocommerce.php'),
            'message' => is_plugin_active('woocommerce/woocommerce.php')
                ? __('WooCommerce activo', 'zoho-sync-core')
                : __('WooCommerce requerido pero no está activo', 'zoho-sync-core')
        );

        // Store results
        update_option('zoho_sync_core_plugin_dependencies', $dependencies_status);

        return $dependencies_status;
    }

    /**
     * Check ecosystem plugins status
     * 
     * @return array Ecosystem status
     */
    public function check_ecosystem_status() {
        $ecosystem_status = array();

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ($this->ecosystem_plugins as $plugin_key => $plugin_info) {
            $is_active = is_plugin_active($plugin_info['file']);
            $plugin_file_path = WP_PLUGIN_DIR . '/' . $plugin_info['file'];
            $is_installed = file_exists($plugin_file_path);

            $status = 'not_installed';
            $message = __('No instalado', 'zoho-sync-core');

            if ($is_installed && $is_active) {
                $status = 'active';
                $message = __('Activo y funcionando', 'zoho-sync-core');
            } elseif ($is_installed && !$is_active) {
                $status = 'inactive';
                $message = __('Instalado pero inactivo', 'zoho-sync-core');
            }

            $ecosystem_status[$plugin_key] = array(
                'name' => $plugin_info['name'],
                'description' => $plugin_info['description'],
                'file' => $plugin_info['file'],
                'required' => $plugin_info['required'],
                'installed' => $is_installed,
                'active' => $is_active,
                'status' => $status,
                'message' => $message
            );
        }

        // Store results
        update_option('zoho_sync_core_ecosystem_status', $ecosystem_status);

        return $ecosystem_status;
    }

    /**
     * Get modules status
     * 
     * @return array Modules status
     */
    public function get_modules_status() {
        $system_requirements = get_option('zoho_sync_core_system_requirements', array());
        $plugin_dependencies = get_option('zoho_sync_core_plugin_dependencies', array());
        $ecosystem_status = get_option('zoho_sync_core_ecosystem_status', array());

        return array(
            'system_requirements' => $system_requirements,
            'plugin_dependencies' => $plugin_dependencies,
            'ecosystem_status' => $ecosystem_status,
            'overall_status' => $this->get_overall_status($system_requirements, $plugin_dependencies, $ecosystem_status)
        );
    }

    /**
     * Get overall system status
     * 
     * @param array $system_requirements System requirements
     * @param array $plugin_dependencies Plugin dependencies
     * @param array $ecosystem_status Ecosystem status
     * @return array Overall status
     */
    private function get_overall_status($system_requirements, $plugin_dependencies, $ecosystem_status) {
        $critical_issues = 0;
        $warnings = 0;
        $active_modules = 0;
        $total_modules = count($ecosystem_status);

        // Check system requirements
        foreach ($system_requirements as $requirement) {
            if (!$requirement['status']) {
                $critical_issues++;
            }
        }

        // Check plugin dependencies
        foreach ($plugin_dependencies as $dependency) {
            if ($dependency['required'] && !$dependency['status']) {
                $critical_issues++;
            }
        }

        // Check ecosystem
        foreach ($ecosystem_status as $plugin) {
            if ($plugin['active']) {
                $active_modules++;
            } elseif ($plugin['installed'] && !$plugin['active']) {
                $warnings++;
            }
        }

        $status = 'healthy';
        $message = __('Sistema funcionando correctamente', 'zoho-sync-core');

        if ($critical_issues > 0) {
            $status = 'critical';
            $message = sprintf(__('%d problemas críticos detectados', 'zoho-sync-core'), $critical_issues);
        } elseif ($warnings > 0) {
            $status = 'warning';
            $message = sprintf(__('%d advertencias detectadas', 'zoho-sync-core'), $warnings);
        }

        return array(
            'status' => $status,
            'message' => $message,
            'critical_issues' => $critical_issues,
            'warnings' => $warnings,
            'active_modules' => $active_modules,
            'total_modules' => $total_modules,
            'completion_percentage' => $total_modules > 0 ? round(($active_modules / $total_modules) * 100) : 0
        );
    }

    /**
     * Display dependency notices
     */
    public function display_dependency_notices() {
        $system_requirements = get_option('zoho_sync_core_system_requirements', array());
        $plugin_dependencies = get_option('zoho_sync_core_plugin_dependencies', array());

        // System requirements notices
        foreach ($system_requirements as $requirement_key => $requirement) {
            if (!$requirement['status']) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Zoho Sync Core:</strong> ' . esc_html($requirement['message']);
                echo '</p></div>';
            }
        }

        // Plugin dependencies notices
        foreach ($plugin_dependencies as $dependency_key => $dependency) {
            if ($dependency['required'] && !$dependency['status']) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>Zoho Sync Core:</strong> ' . esc_html($dependency['message']);
                echo '</p></div>';
            }
        }
    }

    /**
     * Add action links to plugin page
     * 
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links($links) {
        $ecosystem_status = get_option('zoho_sync_core_ecosystem_status', array());
        $active_count = 0;
        $total_count = count($ecosystem_status);

        foreach ($ecosystem_status as $plugin) {
            if ($plugin['active']) {
                $active_count++;
            }
        }

        $status_link = sprintf(
            '<span style="color: %s;">%s (%d/%d)</span>',
            $active_count === $total_count ? '#46b450' : '#ffb900',
            __('Ecosistema', 'zoho-sync-core'),
            $active_count,
            $total_count
        );

        array_unshift($links, $status_link);

        return $links;
    }

    /**
     * Convert memory limit to bytes
     * 
     * @param string $memory_limit Memory limit string
     * @return int Bytes
     */
    private function convert_to_bytes($memory_limit) {
        if ($memory_limit === '-1') {
            return PHP_INT_MAX;
        }

        $memory_limit = trim($memory_limit);
        $last = strtolower($memory_limit[strlen($memory_limit) - 1]);
        $memory_limit = (int) $memory_limit;

        switch ($last) {
            case 'g':
                $memory_limit *= 1024;
            case 'm':
                $memory_limit *= 1024;
            case 'k':
                $memory_limit *= 1024;
        }

        return $memory_limit;
    }

    /**
     * Get ecosystem recommendations
     * 
     * @return array Recommendations
     */
    public function get_ecosystem_recommendations() {
        $ecosystem_status = get_option('zoho_sync_core_ecosystem_status', array());
        $recommendations = array();

        foreach ($ecosystem_status as $plugin_key => $plugin) {
            if (!$plugin['installed']) {
                $recommendations[] = array(
                    'type' => 'install',
                    'plugin' => $plugin_key,
                    'message' => sprintf(__('Considera instalar %s para %s', 'zoho-sync-core'), $plugin['name'], $plugin['description']),
                    'priority' => $plugin['required'] ? 'high' : 'medium'
                );
            } elseif ($plugin['installed'] && !$plugin['active']) {
                $recommendations[] = array(
                    'type' => 'activate',
                    'plugin' => $plugin_key,
                    'message' => sprintf(__('Activa %s para aprovechar %s', 'zoho-sync-core'), $plugin['name'], $plugin['description']),
                    'priority' => $plugin['required'] ? 'high' : 'medium'
                );
            }
        }

        return $recommendations;
    }

    /**
     * Check if ecosystem is complete
     * 
     * @return bool True if all plugins are active
     */
    public function is_ecosystem_complete() {
        $ecosystem_status = get_option('zoho_sync_core_ecosystem_status', array());
        
        foreach ($ecosystem_status as $plugin) {
            if (!$plugin['active']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get ecosystem health score
     * 
     * @return int Health score (0-100)
     */
    public function get_ecosystem_health_score() {
        $modules_status = $this->get_modules_status();
        $overall_status = $modules_status['overall_status'];
        
        $score = 100;
        
        // Deduct points for critical issues
        $score -= $overall_status['critical_issues'] * 20;
        
        // Deduct points for warnings
        $score -= $overall_status['warnings'] * 5;
        
        // Bonus for active modules
        $score += $overall_status['completion_percentage'] * 0.1;
        
        return max(0, min(100, $score));
    }
}
