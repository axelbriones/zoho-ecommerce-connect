<?php

/**
 * Template Name: Dashboard del Distribuidor
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zsdp-dashboard">
    <div class="zsdp-welcome-header">
        <h1><?php printf(
            __('Bienvenido, %s', 'zoho-distributor-portal'),
            esc_html($distributor_name)
        ); ?></h1>
        <p class="zsdp-last-login">
            <?php printf(
                __('Último acceso: %s', 'zoho-distributor-portal'),
                esc_html($last_login)
            ); ?>
        </p>
    </div>

    <div class="zsdp-stats-grid">
        <?php foreach ($widgets as $widget): ?>
            <div class="zsdp-widget <?php echo esc_attr($widget['id']); ?>">
                <?php echo $widget['content']; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="zsdp-main-content">
        <div class="zsdp-recent-orders">
            <h2><?php _e('Pedidos Recientes', 'zoho-distributor-portal'); ?></h2>
            <?php if (!empty($recent_orders)): ?>
                <table class="zsdp-orders-table">
                    <thead>
                        <tr>
                            <th><?php _e('Pedido', 'zoho-distributor-portal'); ?></th>
                            <th><?php _e('Fecha', 'zoho-distributor-portal'); ?></th>
                            <th><?php _e('Estado', 'zoho-distributor-portal'); ?></th>
                            <th><?php _e('Total', 'zoho-distributor-portal'); ?></th>
                            <th><?php _e('Acciones', 'zoho-distributor-portal'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
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
                                       class="button">
                                        <?php _e('Ver', 'zoho-distributor-portal'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="zsdp-no-orders">
                    <?php _e('No hay pedidos recientes.', 'zoho-distributor-portal'); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="zsdp-zone-info">
            <h2><?php _e('Mi Zona Asignada', 'zoho-distributor-portal'); ?></h2>
            <?php if (!empty($zone_data)): ?>
                <div class="zsdp-zone-details">
                    <p><strong><?php _e('Códigos Postales:', 'zoho-distributor-portal'); ?></strong> 
                       <?php echo esc_html($zone_data['postal_codes']); ?></p>
                    <p><strong><?php _e('Población:', 'zoho-distributor-portal'); ?></strong> 
                       <?php echo esc_html($zone_data['population']); ?></p>
                    <p><strong><?php _e('Potencial de Mercado:', 'zoho-distributor-portal'); ?></strong> 
                       <?php echo esc_html($zone_data['market_potential']); ?></p>
                </div>
                <div id="zsdp-zone-map" class="zsdp-map"></div>
            <?php else: ?>
                <p class="zsdp-no-zone">
                    <?php _e('No hay zona asignada actualmente.', 'zoho-distributor-portal'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>