/**
 * Zoho Sync Core Admin JavaScript
 * 
 * Handles all admin panel functionality including dashboard interactions,
 * settings management, and AJAX operations.
 * 
 * @package ZohoSyncCore
 * @subpackage Admin/Assets/JS
 * @since 8.0.0
 */

(function($) {
    'use strict';

    /**
     * Main Admin Object
     */
    window.ZohoSyncAdmin = {
        
        // Configuration
        config: {
            ajaxUrl: zohoSyncCore.ajax_url || '',
            nonces: zohoSyncCore.nonces || {},
            strings: zohoSyncCore.strings || {},
            refreshInterval: 30000, // 30 seconds
            chartColors: {
                primary: '#0073aa',
                success: '#46b450',
                warning: '#ffb900',
                error: '#dc3232',
                info: '#00a0d2'
            }
        },

        // State management
        state: {
            isLoading: false,
            currentTab: 'dashboard',
            charts: {},
            intervals: {}
        },

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initDashboard();
            this.initSettings();
            this.initCharts();
            this.startAutoRefresh();
            
            console.log('Zoho Sync Core Admin initialized');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Dashboard events
            $(document).on('click', '.zoho-sync-refresh-btn', this.refreshDashboard.bind(this));
            $(document).on('click', '.zoho-sync-quick-action', this.handleQuickAction.bind(this));
            $(document).on('click', '.zoho-sync-module-toggle', this.toggleModule.bind(this));

            // Settings events
            $(document).on('click', '.zoho-sync-nav-tab', this.switchTab.bind(this));
            $(document).on('click', '#test-connection', this.testConnection.bind(this));
            $(document).on('click', '#clear-logs', this.clearLogs.bind(this));
            $(document).on('click', '#export-logs', this.exportLogs.bind(this));
            $(document).on('click', '#force-sync', this.forceSync.bind(this));
            $(document).on('click', '#clear-queue', this.clearQueue.bind(this));
            $(document).on('click', '#reset-sync', this.resetSync.bind(this));
            $(document).on('click', '#reset-settings', this.resetSettings.bind(this));
            $(document).on('click', '#reset-form', this.resetForm.bind(this));

            // Form events
            $(document).on('submit', '.zoho-sync-settings-form', this.handleSettingsSubmit.bind(this));
            $(document).on('change', '.zoho-sync-field-input, .zoho-sync-field-select', this.handleFieldChange.bind(this));

            // Utility events
            $(document).on('click', '.toggle-password', this.togglePassword.bind(this));
            $(document).on('click', '.zoho-sync-dismiss-notice', this.dismissNotice.bind(this));

            // Window events
            $(window).on('beforeunload', function() {
                if (self.state.isLoading) {
                    return self.config.strings.unsaved_changes || 'You have unsaved changes.';
                }
            });
        },

        /**
         * Initialize dashboard functionality
         */
        initDashboard: function() {
            this.loadDashboardData();
            this.initStatusIndicators();
            this.initActivityFeed();
        },

        /**
         * Initialize settings functionality
         */
        initSettings: function() {
            this.initFormValidation();
            this.initConditionalFields();
            this.restoreFormState();
        },

        /**
         * Initialize charts
         */
        initCharts: function() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded, skipping chart initialization');
                return;
            }

            this.initSyncChart();
            this.initPerformanceChart();
        },

        /**
         * Load dashboard data
         */
        loadDashboardData: function() {
            var self = this;
            
            this.showLoading('dashboard');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_sync_get_dashboard_data',
                    nonce: this.config.nonces.dashboard || ''
                },
                success: function(response) {
                    if (response.success) {
                        self.updateDashboard(response.data);
                    } else {
                        self.showError(response.data.message || 'Error loading dashboard data');
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error: ' + error);
                },
                complete: function() {
                    self.hideLoading('dashboard');
                }
            });
        },

        /**
         * Update dashboard with new data
         */
        updateDashboard: function(data) {
            // Update system status
            if (data.system_status) {
                this.updateSystemStatus(data.system_status);
            }

            // Update statistics
            if (data.statistics) {
                this.updateStatistics(data.statistics);
            }

            // Update recent activity
            if (data.recent_activity) {
                this.updateRecentActivity(data.recent_activity);
            }

            // Update modules status
            if (data.modules_status) {
                this.updateModulesStatus(data.modules_status);
            }

            // Update charts
            if (data.chart_data) {
                this.updateCharts(data.chart_data);
            }
        },

        /**
         * Update system status indicators
         */
        updateSystemStatus: function(status) {
            var $statusContainer = $('.zoho-sync-system-status');
            
            $statusContainer.find('.status-indicator')
                .removeClass('status-connected status-disconnected status-warning')
                .addClass('status-' + status.connection_status);
            
            $statusContainer.find('.status-text').text(status.connection_message);
            $statusContainer.find('.last-sync').text(status.last_sync);
        },

        /**
         * Update statistics widgets
         */
        updateStatistics: function(stats) {
            $.each(stats, function(key, value) {
                var $stat = $('.zoho-sync-stat[data-stat="' + key + '"]');
                if ($stat.length) {
                    $stat.find('.stat-value').text(value.value);
                    $stat.find('.stat-change').text(value.change);
                    $stat.removeClass('positive negative').addClass(value.trend);
                }
            });
        },

        /**
         * Update recent activity feed
         */
        updateRecentActivity: function(activities) {
            var $activityList = $('.zoho-sync-activity-list');
            $activityList.empty();

            if (activities.length === 0) {
                $activityList.append('<li class="no-activity">' + (this.config.strings.no_activity || 'No recent activity') + '</li>');
                return;
            }

            $.each(activities, function(index, activity) {
                var $item = $('<li class="activity-item">');
                $item.append('<span class="activity-icon dashicons dashicons-' + activity.icon + '"></span>');
                $item.append('<div class="activity-content">');
                $item.find('.activity-content').append('<div class="activity-message">' + activity.message + '</div>');
                $item.find('.activity-content').append('<div class="activity-time">' + activity.time + '</div>');
                $activityList.append($item);
            });
        },

        /**
         * Update modules status
         */
        updateModulesStatus: function(modules) {
            $.each(modules, function(moduleId, moduleData) {
                var $module = $('.zoho-sync-module[data-module="' + moduleId + '"]');
                if ($module.length) {
                    $module.find('.module-status')
                        .removeClass('active inactive error')
                        .addClass(moduleData.status);
                    $module.find('.module-status-text').text(moduleData.status_text);
                    $module.find('.module-last-sync').text(moduleData.last_sync);
                }
            });
        },

        /**
         * Refresh dashboard
         */
        refreshDashboard: function(e) {
            e.preventDefault();
            this.loadDashboardData();
            this.showNotice('Dashboard refreshed', 'success');
        },

        /**
         * Handle quick actions
         */
        handleQuickAction: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var action = $btn.data('action');
            
            if (!action) return;

            var self = this;
            
            $btn.prop('disabled', true).addClass('loading');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_sync_quick_action',
                    quick_action: action,
                    nonce: this.config.nonces.quick_action || ''
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        if (response.data.refresh) {
                            self.loadDashboardData();
                        }
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Switch settings tab
         */
        switchTab: function(e) {
            e.preventDefault();
            
            var $tab = $(e.currentTarget);
            var tabId = $tab.data('tab');
            
            // Update active tab
            $('.zoho-sync-nav-tab').removeClass('active');
            $tab.addClass('active');
            
            // Show corresponding content
            $('.zoho-sync-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');
            
            // Update state
            this.state.currentTab = tabId;
            
            // Save tab state
            localStorage.setItem('zoho_sync_active_tab', tabId);
        },

        /**
         * Test API connection
         */
        testConnection: function(e) {
            e.preventDefault();
            
            var self = this;
            var $btn = $(e.currentTarget);
            
            $btn.prop('disabled', true).addClass('loading');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_sync_test_connection',
                    nonce: this.config.nonces.test_connection || ''
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        $('.zoho-sync-connection-status').html(response.data.status_html);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Clear logs
         */
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm(this.config.strings.confirm_clear_logs || 'Are you sure you want to clear all logs?')) {
                return;
            }
            
            var self = this;
            var $btn = $(e.currentTarget);
            
            $btn.prop('disabled', true).addClass('loading');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_sync_clear_logs',
                    nonce: this.config.nonces.clear_logs || ''
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Export logs
         */
        exportLogs: function(e) {
            e.preventDefault();
            
            var exportUrl = this.config.ajaxUrl + '?action=zoho_sync_export_logs&nonce=' + (this.config.nonces.export_logs || '');
            window.open(exportUrl, '_blank');
        },

        /**
         * Force synchronization
         */
        forceSync: function(e) {
            e.preventDefault();
            
            var self = this;
            var $btn = $(e.currentTarget);
            
            $btn.prop('disabled', true).addClass('loading');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_sync_force_sync',
                    nonce: this.config.nonces.force_sync || ''
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        if (response.data.refresh) {
                            self.loadDashboardData();
                        }
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Clear sync queue
         */
        clearQueue: function(e) {
            e.preventDefault();
            
            if (!confirm(this.config.strings.confirm_clear_queue || 'Are you sure you want to clear the sync queue?')) {
                return;
            }
            
            var self = this;
            var $btn = $(e.currentTarget);
            
            $btn.prop('disabled', true).addClass('loading');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_sync_clear_queue',
                    nonce: this.config.nonces.clear_queue || ''
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Reset synchronization
         */
        resetSync: function(e) {
            e.preventDefault();
            
            if (!confirm(this.config.strings.confirm_reset_sync || 'Are you sure you want to reset synchronization? This will clear all sync data.')) {
                return;
            }
            
            var self = this;
            var $btn = $(e.currentTarget);
            
            $btn.prop('disabled', true).addClass('loading');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_sync_reset_sync',
                    nonce: this.config.nonces.reset_sync || ''
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        self.loadDashboardData();
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Reset settings to defaults
         */
        resetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm(this.config.strings.confirm_reset_settings || 'Are you sure you want to reset all settings to defaults?')) {
                return;
            }
            
            var self = this;
            var $btn = $(e.currentTarget);
            
            $btn.prop('disabled', true).addClass('loading');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zoho_sync_reset_settings',
                    nonce: this.config.nonces.reset_settings || ''
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        },

        /**
         * Reset form to saved values
         */
        resetForm: function(e) {
            e.preventDefault();
            
            if (!confirm(this.config.strings.confirm_reset_form || 'Are you sure you want to reset the form?')) {
                return;
            }
            
            location.reload();
        },

        /**
         * Handle settings form submission
         */
        handleSettingsSubmit: function(e) {
            var $form = $(e.currentTarget);
            
            // Validate form
            if (!this.validateForm($form)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading
            this.showLoading('settings');
            this.state.isLoading = true;
            
            // Form will submit normally, loading will be hidden on page reload
        },

        /**
         * Handle field changes
         */
        handleFieldChange: function(e) {
            var $field = $(e.currentTarget);
            var fieldName = $field.attr('name');
            
            // Save field state
            this.saveFieldState(fieldName, $field.val());
            
            // Validate field
            this.validateField($field);
            
            // Handle conditional fields
            this.handleConditionalFields($field);
        },

        /**
         * Toggle password visibility
         */
        togglePassword: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var targetId = $btn.data('target');
            var $input = $('#' + targetId);
            var $icon = $btn.find('.dashicons');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        },

        /**
         * Initialize form validation
         */
        initFormValidation: function() {
            var self = this;
            
            // Real-time validation
            $('.zoho-sync-field-input, .zoho-sync-field-select').on('blur', function() {
                self.validateField($(this));
            });
        },

        /**
         * Validate entire form
         */
        validateForm: function($form) {
            var isValid = true;
            var self = this;
            
            $form.find('.zoho-sync-field-input[required], .zoho-sync-field-select[required]').each(function() {
                if (!self.validateField($(this))) {
                    isValid = false;
                }
            });
            
            return isValid;
        },

        /**
         * Validate individual field
         */
        validateField: function($field) {
            var value = $field.val();
            var fieldType = $field.attr('type');
            var isRequired = $field.prop('required');
            var $group = $field.closest('.zoho-sync-field-group');
            var $error = $group.find('.field-error');
            
            // Remove existing error
            $group.removeClass('has-error');
            $error.remove();
            
            // Check required
            if (isRequired && (!value || value.trim() === '')) {
                this.showFieldError($field, this.config.strings.field_required || 'This field is required');
                return false;
            }
            
            // Type-specific validation
            if (value && value.trim() !== '') {
                switch (fieldType) {
                    case 'email':
                        if (!this.isValidEmail(value)) {
                            this.showFieldError($field, this.config.strings.invalid_email || 'Please enter a valid email address');
                            return false;
                        }
                        break;
                    case 'url':
                        if (!this.isValidUrl(value)) {
                            this.showFieldError($field, this.config.strings.invalid_url || 'Please enter a valid URL');
                            return false;
                        }
                        break;
                    case 'number':
                        var min = $field.attr('min');
                        var max = $field.attr('max');
                        var numValue = parseFloat(value);
                        
                        if (isNaN(numValue)) {
                            this.showFieldError($field, this.config.strings.invalid_number || 'Please enter a valid number');
                            return false;
                        }
                        
                        if (min && numValue < parseFloat(min)) {
                            this.showFieldError($field, (this.config.strings.number_too_small || 'Value must be at least {min}').replace('{min}', min));
                            return false;
                        }
                        
                        if (max && numValue > parseFloat(max)) {
                            this.showFieldError($field, (this.config.strings.number_too_large || 'Value must be no more than {max}').replace('{max}', max));
                            return false;
                        }
                        break;
                }
            }
            
            return true;
        },

        /**
         * Show field error
         */
        showFieldError: function($field, message) {
            var $group = $field.closest('.zoho-sync-field-group');
            $group.addClass('has-error');
            $group.append('<p class="field-error">' + message + '</p>');
        },

        /**
         * Initialize conditional fields
         */
        initConditionalFields: function() {
            var self = this;
            
            // Handle region-dependent fields
            $('#zoho_region').on('change', function() {
                self.handleRegionChange($(this).val());
            });
            
            // Handle debug mode
            $('input[name="zoho_sync_core_settings[enable_debug]"]').on('change', function() {
                self.handleDebugModeChange($(this).is(':checked'));
            });
        },

        /**
         * Handle conditional fields
         */
        handleConditionalFields: function($field) {
            var fieldName = $field.attr('name');
            
            // Add specific conditional logic here
            if (fieldName === 'zoho_sync_core_settings[zoho_region]') {
                this.handleRegionChange($field.val());
            }
        },

        /**
         * Handle region change
         */
        handleRegionChange: function(region) {
            // Update help text or show/hide region-specific options
            var $regionHelp = $('.region-specific-help');
            $regionHelp.hide();
            $regionHelp.filter('[data-region="' + region + '"]').show();
        },

        /**
         * Handle debug mode change
         */
        handleDebugModeChange: function(enabled) {
            var $debugOptions = $('.debug-options');
            if (enabled) {
                $debugOptions.slideDown();
            } else {
                $debugOptions.slideUp();
            }
        },

        /**
         * Initialize status indicators
         */
        initStatusIndicators: function() {
            // Add pulsing animation to active indicators
            $('.status-indicator.status-connected').addClass('pulse');
        },

        /**
         * Initialize activity feed
         */
        initActivityFeed: function() {
            // Auto-scroll to latest activity
            var $activityList = $('.zoho-sync-activity-list');
            if ($activityList.length) {
                $activityList.scrollTop(0);
            }
        },

        /**
         * Initialize sync chart
         */
        initSyncChart: function() {
            var $canvas = $('#zoho-sync-chart');
            if (!$canvas.length) return;
            
            var ctx = $canvas[0].getContext('2d');
            
            this.state.charts.sync = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: this.config.strings.sync_operations || 'Sync Operations',
                        data: [],
                        borderColor: this.config.chartColors.primary,
                        backgroundColor: this.config.chartColors.primary + '20',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        /**
         * Initialize performance chart
         */
        initPerformanceChart: function() {
            var $canvas = $('#zoho-performance-chart');
            if (!$canvas.length) return;
            
            var ctx = $canvas[0].getContext('2d');
            
            this.state.charts.performance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [
                        this.config.strings.successful || 'Successful',
                        this.config.strings.failed || 'Failed',
                        this.config.strings.pending || 'Pending'
                    ],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: [
                            this.config.chartColors.success,
                            this.config.chartColors.error,
                            this.config.chartColors.warning
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        },

        /**
         * Update charts with new data
         */
        updateCharts: function(chartData) {
            // Update sync chart
            if (this.state.charts.sync && chartData.sync) {
                this.state.charts.sync.data.labels = chartData.sync.labels;
                this.state.charts.sync.data.datasets[0].data = chartData.sync.data;
                this.state.charts.sync.update();
            }
            
            // Update performance chart
            if (this.state.charts.performance && chartData.performance) {
                this.state.charts.performance.data.datasets[0].data = chartData.performance.data;
                this.state.charts.performance.update();
            }
        },

        /**
         * Start auto-refresh
         */
        startAutoRefresh: function() {
            var self = this;
            
            // Only auto-refresh on dashboard page
            if (!$('.zoho-sync-dashboard').length) return;
            
            this.state.intervals.refresh = setInterval(function() {
                if (!self.state.isLoading) {
                    self.loadDashboardData();
                }
            }, this.config.refreshInterval);
        },

        /**
         * Save field state to localStorage
         */
        saveFieldState: function(fieldName, value) {
            var formState = JSON.parse(localStorage.getItem('zoho_sync_form_state') || '{}');
            formState[fieldName] = value;
            localStorage.setItem('zoho_sync_form_state', JSON.stringify(formState));
        },

        /**
         * Restore form state from localStorage
         */
        restoreFormState: function() {
            var formState = JSON.parse(localStorage.getItem('zoho_sync_form_state') || '{}');
            var activeTab = localStorage.getItem('zoho_sync_active_tab');
            
            // Restore active tab
            if (activeTab) {
                $('.zoho-sync-nav-tab[data-tab="' + activeTab + '"]').click();
            }
            
            // Note: Don't restore sensitive fields like passwords
        },

        /**
         * Show loading overlay
         */
        showLoading: function(context) {
            var $overlay = $('#zoho-sync-loading-overlay');
            var message = this.config.strings.loading || 'Loading...';
            
            switch (context) {
                case 'dashboard':
                    message = this.config.strings.loading_dashboard || 'Loading dashboard...';
                    break;
                case 'settings':
                    message = this.config.strings.saving_settings || 'Saving settings...';
                    break;
            }
            
            $overlay.find('#zoho-sync-loading-message').text(message);
            $overlay.fadeIn(200);
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function(context) {
            $('#zoho-sync-loading-overlay').fadeOut(200);
        },

        /**
         * Show success notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible zoho-sync-notice">');
            $notice.append('<p>' + message + '</p>');
            $notice.append('<button type="button" class="notice-dismiss zoho-sync-dismiss-notice"><span class="screen-reader-text">Dismiss</span></button>');
            
            $('.zoho-sync-admin-header').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Dismiss notice
         */
        dismissNotice: function(e) {
            e.preventDefault();
            $(e.currentTarget).closest('.notice').fadeOut(300, function() {
                $(this).remove();
            });
        },

        /**
         * Utility functions
         */
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        }
    };

    /**
     * Settings specific functionality
     */
    window.ZohoSyncSettings = {
        init: function() {
            this.bindEvents();
            this.initValidation();
        },

        bindEvents: function() {
            // Tab switching
            $('.zoho-sync-nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                $('.zoho-sync-nav-tab').removeClass('active');
                $(this).addClass('active');
                
                $('.zoho-sync-tab-content').removeClass('active');
                $('#tab-' + tab).addClass('active');
            });

            // Password toggle
            $('.toggle-password').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                var input = $('#' + target);
                var icon = $(this).find('.dashicons');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                }
            });
        },

        initValidation: function() {
            // Add validation logic here
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Initialize main admin functionality
        if (typeof ZohoSyncAdmin !== 'undefined') {
            ZohoSyncAdmin.init();
        }

        // Initialize settings if on settings page
        if ($('.zoho-sync-settings').length && typeof ZohoSyncSettings !== 'undefined') {
            ZohoSyncSettings.init();
        }
    });

})(jQuery);
