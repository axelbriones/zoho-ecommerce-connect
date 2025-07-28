<?php
/**
 * Verificador de Dependencias para Zoho Sync Core
 * 
 * @package ZohoSyncCore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase Dependency Checker para el ecosistema Zoho Sync
 * Verifica que todas las dependencias estén disponibles y configuradas correctamente
 */
class Zoho_Sync_Core_Dependency_Checker {
    
    /**
     * Dependencias requeridas
     * @var array
     */
    private $required_dependencies = array();
    
    /**
     * Dependencias opcionales
     * @var array
     */
    private $optional_dependencies = array();
    
    /**
     * Resultados de verificación
     * @var array
     */
    private $check_results = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->define_dependencies();
        
        // Hook para verificar dependencias en admin
        add_action('admin_init', array($this, 'check_all_dependencies'));
        add_action('admin_notices', array($this, 'display_dependency_notices'));
    }
    
    /**
     * Definir dependencias del sistema
     */
    private function define_dependencies() {
        // Dependencias requeridas
        $this->required_dependencies = array(
            'php_version' => array(
                'name' => __('Versión de PHP', 'zoho-sync-core'),
                'description' => __('PHP 7.4 o superior es requerido', 'zoho-sync-core'),
                'check_method' => 'check_php_version',
                'required_value' => '7.4.0',
                'critical' => true
            ),
            'wordpress_version' => array(
                'name' => __('Versión de WordPress', 'zoho-sync-core'),
                'description' => __('WordPress 5.0 o superior es requerido', 'zoho-sync-core'),
                'check_method' => 'check_wordpress_version',
                'required_value' => '5.0.0',
                'critical' => true
            ),
            'curl_extension' => array(
                'name' => __('Extensión cURL', 'zoho-sync-core'),
                'description' => __('cURL es necesario para comunicación con APIs', 'zoho-sync-core'),
                'check_method' => 'check_curl_extension',
                'critical' => true
            ),
            'json_extension' => array(
                'name' => __('Extensión JSON', 'zoho-sync-core'),
                'description' => __('JSON es necesario para procesar datos de API', 'zoho-sync-core'),
                'check_method' => 'check_json_extension',
                'critical' => true
            ),
            'openssl_extension' => array(
                'name' => __('Extensión OpenSSL', 'zoho-sync-core'),
                'description' => __('OpenSSL es necesario para conexiones seguras', 'zoho-sync-core'),
                'check_method' => 'check_openssl_extension',
                'critical' => true
            ),
            'database_connection' => array(
                'name' => __('Conexión a Base de Datos', 'zoho-sync-core'),
                'description' => __('Conexión válida a la base de datos', 'zoho-sync-core'),
                'check_method' => 'check_database_connection',
                'critical' => true
            ),
            'write_permissions' => array(
                'name' => __('Permisos de Escritura', 'zoho-sync-core'),
                'description' => __('Permisos para escribir logs y cache', 'zoho-sync-core'),
                'check_method' => 'check_write_permissions',
                'critical' => false
            )
        );
        
        // Dependencias opcionales
        $this->optional_dependencies = array(
            'woocommerce' => array(
                'name' => __('WooCommerce', 'zoho-sync-core'),
                'description' => __('Requerido para sincronización de e-commerce', 'zoho-sync-core'),
                'check_method' => 'check_woocommerce',
                'min_version' => '5.0.0'
            ),
            'mbstring_extension' => array(
                'name' => __('Extensión mbstring', 'zoho-sync-core'),
                'description' => __('Recomendado para manejo de caracteres UTF-8', 'zoho-sync-core'),
                'check_method' => 'check_mbstring_extension'
            ),
            'gd_extension' => array(
                'name' => __('Extensión GD', 'zoho-sync-core'),
                'description' => __('Recomendado para procesamiento de imágenes', 'zoho-sync-core'),
                'check_method' => 'check_gd_extension'
            ),
            'zip_extension' => array(
                'name' => __('Extensión ZIP', 'zoho-sync-core'),
                'description' => __('Recomendado para exportación de archivos', 'zoho-sync-core'),
                'check_method' => 'check_zip_extension'
            ),
            'memory_limit' => array(
                'name' => __('Límite de Memoria', 'zoho-sync-core'),
                'description' => __('Al menos 128MB recomendado', 'zoho-sync-core'),
                'check_method' => 'check_memory_limit',
                'recommended_value' => '128M'
            ),
            'max_execution_time' => array(
                'name' => __('Tiempo Máximo de Ejecución', 'zoho-sync-core'),
                'description' => __('Al menos 60 segundos recomendado', 'zoho-sync-core'),
                'check_method' => 'check_max_execution_time',
                'recommended_value' => 60
            )
        );
    }
    
    /**
     * Verificar todas las dependencias
     * @return array Resultados de verificación
     */
    public function check_all_dependencies() {
        $this->check_results = array(
            'required' => array(),
            'optional' => array(),
            'overall_status' => 'passed',
            'critical_issues' => 0,
            'warnings' => 0,
            'timestamp' => current_time('mysql')
        );
        
        // Verificar dependencias requeridas
        foreach ($this->required_dependencies as $key => $dependency) {
            $result = $this->run_dependency_check($key, $dependency);
            $this->check_results['required'][$key] = $result;
            
            if (!$result['passed']) {
                if ($dependency['critical']) {
                    $this->check_results['critical_issues']++;
                    $this->check_results['overall_status'] = 'failed';
                } else {
                    $this->check_results['warnings']++;
                    if ($this->check_results['overall_status'] === 'passed') {
                        $this->check_results['overall_status'] = 'warning';
                    }
                }
            }
        }
        
        // Verificar dependencias opcionales
        foreach ($this->optional_dependencies as $key => $dependency) {
            $result = $this->run_dependency_check($key, $dependency);
            $this->check_results['optional'][$key] = $result;
            
            if (!$result['passed']) {
                $this->check_results['warnings']++;
            }
        }
        
        // Guardar resultados en cache
        $this->cache_results();
        
        // Log de resultados
        $this->log_check_results();
        
        return $this->check_results;
    }
    
    /**
     * Ejecutar verificación individual
     * @param string $key Clave de la dependencia
     * @param array $dependency Configuración de la dependencia
     * @return array Resultado de la verificación
     */
    private function run_dependency_check($key, $dependency) {
        $result = array(
            'name' => $dependency['name'],
            'description' => $dependency['description'],
            'passed' => false,
            'current_value' => null,
            'required_value' => $dependency['required_value'] ?? null,
            'message' => '',
            'details' => array()
        );
        
        try {
            if (method_exists($this, $dependency['check_method'])) {
                $check_result = call_user_func(array($this, $dependency['check_method']), $dependency);
                $result = array_merge($result, $check_result);
            } else {
                $result['message'] = __('Método de verificación no encontrado', 'zoho-sync-core');
            }
        } catch (Exception $e) {
            $result['message'] = sprintf(
                __('Error en verificación: %s', 'zoho-sync-core'),
                $e->getMessage()
            );
        }
        
        return $result;
    }
    
    /**
     * Verificar versión de PHP
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_php_version($dependency) {
        $current_version = PHP_VERSION;
        $required_version = $dependency['required_value'];
        
        $passed = version_compare($current_version, $required_version, '>=');
        
        return array(
            'passed' => $passed,
            'current_value' => $current_version,
            'message' => $passed 
                ? __('Versión de PHP compatible', 'zoho-sync-core')
                : sprintf(
                    __('PHP %s requerido, actual: %s', 'zoho-sync-core'),
                    $required_version,
                    $current_version
                )
        );
    }
    
    /**
     * Verificar versión de WordPress
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_wordpress_version($dependency) {
        $current_version = get_bloginfo('version');
        $required_version = $dependency['required_value'];
        
        $passed = version_compare($current_version, $required_version, '>=');
        
        return array(
            'passed' => $passed,
            'current_value' => $current_version,
            'message' => $passed 
                ? __('Versión de WordPress compatible', 'zoho-sync-core')
                : sprintf(
                    __('WordPress %s requerido, actual: %s', 'zoho-sync-core'),
                    $required_version,
                    $current_version
                )
        );
    }
    
    /**
     * Verificar extensión cURL
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_curl_extension($dependency) {
        $passed = extension_loaded('curl');
        
        $details = array();
        if ($passed) {
            $curl_info = curl_version();
            $details = array(
                'version' => $curl_info['version'],
                'ssl_version' => $curl_info['ssl_version'],
                'protocols' => $curl_info['protocols']
            );
        }
        
        return array(
            'passed' => $passed,
            'current_value' => $passed ? __('Instalado', 'zoho-sync-core') : __('No instalado', 'zoho-sync-core'),
            'message' => $passed 
                ? __('Extensión cURL disponible', 'zoho-sync-core')
                : __('Extensión cURL no encontrada', 'zoho-sync-core'),
            'details' => $details
        );
    }
    
    /**
     * Verificar extensión JSON
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_json_extension($dependency) {
        $passed = extension_loaded('json') && function_exists('json_encode') && function_exists('json_decode');
        
        return array(
            'passed' => $passed,
            'current_value' => $passed ? __('Instalado', 'zoho-sync-core') : __('No instalado', 'zoho-sync-core'),
            'message' => $passed 
                ? __('Extensión JSON disponible', 'zoho-sync-core')
                : __('Extensión JSON no encontrada', 'zoho-sync-core')
        );
    }
    
    /**
     * Verificar extensión OpenSSL
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_openssl_extension($dependency) {
        $passed = extension_loaded('openssl');
        
        $details = array();
        if ($passed) {
            $details['version'] = OPENSSL_VERSION_TEXT;
        }
        
        return array(
            'passed' => $passed,
            'current_value' => $passed ? __('Instalado', 'zoho-sync-core') : __('No instalado', 'zoho-sync-core'),
            'message' => $passed 
                ? __('Extensión OpenSSL disponible', 'zoho-sync-core')
                : __('Extensión OpenSSL no encontrada', 'zoho-sync-core'),
            'details' => $details
        );
    }
    
    /**
     * Verificar conexión a base de datos
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_database_connection($dependency) {
        global $wpdb;
        
        $passed = false;
        $message = '';
        $details = array();
        
        try {
            // Intentar una consulta simple
            $result = $wpdb->get_var("SELECT 1");
            $passed = ($result == 1);
            
            if ($passed) {
                $details = array(
                    'database' => DB_NAME,
                    'host' => DB_HOST,
                    'charset' => $wpdb->charset,
                    'collate' => $wpdb->collate
                );
                $message = __('Conexión a base de datos exitosa', 'zoho-sync-core');
            } else {
                $message = __('Error en consulta de prueba', 'zoho-sync-core');
            }
        } catch (Exception $e) {
            $message = sprintf(
                __('Error de conexión: %s', 'zoho-sync-core'),
                $e->getMessage()
            );
        }
        
        return array(
            'passed' => $passed,
            'current_value' => $passed ? __('Conectado', 'zoho-sync-core') : __('Error', 'zoho-sync-core'),
            'message' => $message,
            'details' => $details
        );
    }
    
    /**
     * Verificar permisos de escritura
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_write_permissions($dependency) {
        $upload_dir = wp_upload_dir();
        $test_dirs = array(
            $upload_dir['basedir'],
            WP_CONTENT_DIR . '/uploads/zoho-sync-logs'
        );
        
        $passed = true;
        $details = array();
        $messages = array();
        
        foreach ($test_dirs as $dir) {
            $writable = wp_mkdir_p($dir) && is_writable($dir);
            $details[$dir] = $writable;
            
            if (!$writable) {
                $passed = false;
                $messages[] = sprintf(
                    __('No se puede escribir en: %s', 'zoho-sync-core'),
                    $dir
                );
            }
        }
        
        return array(
            'passed' => $passed,
            'current_value' => $passed ? __('Permisos OK', 'zoho-sync-core') : __('Permisos insuficientes', 'zoho-sync-core'),
            'message' => $passed 
                ? __('Permisos de escritura correctos', 'zoho-sync-core')
                : implode(', ', $messages),
            'details' => $details
        );
    }
    
    /**
     * Verificar WooCommerce
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_woocommerce($dependency) {
        $passed = false;
        $current_version = null;
        $message = '';
        
        if (class_exists('WooCommerce')) {
            $woocommerce = WC();
            $current_version = $woocommerce->version;
            $min_version = $dependency['min_version'];
            
            $passed = version_compare($current_version, $min_version, '>=');
            
            $message = $passed 
                ? __('WooCommerce compatible', 'zoho-sync-core')
                : sprintf(
                    __('WooCommerce %s requerido, actual: %s', 'zoho-sync-core'),
                    $min_version,
                    $current_version
                );
        } else {
            $message = __('WooCommerce no está instalado', 'zoho-sync-core');
        }
        
        return array(
            'passed' => $passed,
            'current_value' => $current_version ?: __('No instalado', 'zoho-sync-core'),
            'message' => $message
        );
    }
    
    /**
     * Verificar extensión mbstring
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_mbstring_extension($dependency) {
        $passed = extension_loaded('mbstring');
        
        return array(
            'passed' => $passed,
            'current_value' => $passed ? __('Instalado', 'zoho-sync-core') : __('No instalado', 'zoho-sync-core'),
            'message' => $passed 
                ? __('Extensión mbstring disponible', 'zoho-sync-core')
                : __('Extensión mbstring recomendada para mejor soporte UTF-8', 'zoho-sync-core')
        );
    }
    
    /**
     * Verificar extensión GD
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_gd_extension($dependency) {
        $passed = extension_loaded('gd');
        
        $details = array();
        if ($passed) {
            $gd_info = gd_info();
            $details = array(
                'version' => $gd_info['GD Version'],
                'jpeg_support' => $gd_info['JPEG Support'],
                'png_support' => $gd_info['PNG Support']
            );
        }
        
        return array(
            'passed' => $passed,
            'current_value' => $passed ? __('Instalado', 'zoho-sync-core') : __('No instalado', 'zoho-sync-core'),
            'message' => $passed 
                ? __('Extensión GD disponible', 'zoho-sync-core')
                : __('Extensión GD recomendada para procesamiento de imágenes', 'zoho-sync-core'),
            'details' => $details
        );
    }
    
    /**
     * Verificar extensión ZIP
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_zip_extension($dependency) {
        $passed = extension_loaded('zip');
        
        return array(
            'passed' => $passed,
            'current_value' => $passed ? __('Instalado', 'zoho-sync-core') : __('No instalado', 'zoho-sync-core'),
            'message' => $passed 
                ? __('Extensión ZIP disponible', 'zoho-sync-core')
                : __('Extensión ZIP recomendada para exportación de archivos', 'zoho-sync-core')
        );
    }
    
    /**
     * Verificar límite de memoria
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_memory_limit($dependency) {
        $current_limit = ini_get('memory_limit');
        $recommended_bytes = $this->convert_to_bytes($dependency['recommended_value']);
        $current_bytes = $this->convert_to_bytes($current_limit);
        
        $passed = ($current_bytes >= $recommended_bytes) || ($current_limit === '-1');
        
        return array(
            'passed' => $passed,
            'current_value' => $current_limit,
            'message' => $passed 
                ? __('Límite de memoria adecuado', 'zoho-sync-core')
                : sprintf(
                    __('Se recomienda al menos %s, actual: %s', 'zoho-sync-core'),
                    $dependency['recommended_value'],
                    $current_limit
                )
        );
    }
    
    /**
     * Verificar tiempo máximo de ejecución
     * @param array $dependency Configuración
     * @return array Resultado
     */
    private function check_max_execution_time($dependency) {
        $current_time = ini_get('max_execution_time');
        $recommended_time = $dependency['recommended_value'];
        
        $passed = ($current_time >= $recommended_time) || ($current_time == 0);
        
        return array(
            'passed' => $passed,
            'current_value' => $current_time == 0 ? __('Sin límite', 'zoho-sync-core') : $current_time . 's',
            'message' => $passed 
                ? __('Tiempo de ejecución adecuado', 'zoho-sync-core')
                : sprintf(
                    __('Se recomienda al menos %d segundos, actual: %s', 'zoho-sync-core'),
                    $recommended_time,
                    $current_time
                )
        );
    }
    
    /**
     * Convertir valor de memoria a bytes
     * @param string $value Valor de memoria
     * @return int Bytes
     */
    private function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Guardar resultados en cache
     */
    private function cache_results() {
        update_option('zoho_sync_core_dependency_check', $this->check_results);
    }
    
    /**
     * Obtener resultados desde cache
     * @return array|false Resultados o false si no hay cache
     */
    public function get_cached_results() {
        return get_option('zoho_sync_core_dependency_check', false);
    }
    
    /**
     * Log de resultados de verificación
     */
    private function log_check_results() {
        $level = 'info';
        if ($this->check_results['critical_issues'] > 0) {
            $level = 'critical';
        } elseif ($this->check_results['warnings'] > 0) {
            $level = 'warning';
        }
        
        ZohoSyncCore::log($level, 'Verificación de dependencias completada', array(
            'overall_status' => $this->check_results['overall_status'],
            'critical_issues' => $this->check_results['critical_issues'],
            'warnings' => $this->check_results['warnings']
        ), 'dependencies');
    }
    
    /**
     * Mostrar notificaciones de dependencias en admin
     */
    public function display_dependency_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $results = $this->get_cached_results();
        if (!$results || $results['overall_status'] === 'passed') {
            return;
        }
        
        // Mostrar errores críticos
        if ($results['critical_issues'] > 0) {
            $this->display_critical_notice($results);
        }
        
        // Mostrar advertencias
        if ($results['warnings'] > 0 && $results['critical_issues'] === 0) {
            $this->display_warning_notice($results);
        }
    }
    
    /**
     * Mostrar notificación crítica
     * @param array $results Resultados
     */
    private function display_critical_notice($results) {
        $failed_deps = array();
        
        foreach ($results['required'] as $key => $result) {
            if (!$result['passed'] && $this->required_dependencies[$key]['critical']) {
                $failed_deps[] = $result['name'] . ': ' . $result['message'];
            }
        }
        
        if (!empty($failed_deps)) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . __('Zoho Sync Core: Dependencias críticas faltantes', 'zoho-sync-core') . '</strong><br>';
            echo implode('<br>', $failed_deps);
            echo '</p></div>';
        }
    }
    
    /**
     * Mostrar notificación de advertencia
     * @param array $results Resultados
     */
    private function display_warning_notice($results) {
        $warning_count = $results['warnings'];
        
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>' . __('Zoho Sync Core: Advertencias de dependencias', 'zoho-sync-core') . '</strong><br>';
        echo sprintf(
            _n(
                'Se encontró %d advertencia en la verificación de dependencias.',
                'Se encontraron %d advertencias en la verificación de dependencias.',
                $warning_count,
                'zoho-sync-core'
            ),
            $warning_count
        );
        echo ' <a href="' . admin_url('admin.php?page=zoho-sync-core-dependencies') . '">';
        echo __('Ver detalles', 'zoho-sync-core') . '</a>';
        echo '</p></div>';
    }
    
    /**
     * Obtener resumen de estado
     * @return array Resumen
     */
    public function get_status_summary() {
        $results = $this->get_cached_results();
        
        if (!$results) {
            return array(
                'status' => 'unknown',
                'message' => __('Verificación no realizada', 'zoho-sync-core'),
                'last_check' => null
            );
        }
        
        $status_messages = array(
            'passed' => __('Todas las dependencias están correctas', 'zoho-sync-core'),
            'warning' => sprintf(
                __('%d advertencias encontradas', 'zoho-sync-core'),
                $results['warnings']
            ),
            'failed' => sprintf(
                __('%d problemas críticos encontrados', 'zoho-sync-core'),
                $results['critical_issues']
            )
        );
        
        return array(
            'status' => $results['overall_status'],
            'message' => $status_messages[$results['overall_status']],
            'critical_issues' => $results['critical_issues'],
            'warnings' => $results['warnings'],
            'last_check' => $results['timestamp']
        );
    }
    
    /**
     * Forzar nueva verificación
     * @return array Resultados
     */
    public function force_recheck() {
        delete_option('zoho_sync_core_dependency_check');
        return $this->check_all_dependencies();
    }
}
