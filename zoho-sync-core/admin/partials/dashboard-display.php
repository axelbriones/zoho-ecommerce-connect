<?php
/**
 * Dashboard Display
 * 
 * Main dashboard view for Zoho Sync Core plugin
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
$core = zoho_sync_core();
$settings = zoho_sync_core_settings();
$auth = zoho_sync_core_auth();
$logger = zoho_sync_core_logger();

// Get dashboard data
$auth_status = $auth->get_auth_status();
$system_status = $core->get_system_status();
$recent_logs = $logger->get_logs(array('limit' => 10, 'order' => 'DESC'));
$modules_status = $core->get_modules_status();

// Render admin header
$admin_pages = new Zoho_Sync_Core_Admin_Pages();
$admin_pages->render_admin_header(
    __('Dashboard de Zoho Sync', 'zoho-sync-core'),
    __('Panel de control principal del ecosistema de sincronización con Zoho', 'zoho-sync-core')
);
?>

<div class="zoho-sync-dashboard">
    <div class="zoho-sync-dashboard-grid">
        
        <!-- Status Overview -->
        <div class="zoho-sync-card zoho-sync-status-overview">
            <div class="zoho-sync-card-header">
                <h2><?php _e('Estado General', 'zoho-sync-core'); ?></h2>
                <div class="zoho-sync-card-actions">
                    <button type="button" class="button button-secondary" id="refresh-status">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Actualizar', 'zoho-sync-core'); ?>
                    </button>
                </div>
            </div>
            <div class="zoho-sync-card-body">
                <div class="zoho-sync-status-grid">
                    
                    <!-- Connection Status -->
                    <div class="zoho-sync-status-item">
                        <div class="zoho-sync-status-icon <?php echo $auth_status['tokens_available'] && $auth_status['token_valid'] ? 'status-success' : 'status-error'; ?>">
                            <span class="dashicons <?php echo $auth_status['tokens_available'] && $auth_status['token_valid'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                        </div>
                        <div class="zoho-sync-status-content">
                            <h3><?php _e('Conexión con Zoho', 'zoho-sync-core'); ?></h3>
                            <p class="zoho-sync-status-description">
                                <?php 
                                if ($auth_status['tokens_available'] && $auth_status['token_valid']) {
                                    printf(__('Conectado - Expira en %s', 'zoho-sync-core'), human_time_diff(current_time('timestamp'), $auth_status['expires_at']));
                                } else {
                                    _e('Desconectado - Requiere autorización', 'zoho-sync-core');
                                }
                                ?>
                            </p>
                            <?php if (!$auth_status['tokens_available'] || !$auth_status['token_valid']): ?>
                            <a href="<?php echo admin_url('admin.php?page=zoho-sync-auth'); ?>" class="button button-primary button-small">
                                <?php _e('Configurar Conexión', 'zoho-sync-core'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- System Health -->
                    <div class="zoho-sync-status-item">
                        <div class="zoho-sync-status-icon <?php echo $system_status['health_score'] >= 80 ? 'status-success' : ($system_status['health_score'] >= 60 ? 'status-warning' : 'status-error'); ?>">
                            <span class="dashicons dashicons-heart"></span>
                        </div>
                        <div class="zoho-sync-status-content">
                            <h3><?php _e('Salud del Sistema', 'zoho-sync-core'); ?></h3>
                            <p class="zoho-sync-status-description">
                                <?php printf(__('Puntuación: %d/100', 'zoho-sync-core'), $system_status['health_score']); ?>
                            </p>
                            <div class="zoho-sync-health-bar">
                                <div class="zoho-sync-health-progress" style="width: <?php echo $system_status['health_score']; ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Modules -->
                    <div class="zoho-sync-status-item">
                        <div class="zoho-sync-status-icon status-info">
                            <span class="dashicons dashicons-admin-plugins"></span>
                        </div>
                        <div class="zoho-sync-status-content">
                            <h3><?php _e('Módulos Activos', 'zoho-sync-core'); ?></h3>
                            <p class="zoho-sync-status-description">
                                <?php printf(__('%d de %d módulos activos', 'zoho-sync-core'), $modules_status['active'], $modules_status['total']); ?>
                            </p>
                            <a href="<?php echo admin_url('admin.php?page=zoho-sync-modules'); ?>" class="button button-secondary button-small">
                                <?php _e('Ver Módulos', 'zoho-sync-core'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Last Sync -->
                    <div class="zoho-sync-status-item">
                        <div class="zoho-sync-status-icon status-info">
                            <span class="dashicons dashicons-update"></span>
                        </div>
                        <div class="zoho-sync-status-content">
                            <h3><?php _e('Última Sincronización', 'zoho-sync-core'); ?></h3>
                            <p class="zoho-sync-status-description">
                                <?php 
                                if ($system_status['last_sync']) {
                                    echo human_time_diff(strtotime($system_status['last_sync']), current_time('timestamp')) . ' ' . __('atrás', 'zoho-sync-core');
                                } else {
                                    _e('Nunca', 'zoho-sync-core');
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="zoho-sync-card zoho-sync-quick-actions">
            <div class="zoho-sync-card-header">
                <h2><?php _e('Acciones Rápidas', 'zoho-sync-core'); ?></h2>
            </div>
            <div class="zoho-sync-card-body">
                <div class="zoho-sync-actions-grid">
                    
                    <button type="button" class="zoho-sync-action-button" id="test-connection">
                        <span class="dashicons dashicons-admin-links"></span>
                        <span class="zoho-sync-action-label"><?php _e('Probar Conexión', 'zoho-sync-core'); ?></span>
                    </button>

                    <button type="button" class="zoho-sync-action-button" id="force-sync">
                        <span class="dashicons dashicons-update"></span>
                        <span class="zoho-sync-action-label"><?php _e('Sincronizar Ahora', 'zoho-sync-core'); ?></span>
                    </button>

                    <button type="button" class="zoho-sync-action-button" id="clear-cache">
                        <span class="dashicons dashicons-trash"></span>
                        <span class="zoho-sync-action-label"><?php _e('Limpiar Caché', 'zoho-sync-core'); ?></span>
                    </button>

                    <a href="<?php echo admin_url('admin.php?page=zoho-sync-logs'); ?>" class="zoho-sync-action-button">
                        <span class="dashicons dashicons-list-view"></span>
                        <span class="zoho-sync-action-label"><?php _e('Ver Logs', 'zoho-sync-core'); ?></span>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=zoho-sync-settings'); ?>" class="zoho-sync-action-button">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <span class="zoho-sync-action-label"><?php _e('Configuración', 'zoho-sync-core'); ?></span>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=zoho-sync-system'); ?>" class="zoho-sync-action-button">
                        <span class="dashicons dashicons-info"></span>
                        <span class="zoho-sync-action-label"><?php _e('Info del Sistema', 'zoho-sync-core'); ?></span>
                    </a>

                </div>
            </div>
        </div>

        <!-- Statistics Charts -->
        <div class="zoho-sync-card zoho-sync-statistics">
            <div class="zoho-sync-card-header">
                <h2><?php _e('Estadísticas de Sincronización', 'zoho-sync-core'); ?></h2>
                <div class="zoho-sync-card-actions">
                    <select id="stats-period" class="zoho-sync-select">
                        <option value="7"><?php _e('Últimos 7 días', 'zoho-sync-core'); ?></option>
                        <option value="30"><?php _e('Últimos 30 días', 'zoho-sync-core'); ?></option>
                        <option value="90"><?php _e('Últimos 90 días', 'zoho-sync-core'); ?></option>
                    </select>
                </div>
            </div>
            <div class="zoho-sync-card-body">
                <div class="zoho-sync-chart-container">
                    <canvas id="sync-stats-chart" width="400" height="200"></canvas>
                </div>
                <div class="zoho-sync-stats-summary">
                    <div class="zoho-sync-stat-item">
                        <span class="zoho-sync-stat-number" id="total-syncs">-</span>
                        <span class="zoho-sync-stat-label"><?php _e('Sincronizaciones', 'zoho-sync-core'); ?></span>
                    </div>
                    <div class="zoho-sync-stat-item">
                        <span class="zoho-sync-stat-number" id="success-rate">-</span>
                        <span class="zoho-sync-stat-label"><?php _e('Tasa de Éxito', 'zoho-sync-core'); ?></span>
                    </div>
                    <div class="zoho-sync-stat-item">
                        <span class="zoho-sync-stat-number" id="avg-time">-</span>
                        <span class="zoho-sync-stat-label"><?php _e('Tiempo Promedio', 'zoho-sync-core'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="zoho-sync-card zoho-sync-recent-activity">
            <div class="zoho-sync-card-header">
                <h2><?php _e('Actividad Reciente', 'zoho-sync-core'); ?></h2>
                <div class="zoho-sync-card-actions">
                    <a href="<?php echo admin_url('admin.php?page=zoho-sync-logs'); ?>" class="button button-secondary button-small">
                        <?php _e('Ver Todos', 'zoho-sync-core'); ?>
                    </a>
                </div>
            </div>
            <div class="zoho-sync-card-body">
                <?php if (!empty($recent_logs)): ?>
                <div class="zoho-sync-activity-list">
                    <?php foreach ($recent_logs as $log): ?>
                    <div class="zoho-sync-activity-item level-<?php echo esc_attr($log['level']); ?>">
                        <div class="zoho-sync-activity-icon">
                            <span class="dashicons <?php 
                                switch($log['level']) {
                                    case 'error': echo 'dashicons-warning'; break;
                                    case 'warning': echo 'dashicons-info'; break;
                                    case 'success': echo 'dashicons-yes-alt'; break;
                                    default: echo 'dashicons-marker';
                                }
                            ?>"></span>
                        </div>
                        <div class="zoho-sync-activity-content">
                            <div class="zoho-sync-activity-message">
                                <?php echo esc_html($log['message']); ?>
                            </div>
                            <div class="zoho-sync-activity-meta">
                                <span class="zoho-sync-activity-module"><?php echo esc_html($log['module']); ?></span>
                                <span class="zoho-sync-activity-time"><?php echo human_time_diff(strtotime($log['created_at']), current_time('timestamp')) . ' ' . __('atrás', 'zoho-sync-core'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="zoho-sync-empty-state">
                    <span class="dashicons dashicons-admin-post"></span>
                    <p><?php _e('No hay actividad reciente', 'zoho-sync-core'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modules Status -->
        <div class="zoho-sync-card zoho-sync-modules-status">
            <div class="zoho-sync-card-header">
                <h2><?php _e('Estado de Módulos', 'zoho-sync-core'); ?></h2>
                <div class="zoho-sync-card-actions">
                    <a href="<?php echo admin_url('admin.php?page=zoho-sync-modules'); ?>" class="button button-secondary button-small">
                        <?php _e('Gestionar', 'zoho-sync-core'); ?>
                    </a>
                </div>
            </div>
            <div class="zoho-sync-card-body">
                <?php if (!empty($modules_status['modules'])): ?>
                <div class="zoho-sync-modules-list">
                    <?php foreach ($modules_status['modules'] as $module): ?>
                    <div class="zoho-sync-module-item <?php echo $module['is_active'] ? 'active' : 'inactive'; ?>">
                        <div class="zoho-sync-module-status">
                            <span class="zoho-sync-status-dot <?php echo $module['is_active'] ? 'status-active' : 'status-inactive'; ?>"></span>
                        </div>
                        <div class="zoho-sync-module-info">
                            <div class="zoho-sync-module-name"><?php echo esc_html($module['module_name']); ?></div>
                            <div class="zoho-sync-module-meta">
                                <span class="zoho-sync-module-version">v<?php echo esc_html($module['version']); ?></span>
                                <?php if ($module['last_sync']): ?>
                                <span class="zoho-sync-module-sync"><?php echo human_time_diff(strtotime($module['last_sync']), current_time('timestamp')) . ' ' . __('atrás', 'zoho-sync-core'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="zoho-sync-module-actions">
                            <?php if ($module['sync_status'] === 'syncing'): ?>
                            <span class="zoho-sync-sync-indicator">
                                <span class="dashicons dashicons-update spin"></span>
                            </span>
                            <?php elseif ($module['error_count'] > 0): ?>
                            <span class="zoho-sync-error-indicator" title="<?php echo esc_attr($module['last_error']); ?>">
                                <span class="dashicons dashicons-warning"></span>
                                <span class="zoho-sync-error-count"><?php echo $module['error_count']; ?></span>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="zoho-sync-empty-state">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <p><?php _e('No hay módulos instalados', 'zoho-sync-core'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Alerts -->
        <?php if (!empty($system_status['alerts'])): ?>
        <div class="zoho-sync-card zoho-sync-system-alerts">
            <div class="zoho-sync-card-header">
                <h2><?php _e('Alertas del Sistema', 'zoho-sync-core'); ?></h2>
            </div>
            <div class="zoho-sync-card-body">
                <div class="zoho-sync-alerts-list">
                    <?php foreach ($system_status['alerts'] as $alert): ?>
                    <div class="zoho-sync-alert-item alert-<?php echo esc_attr($alert['type']); ?>">
                        <div class="zoho-sync-alert-icon">
                            <span class="dashicons <?php 
                                switch($alert['type']) {
                                    case 'error': echo 'dashicons-dismiss'; break;
                                    case 'warning': echo 'dashicons-warning'; break;
                                    case 'info': echo 'dashicons-info'; break;
                                    default: echo 'dashicons-marker';
                                }
                            ?>"></span>
                        </div>
                        <div class="zoho-sync-alert-content">
                            <div class="zoho-sync-alert-message"><?php echo esc_html($alert['message']); ?></div>
                            <?php if (!empty($alert['action'])): ?>
                            <div class="zoho-sync-alert-action">
                                <a href="<?php echo esc_url($alert['action']['url']); ?>" class="button button-small">
                                    <?php echo esc_html($alert['action']['label']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="zoho-sync-alert-dismiss">
                            <button type="button" class="button-link dismiss-alert" data-alert-id="<?php echo esc_attr($alert['id']); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Loading Overlay -->
<div id="zoho-sync-loading-overlay" class="zoho-sync-loading-overlay" style="display: none;">
    <div class="zoho-sync-loading-content">
        <div class="zoho-sync-spinner"></div>
        <p id="zoho-sync-loading-message"><?php _e('Cargando...', 'zoho-sync-core'); ?></p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize dashboard
    ZohoSyncDashboard.init();
    
    // Load statistics
    ZohoSyncDashboard.loadStatistics();
    
    // Auto-refresh every 30 seconds
    setInterval(function() {
        ZohoSyncDashboard.refreshStatus();
    }, 30000);
});
</script>
