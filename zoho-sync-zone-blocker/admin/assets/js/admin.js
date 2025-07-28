jQuery(document).ready(function($) {
    // Formulario de agregar zona
    $('#zszb-add-zone-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'zszb_add_zone');
        formData.append('security', zszbAdmin.nonce);

        $.ajax({
            url: zszbAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    // Eliminar zona
    $('.zszb-delete-zone').on('click', function() {
        if (!confirm(zszbAdmin.i18n.confirmDelete)) {
            return;
        }

        const zoneId = $(this).data('zone-id');
        
        $.ajax({
            url: zszbAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zszb_delete_zone',
                zone_id: zoneId,
                security: zszbAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
});