<?php
/**
 * Sync Display
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Sincronización de Clientes', 'zoho-sync-customers'); ?></h1>
    
    <!-- Sync Status -->
    <div class="zoho-sync-status">
        <div class="sync-status-card">
            <div class="status-header">
                <h2><?php _e('Estado de Sincronización', 'zoho-sync-customers'); ?></h2>
                <div class="sync-toggle">
                    <?php
                    $sync_enabled = get_option('zoho_customers_sync_enabled', 'yes') === 'yes';
                    ?>
                    <label class="toggle-switch">
                        <input type="checkbox" id="sync-enabled" <?php checked($sync_enabled); ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label"><?php echo $sync_enabled ? __('Habilitada', 'zoho-sync-customers') : __('Deshabilitada', 'zoho-sync-customers'); ?></span>
                </div>
            </div>
            
            <div class="status-info">
                <?php
                $last_sync = get_option('zoho_customers_last_sync', '');
                $sync_in_progress = get_transient('zoho_customers_sync_in_progress');
                ?>
                
                <div class="info-item">
                    <span class="info-label"><?php _e('Última Sincronización:', 'zoho-sync-customers'); ?></span>
                    <span class="info-value">
                        <?php 
                        if ($last_sync) {
                            echo sprintf(__('Hace %s', 'zoho-sync-customers'), human_time_diff(strtotime($last_sync)));
                        } else {
                            echo __('Nunca', 'zoho-sync-customers');
                        }
                        ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label"><?php _e('Estado:', 'zoho-sync-customers'); ?></span>
                    <span class="info-value">
                        <?php if ($sync_in_progress): ?>
                            <span class="status-badge in-progress">
                                <span class="dashicons dashicons-update spin"></span>
                                <?php _e('En Progreso', 'zoho-sync-customers'); ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge idle">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Inactivo', 'zoho-sync-customers'); ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label"><?php _e('Próxima Sincronización:', 'zoho-sync-customers'); ?></span>
                    <span class="info-value">
                        <?php
                        $next_sync = wp_next_scheduled('zoho_customers_sync_cron');
                        if ($next_sync) {
                            echo sprintf(__('En %s', 'zoho-sync-customers'), human_time_diff($next_sync));
                        } else {
                            echo __('No programada', 'zoho-sync-customers');
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Manual Sync Controls -->
    <div class="zoho-manual-sync">
        <div class="sync-controls-card">
            <h2><?php _e('Sincronización Manual', 'zoho-sync-customers'); ?></h2>
            <p><?php _e('Ejecuta una sincronización inmediata de clientes entre Zoho CRM y WooCommerce.', 'zoho-sync-customers'); ?></p>
            
            <div class="sync-options">
                <div class="sync-option">
                    <label>
                        <input type="radio" name="sync_direction" value="both" checked>
                        <span class="option-title"><?php _e('Sincronización Bidireccional', 'zoho-sync-customers'); ?></span>
                        <span class="option-description"><?php _e('Sincroniza cambios en ambas direcciones', 'zoho-sync-customers'); ?></span>
                    </label>
                </div>
                
                <div class="sync-option">
                    <label>
                        <input type="radio" name="sync_direction" value="from_zoho">
                        <span class="option-title"><?php _e('Desde Zoho a WooCommerce', 'zoho-sync-customers'); ?></span>
                        <span class="option-description"><?php _e('Importa clientes desde Zoho CRM', 'zoho-sync-customers'); ?></span>
                    </label>
                </div>
                
                <div class="sync-option">
                    <label>
                        <input type="radio" name="sync_direction" value="to_zoho">
                        <span class="option-title"><?php _e('Desde WooCommerce a Zoho', 'zoho-sync-customers'); ?></span>
                        <span class="option-description"><?php _e('Exporta clientes a Zoho CRM', 'zoho-sync-customers'); ?></span>
                    </label>
                </div>
            </div>
            
            <div class="sync-filters">
                <h3><?php _e('Filtros de Sincronización', 'zoho-sync-customers'); ?></h3>
                
                <div class="filter-group">
                    <label>
                        <input type="checkbox" name="sync_filters[]" value="new_only">
                        <?php _e('Solo clientes nuevos', 'zoho-sync-customers'); ?>
                    </label>
                </div>
                
                <div class="filter-group">
                    <label>
                        <input type="checkbox" name="sync_filters[]" value="modified_only">
                        <?php _e('Solo clientes modificados', 'zoho-sync-customers'); ?>
                    </label>
                </div>
                
                <div class="filter-group">
                    <label>
                        <input type="checkbox" name="sync_filters[]" value="distributors_only">
                        <?php _e('Solo distribuidores', 'zoho-sync-customers'); ?>
                    </label>
                </div>
                
                <div class="filter-group">
                    <label>
                        <input type="checkbox" name="sync_filters[]" value="b2b_only">
                        <?php _e('Solo clientes B2B', 'zoho-sync-customers'); ?>
                    </label>
                </div>
            </div>
            
            <div class="sync-actions">
                <button type="button" id="start-manual-sync" class="button button-primary button-large">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Iniciar Sincronización', 'zoho-sync-customers'); ?>
                </button>
                
                <button type="button" id="test-connection" class="button button-secondary">
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php _e('Probar Conexión', 'zoho-sync-customers'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Sync Progress -->
    <div id="sync-progress" class="zoho-sync-progress" style="display: none;">
        <div class="progress-card">
            <h3><?php _e('Progreso de Sincronización', 'zoho-sync-customers'); ?></h3>
            
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%;"></div>
            </div>
            
            <div class="progress-info">
                <span class="progress-text"><?php _e('Preparando sincronización...', 'zoho-sync-customers'); ?></span>
                <span class="progress-percentage">0%</span>
            </div>
            
            <div class="progress-details">
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Procesados:', 'zoho-sync-customers'); ?></span>
                    <span class="detail-value" id="processed-count">0</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Total:', 'zoho-sync-customers'); ?></span>
                    <span class="detail-value" id="total-count">0</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label"><?php _e('Errores:', 'zoho-sync-customers'); ?></span>
                    <span class="detail-value error" id="error-count">0</span>
                </div>
            </div>
            
            <div class="progress-log">
                <h4><?php _e('Log de Actividad', 'zoho-sync-customers'); ?></h4>
                <div class="log-container" id="sync-log">
                    <!-- Log entries will be added here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sync Statistics -->
    <div class="zoho-sync-statistics">
        <h2><?php _e('Estadísticas de Sincronización', 'zoho-sync-customers'); ?></h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-arrow-down-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format(get_option('zoho_customers_imported_total', 0)); ?></h3>
                    <p><?php _e('Importados desde Zoho', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-arrow-up-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format(get_option('zoho_customers_exported_total', 0)); ?></h3>
                    <p><?php _e('Exportados a Zoho', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format(get_option('zoho_customers_updated_total', 0)); ?></h3>
                    <p><?php _e('Actualizados', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format(get_option('zoho_customers_errors_total', 0)); ?></h3>
                    <p><?php _e('Errores', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sync Settings -->
    <div class="zoho-sync-settings">
        <h2><?php _e('Configuración de Sincronización', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('zoho_customers_sync'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Intervalo de Sincronización', 'zoho-sync-customers'); ?></th>
                    <td>
                        <select name="zoho_customers_sync_interval">
                            <?php
                            $current_interval = get_option('zoho_customers_sync_interval', 'hourly');
                            $intervals = array(
                                'every_15_minutes' => __('Cada 15 minutos', 'zoho-sync-customers'),
                                'every_30_minutes' => __('Cada 30 minutos', 'zoho-sync-customers'),
                                'hourly' => __('Cada hora', 'zoho-sync-customers'),
                                'twicedaily' => __('Dos veces al día', 'zoho-sync-customers'),
                                'daily' => __('Diariamente', 'zoho-sync-customers'),
                                'weekly' => __('Semanalmente', 'zoho-sync-customers')
                            );
                            
                            foreach ($intervals as $value => $label) {
                                echo '<option value="' . esc_attr($value) . '" ' . selected($current_interval, $value, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php _e('Frecuencia con la que se ejecutará la sincronización automática.', 'zoho-sync-customers'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Límite de Registros por Lote', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="number" name="zoho_customers_batch_size" value="<?php echo esc_attr(get_option('zoho_customers_batch_size', 100)); ?>" min="10" max="500" class="small-text">
                        <p class="description"><?php _e('Número máximo de registros a procesar en cada lote de sincronización.', 'zoho-sync-customers'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Timeout de Sincronización', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="number" name="zoho_customers_sync_timeout" value="<?php echo esc_attr(get_option('zoho_customers_sync_timeout', 300)); ?>" min="60" max="3600" class="small-text">
                        <span><?php _e('segundos', 'zoho-sync-customers'); ?></span>
                        <p class="description"><?php _e('Tiempo máximo permitido para completar una sincronización.', 'zoho-sync-customers'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Notificaciones por Email', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_email_on_success" value="yes" <?php checked(get_option('zoho_customers_email_on_success', 'no'), 'yes'); ?>>
                                <?php _e('Enviar email cuando la sincronización sea exitosa', 'zoho-sync-customers'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="zoho_customers_email_on_error" value="yes" <?php checked(get_option('zoho_customers_email_on_error', 'yes'), 'yes'); ?>>
                                <?php _e('Enviar email cuando ocurran errores', 'zoho-sync-customers'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Email de Notificaciones', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="email" name="zoho_customers_notification_email" value="<?php echo esc_attr(get_option('zoho_customers_notification_email', get_option('admin_email'))); ?>" class="regular-text">
                        <p class="description"><?php _e('Dirección de email donde se enviarán las notificaciones de sincronización.', 'zoho-sync-customers'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Guardar Configuración', 'zoho-sync-customers')); ?>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Toggle sync enabled/disabled
    $('#sync-enabled').on('change', function() {
        var enabled = $(this).is(':checked');
        var $label = $(this).siblings('.toggle-label');
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_toggle_sync',
                enabled: enabled ? 'yes' : 'no',
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $label.text(enabled ? 'Habilitada' : 'Deshabilitada');
                } else {
                    // Revert toggle if failed
                    $('#sync-enabled').prop('checked', !enabled);
                }
            }
        });
    });
    
    // Manual sync
    $('#start-manual-sync').on('click', function() {
        if (!confirm(zohoCustomersAdmin.strings.confirmSync)) {
            return;
        }
        
        var $button = $(this);
        var $progress = $('#sync-progress');
        var direction = $('input[name="sync_direction"]:checked').val();
        var filters = $('input[name="sync_filters[]"]:checked').map(function() {
            return this.value;
        }).get();
        
        $button.prop('disabled', true);
        $progress.show();
        
        // Start sync
        startManualSync(direction, filters);
    });
    
    // Test connection
    $('#test-connection').on('click', function() {
        var $button = $(this);
        
        $button.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_test_connection',
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(zohoCustomersAdmin.strings.connectionSuccess + '\n' + response.data.message);
                } else {
                    alert(zohoCustomersAdmin.strings.connectionError + '\n' + response.data.message);
                }
            },
            error: function() {
                alert(zohoCustomersAdmin.strings.connectionError);
            },
            complete: function() {
                $button.prop('disabled', false).find('.dashicons').removeClass('spin');
            }
        });
    });
    
    function startManualSync(direction, filters) {
        var $progress = $('#sync-progress');
        var $progressBar = $progress.find('.progress-fill');
        var $progressText = $progress.find('.progress-text');
        var $progressPercentage = $progress.find('.progress-percentage');
        var $syncLog = $('#sync-log');
        
        // Reset progress
        $progressBar.css('width', '0%');
        $progressText.text('Iniciando sincronización...');
        $progressPercentage.text('0%');
        $syncLog.empty();
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_manual_sync',
                direction: direction,
                filters: filters,
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Poll for progress updates
                    pollSyncProgress();
                } else {
                    addLogEntry('error', response.data.message || 'Error iniciando sincronización');
                    $('#start-manual-sync').prop('disabled', false);
                }
            },
            error: function() {
                addLogEntry('error', 'Error de comunicación con el servidor');
                $('#start-manual-sync').prop('disabled', false);
            }
        });
    }
    
    function pollSyncProgress() {
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_sync_progress',
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var progress = response.data;
                    updateProgress(progress);
                    
                    if (progress.status === 'completed' || progress.status === 'error') {
                        $('#start-manual-sync').prop('disabled', false);
                        if (progress.status === 'completed') {
                            addLogEntry('success', 'Sincronización completada exitosamente');
                        }
                    } else {
                        // Continue polling
                        setTimeout(pollSyncProgress, 2000);
                    }
                }
            },
            error: function() {
                setTimeout(pollSyncProgress, 5000); // Retry after 5 seconds
            }
        });
    }
    
    function updateProgress(progress) {
        var percentage = Math.round((progress.processed / progress.total) * 100) || 0;
        
        $('#sync-progress .progress-fill').css('width', percentage + '%');
        $('#sync-progress .progress-percentage').text(percentage + '%');
        $('#sync-progress .progress-text').text(progress.message || 'Procesando...');
        
        $('#processed-count').text(progress.processed || 0);
        $('#total-count').text(progress.total || 0);
        $('#error-count').text(progress.errors || 0);
        
        // Add new log entries
        if (progress.log_entries) {
            progress.log_entries.forEach(function(entry) {
                addLogEntry(entry.level, entry.message);
            });
        }
    }
    
    function addLogEntry(level, message) {
        var $log = $('#sync-log');
        var timestamp = new Date().toLocaleTimeString();
        var levelClass = level === 'error' ? 'error' : (level === 'success' ? 'success' : 'info');
        
        var $entry = $('<div class="log-entry ' + levelClass + '">' +
            '<span class="log-time">' + timestamp + '</span>' +
            '<span class="log-message">' + message + '</span>' +
            '</div>');
        
        $log.append($entry);
        $log.scrollTop($log[0].scrollHeight);
    }
});
</script>