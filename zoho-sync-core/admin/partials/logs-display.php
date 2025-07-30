<?php
/**
 * Logs Display
 * 
 * @package ZohoSyncCore
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

$admin_pages = zoho_sync_core()->get_component('admin_pages');
$logger = zoho_sync_core_logger();
?>

<div class="wrap">
    <?php $admin_pages->render_admin_header(__('Logs del Sistema', 'zoho-sync-core'), __('Visualiza los logs de actividad del sistema', 'zoho-sync-core')); ?>
    
    <div class="zoho-sync-admin-content">
        <div class="zoho-sync-logs-container">
            <h2><?php _e('Logs Recientes', 'zoho-sync-core'); ?></h2>
            
            <div class="notice notice-info">
                <p><?php _e('La funcionalidad completa de logs estará disponible en una próxima actualización.', 'zoho-sync-core'); ?></p>
            </div>
            
            <p><?php _e('Aquí podrás ver todos los logs de actividad del sistema Zoho Sync.', 'zoho-sync-core'); ?></p>
        </div>
    </div>
</div>
