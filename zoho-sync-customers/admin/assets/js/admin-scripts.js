/**
 * Zoho Sync Customers - Admin Scripts
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Global variables
    var ZohoCustomersAdmin = {
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.nav-tab', this.handleTabClick);
            
            // Modal events
            $(document).on('click', '.modal-close', this.closeModal);
            $(document).on('click', '.zoho-modal', this.handleModalBackdropClick);
            
            // Table events
            $(document).on('click', '.sortable a', this.handleTableSort);
            $(document).on('change', '#select-all-distributors, #select-all-b2b, #select-all-products', this.handleSelectAll);
            
            // Filter events
            $(document).on('click', '#apply-filters, #apply-b2b-filters, #apply-product-filters', this.handleApplyFilters);
            $(document).on('click', '#clear-filters, #clear-b2b-filters, #clear-product-filters', this.handleClearFilters);
            
            // Bulk action events
            $(document).on('change', '#bulk-action, #b2b-bulk-action, #bulk-pricing-action', this.handleBulkActionChange);
            $(document).on('click', '#apply-bulk-action, #apply-b2b-bulk-action, #apply-bulk-pricing', this.handleBulkAction);
            
            // Quick action events
            $(document).on('click', '.quick-action-button', this.handleQuickAction);
            
            // Sync events
            $(document).on('click', '#start-manual-sync', this.handleManualSync);
            $(document).on('click', '#test-connection, #test-zoho-connection', this.handleTestConnection);
            
            // Level management events
            $(document).on('click', '.edit-level', this.handleEditLevel);
            $(document).on('click', '.save-level', this.handleSaveLevel);
            $(document).on('click', '.cancel-edit', this.handleCancelEdit);
            $(document).on('click', '.delete-level', this.handleDeleteLevel);
            $(document).on('click', '#add-new-level', this.handleAddLevel);
            
            // Custom field events
            $(document).on('click', '#add-custom-field', this.handleAddCustomField);
            $(document).on('click', '.remove-custom-field', this.handleRemoveCustomField);
            
            // Settings events
            $(document).on('click', '#generate-webhook-secret', this.handleGenerateWebhookSecret);
            $(document).on('change', '#sync-enabled', this.handleToggleSync);
            
            // Form validation
            $(document).on('submit', 'form', this.handleFormSubmit);
        },

        initComponents: function() {
            // Initialize tooltips
            this.initTooltips();
            
            // Initialize auto-refresh for dashboard
            this.initAutoRefresh();
            
            // Initialize real-time updates
            this.initRealTimeUpdates();
            
            // Initialize keyboard shortcuts
            this.initKeyboardShortcuts();
        },

        // Tab Navigation
        handleTabClick: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var targetTab = $tab.data('tab');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show target content
            $('.tab-content').removeClass('active').hide();
            $('#' + targetTab + '-tab').addClass('active').fadeIn(300);
            
            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, '#' + targetTab);
            }
        },

        // Modal Management
        closeModal: function(e) {
            e.preventDefault();
            $(this).closest('.zoho-modal').fadeOut(300);
        },

        handleModalBackdropClick: function(e) {
            if (e.target === this) {
                $(this).fadeOut(300);
            }
        },

        showModal: function(modalId, data) {
            var $modal = $('#' + modalId);
            if (data) {
                $modal.find('.modal-body').html(data);
            }
            $modal.fadeIn(300);
        },

        // Table Management
        handleTableSort: function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var sortField = $link.data('sort');
            var $table = $link.closest('table');
            var currentSort = $table.data('current-sort');
            var currentOrder = $table.data('current-order') || 'asc';
            
            // Determine new order
            var newOrder = 'asc';
            if (currentSort === sortField && currentOrder === 'asc') {
                newOrder = 'desc';
            }
            
            // Update table data
            $table.data('current-sort', sortField);
            $table.data('current-order', newOrder);
            
            // Update sorting indicators
            $table.find('.sorting-indicator').removeClass('asc desc');
            $link.find('.sorting-indicator').addClass(newOrder);
            
            // Reload table data
            ZohoCustomersAdmin.reloadTableData($table);
        },

        handleSelectAll: function() {
            var isChecked = $(this).is(':checked');
            var $table = $(this).closest('table');
            $table.find('tbody input[type="checkbox"]').prop('checked', isChecked);
        },

        reloadTableData: function($table) {
            var tableType = $table.attr('id');
            var data = this.getTableFilters($table);
            
            // Show loading state
            this.showTableLoading($table);
            
            // Make AJAX request
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: $.extend(data, {
                    action: 'zoho_customers_load_table_data',
                    table_type: tableType,
                    nonce: zohoCustomersAdmin.nonce
                }),
                success: function(response) {
                    if (response.success) {
                        $table.find('tbody').html(response.data.html);
                        ZohoCustomersAdmin.updatePagination($table, response.data.pagination);
                    } else {
                        ZohoCustomersAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    ZohoCustomersAdmin.showNotice('error', 'Error loading table data');
                },
                complete: function() {
                    ZohoCustomersAdmin.hideTableLoading($table);
                }
            });
        },

        getTableFilters: function($table) {
            var $container = $table.closest('.wrap');
            var filters = {};
            
            // Get filter values
            $container.find('.filter-group input, .filter-group select').each(function() {
                var $input = $(this);
                var name = $input.attr('name') || $input.attr('id');
                if (name && $input.val()) {
                    filters[name] = $input.val();
                }
            });
            
            // Get sort and pagination
            filters.sort = $table.data('current-sort') || 'name';
            filters.order = $table.data('current-order') || 'asc';
            filters.page = $table.data('current-page') || 1;
            
            return filters;
        },

        showTableLoading: function($table) {
            var $tbody = $table.find('tbody');
            var colCount = $table.find('thead th').length;
            
            $tbody.html('<tr><td colspan="' + colCount + '" class="text-center"><div class="loading-spinner"><span class="dashicons dashicons-update spin"></span> Cargando...</div></td></tr>');
        },

        hideTableLoading: function($table) {
            // Loading will be hidden when new content is loaded
        },

        updatePagination: function($table, pagination) {
            var $container = $table.closest('.wrap');
            var $paginationInfo = $container.find('.pagination-info span');
            var $pageNumbers = $container.find('.page-numbers');
            var $prevBtn = $container.find('#prev-page, #b2b-prev-page, #products-prev-page');
            var $nextBtn = $container.find('#next-page, #b2b-next-page, #products-next-page');
            
            // Update info
            $paginationInfo.text(pagination.info);
            
            // Update buttons
            $prevBtn.prop('disabled', pagination.current_page <= 1);
            $nextBtn.prop('disabled', pagination.current_page >= pagination.total_pages);
            
            // Update page numbers
            var pageNumbers = '';
            var start = Math.max(1, pagination.current_page - 2);
            var end = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            for (var i = start; i <= end; i++) {
                if (i === pagination.current_page) {
                    pageNumbers += '<span class="current-page">' + i + '</span>';
                } else {
                    pageNumbers += '<a href="#" class="page-number" data-page="' + i + '">' + i + '</a>';
                }
            }
            
            $pageNumbers.html(pageNumbers);
            $table.data('current-page', pagination.current_page);
        },

        // Filter Management
        handleApplyFilters: function(e) {
            e.preventDefault();
            var $table = $(this).closest('.wrap').find('table');
            $table.data('current-page', 1);
            ZohoCustomersAdmin.reloadTableData($table);
        },

        handleClearFilters: function(e) {
            e.preventDefault();
            var $container = $(this).closest('.wrap');
            
            // Clear all filter inputs
            $container.find('.filter-group input, .filter-group select').val('');
            
            // Reload table
            var $table = $container.find('table');
            $table.data('current-page', 1);
            ZohoCustomersAdmin.reloadTableData($table);
        },

        // Bulk Actions
        handleBulkActionChange: function() {
            var $select = $(this);
            var action = $select.val();
            var $container = $select.closest('.bulk-actions, .distributor-bulk-actions, .b2b-bulk-actions, .bulk-pricing-actions');
            
            // Show/hide additional options based on action
            $container.find('.bulk-option').hide();
            if (action === 'change_level') {
                $container.find('#level-selector').show();
            } else if (action === 'apply_discount') {
                $container.find('#discount-settings').show();
            }
        },

        handleBulkAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $container = $button.closest('.wrap');
            var action = $container.find('select[id*="bulk-action"]').val();
            
            if (!action) {
                ZohoCustomersAdmin.showNotice('error', 'Por favor selecciona una acción.');
                return;
            }
            
            // Get selected items
            var selectedIds = [];
            $container.find('table tbody input[type="checkbox"]:checked').each(function() {
                if ($(this).val() !== 'on') {
                    selectedIds.push($(this).val());
                }
            });
            
            if (selectedIds.length === 0) {
                ZohoCustomersAdmin.showNotice('error', 'Por favor selecciona al menos un elemento.');
                return;
            }
            
            // Confirm action
            var confirmMessage = '¿Confirmar acción para ' + selectedIds.length + ' elemento(s)?';
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Prepare data
            var data = {
                action: 'zoho_customers_bulk_action',
                bulk_action: action,
                selected_ids: selectedIds,
                nonce: zohoCustomersAdmin.nonce
            };
            
            // Add additional data based on action
            if (action === 'change_level') {
                data.new_level = $container.find('#new-level').val();
            } else if (action === 'apply_discount') {
                data.discount_percentage = $container.find('#bulk-discount-percentage').val();
                data.discount_level = $container.find('#bulk-discount-level').val();
            }
            
            // Execute bulk action
            ZohoCustomersAdmin.executeBulkAction(data, $container);
        },

        executeBulkAction: function(data, $container) {
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                beforeSend: function() {
                    ZohoCustomersAdmin.showLoading();
                },
                success: function(response) {
                    if (response.success) {
                        ZohoCustomersAdmin.showNotice('success', 'Acción completada exitosamente.');
                        ZohoCustomersAdmin.reloadTableData($container.find('table'));
                    } else {
                        ZohoCustomersAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    ZohoCustomersAdmin.showNotice('error', 'Error ejecutando la acción.');
                },
                complete: function() {
                    ZohoCustomersAdmin.hideLoading();
                }
            });
        },

        // Quick Actions
        handleQuickAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var actionId = $button.attr('id');
            
            // Handle different quick actions
            switch (actionId) {
                case 'sync-all-distributors':
                case 'sync-all-b2b':
                case 'sync-all-pricing':
                    ZohoCustomersAdmin.handleSyncAll($button);
                    break;
                    
                case 'approve-pending':
                case 'approve-all-pending':
                    ZohoCustomersAdmin.handleApproveAll($button);
                    break;
                    
                case 'export-distributors':
                case 'export-b2b-list':
                case 'export-pricing-report':
                    ZohoCustomersAdmin.handleExport($button);
                    break;
                    
                case 'recalculate-all-prices':
                    ZohoCustomersAdmin.handleRecalculatePrices($button);
                    break;
                    
                default:
                    console.log('Unknown quick action:', actionId);
            }
        },

        handleSyncAll: function($button) {
            if (!confirm('¿Sincronizar todos los elementos con Zoho CRM?')) {
                return;
            }
            
            var actionType = $button.attr('id').replace('sync-all-', '');
            
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_sync_all',
                    sync_type: actionType,
                    nonce: zohoCustomersAdmin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true);
                    ZohoCustomersAdmin.showLoading();
                },
                success: function(response) {
                    if (response.success) {
                        ZohoCustomersAdmin.showNotice('success', 'Sincronización completada exitosamente.');
                        location.reload();
                    } else {
                        ZohoCustomersAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    ZohoCustomersAdmin.showNotice('error', 'Error en la sincronización.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    ZohoCustomersAdmin.hideLoading();
                }
            });
        },

        handleApproveAll: function($button) {
            if (!confirm('¿Aprobar todos los elementos pendientes?')) {
                return;
            }
            
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_approve_all_pending',
                    nonce: zohoCustomersAdmin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true);
                    ZohoCustomersAdmin.showLoading();
                },
                success: function(response) {
                    if (response.success) {
                        ZohoCustomersAdmin.showNotice('success', 'Todos los elementos pendientes han sido aprobados.');
                        location.reload();
                    } else {
                        ZohoCustomersAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    ZohoCustomersAdmin.showNotice('error', 'Error aprobando elementos.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    ZohoCustomersAdmin.hideLoading();
                }
            });
        },

        handleExport: function($button) {
            var exportType = $button.attr('id').replace('export-', '').replace('-report', '');
            
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_export_data',
                    export_type: exportType,
                    format: 'csv',
                    nonce: zohoCustomersAdmin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true);
                    ZohoCustomersAdmin.showLoading();
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link
                        var link = document.createElement('a');
                        link.href = response.data.download_url;
                        link.download = response.data.filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        ZohoCustomersAdmin.showNotice('success', 'Archivo exportado exitosamente.');
                    } else {
                        ZohoCustomersAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    ZohoCustomersAdmin.showNotice('error', 'Error exportando datos.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    ZohoCustomersAdmin.hideLoading();
                }
            });
        },

        handleRecalculatePrices: function($button) {
            if (!confirm('¿Recalcular todos los precios especiales?')) {
                return;
            }
            
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_recalculate_prices',
                    nonce: zohoCustomersAdmin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true);
                    ZohoCustomersAdmin.showLoading();
                },
                success: function(response) {
                    if (response.success) {
                        ZohoCustomersAdmin.showNotice('success', 'Precios recalculados exitosamente.');
                        location.reload();
                    } else {
                        ZohoCustomersAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    ZohoCustomersAdmin.showNotice('error', 'Error recalculando precios.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    ZohoCustomersAdmin.hideLoading();
                }
            });
        },

        // Sync Management
        handleManualSync: function(e) {
            e.preventDefault();
            
            if (!confirm(zohoCustomersAdmin.strings.confirmSync)) {
                return;
            }
            
            var $button = $(this);
            var $container = $button.closest('.wrap');
            var direction = $container.find('input[name="sync_direction"]:checked').val();
            var filters = [];
            
            $container.find('input[name="sync_filters[]"]:checked').each(function() {
                filters.push($(this).val());
            });
            
            ZohoCustomersAdmin.startManualSync(direction, filters, $button);
        },

        startManualSync: function(direction, filters, $button) {
            var $progress = $('#sync-progress');
            
            $button.prop('disabled', true);
            $progress.show();
            
            // Reset progress
            $progress.find('.progress-fill').css('width', '0%');
            $progress.find('.progress-text').text('Iniciando sincronización...');
            $progress.find('.progress-percentage').text('0%');
            $progress.find('#sync-log').empty();
            
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_manual_sync',
                    direction: direction,
                    filters: filters,
                    nonce: zohoCustomersAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        ZohoCustomersAdmin.pollSyncProgress($button);
                    } else {
                        ZohoCustomersAdmin.addLogEntry('error', response.data.message || 'Error iniciando sincronización');
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    ZohoCustomersAdmin.addLogEntry('error', 'Error de comunicación con el servidor');
                    $button.prop('disabled', false);
                }
            });
        },

        pollSyncProgress: function($button) {
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_sync_progress',
                    nonce: zohoCustomersAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var progress = response.data;
                        ZohoCustomersAdmin.updateSyncProgress(progress);
                        
                        if (progress.status === 'completed' || progress.status === 'error') {
                            $button.prop('disabled', false);
                            if (progress.status === 'completed') {
                                ZohoCustomersAdmin.addLogEntry('success', 'Sincronización completada exitosamente');
                            }
                        } else {
                            setTimeout(function() {
                                ZohoCustomersAdmin.pollSyncProgress($button);
                            }, 2000);
                        }
                    }
                },
                error: function() {
                    setTimeout(function() {
                        ZohoCustomersAdmin.pollSyncProgress($button);
                    }, 5000);
                }
            });
        },

        updateSyncProgress: function(progress) {
            var percentage = Math.round((progress.processed / progress.total) * 100) || 0;
            
            $('#sync-progress .progress-fill').css('width', percentage + '%');
            $('#sync-progress .progress-percentage').text(percentage + '%');
            $('#sync-progress .progress-text').text(progress.message || 'Procesando...');
            
            $('#processed-count').text(progress.processed || 0);
            $('#total-count').text(progress.total || 0);
            $('#error-count').text(progress.errors || 0);
            
            if (progress.log_entries) {
                progress.log_entries.forEach(function(entry) {
                    ZohoCustomersAdmin.addLogEntry(entry.level, entry.message);
                });
            }
        },

        addLogEntry: function(level, message) {
            var $log = $('#sync-log');
            var timestamp = new Date().toLocaleTimeString();
            var levelClass = level === 'error' ? 'error' : (level === 'success' ? 'success' : 'info');
            
            var $entry = $('<div class="log-entry ' + levelClass + '">' +
                '<span class="log-time">' + timestamp + '</span>' +
                '<span class="log-message">' + message + '</span>' +
                '</div>');
            
            $log.append($entry);
            $log.scrollTop($log[0].scrollHeight);
        },

        handleTestConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#connection-test-result');
            
            $button.prop('disabled', true).find('.dashicons').addClass('spin');
            $result.hide();
            
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_test_connection',
                    nonce: zohoCustomersAdmin.nonce
                },
                success: function(response) {
                    var resultClass = response.success ? 'notice-success' : 'notice-error';
                    var resultTitle = response.success ? zohoCustomersAdmin.strings.connectionSuccess : zohoCustomersAdmin.strings.connectionError;
                    
                    $result.find('.result-content').html(
                        '<div class="notice ' + resultClass + ' inline"><p><strong>' + 
                        resultTitle + '</strong><br>' + response.data.message + '</p></div>'
                    );
                    $result.show();
                },
                error: function() {
                    $result.find('.result-content').html(
                        '<div class="notice notice-error inline"><p><strong>' + 
                        zohoCustomersAdmin.strings.connectionError + 
                        '</strong><br>Error de comunicación con el servidor.</p></div>'
                    );
                    $result.show();
                },
                complete: function() {
                    $button.prop('disabled', false).find('.dashicons').removeClass('spin');
                }
            });
        },

        // Level Management
        handleEditLevel: function(e) {
            e.preventDefault();
            
            var $card = $(this).closest('.level-card');
            $card.find('.level-info').hide();
            $card.find('.level-edit-form').show();
            $(this).hide();
        },

        handleSaveLevel: function(e) {
            e.preventDefault();
            
            var $card = $(this).closest('.level-card');
            var $form = $card.find('.level-edit-form');
            var levelKey = $card.data('level');
            
            var data = {
                action: 'zoho_customers_save_distributor_level',
                level_key: levelKey,
                nonce: zohoCustomersAdmin.nonce
            };
            
            // Collect form data
            $form.find('input, textarea, select').each(function() {
                var $input = $(this);
                data[$input.attr('name')] = $input.val();
            });
            
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        ZohoCustomersAdmin.showNotice('success', 'Nivel guardado exitosamente.');
                        location.reload();
                    } else {
                        ZohoCustomersAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    ZohoCustomersAdmin.showNotice('error', 'Error guardando nivel.');
                }
            });
        },

        handleCancelEdit: function(e) {
            e.preventDefault();
            
            var $card = $(this).closest('.level-card');
            $card.find('.level-edit-form').hide();
            $card.find('.level-info').show();
            $card.find('.edit-level').show();
        },

        handleDeleteLevel: function(e) {
            e.preventDefault();
            
            if (!confirm('¿Estás seguro de que quieres eliminar este nivel?')) {
                return;
            }
            
            var $card = $(this).closest('.level-card');
            var levelKey = $card.data('level');
            
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_delete_distributor_level',
                    level_key: levelKey,
                    nonce: zohoCustomersAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $card.fadeOut(300, function() {
                            $(this).remove();
                        });
                        ZohoCustomersAdmin.showNotice('success', 'Nivel eliminado exitosamente.');
                    } else {
                        ZohoCustomersAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    ZohoCustomersAdmin.showNotice('error', 'Error eliminando nivel.');
                }
            });
        },

        handleAddLevel: function(e) {
            e.preventDefault();
            
            var $container = $('.levels-container');
            var newIndex = $container.find('.level-card').length + 1;
            var levelKey = 'level_' + newIndex;
            
            var newLevelHtml = '<div class="level-card" data-level="' + levelKey + '">' +
                '<div class="level-header">' +
                    '<h3>Nuevo Nivel</h3>' +
                    '<div class="level-actions">' +
                        '<button type="button" class="button button-small edit-level">Editar</button>' +
                        '<button type="button" class="button button-small button-link-delete delete-level">Eliminar</button>' +
                    '</div>' +
                '</div>' +
                '<div class="level-content">' +
                    '<div class="level-info">' +
                        '<div class="info-item">' +
                            '<span class="info-label">Descuento:</span>' +
                            '<span class="info-value">0%</span>' +
                        '</div>' +
                        '<div class="info-item">' +
                            '<span class="info-label">Mínimo de Compra:</span>' +
                            '<span class="info-value">$0</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="level-edit-form" style="display: block;">' +
                        '<table class="form-table">' +
                            '<tr>' +
                                '<th>Nombre del Nivel</th>' +
                                '<td><input type="text" name="name" value="Nuevo Nivel" class="regular-text" required></td>' +
                            '</tr>' +
                            '<tr>' +
                                '<th>Porcentaje de Descuento</th>' +
                                '<td><input type="number" name="discount" value="0" min="0" max="100" step="0.01" class="small-text" required> %</td>' +
                            '</tr>' +
                            '<tr>' +
                                '<th>Compra Mínima Requerida</th>' +
                                '<td><input type="number" name="min_purchase" value="0" min="0" step="0.01" class="regular-text" required></td>' +
                            '</tr>' +
                            '<tr>' +
                                '<th>Descripción</th>' +
                                '<td><textarea name="description" class="large-text" rows="3"></textarea></td>' +
                            '</tr>' +
                        '</table>' +
                        '<div class="level-form-actions">' +
                            '<button type="button" class="button button-primary save-level">Guardar</button>' +
                            '<button type="button" class="button cancel-edit">Cancelar</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            $container.append(newLevelHtml);
        },

        // Custom Fields Management
        handleAddCustomField: function(e) {
            e.preventDefault();
            
            var $container = $('#custom-fields-container');
            var index = $container.find('.custom-field-row').length;
            
            var newFieldHtml = '<div class="custom-field-row">' +
                '<input type="text" name="zoho_customers_custom_fields[' + index + '][wc_field]" placeholder="Campo WooCommerce">' +
                '<input type="text" name="zoho_customers_custom_fields[' + index + '][zoho_field]" placeholder="Campo Zoho">' +
                '<select name="zoho_customers_custom_fields[' + index + '][direction]">' +
                    '<option value="both">Bidireccional</option>' +
                    '<option value="from_zoho">Solo desde Zoho</option>' +
                    '<option value="to_zoho">Solo hacia Zoho</option>' +
                '</select>' +
                '<button type="button" class="button remove-custom-field">Eliminar</button>' +
                '</div>';
            
            $container.append(newFieldHtml);
        },

        handleRemoveCustomField: function(e) {
            e.preventDefault();
            $(this).closest('.custom-field-row').fadeOut(300, function() {
                $(this).remove();
            });
        },

        // Settings Management
        handleGenerateWebhookSecret: function(e) {
            e.preventDefault();
            
            var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            var secret = '';
            for (var i = 0; i < 32; i++) {
                secret += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            $('input[name="zoho_customers_webhook_secret"]').val(secret);
            ZohoCustomersAdmin.showNotice('success', 'Nueva clave secreta generada.');
        },

        handleToggleSync: function() {
            var enabled = $(this).is(':checked');
            var $label = $(this).siblings('.toggle-label');
            
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_toggle_sync',
                    enabled: enabled ? 'yes' : 'no',
                    nonce: zohoCustomersAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $label.text(enabled ? 'Habilitada' : 'Deshabilitada');
                        ZohoCustomersAdmin.showNotice('success', 'Configuración actualizada.');
                    } else {
                        // Revert toggle if failed
                        $('#sync-enabled').prop('checked', !enabled);
                        ZohoCustomersAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    $('#sync-enabled').prop('checked', !enabled);
                    ZohoCustomersAdmin.showNotice('error', 'Error actualizando configuración.');
                }
            });
        },

        // Form Validation
        handleFormSubmit: function(e) {
            var $form = $(this);
            var isValid = true;
            var errorMessages = [];
            
            // Validate batch size
            var $batchSize = $form.find('input[name="zoho_customers_batch_size"]');
            if ($batchSize.length) {
                var batchSize = parseInt($batchSize.val());
                if (batchSize < 10 || batchSize > 500) {
                    isValid = false;
                    errorMessages.push('El tamaño del lote debe estar entre 10 y 500.');
                }
            }
            
            // Validate timeout
            var $timeout = $form.find('input[name="zoho_customers_sync_timeout"]');
            if ($timeout.length) {
                var timeout = parseInt($timeout.val());
                if (timeout < 60 || timeout > 3600) {
                    isValid = false;
                    errorMessages.push('El timeout debe estar entre 60 y 3600 segundos.');
                }
            }
            
            // Validate rate limit
            var $rateLimit = $form.find('input[name="zoho_customers_rate_limit"]');
            if ($rateLimit.length) {
                var rateLimit = parseInt($rateLimit.val());
                if (rateLimit < 10 || rateLimit > 1000) {
                    isValid = false;
                    errorMessages.push('El límite de rate limiting debe estar entre 10 y 1000.');
                }
            }
            
            // Validate discount percentages
            $form.find('input[type="number"][name*="discount"]').each(function() {
                var discount = parseFloat($(this).val());
                if (discount < 0 || discount > 100) {
                    isValid = false;
                    errorMessages.push('Los descuentos deben estar entre 0% y 100%.');
                }
            });
            
            // Validate required fields
            $form.find('input[required], select[required], textarea[required]').each(function() {
                if (!$(this).val().trim()) {
                    isValid = false;
                    var label = $(this).closest('tr').find('th').text() || $(this).attr('placeholder') || 'Campo requerido';
                    errorMessages.push(label + ' es obligatorio.');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                ZohoCustomersAdmin.showNotice('error', 'Errores de validación:\n' + errorMessages.join('\n'));
                return false;
            }
            
            return true;
        },

        // Utility Functions
        initTooltips: function() {
            // Initialize tooltips for elements with title attributes
            $('[title]').each(function() {
                var $element = $(this);
                var title = $element.attr('title');
                
                $element.hover(
                    function() {
                        var tooltip = $('<div class="zoho-tooltip">' + title + '</div>');
                        $('body').append(tooltip);
                        
                        var offset = $element.offset();
                        tooltip.css({
                            top: offset.top - tooltip.outerHeight() - 5,
                            left: offset.left + ($element.outerWidth() / 2) - (tooltip.outerWidth() / 2)
                        });
                    },
                    function() {
                        $('.zoho-tooltip').remove();
                    }
                );
            });
        },

        initAutoRefresh: function() {
            // Auto-refresh dashboard stats every 5 minutes
            if ($('.zoho-customers-stats-grid').length) {
                setInterval(function() {
                    ZohoCustomersAdmin.refreshDashboardStats();
                }, 300000); // 5 minutes
            }
        },

        refreshDashboardStats: function() {
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_refresh_stats',
                    nonce: zohoCustomersAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update stats cards
                        $('.stats-card').each(function(index) {
                            if (response.data.stats[index]) {
                                $(this).find('h3').text(response.data.stats[index].value);
                            }
                        });
                    }
                }
            });
        },

        initRealTimeUpdates: function() {
            // Check for real-time updates every 30 seconds
            if (typeof(EventSource) !== "undefined") {
                // Use Server-Sent Events if available
                var eventSource = new EventSource(zohoCustomersAdmin.ajaxUrl + '?action=zoho_customers_sse&nonce=' + zohoCustomersAdmin.nonce);
                
                eventSource.onmessage = function(event) {
                    var data = JSON.parse(event.data);
                    ZohoCustomersAdmin.handleRealTimeUpdate(data);
                };
            } else {
                // Fallback to polling
                setInterval(function() {
                    ZohoCustomersAdmin.checkForUpdates();
                }, 30000);
            }
        },

        handleRealTimeUpdate: function(data) {
            switch (data.type) {
                case 'sync_progress':
                    if ($('#sync-progress').is(':visible')) {
                        ZohoCustomersAdmin.updateSyncProgress(data.progress);
                    }
                    break;
                    
                case 'new_approval':
                    ZohoCustomersAdmin.showNotice('info', 'Nueva solicitud de aprobación recibida.');
                    ZohoCustomersAdmin.updateApprovalCounts();
                    break;
                    
                case 'sync_complete':
                    ZohoCustomersAdmin.showNotice('success', 'Sincronización completada.');
                    ZohoCustomersAdmin.refreshDashboardStats();
                    break;
            }
        },

        checkForUpdates: function() {
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_check_updates',
                    nonce: zohoCustomersAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.updates) {
                        response.data.updates.forEach(function(update) {
                            ZohoCustomersAdmin.handleRealTimeUpdate(update);
                        });
                    }
                }
            });
        },

        updateApprovalCounts: function() {
            $.ajax({
                url: zohoCustomersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_customers_get_approval_counts',
                    nonce: zohoCustomersAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update pending counts in stats cards
                        $('.stat-card.pending h3').text(response.data.pending_count);
                    }
                }
            });
        },

        initKeyboardShortcuts: function() {
            $(document).keydown(function(e) {
                // Ctrl/Cmd + S to save forms
                if ((e.ctrlKey || e.metaKey) && e.which === 83) {
                    e.preventDefault();
                    var $form = $('form:visible').first();
                    if ($form.length) {
                        $form.find('input[type="submit"], button[type="submit"]').first().click();
                    }
                }
                
                // Escape to close modals
                if (e.which === 27) {
                    $('.zoho-modal:visible').fadeOut(300);
                }
                
                // Ctrl/Cmd + R to refresh tables
                if ((e.ctrlKey || e.metaKey) && e.which === 82) {
                    e.preventDefault();
                    var $table = $('table.wp-list-table:visible').first();
                    if ($table.length) {
                        ZohoCustomersAdmin.reloadTableData($table);
                    }
                }
            });
        },

        showNotice: function(type, message) {
            var noticeClass = 'notice-' + type;
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Add dismiss button functionality
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        showLoading: function() {
            if ($('#zoho-loading-overlay').length === 0) {
                var $overlay = $('<div id="zoho-loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; display: flex; align-items: center; justify-content: center;">' +
                    '<div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">' +
                        '<div class="loading-spinner"><span class="dashicons dashicons-update spin"></span> Procesando...</div>' +
                    '</div>' +
                '</div>');
                $('body').append($overlay);
            }
        },

        hideLoading: function() {
            $('#zoho-loading-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ZohoCustomersAdmin.init();
        
        // Handle URL hash on page load
        if (window.location.hash) {
            var hash = window.location.hash.substring(1);
            var $tab = $('.nav-tab[data-tab="' + hash + '"]');
            if ($tab.length) {
                $tab.click();
            }
        }
    });

})(jQuery);