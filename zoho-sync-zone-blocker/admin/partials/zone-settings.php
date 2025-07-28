<?php
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('zszb_options');
        do_settings_sections('zszb_zone_admin');
        submit_button(__('Guardar Cambios', 'zoho-sync-zone-blocker'));
        ?>
    </form>

    <div class="zszb-status-panel">
        <h2><?php _e('Estado del Sistema', 'zoho-sync-zone-blocker'); ?></h2>
        <?php
        $zones_count = ZSZB_Zone_Manager::get_zones_count();
        $active_blocks = ZSZB_Access_Controller::get_active_blocks();
        ?>
        <p><?php printf(__('Zonas configuradas: %d', 'zoho-sync-zone-blocker'), $zones_count); ?></p>
        <p><?php printf(__('Bloqueos activos: %d', 'zoho-sync-zone-blocker'), $active_blocks); ?></p>
    </div>
</div>