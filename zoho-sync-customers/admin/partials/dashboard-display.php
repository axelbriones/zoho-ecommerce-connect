<?php
/**
 * Dashboard Display
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
    <h1><?php _e('Dashboard - Zoho Sync Customers', 'zoho-sync-customers'); ?></h1>
    
    <!-- Statistics Cards -->
    <div class="zoho-customers-stats-grid">
        <div class="stats-card">
            <div class="stats-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="stats-content">
                <h3><?php echo number_format($sync_stats['total_customers']); ?></h3>
                <p><?php _e('Total Clientes', 'zoho-sync-customers'); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon">
                <span class="dashicons dashicons-businessman"></span>
            </div>
            <div class="stats-content">
                <h3><?php echo number_format($distributor_stats['total']); ?></h3>
                <p><?php _e('Distribuidores', 'zoho-sync-customers'); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon">
                <span class="dashicons dashicons-building"></span>
            </div>
            <div class="stats-content">
                <h3><?php echo number_format($b2b_stats['total_b2b_customers']); ?></h3>
                <p><?php _e('Clientes B2B', 'zoho-sync-customers'); ?></p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stats-content">
                <h3><?php echo $sync_stats['last_sync'] ? human_time_diff(strtotime($sync_stats['last_sync'])) . ' ' . __('ago', 'zoho-sync-customers') : __('Nunca', 'zoho-sync-customers'); ?></h3>
                <p><?php _e('Última Sincronización', 'zoho-sync-customers'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="zoho-customers-quick-actions">
        <h2><?php _e('Acciones Rápidas', 'zoho-sync-customers'); ?></h2>
        <div class="quick-actions-grid">
            <a href="<?php echo admin_url('admin.php?page=zoho-sync-customers-sync'); ?>" class="quick-action-button">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Sincronizar Ahora', 'zoho-sync-customers'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=zoho-sync-customers-distributors'); ?>" class="quick-action-button">
                <span class="dashicons dashicons-businessman"></span>
                <?php _e('Gestionar Distribuidores', 'zoho-sync-customers'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=zoho-sync-customers-b2b'); ?>" class="quick-action-button">
                <span class="dashicons dashicons-building"></span>
                <?php _e('Aprobar Clientes B2B', 'zoho-sync-customers'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=zoho-sync-customers-settings'); ?>" class="quick-action-button">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Configuración', 'zoho-sync-customers'); ?>
            </a>
        </div>
    </div>
    
    <!-- Status Overview -->
    <div class="zoho-customers-status-overview">
        <div class="status-section">
            <h2><?php _e('Estado de Distribuidores', 'zoho-sync-customers'); ?></h2>
            <div class="status-grid">
                <div class="status-item approved">
                    <span class="count"><?php echo $distributor_stats['approved']; ?></span>
                    <span class="label"><?php _e('Aprobados', 'zoho-sync-customers'); ?></span>
                </div>
                <div class="status-item pending">
                    <span class="count"><?php echo $distributor_stats['pending']; ?></span>
                    <span class="label"><?php _e('Pendientes', 'zoho-sync-customers'); ?></span>
                </div>
                <div class="status-item rejected">
                    <span class="count"><?php echo $distributor_stats['rejected']; ?></span>
                    <span class="label"><?php _e('Rechazados', 'zoho-sync-customers'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="status-section">
            <h2><?php _e('Estado de Clientes B2B', 'zoho-sync-customers'); ?></h2>
            <div class="status-grid">
                <div class="status-item approved">
                    <span class="count"><?php echo $b2b_stats['approved_customers']; ?></span>
                    <span class="label"><?php _e('Aprobados', 'zoho-sync-customers'); ?></span>
                </div>
                <div class="status-item pending">
                    <span class="count"><?php echo $b2b_stats['pending_approval']; ?></span>
                    <span class="label"><?php _e('Pendientes', 'zoho-sync-customers'); ?></span>
                </div>
                <div class="status-item rejected">
                    <span class="count"><?php echo $b2b_stats['rejected_customers']; ?></span>
                    <span class="label"><?php _e('Rechazados', 'zoho-sync-customers'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="zoho-customers-recent-activity">
        <h2><?php _e('Actividad Reciente', 'zoho-sync-customers'); ?></h2>
        <div class="activity-list">
            <?php
            // Get recent logs from Zoho Sync Core
            if (class_exists('ZohoSyncCore')) {
                $recent_logs = ZohoSyncCore::get_recent_logs('customers', 10);
                
                if (!empty($recent_logs)) {
                    foreach ($recent_logs as $log) {
                        $time_diff = human_time_diff(strtotime($log['created_at']));
                        $level_class = strtolower($log['level']);
                        ?>
                        <div class="activity-item <?php echo esc_attr($level_class); ?>">
                            <div class="activity-icon">
                                <span class="dashicons dashicons-<?php echo $level_class === 'error' ? 'warning' : ($level_class === 'info' ? 'info' : 'yes'); ?>"></span>
                            </div>
                            <div class="activity-content">
                                <p class="activity-message"><?php echo esc_html($log['message']); ?></p>
                                <span class="activity-time"><?php echo sprintf(__('Hace %s', 'zoho-sync-customers'), $time_diff); ?></span>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    ?>
                    <div class="activity-item">
                        <div class="activity-content">
                            <p class="activity-message"><?php _e('No hay actividad reciente', 'zoho-sync-customers'); ?></p>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
    </div>
    
    <!-- System Health -->
    <div class="zoho-customers-system-health">
        <h2><?php _e('Estado del Sistema', 'zoho-sync-customers'); ?></h2>
        <div class="health-checks">
            <div class="health-check">
                <span class="health-icon <?php echo class_exists('ZohoSyncCore') ? 'healthy' : 'error'; ?>">
                    <span class="dashicons dashicons-<?php echo class_exists('ZohoSyncCore') ? 'yes-alt' : 'warning'; ?>"></span>
                </span>
                <span class="health-label"><?php _e('Zoho Sync Core', 'zoho-sync-customers'); ?></span>
                <span class="health-status"><?php echo class_exists('ZohoSyncCore') ? __('Activo', 'zoho-sync-customers') : __('Inactivo', 'zoho-sync-customers'); ?></span>
            </div>
            
            <div class="health-check">
                <span class="health-icon <?php echo class_exists('WooCommerce') ? 'healthy' : 'error'; ?>">
                    <span class="dashicons dashicons-<?php echo class_exists('WooCommerce') ? 'yes-alt' : 'warning'; ?>"></span>
                </span>
                <span class="health-label"><?php _e('WooCommerce', 'zoho-sync-customers'); ?></span>
                <span class="health-status"><?php echo class_exists('WooCommerce') ? __('Activo', 'zoho-sync-customers') : __('Inactivo', 'zoho-sync-customers'); ?></span>
            </div>
            
            <div class="health-check">
                <?php
                $sync_enabled = get_option('zoho_customers_sync_enabled', 'yes') === 'yes';
                ?>
                <span class="health-icon <?php echo $sync_enabled ? 'healthy' : 'warning'; ?>">
                    <span class="dashicons dashicons-<?php echo $sync_enabled ? 'yes-alt' : 'minus'; ?>"></span>
                </span>
                <span class="health-label"><?php _e('Sincronización', 'zoho-sync-customers'); ?></span>
                <span class="health-status"><?php echo $sync_enabled ? __('Habilitada', 'zoho-sync-customers') : __('Deshabilitada', 'zoho-sync-customers'); ?></span>
            </div>
            
            <div class="health-check">
                <?php
                $pricing_enabled = get_option('zoho_customers_pricing_enabled', 'yes') === 'yes';
                ?>
                <span class="health-icon <?php echo $pricing_enabled ? 'healthy' : 'warning'; ?>">
                    <span class="dashicons dashicons-<?php echo $pricing_enabled ? 'yes-alt' : 'minus'; ?>"></span>
                </span>
                <span class="health-label"><?php _e('Precios por Nivel', 'zoho-sync-customers'); ?></span>
                <span class="health-status"><?php echo $pricing_enabled ? __('Habilitado', 'zoho-sync-customers') : __('Deshabilitado', 'zoho-sync-customers'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Connection Test -->
    <div class="zoho-customers-connection-test">
        <h2><?php _e('Prueba de Conexión', 'zoho-sync-customers'); ?></h2>
        <p><?php _e('Verifica la conexión con Zoho CRM para asegurar que la sincronización funcione correctamente.', 'zoho-sync-customers'); ?></p>
        
        <button type="button" id="test-zoho-connection" class="button button-secondary">
            <span class="dashicons dashicons-admin-network"></span>
            <?php _e('Probar Conexión', 'zoho-sync-customers'); ?>
        </button>
        
        <div id="connection-test-result" class="connection-result" style="display: none;">
            <div class="result-content"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test connection button
    $('#test-zoho-connection').on('click', function() {
        var $button = $(this);
        var $result = $('#connection-test-result');
        
        $button.prop('disabled', true).find('.dashicons').addClass('spin');
        $result.hide();
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_test_connection',
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.find('.result-content').html(
                        '<div class="notice notice-success inline"><p><strong>' + 
                        zohoCustomersAdmin.strings.connectionSuccess + 
                        '</strong><br>' + response.data.message + '</p></div>'
                    );
                } else {
                    $result.find('.result-content').html(
                        '<div class="notice notice-error inline"><p><strong>' + 
                        zohoCustomersAdmin.strings.connectionError + 
                        '</strong><br>' + response.data.message + '</p></div>'
                    );
                }
                $result.show();
            },
            error: function() {
                $result.find('.result-content').html(
                    '<div class="notice notice-error inline"><p><strong>' + 
                    zohoCustomersAdmin.strings.connectionError + 
                    '</strong><br>Error de comunicación con el servidor.</p></div>'
                );
                $result.show();
            },
            complete: function() {
                $button.prop('disabled', false).find('.dashicons').removeClass('spin');
            }
        });
    });
});
</script>