<?php
/**
 * Pricing Display
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get pricing manager instance
$pricing_manager = ZohoSyncCustomers_PricingManager::get_instance();
$distributor_manager = ZohoSyncCustomers_DistributorManager::get_instance();
$distributor_levels = $distributor_manager->get_distributor_levels();
?>

<div class="wrap">
    <h1><?php _e('Gestión de Precios por Nivel', 'zoho-sync-customers'); ?></h1>
    
    <!-- Pricing Statistics -->
    <div class="zoho-pricing-stats">
        <div class="stats-grid">
            <?php
            $pricing_stats = $pricing_manager->get_pricing_statistics();
            ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-tag"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($pricing_stats['products_with_pricing']); ?></h3>
                    <p><?php _e('Productos con Precios Especiales', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-businessman"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($pricing_stats['active_distributors']); ?></h3>
                    <p><?php _e('Distribuidores Activos', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-building"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($pricing_stats['b2b_customers']); ?></h3>
                    <p><?php _e('Clientes B2B con Precios', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo wc_price($pricing_stats['total_savings']); ?></h3>
                    <p><?php _e('Ahorros Totales del Mes', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pricing Configuration -->
    <div class="zoho-pricing-config">
        <h2><?php _e('Configuración General de Precios', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('zoho_customers_pricing'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Sistema de Precios Habilitado', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_pricing_enabled" value="yes" 
                                       <?php checked(get_option('zoho_customers_pricing_enabled', 'yes'), 'yes'); ?>>
                                <?php _e('Habilitar sistema de precios por nivel', 'zoho-sync-customers'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Activa o desactiva el sistema completo de precios especiales.', 'zoho-sync-customers'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Mostrar Precio Original', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_show_original_price" value="yes" 
                                       <?php checked(get_option('zoho_customers_show_original_price', 'yes'), 'yes'); ?>>
                                <?php _e('Mostrar precio original tachado junto al precio especial', 'zoho-sync-customers'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Los usuarios verán tanto el precio original como el precio con descuento.', 'zoho-sync-customers'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Aplicar Descuentos en', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <?php
                            $apply_discounts = get_option('zoho_customers_apply_discounts', array('product_page', 'shop_page', 'cart'));
                            ?>
                            <label>
                                <input type="checkbox" name="zoho_customers_apply_discounts[]" value="product_page" 
                                       <?php checked(in_array('product_page', $apply_discounts)); ?>>
                                <?php _e('Página de producto', 'zoho-sync-customers'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="zoho_customers_apply_discounts[]" value="shop_page" 
                                       <?php checked(in_array('shop_page', $apply_discounts)); ?>>
                                <?php _e('Página de tienda', 'zoho-sync-customers'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="zoho_customers_apply_discounts[]" value="cart" 
                                       <?php checked(in_array('cart', $apply_discounts)); ?>>
                                <?php _e('Carrito de compras', 'zoho-sync-customers'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="zoho_customers_apply_discounts[]" value="checkout" 
                                       <?php checked(in_array('checkout', $apply_discounts)); ?>>
                                <?php _e('Página de checkout', 'zoho-sync-customers'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Mensaje para Usuarios No Autorizados', 'zoho-sync-customers'); ?></th>
                    <td>
                        <textarea name="zoho_customers_unauthorized_message" class="large-text" rows="3"><?php 
                            echo esc_textarea(get_option('zoho_customers_unauthorized_message', 
                                __('Inicia sesión para ver precios especiales o contacta con nosotros para obtener acceso como distribuidor.', 'zoho-sync-customers')
                            )); 
                        ?></textarea>
                        <p class="description">
                            <?php _e('Mensaje mostrado a usuarios que no tienen acceso a precios especiales.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Redondeo de Precios', 'zoho-sync-customers'); ?></th>
                    <td>
                        <select name="zoho_customers_price_rounding">
                            <?php
                            $current_rounding = get_option('zoho_customers_price_rounding', 'none');
                            $rounding_options = array(
                                'none' => __('Sin redondeo', 'zoho-sync-customers'),
                                'up' => __('Redondear hacia arriba', 'zoho-sync-customers'),
                                'down' => __('Redondear hacia abajo', 'zoho-sync-customers'),
                                'nearest' => __('Redondear al más cercano', 'zoho-sync-customers')
                            );
                            
                            foreach ($rounding_options as $value => $label) {
                                echo '<option value="' . esc_attr($value) . '" ' . selected($current_rounding, $value, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php _e('Cómo redondear los precios calculados con descuentos.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Guardar Configuración de Precios', 'zoho-sync-customers')); ?>
        </form>
    </div>
    
    <!-- Pricing Levels Overview -->
    <div class="zoho-pricing-levels">
        <h2><?php _e('Niveles de Precios', 'zoho-sync-customers'); ?></h2>
        
        <div class="pricing-levels-grid">
            <?php foreach ($distributor_levels as $level_key => $level_data): ?>
            <div class="pricing-level-card" data-level="<?php echo esc_attr($level_key); ?>">
                <div class="level-header">
                    <h3><?php echo esc_html($level_data['name']); ?></h3>
                    <div class="level-discount">
                        <span class="discount-badge"><?php echo esc_html($level_data['discount']); ?>% OFF</span>
                    </div>
                </div>
                
                <div class="level-stats">
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Usuarios:', 'zoho-sync-customers'); ?></span>
                        <span class="stat-value"><?php echo $distributor_manager->count_distributors_by_level($level_key); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Productos:', 'zoho-sync-customers'); ?></span>
                        <span class="stat-value"><?php echo $pricing_manager->count_products_with_level_pricing($level_key); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Mín. Compra:', 'zoho-sync-customers'); ?></span>
                        <span class="stat-value"><?php echo wc_price($level_data['min_purchase']); ?></span>
                    </div>
                </div>
                
                <div class="level-actions">
                    <button type="button" class="button button-small view-level-products" data-level="<?php echo esc_attr($level_key); ?>">
                        <?php _e('Ver Productos', 'zoho-sync-customers'); ?>
                    </button>
                    <button type="button" class="button button-small manage-level-pricing" data-level="<?php echo esc_attr($level_key); ?>">
                        <?php _e('Gestionar Precios', 'zoho-sync-customers'); ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- B2B Level -->
            <div class="pricing-level-card b2b-level">
                <div class="level-header">
                    <h3><?php _e('Clientes B2B', 'zoho-sync-customers'); ?></h3>
                    <div class="level-discount">
                        <span class="discount-badge"><?php echo esc_html(get_option('zoho_customers_b2b_discount', 15)); ?>% OFF</span>
                    </div>
                </div>
                
                <div class="level-stats">
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Usuarios:', 'zoho-sync-customers'); ?></span>
                        <span class="stat-value"><?php echo $pricing_stats['b2b_customers']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><?php _e('Productos:', 'zoho-sync-customers'); ?></span>
                        <span class="stat-value"><?php echo $pricing_manager->count_products_with_b2b_pricing(); ?></span>
                    </div>
                </div>
                
                <div class="level-actions">
                    <button type="button" class="button button-small view-b2b-products">
                        <?php _e('Ver Productos', 'zoho-sync-customers'); ?>
                    </button>
                    <button type="button" class="button button-small manage-b2b-pricing">
                        <?php _e('Gestionar Precios', 'zoho-sync-customers'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Pricing Management -->
    <div class="zoho-product-pricing">
        <h2><?php _e('Gestión de Precios por Producto', 'zoho-sync-customers'); ?></h2>
        
        <!-- Product Search and Filters -->
        <div class="product-pricing-filters">
            <div class="filter-group">
                <label for="product-search"><?php _e('Buscar Producto:', 'zoho-sync-customers'); ?></label>
                <input type="text" id="product-search" placeholder="<?php _e('Nombre del producto, SKU...', 'zoho-sync-customers'); ?>">
            </div>
            
            <div class="filter-group">
                <label for="category-filter"><?php _e('Categoría:', 'zoho-sync-customers'); ?></label>
                <select id="category-filter">
                    <option value=""><?php _e('Todas las categorías', 'zoho-sync-customers'); ?></option>
                    <?php
                    $categories = get_terms(array(
                        'taxonomy' => 'product_cat',
                        'hide_empty' => false
                    ));
                    
                    foreach ($categories as $category) {
                        echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="pricing-status-filter"><?php _e('Estado de Precios:', 'zoho-sync-customers'); ?></label>
                <select id="pricing-status-filter">
                    <option value=""><?php _e('Todos', 'zoho-sync-customers'); ?></option>
                    <option value="with_pricing"><?php _e('Con precios especiales', 'zoho-sync-customers'); ?></option>
                    <option value="without_pricing"><?php _e('Sin precios especiales', 'zoho-sync-customers'); ?></option>
                </select>
            </div>
            
            <button type="button" id="apply-product-filters" class="button"><?php _e('Aplicar Filtros', 'zoho-sync-customers'); ?></button>
            <button type="button" id="clear-product-filters" class="button"><?php _e('Limpiar', 'zoho-sync-customers'); ?></button>
        </div>
        
        <!-- Bulk Pricing Actions -->
        <div class="bulk-pricing-actions">
            <select id="bulk-pricing-action">
                <option value=""><?php _e('Acciones en lote', 'zoho-sync-customers'); ?></option>
                <option value="apply_discount"><?php _e('Aplicar descuento', 'zoho-sync-customers'); ?></option>
                <option value="remove_pricing"><?php _e('Eliminar precios especiales', 'zoho-sync-customers'); ?></option>
                <option value="sync_from_zoho"><?php _e('Sincronizar desde Zoho', 'zoho-sync-customers'); ?></option>
                <option value="export_pricing"><?php _e('Exportar precios', 'zoho-sync-customers'); ?></option>
            </select>
            
            <div id="discount-settings" style="display: none;">
                <input type="number" id="bulk-discount-percentage" placeholder="%" min="0" max="100" step="0.01" class="small-text">
                <select id="bulk-discount-level">
                    <option value=""><?php _e('Seleccionar nivel', 'zoho-sync-customers'); ?></option>
                    <?php foreach ($distributor_levels as $level_key => $level_data): ?>
                    <option value="<?php echo esc_attr($level_key); ?>"><?php echo esc_html($level_data['name']); ?></option>
                    <?php endforeach; ?>
                    <option value="b2b"><?php _e('Clientes B2B', 'zoho-sync-customers'); ?></option>
                </select>
            </div>
            
            <button type="button" id="apply-bulk-pricing" class="button"><?php _e('Aplicar', 'zoho-sync-customers'); ?></button>
        </div>
        
        <!-- Products Table -->
        <div class="products-pricing-table-container">
            <table class="wp-list-table widefat fixed striped" id="products-pricing-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-products">
                        </td>
                        <th class="manage-column column-product sortable">
                            <a href="#" data-sort="name">
                                <span><?php _e('Producto', 'zoho-sync-customers'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-sku">
                            <?php _e('SKU', 'zoho-sync-customers'); ?>
                        </th>
                        <th class="manage-column column-regular-price sortable">
                            <a href="#" data-sort="price">
                                <span><?php _e('Precio Regular', 'zoho-sync-customers'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <?php foreach ($distributor_levels as $level_key => $level_data): ?>
                        <th class="manage-column column-level-price">
                            <?php echo esc_html($level_data['name']); ?>
                        </th>
                        <?php endforeach; ?>
                        <th class="manage-column column-b2b-price">
                            <?php _e('B2B', 'zoho-sync-customers'); ?>
                        </th>
                        <th class="manage-column column-actions">
                            <?php _e('Acciones', 'zoho-sync-customers'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody id="products-pricing-table-body">
                    <!-- Table content will be loaded via AJAX -->
                </tbody>
            </table>
            
            <div class="table-pagination">
                <div class="pagination-info">
                    <span id="products-pagination-info-text"></span>
                </div>
                <div class="pagination-controls">
                    <button type="button" id="products-prev-page" class="button" disabled>
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <?php _e('Anterior', 'zoho-sync-customers'); ?>
                    </button>
                    <span class="page-numbers" id="products-page-numbers"></span>
                    <button type="button" id="products-next-page" class="button" disabled>
                        <?php _e('Siguiente', 'zoho-sync-customers'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="zoho-pricing-quick-actions">
        <h2><?php _e('Acciones Rápidas', 'zoho-sync-customers'); ?></h2>
        
        <div class="quick-actions-grid">
            <button type="button" id="sync-all-pricing" class="quick-action-button">
                <span class="dashicons dashicons-update"></span>
                <span class="action-title"><?php _e('Sincronizar Precios', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Sincroniza todos los precios con Zoho CRM', 'zoho-sync-customers'); ?></span>
            </button>
            
            <button type="button" id="recalculate-all-prices" class="quick-action-button">
                <span class="dashicons dashicons-calculator"></span>
                <span class="action-title"><?php _e('Recalcular Precios', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Recalcula todos los precios especiales', 'zoho-sync-customers'); ?></span>
            </button>
            
            <button type="button" id="export-pricing-report" class="quick-action-button">
                <span class="dashicons dashicons-download"></span>
                <span class="action-title"><?php _e('Exportar Reporte', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Descarga reporte completo de precios', 'zoho-sync-customers'); ?></span>
            </button>
            
            <button type="button" id="import-pricing-data" class="quick-action-button">
                <span class="dashicons dashicons-upload"></span>
                <span class="action-title"><?php _e('Importar Precios', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Importa precios desde archivo CSV', 'zoho-sync-customers'); ?></span>
            </button>
        </div>
    </div>
</div>

<!-- Product Pricing Modal -->
<div id="product-pricing-modal" class="zoho-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Gestionar Precios del Producto', 'zoho-sync-customers'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Content will be loaded via AJAX -->
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-primary" id="save-product-pricing"><?php _e('Guardar Precios', 'zoho-sync-customers'); ?></button>
            <button type="button" class="button" id="close-pricing-modal"><?php _e('Cerrar', 'zoho-sync-customers'); ?></button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var currentProductsPage = 1;
    var currentProductsSort = 'name';
    var currentProductsOrder = 'asc';
    
    // Load products pricing table
    loadProductsPricingTable();
    
    // Bulk pricing action selector
    $('#bulk-pricing-action').on('change', function() {
        if ($(this).val() === 'apply_discount') {
            $('#discount-settings').show();
        } else {
            $('#discount-settings').hide();
        }
    });
    
    // Product filters
    $('#apply-product-filters').on('click', function() {
        currentProductsPage = 1;
        loadProductsPricingTable();
    });
    
    $('#clear-product-filters').on('click', function() {
        $('#product-search, #category-filter, #pricing-status-filter').val('');
        currentProductsPage = 1;
        loadProductsPricingTable();
    });
    
    // Bulk pricing actions
    $('#apply-bulk-pricing').on('click', function() {
        var action = $('#bulk-pricing-action').val();
        var selectedIds = [];
        
        $('#products-pricing-table input[type="checkbox"]:checked').each(function() {
            if ($(this).val() !== 'on') {
                selectedIds.push($(this).val());
            }
        });
        
        if (selectedIds.length === 0) {
            alert('Por favor selecciona al menos un producto.');
            return;
        }
        
        if (!action) {
            alert('Por favor selecciona una acción.');
            return;
        }
        
        var data = {
            action: 'zoho_customers_bulk_pricing_action',
            bulk_action: action,
            product_ids: selectedIds,
            nonce: zohoCustomersAdmin.nonce
        };
        
        if (action === 'apply_discount') {
            var discount = $('#bulk-discount-percentage').val();
            var level = $('#bulk-discount-level').val();
            
            if (!discount || !level) {
                alert('Por favor especifica el descuento y el nivel.');
                return;
            }
            
            data.discount_percentage = discount;
            data.discount_level = level;
        }
        
        if (!confirm('Confirmar acción para ' + selectedIds.length + ' producto(s)?')) {
            return;
        }
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    loadProductsPricingTable();
                    alert('Acción completada exitosamente.');
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    // Quick actions
    $('#sync-all-pricing').on('click', function() {
        if (!confirm('¿Sincronizar todos los precios con Zoho CRM?')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true);
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_sync_all_pricing',
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadProductsPricingTable();
                    alert('Sincronización de precios completada.');
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    $('#recalculate-all-prices').on('click', function() {
        if (!confirm('¿Recalcular todos los precios especiales?')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true);
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_recalculate_all_prices',
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadProductsPricingTable();
                    alert('Recálculo de precios completado.');
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Sorting
    $('#products-pricing-table .sortable a').on('click', function(e) {
        e.preventDefault();
        var sort = $(this).data('sort');
        
        if (currentProductsSort === sort) {
            currentProductsOrder = currentProductsOrder === 'asc' ? 'desc' : 'asc';
        } else {
            currentProductsSort = sort;
            currentProductsOrder = 'asc';
        }
        
        loadProductsPricingTable();
    });
    
    // Pagination
    $('#products-prev-page').on('click', function() {
        if (currentProductsPage > 1) {
            currentProductsPage--;
            loadProductsPricingTable();
        }
    });
    
    $('#products-next-page').on('click', function() {
        currentProductsPage++;
        loadProductsPricingTable();
    });
    
    function loadProductsPricingTable() {
        var data = {
            action: 'zoho_customers_load_products_pricing',
            page: currentProductsPage,
            sort: currentProductsSort,
            order: currentProductsOrder,
            search: $('#product-search').val(),
            category: $('#category-filter').val(),
            pricing_status: $('#pricing-status-filter').val(),
            nonce: zohoCustomersAdmin.nonce
        };
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#products-pricing-table-body').html(response.data.html);
                    updateProductsPagination(response.data.pagination);
                    updateProductsSortingIndicators();
                }
            }
        });
    }
    
    function updateProductsPagination(pagination) {
        $('#products-pagination-info-text').text(pagination.info);
        
        $('#products-prev-page').prop('disabled', pagination.current_page <= 1);
        $('#products-next-page').prop('disabled', pagination.current_page >= pagination.total_pages);
        
        var pageNumbers = '';
        for (var i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
            if (i === pagination.current_page) {
                pageNumbers += '<span class="current-page">' + i + '</span>';
            } else {
                pageNumbers += '<a href="#" class="products-page-number" data-page="' + i + '">' + i + '</a>';
            }
        }
        $('#products-page-numbers').html(pageNumbers);
    }
    
    function updateProductsSortingIndicators() {
        $('#products-pricing-table .sorting-indicator').removeClass('asc desc');
        $('#products-pricing-table [data-sort="' + currentProductsSort + '"] .sorting-indicator').addClass(currentProductsOrder);
    }
    
    // Page number clicks
    $(document).on('click', '.products-page-number', function(e) {
        e.preventDefault();
        currentProductsPage = parseInt($(this).data('page'));
        loadProductsPricingTable();
    });
    
    // Select all checkbox
    $('#select-all-products').on('change', function() {
        $('#products-pricing-table input[type="checkbox"]').prop('checked', $(this).is(':checked'));
    });
    
    // Modal actions
    $(document).on('click', '.manage-product-pricing', function() {
        var productId = $(this).data('product-id');
        loadProductPricingDetails(productId);
    });
    
    function loadProductPricingDetails(productId) {
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_load_product_pricing_details',
                product_id: productId,
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#product-pricing-modal .modal-body').html(response.data.html);
                    $('#product-pricing-modal').show();
                    
                    // Store product ID for modal actions
                    $('#product-pricing-modal').data('product-id', productId);
                }
            }
        });
    }
    
    // Modal close
    $('.modal-close, #close-pricing-modal').on('click', function() {
        $('#product-pricing-modal').hide();
    });
    
    // Save product pricing
    $('#save-product-pricing').on('click', function() {
        var productId = $('#product-pricing-modal').data('product-id');
        var formData = $('#product-pricing-modal form').serialize();
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: formData + '&action=zoho_customers_save_product_pricing&product_id=' + productId + '&nonce=' + zohoCustomersAdmin.nonce,
            success: function(response) {
                if (response.success) {
                    $('#product-pricing-modal').hide();
                    loadProductsPricingTable();
                    alert('Precios guardados exitosamente.');
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
});
</script>