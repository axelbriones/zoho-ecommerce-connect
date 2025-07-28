<?php if (!defined('ABSPATH')) exit; ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php _e('Resumen de Actualizaciones de Inventario', 'zoho-sync-inventory'); ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h1 style="color: #0d6efd; margin: 0;">
                <?php _e('Resumen de Actualizaciones de Inventario', 'zoho-sync-inventory'); ?>
            </h1>
        </div>

        <p><?php printf(
            __('Se han detectado %d actualizaciones de inventario:', 'zoho-sync-inventory'),
            count($notifications)
        ); ?></p>

        <?php foreach ($notifications as $notification): ?>
            <div style="background: #fff; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin-bottom: 15px;">
                <h3 style="margin-top: 0; color: <?php echo $this->get_notification_color($notification['type']); ?>;">
                    <?php echo $this->get_notification_icon($notification['type']); ?> 
                    <?php echo esc_html($notification['data']['product_name']); ?>
                </h3>
                
                <p style="margin: 10px 0;">
                    <?php echo $this->format_notification_message($notification); ?>
                </p>

                <?php if (isset($notification['data']['current_stock'])): ?>
                    <p style="margin: 5px 0; color: #6c757d;">
                        <?php printf(
                            __('Stock actual: %d', 'zoho-sync-inventory'),
                            $notification['data']['current_stock']
                        ); ?>
                    </p>
                <?php endif; ?>

                <p style="margin: 5px 0; color: #6c757d; font-size: 0.9em;">
                    <?php echo esc_html(
                        date_i18n(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            $notification['timestamp']
                        )
                    ); ?>
                </p>
            </div>
        <?php endforeach; ?>

        <p><a href="<?php echo esc_url(admin_url('admin.php?page=zssi-inventory')); ?>" 
              style="background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
            <?php _e('Ver Panel de Inventario', 'zoho-sync-inventory'); ?>
        </a></p>

        <hr style="border: 0; border-top: 1px solid #dee2e6; margin: 20px 0;">

        <p style="color: #6c757d; font-size: 0.9em;">
            <?php _e('Este es un resumen automático de las actualizaciones de inventario.', 'zoho-sync-inventory'); ?>
            <?php _e('Para ajustar la frecuencia de estas notificaciones, visite la configuración del plugin.', 'zoho-sync-inventory'); ?>
        </p>
    </div>
</body>
</html>