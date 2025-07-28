<?php

if (!defined('ABSPATH')) exit;

global $pricing_manager;

$products = wc_get_products([
    'limit' => 10,
    'orderby' => 'date',
    'order' => 'DESC',
    'status' => 'publish'
]);
?>

<div class="special-prices-section">
    <h2><?php _e('Precios Especiales', 'zoho-sync-customers'); ?></h2>
    
    <?php if (!empty($products)) : ?>
        <table class="zscu-table prices-table">
            <thead>
                <tr>
                    <th><?php _e('Producto', 'zoho-sync-customers'); ?></th>
                    <th><?php _e('Precio Regular', 'zoho-sync-customers'); ?></th>
                    <th><?php _e('Tu Precio', 'zoho-sync-customers'); ?></th>
                    <th><?php _e('Ahorro', 'zoho-sync-customers'); ?></th>
                    <th><?php _e('Stock', 'zoho-sync-customers'); ?></th>
                    <th><?php _e('Acciones', 'zoho-sync-customers'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product) : 
                    $special_price = $pricing_manager->get_special_price($product->get_id());
                    $regular_price = $product->get_regular_price();
                    $savings = $regular_price - $special_price;
                    $savings_percent = ($savings / $regular_price) * 100;
                ?>
                    <tr>
                        <td>
                            <div class="product-info">
                                <?php if ($product->get_image_id()) : ?>
                                    <img src="<?php echo wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'); ?>" 
                                         alt="<?php echo esc_attr($product->get_name()); ?>">
                                <?php endif; ?>
                                <div class="product-details">
                                    <strong><?php echo esc_html($product->get_name()); ?></strong>
                                    <span class="sku"><?php echo esc_html($product->get_sku()); ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?php echo wc_price($regular_price); ?></td>
                        <td class="special-price"><?php echo wc_price($special_price); ?></td>
                        <td class="savings">
                            <?php printf(
                                __('-%d%% (%s)', 'zoho-sync-customers'),
                                round($savings_percent),
                                wc_price($savings)
                            ); ?>
                        </td>
                        <td>
                            <span class="stock-status <?php echo esc_attr($product->get_stock_status()); ?>">
                                <?php echo wc_get_stock_html($product); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($product->is_in_stock()) : ?>
                                <button type="button" 
                                        class="button add-to-cart" 
                                        data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                                    <?php _e('Añadir al Carrito', 'zoho-sync-customers'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button view-all">
            <?php _e('Ver Todos los Productos', 'zoho-sync-customers'); ?>
        </a>
    <?php else : ?>
        <p class="no-products">
            <?php _e('No hay productos disponibles.', 'zoho-sync-customers'); ?>
        </p>
    <?php endif; ?>
</div>