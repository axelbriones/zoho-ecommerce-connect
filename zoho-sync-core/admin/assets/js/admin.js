(function($) {
    'use strict';

    $(function() {
        $('#zoho-check-connection').on('click', function() {
            var $status = $('#zoho-connection-status');
            $status.text('Checking...');

            var data = {
                'action': 'zoho_sync_core_check_connection',
                'nonce': zohoSyncCore.check_connection_nonce
            };

            $.post(zohoSyncCore.ajax_url, data, function(response) {
                if (response.success) {
                    $status.text('Connection successful!');
                } else {
                    $status.text('Connection failed: ' + response.data.message);
                }
            }).fail(function(xhr, status, error) {
                $status.text('An error occurred: ' + error);
                console.error(xhr.responseText);
            });
        });

        $('#zoho-generate-auth-url').on('click', function() {
            var $urlContainer = $('#zoho-auth-url-container');
            var $urlLink = $('#zoho-auth-url');

            var data = {
                'action': 'zoho_sync_core_generate_auth_url',
                'nonce': zohoSyncCore.generate_auth_url_nonce
            };

            $.post(zohoSyncCore.ajax_url, data, function(response) {
                if (response.success) {
                    $urlLink.attr('href', response.data.url).text(response.data.url);
                    $urlContainer.show();
                } else {
                    alert(response.data.message);
                }
            });
        });
    });
})(jQuery);
