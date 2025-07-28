<?php

/**
 * Template Name: Precios Especiales
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zsdp-special-pricing">
    <div class="zsdp-pricing-header">
        <h1><?php _e('Mis Precios Especiales', 'zoho-distributor-portal'); ?></h1>
        <div class="zsdp-level-info">
            <p><?php printf(
                __('Nivel de Distribuidor: %s', 'zoho-distributor-portal'),
                '<strong>' . esc_html($distributor_level) . '</strong>'
            ); ?></p>
            <p><?php printf(
                __('Descuento Base: %s%%', 'zoho-distributor-portal'),
                esc_html($base_discount)
            ); ?></p>
        </div>
    </div>

    <div class="zsdp-pricing-filters">
        <form method="get" class="zsdp-filter-form">
            <select name="category" class="zsdp-category-filter">
                <option value=""><?php _e('Todas las categorías', 'zoho-distributor-portal'); ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->slug); ?>" 
                            <?php selected($current_category, $cat->slug); ?>>
                        <?php echo esc_html($cat->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" 
                   name="search" 
                   placeholder="<?php esc_attr_e('Buscar productos...', 'zoho-distributor-portal'); ?>"
                   value="<?php echo esc_attr($current_search); ?>">
            <button type="submit" class="button">
                <?php _e('Filtrar', 'zoho-distributor-portal'); ?>
            </button>
        </form>
    </div>

    <?php if (!empty($products)): ?>
        <table class="zsdp-pricing-table">
            <thead>
                <tr>
                    <th><?php _e('Producto', 'zoho-distributor-portal'); ?></th>
                    <th><?php _e('SKU', 'zoho-distributor-portal'); ?></th>
                    <th><?php _e('Precio Normal', 'zoho-distributor-portal'); ?></th>
                    <th><?php _e('Tu Precio', 'zoho-distributor-portal'); ?></th>
                    <th><?php _e('Stock', 'zoho-distributor-portal'); ?></th>
                    <th><?php _e('Acciones', 'zoho-distributor-portal'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <div class="product-info">
                                <?php if ($product->get_image_id()): ?>
                                    <img src="<?php echo wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'); ?>" 
                                         alt="<?php echo esc_attr($product->get_name()); ?>">
                                <?php endif; ?>
                                <div class="product-details">
                                    <strong><?php echo esc_html($product->get_name()); ?></strong>
                                    <span class="category"><?php echo $product_categories[$product->get_id()]; ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?php echo esc_html($product->get_sku()); ?></td>
                        <td><?php echo wp_kses_post($product->get_regular_price()); ?></td>
                        <td class="special-price">
                            <?php echo wp_kses_post($special_prices[$product->get_id()]); ?>
                            <span class="savings">
                                <?php echo esc_html($savings[$product->get_id()]); ?>
                            </span>
                        </td>
                        <td>
                            <span class="stock-status <?php echo esc_attr($product->get_stock_status()); ?>">
                                <?php echo esc_html($stock_status[$product->get_stock_status()]); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($product->is_in_stock()): ?>
                                <button type="button" 
                                        class="button add-to-cart" 
                                        data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                                    <?php _e('Añadir', 'zoho-distributor-portal'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php echo $pagination; ?>
    <?php else: ?>
        <p class="zsdp-no-products">
            <?php _e('No se encontraron productos.', 'zoho-distributor-portal'); ?>
        </p>
    <?php endif; ?>
</div>