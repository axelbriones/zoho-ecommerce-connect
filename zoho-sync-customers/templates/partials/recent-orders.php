<?php

if (!defined('ABSPATH')) exit;

$recent_orders = wc_get_orders([
    'customer' => get_current_user_id(),
    'limit' => 5,
    'orderby' => 'date',
    'order' => 'DESC'
]);
?>

<div class="recent-orders-section">
    <h2><?php _e('Pedidos Recientes', 'zoho-sync-customers'); ?></h2>
    
    <?php if (!empty($recent_orders)) : ?>
        <table class="zscu-table orders-table">
            <thead>
                <tr>
                    <th><?php _e('Pedido', 'zoho-sync-customers'); ?></th>
                    <th><?php _e('Fecha', 'zoho-sync-customers'); ?></th>
                    <th><?php _e('Estado', 'zoho-sync-customers'); ?></th>
                    <th><?php _e('Total', 'zoho-sync-customers'); ?></th>
                    <th><?php _e('Acciones', 'zoho-sync-customers'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $order) : ?>
                    <tr>
                        <td>#<?php echo esc_html($order->get_order_number()); ?></td>
                        <td><?php echo esc_html($order->get_date_created()->date_i18n('d/m/Y')); ?></td>
                        <td>
                            <span class="order-status <?php echo esc_attr($order->get_status()); ?>">
                                <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                            </span>
                        </td>
                        <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                        <td>
                            <a href="<?php echo esc_url($order->get_view_order_url()); ?>" 
                               class="button view-order">
                                <?php _e('Ver Detalles', 'zoho-sync-customers'); ?>
                            </a>
                            <?php if ($order->has_invoice()) : ?>
                                <a href="<?php echo esc_url($order->get_invoice_url()); ?>" 
                                   class="button download-invoice" 
                                   target="_blank">
                                    <?php _e('Descargar Factura', 'zoho-sync-customers'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p class="no-orders">
            <?php _e('No hay pedidos recientes.', 'zoho-sync-customers'); ?>
        </p>
    <?php endif; ?>
</div>