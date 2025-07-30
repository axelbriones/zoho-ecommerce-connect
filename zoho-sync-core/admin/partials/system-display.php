<?php
/**
 * System Display
 * 
 * @package ZohoSyncCore
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

$admin_pages = zoho_sync_core()->get_component('admin_pages');
?>

<div class="wrap">
    <?php $admin_pages->render_admin_header(__('Información del Sistema', 'zoho-sync-core'), __('Información técnica del sistema y diagnósticos', 'zoho-sync-core')); ?>
    
    <div class="zoho-sync-admin-content">
        <div class="zoho-sync-system-info">
            <h2><?php _e('Estado del Sistema', 'zoho-sync-core'); ?></h2>
            
            <div class="notice notice-info">
                <p><?php _e('La información completa del sistema estará disponible en una próxima actualización.', 'zoho-sync-core'); ?></p>
            </div>
            
            <p><?php _e('Aquí podrás ver información detallada sobre el estado del sistema y realizar diagnósticos.', 'zoho-sync-core'); ?></p>
        </div>
    </div>
</div>
