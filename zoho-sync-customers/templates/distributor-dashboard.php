<?php
/**
 * Template Name: Dashboard del Distribuidor
 */

if (!defined('ABSPATH')) exit;
?>

<div class="zscu-dashboard distributor-dashboard">
    <div class="dashboard-header">
        <h1><?php printf(
            __('Dashboard de %s', 'zoho-sync-customers'),
            esc_html(wp_get_current_user()->display_name)
        ); ?></h1>
        <div class="level-badge <?php echo esc_attr($level); ?>">
            <?php echo esc_html(ucfirst($level)); ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card orders">
            <h3><?php _e('Total Pedidos', 'zoho-sync-customers'); ?></h3>
            <div class="stat-value"><?php echo esc_html($stats['total_orders']); ?></div>
        </div>
        
        <div class="stat-card spent">
            <h3><?php _e('Total Gastado', 'zoho-sync-customers'); ?></h3>
            <div class="stat-value"><?php echo wp_kses_post($stats['total_spent']); ?></div>
        </div>
        
        <div class="stat-card average">
            <h3><?php _e('Promedio por Pedido', 'zoho-sync-customers'); ?></h3>
            <div class="stat-value"><?php echo wp_kses_post($stats['average_order']); ?></div>
        </div>
        
        <div class="stat-card discount">
            <h3><?php _e('Descuento Actual', 'zoho-sync-customers'); ?></h3>
            <div class="stat-value"><?php echo esc_html($stats['discount_level']); ?></div>
        </div>
    </div>

    <?php include ZSCU_PLUGIN_DIR . 'templates/partials/recent-orders.php'; ?>
    
    <?php include ZSCU_PLUGIN_DIR . 'templates/partials/special-prices.php'; ?>
</div>