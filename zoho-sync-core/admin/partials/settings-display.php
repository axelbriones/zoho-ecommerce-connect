<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
        <?php
        settings_fields('zoho_sync_core');
        do_settings_sections('zoho_sync_core');
        submit_button(__('Save Settings', 'zoho-sync-core'));
        ?>
        <button type="button" id="zoho-check-connection" class="button button-secondary"><?php _e('Check Connection', 'zoho-sync-core'); ?></button>
        <span id="zoho-connection-status"></span>
    </form>
</div>