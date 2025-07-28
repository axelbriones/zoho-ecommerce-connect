<?php
/**
 * Distributors Display
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get distributor manager instance
$distributor_manager = ZohoSyncCustomers_DistributorManager::get_instance();
$distributor_levels = $distributor_manager->get_distributor_levels();
?>

<div class="wrap">
    <h1><?php _e('Gestión de Distribuidores', 'zoho-sync-customers'); ?></h1>
    
    <!-- Distributor Statistics -->
    <div class="zoho-distributor-stats">
        <div class="stats-grid">
            <?php
            $distributor_stats = $distributor_manager->get_distributor_statistics();
            ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-businessman"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($distributor_stats['total']); ?></h3>
                    <p><?php _e('Total Distribuidores', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card approved">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($distributor_stats['approved']); ?></h3>
                    <p><?php _e('Aprobados', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($distributor_stats['pending']); ?></h3>
                    <p><?php _e('Pendientes', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card rejected">
                <div class="stat-icon">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($distributor_stats['rejected']); ?></h3>
                    <p><?php _e('Rechazados', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Distributor Levels Configuration -->
    <div class="zoho-distributor-levels">
        <h2><?php _e('Configuración de Niveles', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php" id="distributor-levels-form">
            <?php settings_fields('zoho_customers_distributor_levels'); ?>
            
            <div class="levels-container">
                <?php foreach ($distributor_levels as $level_key => $level_data): ?>
                <div class="level-card" data-level="<?php echo esc_attr($level_key); ?>">
                    <div class="level-header">
                        <h3><?php echo esc_html($level_data['name']); ?></h3>
                        <div class="level-actions">
                            <button type="button" class="button button-small edit-level"><?php _e('Editar', 'zoho-sync-customers'); ?></button>
                            <?php if ($level_key !== 'level_1'): // Don't allow deleting the first level ?>
                            <button type="button" class="button button-small button-link-delete delete-level"><?php _e('Eliminar', 'zoho-sync-customers'); ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="level-content">
                        <div class="level-info">
                            <div class="info-item">
                                <span class="info-label"><?php _e('Descuento:', 'zoho-sync-customers'); ?></span>
                                <span class="info-value"><?php echo esc_html($level_data['discount']); ?>%</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php _e('Mínimo de Compra:', 'zoho-sync-customers'); ?></span>
                                <span class="info-value"><?php echo wc_price($level_data['min_purchase']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><?php _e('Distribuidores:', 'zoho-sync-customers'); ?></span>
                                <span class="info-value"><?php echo $distributor_manager->count_distributors_by_level($level_key); ?></span>
                            </div>
                        </div>
                        
                        <div class="level-edit-form" style="display: none;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Nombre del Nivel', 'zoho-sync-customers'); ?></th>
                                    <td>
                                        <input type="text" name="distributor_levels[<?php echo esc_attr($level_key); ?>][name]" 
                                               value="<?php echo esc_attr($level_data['name']); ?>" class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Porcentaje de Descuento', 'zoho-sync-customers'); ?></th>
                                    <td>
                                        <input type="number" name="distributor_levels[<?php echo esc_attr($level_key); ?>][discount]" 
                                               value="<?php echo esc_attr($level_data['discount']); ?>" min="0" max="100" step="0.01" class="small-text" required>
                                        <span>%</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Compra Mínima Requerida', 'zoho-sync-customers'); ?></th>
                                    <td>
                                        <input type="number" name="distributor_levels[<?php echo esc_attr($level_key); ?>][min_purchase]" 
                                               value="<?php echo esc_attr($level_data['min_purchase']); ?>" min="0" step="0.01" class="regular-text" required>
                                        <span><?php echo get_woocommerce_currency_symbol(); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Descripción', 'zoho-sync-customers'); ?></th>
                                    <td>
                                        <textarea name="distributor_levels[<?php echo esc_attr($level_key); ?>][description]" 
                                                  class="large-text" rows="3"><?php echo esc_textarea($level_data['description'] ?? ''); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="level-form-actions">
                                <button type="button" class="button button-primary save-level"><?php _e('Guardar', 'zoho-sync-customers'); ?></button>
                                <button type="button" class="button cancel-edit"><?php _e('Cancelar', 'zoho-sync-customers'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="add-level-section">
                <button type="button" id="add-new-level" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Agregar Nuevo Nivel', 'zoho-sync-customers'); ?>
                </button>
            </div>
            
            <?php submit_button(__('Guardar Configuración de Niveles', 'zoho-sync-customers')); ?>
        </form>
    </div>
    
    <!-- Distributor Management -->
    <div class="zoho-distributor-management">
        <h2><?php _e('Gestión de Distribuidores', 'zoho-sync-customers'); ?></h2>
        
        <!-- Filters -->
        <div class="distributor-filters">
            <div class="filter-group">
                <label for="filter-status"><?php _e('Estado:', 'zoho-sync-customers'); ?></label>
                <select id="filter-status">
                    <option value=""><?php _e('Todos', 'zoho-sync-customers'); ?></option>
                    <option value="approved"><?php _e('Aprobados', 'zoho-sync-customers'); ?></option>
                    <option value="pending"><?php _e('Pendientes', 'zoho-sync-customers'); ?></option>
                    <option value="rejected"><?php _e('Rechazados', 'zoho-sync-customers'); ?></option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-level"><?php _e('Nivel:', 'zoho-sync-customers'); ?></label>
                <select id="filter-level">
                    <option value=""><?php _e('Todos los niveles', 'zoho-sync-customers'); ?></option>
                    <?php foreach ($distributor_levels as $level_key => $level_data): ?>
                    <option value="<?php echo esc_attr($level_key); ?>"><?php echo esc_html($level_data['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-search"><?php _e('Buscar:', 'zoho-sync-customers'); ?></label>
                <input type="text" id="filter-search" placeholder="<?php _e('Nombre, email o empresa...', 'zoho-sync-customers'); ?>">
            </div>
            
            <button type="button" id="apply-filters" class="button"><?php _e('Aplicar Filtros', 'zoho-sync-customers'); ?></button>
            <button type="button" id="clear-filters" class="button"><?php _e('Limpiar', 'zoho-sync-customers'); ?></button>
        </div>
        
        <!-- Bulk Actions -->
        <div class="distributor-bulk-actions">
            <select id="bulk-action">
                <option value=""><?php _e('Acciones en lote', 'zoho-sync-customers'); ?></option>
                <option value="approve"><?php _e('Aprobar seleccionados', 'zoho-sync-customers'); ?></option>
                <option value="reject"><?php _e('Rechazar seleccionados', 'zoho-sync-customers'); ?></option>
                <option value="change_level"><?php _e('Cambiar nivel', 'zoho-sync-customers'); ?></option>
                <option value="sync_to_zoho"><?php _e('Sincronizar con Zoho', 'zoho-sync-customers'); ?></option>
                <option value="export"><?php _e('Exportar seleccionados', 'zoho-sync-customers'); ?></option>
            </select>
            
            <div id="level-selector" style="display: none;">
                <select id="new-level">
                    <?php foreach ($distributor_levels as $level_key => $level_data): ?>
                    <option value="<?php echo esc_attr($level_key); ?>"><?php echo esc_html($level_data['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="button" id="apply-bulk-action" class="button"><?php _e('Aplicar', 'zoho-sync-customers'); ?></button>
        </div>
        
        <!-- Distributors Table -->
        <div class="distributors-table-container">
            <table class="wp-list-table widefat fixed striped" id="distributors-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-distributors">
                        </td>
                        <th class="manage-column column-name sortable">
                            <a href="#" data-sort="name">
                                <span><?php _e('Nombre', 'zoho-sync-customers'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-email sortable">
                            <a href="#" data-sort="email">
                                <span><?php _e('Email', 'zoho-sync-customers'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-company">
                            <?php _e('Empresa', 'zoho-sync-customers'); ?>
                        </th>
                        <th class="manage-column column-level sortable">
                            <a href="#" data-sort="level">
                                <span><?php _e('Nivel', 'zoho-sync-customers'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-status sortable">
                            <a href="#" data-sort="status">
                                <span><?php _e('Estado', 'zoho-sync-customers'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-registered sortable">
                            <a href="#" data-sort="registered">
                                <span><?php _e('Registrado', 'zoho-sync-customers'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-sync">
                            <?php _e('Sincronizado', 'zoho-sync-customers'); ?>
                        </th>
                        <th class="manage-column column-actions">
                            <?php _e('Acciones', 'zoho-sync-customers'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody id="distributors-table-body">
                    <!-- Table content will be loaded via AJAX -->
                </tbody>
            </table>
            
            <div class="table-pagination">
                <div class="pagination-info">
                    <span id="pagination-info-text"></span>
                </div>
                <div class="pagination-controls">
                    <button type="button" id="prev-page" class="button" disabled>
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <?php _e('Anterior', 'zoho-sync-customers'); ?>
                    </button>
                    <span class="page-numbers" id="page-numbers"></span>
                    <button type="button" id="next-page" class="button" disabled>
                        <?php _e('Siguiente', 'zoho-sync-customers'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="zoho-distributor-quick-actions">
        <h2><?php _e('Acciones Rápidas', 'zoho-sync-customers'); ?></h2>
        
        <div class="quick-actions-grid">
            <button type="button" id="sync-all-distributors" class="quick-action-button">
                <span class="dashicons dashicons-update"></span>
                <span class="action-title"><?php _e('Sincronizar Todos', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Sincroniza todos los distribuidores con Zoho CRM', 'zoho-sync-customers'); ?></span>
            </button>
            
            <button type="button" id="approve-pending" class="quick-action-button">
                <span class="dashicons dashicons-yes-alt"></span>
                <span class="action-title"><?php _e('Aprobar Pendientes', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Aprueba todos los distribuidores pendientes', 'zoho-sync-customers'); ?></span>
            </button>
            
            <button type="button" id="export-distributors" class="quick-action-button">
                <span class="dashicons dashicons-download"></span>
                <span class="action-title"><?php _e('Exportar Lista', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Descarga la lista completa de distribuidores', 'zoho-sync-customers'); ?></span>
            </button>
            
            <button type="button" id="send-notifications" class="quick-action-button">
                <span class="dashicons dashicons-email-alt"></span>
                <span class="action-title"><?php _e('Enviar Notificaciones', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Notifica a distribuidores sobre cambios de estado', 'zoho-sync-customers'); ?></span>
            </button>
        </div>
    </div>
</div>

<!-- Distributor Details Modal -->
<div id="distributor-modal" class="zoho-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Detalles del Distribuidor', 'zoho-sync-customers'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Content will be loaded via AJAX -->
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-primary" id="save-distributor-changes"><?php _e('Guardar Cambios', 'zoho-sync-customers'); ?></button>
            <button type="button" class="button" id="close-distributor-modal"><?php _e('Cerrar', 'zoho-sync-customers'); ?></button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var currentPage = 1;
    var currentSort = 'name';
    var currentOrder = 'asc';
    
    // Load distributors table
    loadDistributorsTable();
    
    // Level management
    $('.edit-level').on('click', function() {
        var $card = $(this).closest('.level-card');
        $card.find('.level-info').hide();
        $card.find('.level-edit-form').show();
        $(this).hide();
    });
    
    $('.cancel-edit').on('click', function() {
        var $card = $(this).closest('.level-card');
        $card.find('.level-edit-form').hide();
        $card.find('.level-info').show();
        $card.find('.edit-level').show();
    });
    
    $('.save-level').on('click', function() {
        var $form = $('#distributor-levels-form');
        var $card = $(this).closest('.level-card');
        
        // Submit form via AJAX
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: $form.serialize() + '&action=zoho_customers_save_distributor_levels&nonce=' + zohoCustomersAdmin.nonce,
            success: function(response) {
                if (response.success) {
                    location.reload(); // Reload to show updated data
                } else {
                    alert('Error guardando configuración: ' + response.data.message);
                }
            }
        });
    });
    
    // Filters
    $('#apply-filters').on('click', function() {
        currentPage = 1;
        loadDistributorsTable();
    });
    
    $('#clear-filters').on('click', function() {
        $('#filter-status, #filter-level').val('');
        $('#filter-search').val('');
        currentPage = 1;
        loadDistributorsTable();
    });
    
    // Bulk actions
    $('#bulk-action').on('change', function() {
        if ($(this).val() === 'change_level') {
            $('#level-selector').show();
        } else {
            $('#level-selector').hide();
        }
    });
    
    $('#apply-bulk-action').on('click', function() {
        var action = $('#bulk-action').val();
        var selectedIds = [];
        
        $('#distributors-table input[type="checkbox"]:checked').each(function() {
            if ($(this).val() !== 'on') {
                selectedIds.push($(this).val());
            }
        });
        
        if (selectedIds.length === 0) {
            alert('Por favor selecciona al menos un distribuidor.');
            return;
        }
        
        if (!action) {
            alert('Por favor selecciona una acción.');
            return;
        }
        
        var confirmMessage = 'Confirmar acción para ' + selectedIds.length + ' distribuidor(es)?';
        if (!confirm(confirmMessage)) {
            return;
        }
        
        var data = {
            action: 'zoho_customers_bulk_distributor_action',
            bulk_action: action,
            distributor_ids: selectedIds,
            nonce: zohoCustomersAdmin.nonce
        };
        
        if (action === 'change_level') {
            data.new_level = $('#new-level').val();
        }
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    loadDistributorsTable();
                    alert('Acción completada exitosamente.');
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    // Sorting
    $('.sortable a').on('click', function(e) {
        e.preventDefault();
        var sort = $(this).data('sort');
        
        if (currentSort === sort) {
            currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort = sort;
            currentOrder = 'asc';
        }
        
        loadDistributorsTable();
    });
    
    // Pagination
    $('#prev-page').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadDistributorsTable();
        }
    });
    
    $('#next-page').on('click', function() {
        currentPage++;
        loadDistributorsTable();
    });
    
    function loadDistributorsTable() {
        var data = {
            action: 'zoho_customers_load_distributors',
            page: currentPage,
            sort: currentSort,
            order: currentOrder,
            status: $('#filter-status').val(),
            level: $('#filter-level').val(),
            search: $('#filter-search').val(),
            nonce: zohoCustomersAdmin.nonce
        };
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#distributors-table-body').html(response.data.html);
                    updatePagination(response.data.pagination);
                    updateSortingIndicators();
                }
            }
        });
    }
    
    function updatePagination(pagination) {
        $('#pagination-info-text').text(pagination.info);
        
        $('#prev-page').prop('disabled', pagination.current_page <= 1);
        $('#next-page').prop('disabled', pagination.current_page >= pagination.total_pages);
        
        var pageNumbers = '';
        for (var i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
            if (i === pagination.current_page) {
                pageNumbers += '<span class="current-page">' + i + '</span>';
            } else {
                pageNumbers += '<a href="#" class="page-number" data-page="' + i + '">' + i + '</a>';
            }
        }
        $('#page-numbers').html(pageNumbers);
    }
    
    function updateSortingIndicators() {
        $('.sorting-indicator').removeClass('asc desc');
        $('[data-sort="' + currentSort + '"] .sorting-indicator').addClass(currentOrder);
    }
    
    // Page number clicks
    $(document).on('click', '.page-number', function(e) {
        e.preventDefault();
        currentPage = parseInt($(this).data('page'));
        loadDistributorsTable();
    });
    
    // Select all checkbox
    $('#select-all-distributors').on('change', function() {
        $('#distributors-table input[type="checkbox"]').prop('checked', $(this).is(':checked'));
    });
});
</script>