<?php
/**
 * Settings Display
 * 
 * Settings configuration view for Zoho Sync Core plugin
 * 
 * @package ZohoSyncCore
 * @subpackage Admin/Partials
 * @since 8.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Get instances
$settings = zoho_sync_core_settings();
$auth = zoho_sync_core_auth();

// Render admin header
$admin_pages = new Zoho_Sync_Core_Admin_Pages();
$admin_pages->render_admin_header(
    __('Configuración de Zoho Sync', 'zoho-sync-core'),
    __('Configura los parámetros principales del sistema de sincronización', 'zoho-sync-core')
);

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'zoho_sync_core_settings-options')) {
    // Settings will be handled by WordPress Settings API
}
?>

<div class="zoho-sync-settings">
    <div class="zoho-sync-settings-container">
        
        <!-- Settings Navigation -->
        <div class="zoho-sync-settings-nav">
            <ul class="zoho-sync-nav-tabs">
                <li class="zoho-sync-nav-tab active" data-tab="api">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php _e('API', 'zoho-sync-core'); ?>
                </li>
                <li class="zoho-sync-nav-tab" data-tab="logging">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('Logs', 'zoho-sync-core'); ?>
                </li>
                <li class="zoho-sync-nav-tab" data-tab="sync">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Sincronización', 'zoho-sync-core'); ?>
                </li>
                <li class="zoho-sync-nav-tab" data-tab="advanced">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Avanzado', 'zoho-sync-core'); ?>
                </li>
            </ul>
        </div>

        <!-- Settings Form -->
        <form method="post" action="options.php" class="zoho-sync-settings-form">
            <?php
            settings_fields('zoho_sync_core_settings');
            do_settings_sections('zoho_sync_core_settings');
            ?>

            <!-- API Settings Tab -->
            <div class="zoho-sync-tab-content active" id="tab-api">
                <div class="zoho-sync-settings-section">
                    <div class="zoho-sync-section-header">
                        <h2><?php _e('Configuración de API de Zoho', 'zoho-sync-core'); ?></h2>
                        <p class="zoho-sync-section-description">
                            <?php _e('Configura las credenciales de tu aplicación Zoho para habilitar la sincronización.', 'zoho-sync-core'); ?>
                        </p>
                    </div>

                    <div class="zoho-sync-settings-grid">
                        
                        <!-- Client ID -->
                        <div class="zoho-sync-field-group">
                            <label for="zoho_client_id" class="zoho-sync-field-label">
                                <?php _e('Client ID', 'zoho-sync-core'); ?>
                                <span class="zoho-sync-required">*</span>
                            </label>
                            <input type="text" 
                                   id="zoho_client_id" 
                                   name="zoho_sync_core_settings[zoho_client_id]" 
                                   value="<?php echo esc_attr($settings->get('zoho_client_id', '')); ?>" 
                                   class="zoho-sync-field-input large-text" 
                                   placeholder="<?php esc_attr_e('Ingresa tu Client ID de Zoho', 'zoho-sync-core'); ?>"
                                   required>
                            <p class="zoho-sync-field-description">
                                <?php _e('ID del cliente de tu aplicación Zoho. Lo puedes obtener desde la consola de desarrolladores de Zoho.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Client Secret -->
                        <div class="zoho-sync-field-group">
                            <label for="zoho_client_secret" class="zoho-sync-field-label">
                                <?php _e('Client Secret', 'zoho-sync-core'); ?>
                                <span class="zoho-sync-required">*</span>
                            </label>
                            <div class="zoho-sync-password-field">
                                <input type="password" 
                                       id="zoho_client_secret" 
                                       name="zoho_sync_core_settings[zoho_client_secret]" 
                                       value="<?php echo esc_attr($settings->get('zoho_client_secret', '')); ?>" 
                                       class="zoho-sync-field-input large-text" 
                                       placeholder="<?php esc_attr_e('Ingresa tu Client Secret de Zoho', 'zoho-sync-core'); ?>"
                                       required>
                                <button type="button" class="button button-secondary toggle-password" data-target="zoho_client_secret">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                            <p class="zoho-sync-field-description">
                                <?php _e('Secreto del cliente de tu aplicación Zoho. Mantenlo seguro y no lo compartas.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Region -->
                        <div class="zoho-sync-field-group">
                            <label for="zoho_region" class="zoho-sync-field-label">
                                <?php _e('Región de Zoho', 'zoho-sync-core'); ?>
                            </label>
                            <select id="zoho_region" 
                                    name="zoho_sync_core_settings[zoho_region]" 
                                    class="zoho-sync-field-select">
                                <?php
                                $current_region = $settings->get('zoho_region', 'com');
                                $regions = $auth->get_available_regions();
                                foreach ($regions as $region_code => $region_name) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($region_code),
                                        selected($current_region, $region_code, false),
                                        esc_html($region_name)
                                    );
                                }
                                ?>
                            </select>
                            <p class="zoho-sync-field-description">
                                <?php _e('Selecciona la región donde está registrada tu cuenta de Zoho.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Connection Status -->
                        <div class="zoho-sync-field-group">
                            <label class="zoho-sync-field-label">
                                <?php _e('Estado de Conexión', 'zoho-sync-core'); ?>
                            </label>
                            <div class="zoho-sync-connection-status">
                                <?php
                                $auth_status = $auth->get_auth_status();
                                if ($auth_status['tokens_available'] && $auth_status['token_valid']) {
                                    echo '<div class="zoho-sync-status-indicator status-connected">';
                                    echo '<span class="dashicons dashicons-yes-alt"></span>';
                                    printf(__('Conectado - Expira en %s', 'zoho-sync-core'), human_time_diff(current_time('timestamp'), $auth_status['expires_at']));
                                    echo '</div>';
                                } else {
                                    echo '<div class="zoho-sync-status-indicator status-disconnected">';
                                    echo '<span class="dashicons dashicons-warning"></span>';
                                    _e('Desconectado - Requiere autorización', 'zoho-sync-core');
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            <div class="zoho-sync-connection-actions">
                                <button type="button" id="test-connection" class="button button-secondary">
                                    <span class="dashicons dashicons-admin-links"></span>
                                    <?php _e('Probar Conexión', 'zoho-sync-core'); ?>
                                </button>
                                <?php if (!$auth_status['tokens_available'] || !$auth_status['token_valid']): ?>
                                <a href="<?php echo admin_url('admin.php?page=zoho-sync-auth'); ?>" class="button button-primary">
                                    <span class="dashicons dashicons-admin-network"></span>
                                    <?php _e('Autorizar Conexión', 'zoho-sync-core'); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <!-- API Documentation -->
                    <div class="zoho-sync-help-section">
                        <h3><?php _e('¿Necesitas ayuda?', 'zoho-sync-core'); ?></h3>
                        <div class="zoho-sync-help-grid">
                            <div class="zoho-sync-help-item">
                                <span class="dashicons dashicons-book"></span>
                                <div class="zoho-sync-help-content">
                                    <h4><?php _e('Documentación', 'zoho-sync-core'); ?></h4>
                                    <p><?php _e('Consulta nuestra guía completa para configurar las credenciales de Zoho.', 'zoho-sync-core'); ?></p>
                                    <a href="https://bbrion.es/zoho-ecommerce-connect/configuracion" target="_blank" class="button button-secondary button-small">
                                        <?php _e('Ver Guía', 'zoho-sync-core'); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="zoho-sync-help-item">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <div class="zoho-sync-help-content">
                                    <h4><?php _e('Consola de Desarrolladores', 'zoho-sync-core'); ?></h4>
                                    <p><?php _e('Crea y gestiona tus aplicaciones en la consola de desarrolladores de Zoho.', 'zoho-sync-core'); ?></p>
                                    <a href="https://api-console.zoho.com/" target="_blank" class="button button-secondary button-small">
                                        <?php _e('Ir a Consola', 'zoho-sync-core'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Logging Settings Tab -->
            <div class="zoho-sync-tab-content" id="tab-logging">
                <div class="zoho-sync-settings-section">
                    <div class="zoho-sync-section-header">
                        <h2><?php _e('Configuración de Logs', 'zoho-sync-core'); ?></h2>
                        <p class="zoho-sync-section-description">
                            <?php _e('Configura cómo se registran y almacenan los logs del sistema.', 'zoho-sync-core'); ?>
                        </p>
                    </div>

                    <div class="zoho-sync-settings-grid">
                        
                        <!-- Log Level -->
                        <div class="zoho-sync-field-group">
                            <label for="log_level" class="zoho-sync-field-label">
                                <?php _e('Nivel de Log', 'zoho-sync-core'); ?>
                            </label>
                            <select id="log_level" 
                                    name="zoho_sync_core_settings[log_level]" 
                                    class="zoho-sync-field-select">
                                <?php
                                $current_level = $settings->get('log_level', 'info');
                                $log_levels = array(
                                    'debug' => __('Debug (Muy detallado)', 'zoho-sync-core'),
                                    'info' => __('Info (Recomendado)', 'zoho-sync-core'),
                                    'warning' => __('Warning (Solo advertencias)', 'zoho-sync-core'),
                                    'error' => __('Error (Solo errores)', 'zoho-sync-core')
                                );
                                foreach ($log_levels as $level => $label) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($level),
                                        selected($current_level, $level, false),
                                        esc_html($label)
                                    );
                                }
                                ?>
                            </select>
                            <p class="zoho-sync-field-description">
                                <?php _e('Nivel mínimo de logs a registrar. Debug incluye información muy detallada.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Log Retention -->
                        <div class="zoho-sync-field-group">
                            <label for="log_retention_days" class="zoho-sync-field-label">
                                <?php _e('Retención de Logs (días)', 'zoho-sync-core'); ?>
                            </label>
                            <input type="number" 
                                   id="log_retention_days" 
                                   name="zoho_sync_core_settings[log_retention_days]" 
                                   value="<?php echo esc_attr($settings->get('log_retention_days', 30)); ?>" 
                                   class="zoho-sync-field-input small-text" 
                                   min="1" 
                                   max="365">
                            <p class="zoho-sync-field-description">
                                <?php _e('Número de días para mantener los logs antes de eliminarlos automáticamente.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Enable File Logging -->
                        <div class="zoho-sync-field-group">
                            <label class="zoho-sync-field-label">
                                <input type="checkbox" 
                                       name="zoho_sync_core_settings[enable_file_logging]" 
                                       value="1" 
                                       <?php checked($settings->get('enable_file_logging', false), true); ?>>
                                <?php _e('Habilitar logs en archivos', 'zoho-sync-core'); ?>
                            </label>
                            <p class="zoho-sync-field-description">
                                <?php _e('Además de la base de datos, guardar logs en archivos del sistema.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Log Actions -->
                        <div class="zoho-sync-field-group">
                            <label class="zoho-sync-field-label">
                                <?php _e('Acciones de Logs', 'zoho-sync-core'); ?>
                            </label>
                            <div class="zoho-sync-log-actions">
                                <button type="button" id="clear-logs" class="button button-secondary">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php _e('Limpiar Logs', 'zoho-sync-core'); ?>
                                </button>
                                <button type="button" id="export-logs" class="button button-secondary">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php _e('Exportar Logs', 'zoho-sync-core'); ?>
                                </button>
                                <a href="<?php echo admin_url('admin.php?page=zoho-sync-logs'); ?>" class="button button-secondary">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <?php _e('Ver Logs', 'zoho-sync-core'); ?>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Sync Settings Tab -->
            <div class="zoho-sync-tab-content" id="tab-sync">
                <div class="zoho-sync-settings-section">
                    <div class="zoho-sync-section-header">
                        <h2><?php _e('Configuración de Sincronización', 'zoho-sync-core'); ?></h2>
                        <p class="zoho-sync-section-description">
                            <?php _e('Configura los parámetros de sincronización automática con Zoho.', 'zoho-sync-core'); ?>
                        </p>
                    </div>

                    <div class="zoho-sync-settings-grid">
                        
                        <!-- Sync Frequency -->
                        <div class="zoho-sync-field-group">
                            <label for="sync_frequency" class="zoho-sync-field-label">
                                <?php _e('Frecuencia de Sincronización', 'zoho-sync-core'); ?>
                            </label>
                            <select id="sync_frequency" 
                                    name="zoho_sync_core_settings[sync_frequency]" 
                                    class="zoho-sync-field-select">
                                <?php
                                $current_frequency = $settings->get('sync_frequency', 'every_15_minutes');
                                $frequencies = array(
                                    'every_minute' => __('Cada minuto', 'zoho-sync-core'),
                                    'every_5_minutes' => __('Cada 5 minutos', 'zoho-sync-core'),
                                    'every_15_minutes' => __('Cada 15 minutos (Recomendado)', 'zoho-sync-core'),
                                    'hourly' => __('Cada hora', 'zoho-sync-core'),
                                    'twicedaily' => __('Dos veces al día', 'zoho-sync-core'),
                                    'daily' => __('Diariamente', 'zoho-sync-core')
                                );
                                foreach ($frequencies as $freq => $label) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($freq),
                                        selected($current_frequency, $freq, false),
                                        esc_html($label)
                                    );
                                }
                                ?>
                            </select>
                            <p class="zoho-sync-field-description">
                                <?php _e('Frecuencia con la que se ejecutarán las sincronizaciones automáticas.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Batch Size -->
                        <div class="zoho-sync-field-group">
                            <label for="batch_size" class="zoho-sync-field-label">
                                <?php _e('Tamaño de Lote', 'zoho-sync-core'); ?>
                            </label>
                            <input type="number" 
                                   id="batch_size" 
                                   name="zoho_sync_core_settings[batch_size]" 
                                   value="<?php echo esc_attr($settings->get('batch_size', 50)); ?>" 
                                   class="zoho-sync-field-input small-text" 
                                   min="1" 
                                   max="1000">
                            <p class="zoho-sync-field-description">
                                <?php _e('Número de elementos a procesar en cada lote de sincronización.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Timeout -->
                        <div class="zoho-sync-field-group">
                            <label for="api_timeout" class="zoho-sync-field-label">
                                <?php _e('Timeout de API (segundos)', 'zoho-sync-core'); ?>
                            </label>
                            <input type="number" 
                                   id="api_timeout" 
                                   name="zoho_sync_core_settings[api_timeout]" 
                                   value="<?php echo esc_attr($settings->get('api_timeout', 30)); ?>" 
                                   class="zoho-sync-field-input small-text" 
                                   min="5" 
                                   max="300">
                            <p class="zoho-sync-field-description">
                                <?php _e('Tiempo máximo de espera para las peticiones a la API de Zoho.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Retry Attempts -->
                        <div class="zoho-sync-field-group">
                            <label for="retry_attempts" class="zoho-sync-field-label">
                                <?php _e('Intentos de Reintento', 'zoho-sync-core'); ?>
                            </label>
                            <input type="number" 
                                   id="retry_attempts" 
                                   name="zoho_sync_core_settings[retry_attempts]" 
                                   value="<?php echo esc_attr($settings->get('retry_attempts', 3)); ?>" 
                                   class="zoho-sync-field-input small-text" 
                                   min="1" 
                                   max="10">
                            <p class="zoho-sync-field-description">
                                <?php _e('Número de intentos de reintento en caso de fallo en la sincronización.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Sync Actions -->
                        <div class="zoho-sync-field-group">
                            <label class="zoho-sync-field-label">
                                <?php _e('Acciones de Sincronización', 'zoho-sync-core'); ?>
                            </label>
                            <div class="zoho-sync-sync-actions">
                                <button type="button" id="force-sync" class="button button-secondary">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php _e('Sincronizar Ahora', 'zoho-sync-core'); ?>
                                </button>
                                <button type="button" id="clear-queue" class="button button-secondary">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php _e('Limpiar Cola', 'zoho-sync-core'); ?>
                                </button>
                                <button type="button" id="reset-sync" class="button button-secondary">
                                    <span class="dashicons dashicons-undo"></span>
                                    <?php _e('Reiniciar Sincronización', 'zoho-sync-core'); ?>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Advanced Settings Tab -->
            <div class="zoho-sync-tab-content" id="tab-advanced">
                <div class="zoho-sync-settings-section">
                    <div class="zoho-sync-section-header">
                        <h2><?php _e('Configuración Avanzada', 'zoho-sync-core'); ?></h2>
                        <p class="zoho-sync-section-description">
                            <?php _e('Configuraciones avanzadas para usuarios experimentados.', 'zoho-sync-core'); ?>
                        </p>
                        <div class="notice notice-warning inline">
                            <p><strong><?php _e('Advertencia:', 'zoho-sync-core'); ?></strong> <?php _e('Cambiar estas configuraciones puede afectar el funcionamiento del plugin.', 'zoho-sync-core'); ?></p>
                        </div>
                    </div>

                    <div class="zoho-sync-settings-grid">
                        
                        <!-- Debug Mode -->
                        <div class="zoho-sync-field-group">
                            <label class="zoho-sync-field-label">
                                <input type="checkbox" 
                                       name="zoho_sync_core_settings[enable_debug]" 
                                       value="1" 
                                       <?php checked($settings->get('enable_debug', false), true); ?>>
                                <?php _e('Modo Debug', 'zoho-sync-core'); ?>
                            </label>
                            <p class="zoho-sync-field-description">
                                <?php _e('Habilitar modo debug para obtener información detallada de depuración.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Enable Webhooks -->
                        <div class="zoho-sync-field-group">
                            <label class="zoho-sync-field-label">
                                <input type="checkbox" 
                                       name="zoho_sync_core_settings[enable_webhooks]" 
                                       value="1" 
                                       <?php checked($settings->get('enable_webhooks', false), true); ?>>
                                <?php _e('Habilitar Webhooks', 'zoho-sync-core'); ?>
                            </label>
                            <p class="zoho-sync-field-description">
                                <?php _e('Permitir que Zoho envíe notificaciones automáticas de cambios.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Cache Duration -->
                        <div class="zoho-sync-field-group">
                            <label for="cache_duration" class="zoho-sync-field-label">
                                <?php _e('Duración de Caché (minutos)', 'zoho-sync-core'); ?>
                            </label>
                            <input type="number" 
                                   id="cache_duration" 
                                   name="zoho_sync_core_settings[cache_duration]" 
                                   value="<?php echo esc_attr($settings->get('cache_duration', 15)); ?>" 
                                   class="zoho-sync-field-input small-text" 
                                   min="1" 
                                   max="1440">
                            <p class="zoho-sync-field-description">
                                <?php _e('Tiempo en minutos para mantener los datos en caché.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Memory Limit -->
                        <div class="zoho-sync-field-group">
                            <label for="memory_limit" class="zoho-sync-field-label">
                                <?php _e('Límite de Memoria (MB)', 'zoho-sync-core'); ?>
                            </label>
                            <input type="number" 
                                   id="memory_limit" 
                                   name="zoho_sync_core_settings[memory_limit]" 
                                   value="<?php echo esc_attr($settings->get('memory_limit', 256)); ?>" 
                                   class="zoho-sync-field-input small-text" 
                                   min="128" 
                                   max="2048">
                            <p class="zoho-sync-field-description">
                                <?php _e('Límite de memoria para procesos de sincronización intensivos.', 'zoho-sync-core'); ?>
                            </p>
                        </div>

                        <!-- Reset Settings -->
                        <div class="zoho-sync-field-group">
                            <label class="zoho-sync-field-label">
                                <?php _e('Restablecer Configuración', 'zoho-sync-core'); ?>
                            </label>
                            <div class="zoho-sync-reset-actions">
                                <button type="button" id="reset-settings" class="button button-secondary">
                                    <span class="dashicons dashicons-undo"></span>
                                    <?php _e('Restablecer a Valores por Defecto', 'zoho-sync-core'); ?>
                                </button>
                                <p class="zoho-sync-field-description">
                                    <?php _e('Esta acción restablecerá todas las configuraciones a sus valores por defecto.', 'zoho-sync-core'); ?>
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="zoho-sync-settings-footer">
                <?php submit_button(__('Guardar Configuración', 'zoho-sync-core'), 'primary', 'submit', false); ?>
                <button type="button" id="reset-form" class="button button-secondary">
                    <?php _e('Restablecer Formulario', 'zoho-sync-core'); ?>
                </button>
            </div>

        </form>

    </div>
</div>

<!-- Loading Overlay -->
<div id="zoho-sync-loading-overlay" class="zoho-sync-loading-overlay" style="display: none;">
    <div class="zoho-sync-loading-content">
        <div class="zoho-sync-spinner"></div>
        <p id="zoho-sync-loading-message"><?php _e('Guardando configuración...', 'zoho-sync-core'); ?></p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize settings page
    ZohoSyncSettings.init();
    
    // Tab navigation
    $('.zoho-sync-nav-tab').on('click', function() {
        var tab = $(this).data('tab');
        
        // Update active tab
        $('.zoho-sync-nav-tab').removeClass('active');
        $(this).addClass('active');
        
        // Show corresponding content
        $('.zoho-sync-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });
    
    // Password toggle
    $('.toggle-password').on('click', function() {
        var target = $(this).data('target');
        var input = $('#' + target);
        var icon = $(this).find('.dashicons');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });
});
</script>
