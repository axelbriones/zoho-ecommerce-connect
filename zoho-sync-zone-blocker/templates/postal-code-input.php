<?php get_header(); ?>

<div class="zszb-postal-form-container">
    <h1><?php _e('Ingresa tu Código Postal', 'zoho-sync-zone-blocker'); ?></h1>
    
    <div class="zszb-form-message" style="display: none;"></div>
    
    <form id="zszb-postal-form" method="post">
        <?php wp_nonce_field('zszb_check_postal', 'zszb_nonce'); ?>
        
        <div class="form-group">
            <label for="postal_code"><?php _e('Código Postal:', 'zoho-sync-zone-blocker'); ?></label>
            <input type="text" 
                   id="postal_code" 
                   name="postal_code" 
                   required 
                   pattern="[0-9]{5}"
                   maxlength="5"
                   placeholder="<?php esc_attr_e('Ej: 28001', 'zoho-sync-zone-blocker'); ?>">
            <small class="help-text"><?php _e('Ingresa los 5 dígitos de tu código postal', 'zoho-sync-zone-blocker'); ?></small>
        </div>

        <button type="submit" class="button">
            <span class="button-text"><?php _e('Comprobar Disponibilidad', 'zoho-sync-zone-blocker'); ?></span>
            <span class="spinner" style="display: none;"></span>
        </button>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    const $form = $('#zszb-postal-form');
    const $message = $('.zszb-form-message');
    const $button = $form.find('button[type="submit"]');
    const $buttonText = $button.find('.button-text');
    const $spinner = $button.find('.spinner');
    
    function showMessage(text, type = 'error') {
        $message
            .removeClass('success error')
            .addClass(type)
            .html(text)
            .slideDown();
    }
    
    function setLoading(isLoading) {
        $button.prop('disabled', isLoading);
        $buttonText.toggle(!isLoading);
        $spinner.toggle(isLoading);
    }
    
    $form.on('submit', function(e) {
        e.preventDefault();
        $message.slideUp();
        setLoading(true);
        
        const formData = new FormData(this);
        formData.append('action', 'zszb_check_postal');

        $.ajax({
            url: zszbFront.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    setTimeout(() => {
                        window.location.href = response.data.redirect;
                    }, 1500);
                } else {
                    showMessage(response.data.message);
                }
            },
            error: function() {
                showMessage('<?php echo esc_js(__('Error de conexión. Por favor intenta nuevamente.', 'zoho-sync-zone-blocker')); ?>');
            },
            complete: function() {
                setLoading(false);
            }
        });
    });
    
    // Formateo automático del código postal
    $('#postal_code').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});
</script>

<style>
.zszb-postal-form-container {
    max-width: 600px;
    margin: 50px auto;
    padding: 30px;
    background: #fff;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.zszb-postal-form-container h1 {
    text-align: center;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
}

.form-group input {
    width: 100%;
    padding: 8px;
    font-size: 16px;
}

button[type="submit"] {
    width: 100%;
    padding: 12px;
    background: #0073aa;
    color: #fff;
    border: none;
    cursor: pointer;
}

button[type="submit"]:hover {
    background: #005177;
}

.zszb-form-message {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    text-align: center;
}

.zszb-form-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.zszb-form-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.help-text {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 0.9em;
}

.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

button[type="submit"]:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
</style>

<?php get_footer(); ?>