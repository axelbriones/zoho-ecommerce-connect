<?php if (!defined('ABSPATH')) exit; ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php _e('Alerta de Stock Bajo', 'zoho-sync-inventory'); ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h1 style="color: #dc3545; margin: 0;">⚠️ <?php _e('Alerta de Stock Bajo', 'zoho-sync-inventory'); ?></h1>
        </div>

        <p><?php printf(
            __('El producto "%s" ha alcanzado un nivel bajo de stock:', 'zoho-sync-inventory'),
            esc_html($data['product_name'])
        ); ?></p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr>
                <th style="text-align: left; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6;">
                    <?php _e('SKU', 'zoho-sync-inventory'); ?>
                </th>
                <td style="padding: 10px; border: 1px solid #dee2e6;">
                    <?php echo esc_html($data['sku']); ?>
                </td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6;">
                    <?php _e('Stock Actual', 'zoho-sync-inventory'); ?>
                </th>
                <td style="padding: 10px; border: 1px solid #dee2e6;">
                    <?php echo esc_html($data['current_stock']); ?>
                </td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6;">
                    <?php _e('Umbral', 'zoho-sync-inventory'); ?>
                </th>
                <td style="padding: 10px; border: 1px solid #dee2e6;">
                    <?php echo esc_html($data['threshold']); ?>
                </td>
            </tr>
        </table>

        <p><a href="<?php echo esc_url($data['admin_url']); ?>" 
              style="background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
            <?php _e('Ver Detalles', 'zoho-sync-inventory'); ?>
        </a></p>

        <hr style="border: 0; border-top: 1px solid #dee2e6; margin: 20px 0;">

        <p style="color: #6c757d; font-size: 0.9em;">
            <?php _e('Este es un mensaje automático del sistema de gestión de inventario.', 'zoho-sync-inventory'); ?>
        </p>
    </div>
</body>
</html>