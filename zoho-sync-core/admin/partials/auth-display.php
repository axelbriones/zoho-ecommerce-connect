<?php
/**
 * Authentication Display
 * 
 * @package ZohoSyncCore
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

$admin_pages = zoho_sync_core()->get_component('admin_pages');
$auth_manager = zoho_sync_core_auth();
$auth_status = $auth_manager->get_auth_status();
?>

<div class="wrap">
    <?php $admin_pages->render_admin_header(__('Autenticación Zoho', 'zoho-sync-core'), __('Configura la autenticación con los servicios de Zoho', 'zoho-sync-core')); ?>
    
    <div class="zoho-sync-admin-content">
        <div class="zoho-sync-auth-status">
            <h2><?php _e('Estado de Autenticación', 'zoho-sync-core'); ?></h2>
            
            <?php if ($auth_status['tokens_available']): ?>
                <div class="notice notice-success">
                    <p><strong><?php _e('✓ Tokens disponibles', 'zoho-sync-core'); ?></strong></p>
                </div>
                
                <?php if ($auth_status['token_valid']): ?>
                    <div class="notice notice-success">
                        <p><strong><?php _e('✓ Token válido', 'zoho-sync-core'); ?></strong></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning">
                        <p><strong><?php _e('⚠ Token expirado o inválido', 'zoho-sync-core'); ?></strong></p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="notice notice-error">
                    <p><strong><?php _e('✗ No hay tokens disponibles', 'zoho-sync-core'); ?></strong></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="zoho-sync-auth-actions">
            <h2><?php _e('Acciones de Autenticación', 'zoho-sync-core'); ?></h2>
            
            <p><?php _e('Para configurar la autenticación, primero debes configurar las credenciales en la página de configuración.', 'zoho-sync-core'); ?></p>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=zoho-sync-settings'); ?>" class="button button-primary">
                    <?php _e('Ir a Configuración', 'zoho-sync-core'); ?>
                </a>
            </p>
        </div>
    </div>
</div>
