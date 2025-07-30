<?php
/**
 * Modules Display
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
    <?php $admin_pages->render_admin_header(__('Módulos del Ecosistema', 'zoho-sync-core'), __('Gestiona los módulos del ecosistema Zoho Sync', 'zoho-sync-core')); ?>
    
    <div class="zoho-sync-admin-content">
        <div class="zoho-sync-modules-grid">
            <h2><?php _e('Módulos Disponibles', 'zoho-sync-core'); ?></h2>
            
            <div class="notice notice-info">
                <p><?php _e('Esta funcionalidad estará disponible en una próxima actualización.', 'zoho-sync-core'); ?></p>
            </div>
            
            <p><?php _e('Aquí podrás ver y gestionar todos los módulos del ecosistema Zoho Sync.', 'zoho-sync-core'); ?></p>
        </div>
    </div>
</div>
