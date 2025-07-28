<?php if (!defined('ABSPATH')) exit; ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php _e('Stock Reabastecido', 'zoho-sync-inventory'); ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #28a745; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h1 style="color: white; margin: 0;">✅ <?php _e('Stock Reabastecido', 'zoho-sync-inventory'); ?></h1>
        </div>

        <p style="font-size: 16px; margin-bottom: 20px;">
            <?php printf(
                __('El producto "%s" ha sido reabastecido:', 'zoho-sync-inventory'),
                esc_html($data['product_name'])
            ); ?>
        </p>

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
                    <?php _e('Stock Anterior', 'zoho-sync-inventory'); ?>
                </th>
                <td style="padding: 10px; border: 1px solid #dee2e6;">
                    <?php echo esc_html($data['previous_stock']); ?>
                </td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6;">
                    <?php _e('Stock Actual', 'zoho-sync-inventory'); ?>
                </th>
                <td style="padding: 10px; border: 1px solid #dee2e6; color: #28a745; font-weight: bold;">
                    <?php echo esc_html($data['current_stock']); ?>
                </td>
            </tr>
        </table>

        <?php if (!empty($data['pending_orders'])) : ?>
            <div style="background: #cce5ff; color: #004085; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <?php printf(
                    __('Hay %d pedidos pendientes que pueden ser procesados ahora.', 'zoho-sync-inventory'),
                    $data['pending_orders']
                ); ?>
            </div>
        <?php endif; ?>

        <div style="margin: 20px 0; text-align: center;">
            <a href="<?php echo esc_url($data['admin_url']); ?>" 
               style="background: #0d6efd; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                <?php _e('Ver Detalles', 'zoho-sync-inventory'); ?>
            </a>
        </div>

        <hr style="border: 0; border-top: 1px solid #dee2e6; margin: 20px 0;">

        <p style="color: #6c757d; font-size: 0.9em;">
            <?php _e('Este es un mensaje automático del sistema de gestión de inventario.', 'zoho-sync-inventory'); ?>
        </p>
    </div>
</body>
</html>