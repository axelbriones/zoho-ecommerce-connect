<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
        <?php
        settings_fields('zoho_sync_core');
        do_settings_sections('zoho_sync_core');
        submit_button(__('Save Settings', 'zoho-sync-core'));
        ?>
    </form>
</div>