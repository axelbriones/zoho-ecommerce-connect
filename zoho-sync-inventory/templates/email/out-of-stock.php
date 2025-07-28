<?php if (!defined('ABSPATH')) exit; ?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php _e('Producto Agotado', 'zoho-sync-inventory'); ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #dc3545; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h1 style="color: white; margin: 0;">❌ <?php _e('Producto Agotado', 'zoho-sync-inventory'); ?></h1>
        </div>

        <p style="font-size: 16px; margin-bottom: 20px;">
            <?php printf(
                __('El producto "%s" se ha agotado completamente:', 'zoho-sync-inventory'),
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
                    <?php _e('Último Stock', 'zoho-sync-inventory'); ?>
                </th>
                <td style="padding: 10px; border: 1px solid #dee2e6;">0</td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6;">
                    <?php _e('Pedidos Pendientes', 'zoho-sync-inventory'); ?>
                </th>
                <td style="padding: 10px; border: 1px solid #dee2e6;">
                    <?php echo isset($data['pending_orders']) ? esc_html($data['pending_orders']) : '0'; ?>
                </td>
            </tr>
        </table>

        <div style="margin: 20px 0; text-align: center;">
            <a href="<?php echo esc_url($data['admin_url']); ?>" 
               style="background: #0d6efd; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                <?php _e('Gestionar Inventario', 'zoho-sync-inventory'); ?>
            </a>
        </div>

        <hr style="border: 0; border-top: 1px solid #dee2e6; margin: 20px 0;">

        <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-top: 20px;">
            <?php _e('Se recomienda revisar el inventario y realizar un pedido de reposición lo antes posible.', 'zoho-sync-inventory'); ?>
        </div>

        <p style="color: #6c757d; font-size: 0.9em; margin-top: 20px;">
            <?php _e('Este es un mensaje automático del sistema de gestión de inventario.', 'zoho-sync-inventory'); ?><br>
            <?php _e('Por favor, no responda a este correo.', 'zoho-sync-inventory'); ?>
        </p>
    </div>
</body>
</html>