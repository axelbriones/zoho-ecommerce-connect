<?php
/**
 * Tools Display
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
    <?php $admin_pages->render_admin_header(__('Herramientas', 'zoho-sync-core'), __('Herramientas de mantenimiento y utilidades', 'zoho-sync-core')); ?>
    
    <div class="zoho-sync-admin-content">
        <div class="zoho-sync-tools-container">
            <h2><?php _e('Herramientas Disponibles', 'zoho-sync-core'); ?></h2>
            
            <div class="notice notice-info">
                <p><?php _e('Las herramientas de mantenimiento estarán disponibles en una próxima actualización.', 'zoho-sync-core'); ?></p>
            </div>
            
            <p><?php _e('Aquí encontrarás herramientas para mantenimiento, limpieza de datos y utilidades del sistema.', 'zoho-sync-core'); ?></p>
        </div>
    </div>
</div>
