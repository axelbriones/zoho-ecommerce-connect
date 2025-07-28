(function($) {
    'use strict';

    const ZSCUPortal = {
        init: function() {
            this.initializeTooltips();
            this.bindEvents();
            this.initializeCharts();
        },

        bindEvents: function() {
            $('.add-to-cart').on('click', this.handleAddToCart);
            $('.toggle-price-history').on('click', this.togglePriceHistory);
            $('#filter-products').on('submit', this.handleProductFilter);
        },

        handleAddToCart: function(e) {
            e.preventDefault();
            const $button = $(this);
            const productId = $button.data('product-id');

            $button.addClass('loading');

            $.ajax({
                url: zscuPortal.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'zscu_add_to_cart',
                    product_id: productId,
                    nonce: zscuPortal.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.addClass('added');
                        $(document.body).trigger('wc_fragment_refresh');
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert(zscuPortal.i18n.errorMessage);
                },
                complete: function() {
                    $button.removeClass('loading');
                }
            });
        },

        initializeCharts: function() {
            const $orderChart = $('#order-history-chart');
            if (!$orderChart.length) return;

            const ctx = $orderChart[0].getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: $orderChart.data('orders'),
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        handleProductFilter: function(e) {
            e.preventDefault();
            const $form = $(this);
            const $results = $('#filtered-products');

            $results.addClass('loading');

            $.ajax({
                url: zscuPortal.ajaxUrl,
                type: 'GET',
                data: $form.serialize() + '&action=zscu_filter_products',
                success: function(response) {
                    if (response.success) {
                        $results.html(response.data.html);
                    }
                },
                complete: function() {
                    $results.removeClass('loading');
                }
            });
        },

        initializeTooltips: function() {
            $('[data-tooltip]').tooltip({
                position: {
                    my: "center bottom-20",
                    at: "center top"
                }
            });
        }
    };

    $(document).ready(function() {
        ZSCUPortal.init();
    });

})(jQuery);