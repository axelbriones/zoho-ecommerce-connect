<?php
/**
 * B2B Customers Display
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get B2B validator instance
$b2b_validator = ZohoSyncCustomers_B2BValidator::get_instance();
?>

<div class="wrap">
    <h1><?php _e('Gestión de Clientes B2B', 'zoho-sync-customers'); ?></h1>
    
    <!-- B2B Statistics -->
    <div class="zoho-b2b-stats">
        <div class="stats-grid">
            <?php
            $b2b_stats = $b2b_validator->get_b2b_statistics();
            ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-building"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($b2b_stats['total_b2b_customers']); ?></h3>
                    <p><?php _e('Total Clientes B2B', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($b2b_stats['pending_approval']); ?></h3>
                    <p><?php _e('Pendientes de Aprobación', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card approved">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($b2b_stats['approved_customers']); ?></h3>
                    <p><?php _e('Aprobados', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
            
            <div class="stat-card rejected">
                <div class="stat-icon">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($b2b_stats['rejected_customers']); ?></h3>
                    <p><?php _e('Rechazados', 'zoho-sync-customers'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Approvals Alert -->
    <?php if ($b2b_stats['pending_approval'] > 0): ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php _e('Atención:', 'zoho-sync-customers'); ?></strong>
            <?php printf(
                _n(
                    'Tienes %d cliente B2B pendiente de aprobación.',
                    'Tienes %d clientes B2B pendientes de aprobación.',
                    $b2b_stats['pending_approval'],
                    'zoho-sync-customers'
                ),
                $b2b_stats['pending_approval']
            ); ?>
            <a href="#pending-approvals" class="button button-small"><?php _e('Revisar Ahora', 'zoho-sync-customers'); ?></a>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- B2B Configuration -->
    <div class="zoho-b2b-config">
        <h2><?php _e('Configuración B2B', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('zoho_customers_b2b'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Aprobación Requerida', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_b2b_approval_required" value="yes" 
                                       <?php checked(get_option('zoho_customers_b2b_approval_required', 'yes'), 'yes'); ?>>
                                <?php _e('Requerir aprobación manual para nuevos clientes B2B', 'zoho-sync-customers'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Si está habilitado, los nuevos clientes B2B necesitarán aprobación antes de acceder a precios especiales.', 'zoho-sync-customers'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Descuento B2B por Defecto', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="number" name="zoho_customers_b2b_discount" 
                               value="<?php echo esc_attr(get_option('zoho_customers_b2b_discount', 15)); ?>" 
                               min="0" max="100" step="0.01" class="small-text">
                        <span>%</span>
                        <p class="description">
                            <?php _e('Descuento por defecto aplicado a clientes B2B aprobados.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Ocultar Precios a Invitados', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_hide_prices_guests" value="yes" 
                                       <?php checked(get_option('zoho_customers_hide_prices_guests', 'no'), 'yes'); ?>>
                                <?php _e('Ocultar precios a usuarios no registrados', 'zoho-sync-customers'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Los precios solo serán visibles para usuarios registrados y aprobados.', 'zoho-sync-customers'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Campos Requeridos para B2B', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <?php
                            $required_fields = get_option('zoho_customers_b2b_required_fields', array('company_name', 'tax_id'));
                            $available_fields = array(
                                'company_name' => __('Nombre de la Empresa', 'zoho-sync-customers'),
                                'tax_id' => __('NIT/RUT', 'zoho-sync-customers'),
                                'company_address' => __('Dirección de la Empresa', 'zoho-sync-customers'),
                                'company_phone' => __('Teléfono de la Empresa', 'zoho-sync-customers'),
                                'contact_person' => __('Persona de Contacto', 'zoho-sync-customers'),
                                'business_type' => __('Tipo de Negocio', 'zoho-sync-customers')
                            );
                            
                            foreach ($available_fields as $field_key => $field_label):
                            ?>
                            <label>
                                <input type="checkbox" name="zoho_customers_b2b_required_fields[]" 
                                       value="<?php echo esc_attr($field_key); ?>"
                                       <?php checked(in_array($field_key, $required_fields)); ?>>
                                <?php echo esc_html($field_label); ?>
                            </label><br>
                            <?php endforeach; ?>
                            <p class="description">
                                <?php _e('Campos que serán obligatorios durante el registro B2B.', 'zoho-sync-customers'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Notificaciones de Aprobación', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_b2b_notify_admin" value="yes" 
                                       <?php checked(get_option('zoho_customers_b2b_notify_admin', 'yes'), 'yes'); ?>>
                                <?php _e('Notificar al administrador sobre nuevas solicitudes B2B', 'zoho-sync-customers'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="zoho_customers_b2b_notify_customer" value="yes" 
                                       <?php checked(get_option('zoho_customers_b2b_notify_customer', 'yes'), 'yes'); ?>>
                                <?php _e('Notificar al cliente sobre cambios de estado', 'zoho-sync-customers'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Guardar Configuración B2B', 'zoho-sync-customers')); ?>
        </form>
    </div>
    
    <!-- Pending Approvals Section -->
    <div id="pending-approvals" class="zoho-pending-approvals">
        <h2><?php _e('Solicitudes Pendientes de Aprobación', 'zoho-sync-customers'); ?></h2>
        
        <?php if ($b2b_stats['pending_approval'] > 0): ?>
        <div class="pending-approvals-container">
            <!-- Content will be loaded via AJAX -->
            <div class="loading-spinner">
                <span class="dashicons dashicons-update spin"></span>
                <?php _e('Cargando solicitudes pendientes...', 'zoho-sync-customers'); ?>
            </div>
        </div>
        <?php else: ?>
        <div class="no-pending-approvals">
            <p><?php _e('No hay solicitudes pendientes de aprobación en este momento.', 'zoho-sync-customers'); ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- B2B Customers Management -->
    <div class="zoho-b2b-management">
        <h2><?php _e('Gestión de Clientes B2B', 'zoho-sync-customers'); ?></h2>
        
        <!-- Filters -->
        <div class="b2b-filters">
            <div class="filter-group">
                <label for="b2b-filter-status"><?php _e('Estado:', 'zoho-sync-customers'); ?></label>
                <select id="b2b-filter-status">
                    <option value=""><?php _e('Todos', 'zoho-sync-customers'); ?></option>
                    <option value="approved"><?php _e('Aprobados', 'zoho-sync-customers'); ?></option>
                    <option value="pending"><?php _e('Pendientes', 'zoho-sync-customers'); ?></option>
                    <option value="rejected"><?php _e('Rechazados', 'zoho-sync-customers'); ?></option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="b2b-filter-search"><?php _e('Buscar:', 'zoho-sync-customers'); ?></label>
                <input type="text" id="b2b-filter-search" placeholder="<?php _e('Empresa, NIT, contacto...', 'zoho-sync-customers'); ?>">
            </div>
            
            <button type="button" id="apply-b2b-filters" class="button"><?php _e('Aplicar Filtros', 'zoho-sync-customers'); ?></button>
            <button type="button" id="clear-b2b-filters" class="button"><?php _e('Limpiar', 'zoho-sync-customers'); ?></button>
        </div>
        
        <!-- Bulk Actions -->
        <div class="b2b-bulk-actions">
            <select id="b2b-bulk-action">
                <option value=""><?php _e('Acciones en lote', 'zoho-sync-customers'); ?></option>
                <option value="approve"><?php _e('Aprobar seleccionados', 'zoho-sync-customers'); ?></option>
                <option value="reject"><?php _e('Rechazar seleccionados', 'zoho-sync-customers'); ?></option>
                <option value="sync_to_zoho"><?php _e('Sincronizar con Zoho', 'zoho-sync-customers'); ?></option>
                <option value="export"><?php _e('Exportar seleccionados', 'zoho-sync-customers'); ?></option>
            </select>
            
            <button type="button" id="apply-b2b-bulk-action" class="button"><?php _e('Aplicar', 'zoho-sync-customers'); ?></button>
        </div>
        
        <!-- B2B Customers Table -->
        <div class="b2b-table-container">
            <table class="wp-list-table widefat fixed striped" id="b2b-customers-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-b2b">
                        </td>
                        <th class="manage-column column-company sortable">
                            <a href="#" data-sort="company">
                                <span><?php _e('Empresa', 'zoho-sync-customers'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-contact sortable">
                            <a href="#" data-sort="contact">
                                <span><?php _e('Contacto', 'zoho-sync-customers'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-tax-id">
                            <?php _e('NIT/RUT', 'zoho-sync-customers'); ?>
                        </th>
                        <th class="manage-column column-email">
                            <?php _e('Email', 'zoho-sync-customers'); ?>
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
                <tbody id="b2b-customers-table-body">
                    <!-- Table content will be loaded via AJAX -->
                </tbody>
            </table>
            
            <div class="table-pagination">
                <div class="pagination-info">
                    <span id="b2b-pagination-info-text"></span>
                </div>
                <div class="pagination-controls">
                    <button type="button" id="b2b-prev-page" class="button" disabled>
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <?php _e('Anterior', 'zoho-sync-customers'); ?>
                    </button>
                    <span class="page-numbers" id="b2b-page-numbers"></span>
                    <button type="button" id="b2b-next-page" class="button" disabled>
                        <?php _e('Siguiente', 'zoho-sync-customers'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="zoho-b2b-quick-actions">
        <h2><?php _e('Acciones Rápidas', 'zoho-sync-customers'); ?></h2>
        
        <div class="quick-actions-grid">
            <button type="button" id="approve-all-pending" class="quick-action-button">
                <span class="dashicons dashicons-yes-alt"></span>
                <span class="action-title"><?php _e('Aprobar Todos los Pendientes', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Aprueba todas las solicitudes B2B pendientes', 'zoho-sync-customers'); ?></span>
            </button>
            
            <button type="button" id="sync-all-b2b" class="quick-action-button">
                <span class="dashicons dashicons-update"></span>
                <span class="action-title"><?php _e('Sincronizar Todos', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Sincroniza todos los clientes B2B con Zoho CRM', 'zoho-sync-customers'); ?></span>
            </button>
            
            <button type="button" id="export-b2b-list" class="quick-action-button">
                <span class="dashicons dashicons-download"></span>
                <span class="action-title"><?php _e('Exportar Lista B2B', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Descarga la lista completa de clientes B2B', 'zoho-sync-customers'); ?></span>
            </button>
            
            <button type="button" id="send-b2b-notifications" class="quick-action-button">
                <span class="dashicons dashicons-email-alt"></span>
                <span class="action-title"><?php _e('Enviar Notificaciones', 'zoho-sync-customers'); ?></span>
                <span class="action-description"><?php _e('Notifica a clientes sobre cambios de estado', 'zoho-sync-customers'); ?></span>
            </button>
        </div>
    </div>
</div>

<!-- B2B Customer Details Modal -->
<div id="b2b-customer-modal" class="zoho-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Detalles del Cliente B2B', 'zoho-sync-customers'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Content will be loaded via AJAX -->
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-primary" id="approve-b2b-customer"><?php _e('Aprobar', 'zoho-sync-customers'); ?></button>
            <button type="button" class="button button-secondary" id="reject-b2b-customer"><?php _e('Rechazar', 'zoho-sync-customers'); ?></button>
            <button type="button" class="button" id="close-b2b-modal"><?php _e('Cerrar', 'zoho-sync-customers'); ?></button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var currentB2BPage = 1;
    var currentB2BSort = 'company';
    var currentB2BOrder = 'asc';
    
    // Load pending approvals
    loadPendingApprovals();
    
    // Load B2B customers table
    loadB2BCustomersTable();
    
    // Filters
    $('#apply-b2b-filters').on('click', function() {
        currentB2BPage = 1;
        loadB2BCustomersTable();
    });
    
    $('#clear-b2b-filters').on('click', function() {
        $('#b2b-filter-status').val('');
        $('#b2b-filter-search').val('');
        currentB2BPage = 1;
        loadB2BCustomersTable();
    });
    
    // Bulk actions
    $('#apply-b2b-bulk-action').on('click', function() {
        var action = $('#b2b-bulk-action').val();
        var selectedIds = [];
        
        $('#b2b-customers-table input[type="checkbox"]:checked').each(function() {
            if ($(this).val() !== 'on') {
                selectedIds.push($(this).val());
            }
        });
        
        if (selectedIds.length === 0) {
            alert('Por favor selecciona al menos un cliente B2B.');
            return;
        }
        
        if (!action) {
            alert('Por favor selecciona una acción.');
            return;
        }
        
        var confirmMessage = 'Confirmar acción para ' + selectedIds.length + ' cliente(s) B2B?';
        if (!confirm(confirmMessage)) {
            return;
        }
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_bulk_b2b_action',
                bulk_action: action,
                customer_ids: selectedIds,
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadB2BCustomersTable();
                    loadPendingApprovals();
                    alert('Acción completada exitosamente.');
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    // Quick actions
    $('#approve-all-pending').on('click', function() {
        if (!confirm('¿Aprobar todas las solicitudes B2B pendientes?')) {
            return;
        }
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_approve_all_pending_b2b',
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadB2BCustomersTable();
                    loadPendingApprovals();
                    alert('Todas las solicitudes pendientes han sido aprobadas.');
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    // Sorting
    $('#b2b-customers-table .sortable a').on('click', function(e) {
        e.preventDefault();
        var sort = $(this).data('sort');
        
        if (currentB2BSort === sort) {
            currentB2BOrder = currentB2BOrder === 'asc' ? 'desc' : 'asc';
        } else {
            currentB2BSort = sort;
            currentB2BOrder = 'asc';
        }
        
        loadB2BCustomersTable();
    });
    
    // Pagination
    $('#b2b-prev-page').on('click', function() {
        if (currentB2BPage > 1) {
            currentB2BPage--;
            loadB2BCustomersTable();
        }
    });
    
    $('#b2b-next-page').on('click', function() {
        currentB2BPage++;
        loadB2BCustomersTable();
    });
    
    function loadPendingApprovals() {
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_load_pending_b2b_approvals',
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.pending-approvals-container').html(response.data.html);
                }
            }
        });
    }
    
    function loadB2BCustomersTable() {
        var data = {
            action: 'zoho_customers_load_b2b_customers',
            page: currentB2BPage,
            sort: currentB2BSort,
            order: currentB2BOrder,
            status: $('#b2b-filter-status').val(),
            search: $('#b2b-filter-search').val(),
            nonce: zohoCustomersAdmin.nonce
        };
        
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#b2b-customers-table-body').html(response.data.html);
                    updateB2BPagination(response.data.pagination);
                    updateB2BSortingIndicators();
                }
            }
        });
    }
    
    function updateB2BPagination(pagination) {
        $('#b2b-pagination-info-text').text(pagination.info);
        
        $('#b2b-prev-page').prop('disabled', pagination.current_page <= 1);
        $('#b2b-next-page').prop('disabled', pagination.current_page >= pagination.total_pages);
        
        var pageNumbers = '';
        for (var i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
            if (i === pagination.current_page) {
                pageNumbers += '<span class="current-page">' + i + '</span>';
            } else {
                pageNumbers += '<a href="#" class="b2b-page-number" data-page="' + i + '">' + i + '</a>';
            }
        }
        $('#b2b-page-numbers').html(pageNumbers);
    }
    
    function updateB2BSortingIndicators() {
        $('#b2b-customers-table .sorting-indicator').removeClass('asc desc');
        $('#b2b-customers-table [data-sort="' + currentB2BSort + '"] .sorting-indicator').addClass(currentB2BOrder);
    }
    
    // Page number clicks
    $(document).on('click', '.b2b-page-number', function(e) {
        e.preventDefault();
        currentB2BPage = parseInt($(this).data('page'));
        loadB2BCustomersTable();
    });
    
    // Select all checkbox
    $('#select-all-b2b').on('change', function() {
        $('#b2b-customers-table input[type="checkbox"]').prop('checked', $(this).is(':checked'));
    });
    
    // Modal actions
    $(document).on('click', '.view-b2b-customer', function() {
        var customerId = $(this).data('customer-id');
        loadB2BCustomerDetails(customerId);
    });
    
    function loadB2BCustomerDetails(customerId) {
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_load_b2b_customer_details',
                customer_id: customerId,
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#b2b-customer-modal .modal-body').html(response.data.html);
                    $('#b2b-customer-modal').show();
                    
                    // Store customer ID for modal actions
                    $('#b2b-customer-modal').data('customer-id', customerId);
                }
            }
        });
    }
    
    // Modal close
    $('.modal-close, #close-b2b-modal').on('click', function() {
        $('#b2b-customer-modal').hide();
    });
    
    // Modal approve/reject actions
    $('#approve-b2b-customer').on('click', function() {
        var customerId = $('#b2b-customer-modal').data('customer-id');
        updateB2BCustomerStatus(customerId, 'approved');
    });
    
    $('#reject-b2b-customer').on('click', function() {
        var customerId = $('#b2b-customer-modal').data('customer-id');
        updateB2BCustomerStatus(customerId, 'rejected');
    });
    
    function updateB2BCustomerStatus(customerId, status) {
        $.ajax({
            url: zohoCustomersAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zoho_customers_update_b2b_status',
                customer_id: customerId,
                status: status,
                nonce: zohoCustomersAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#b2b-customer-modal').hide();
                    loadB2BCustomersTable();
                    loadPendingApprovals();
                    alert('Estado actualizado exitosamente.');
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    }
});
</script>