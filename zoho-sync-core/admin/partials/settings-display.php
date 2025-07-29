<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
        <?php
        settings_fields('zoho_sync_core');
        do_settings_sections('zoho_sync_core');
        submit_button(__('Save Settings', 'zoho-sync-core'));
        ?>
    </form>
    <hr>
    <h2><?php _e('Authorization', 'zoho-sync-core'); ?></h2>
    <?php
    require_once ZOHO_SYNC_CORE_INCLUDES_DIR . 'class-auth-manager.php';
    $options = get_option('zoho_sync_core_settings');
    $client_id = isset($options['zoho_client_id']) ? $options['zoho_client_id'] : '';
    $client_secret = isset($options['zoho_client_secret']) ? $options['zoho_client_secret'] : '';

    if (!empty($client_id) && !empty($client_secret)) {
        $auth_manager = new Zoho_Sync_Core_Auth_Manager();
        $redirect_uri = admin_url('admin.php?page=zoho-sync-core');
        $auth_url = $auth_manager->get_authorization_url('inventory', 'com', $redirect_uri);
        ?>
        <p><?php _e('Click the link below to authorize the application with Zoho.', 'zoho-sync-core'); ?></p>
        <p><a href="<?php echo esc_url($auth_url); ?>" target="_blank"><?php _e('Authorize with Zoho', 'zoho-sync-core'); ?></a></p>
        <?php
    } else {
        ?>
        <p><?php _e('Please save your Client ID and Client Secret to generate the authorization URL.', 'zoho-sync-core'); ?></p>
        <?php
    }
    ?>
    <hr>
    <h2><?php _e('Connection Test', 'zoho-sync-core'); ?></h2>
    <button type="button" id="zoho-check-connection" class="button button-secondary"><?php _e('Check Connection', 'zoho-sync-core'); ?></button>
    <span id="zoho-connection-status"></span>
</div>
