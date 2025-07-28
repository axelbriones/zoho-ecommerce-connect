(function($) {
    'use strict';

    // Caché de elementos DOM
    const $form = $('#zszb-postal-form');
    const $message = $('.zszb-form-message');
    const $postalInput = $('#postal_code');
    
    // Funciones auxiliares
    const showMessage = (text, type = 'error') => {
        $message
            .removeClass('error success')
            .addClass(type)
            .html(text)
            .slideDown();
    };
    
    const validatePostalCode = (code) => {
        return /^[0-9]{5}$/.test(code);
    };
    
    // Event handlers
    $form.on('submit', function(e) {
        e.preventDefault();
        
        const postalCode = $postalInput.val().trim();
        
        if (!validatePostalCode(postalCode)) {
            showMessage(zszbFront.i18n.invalidPostal);
            return;
        }
        
        $.ajax({
            url: zszbFront.ajaxUrl,
            type: 'POST',
            data: {
                action: 'zszb_check_postal',
                postal_code: postalCode,
                security: zszbFront.nonce
            },
            beforeSend: () => {
                $form.addClass('loading');
                $message.slideUp();
            },
            success: (response) => {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    // Guardar en localStorage para uso futuro
                    localStorage.setItem('zszb_postal_code', postalCode);
                    setTimeout(() => {
                        window.location.href = response.data.redirect;
                    }, 1500);
                } else {
                    showMessage(response.data.message);
                }
            },
            error: () => {
                showMessage(zszbFront.i18n.errorConnection);
            },
            complete: () => {
                $form.removeClass('loading');
            }
        });
    });

    // Autocompletar con código postal guardado
    const savedPostalCode = localStorage.getItem('zszb_postal_code');
    if (savedPostalCode && validatePostalCode(savedPostalCode)) {
        $postalInput.val(savedPostalCode);
    }
    
    // Formateo automático del código postal
    $postalInput.on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 5);
        
        // Validación en tiempo real
        const isValid = validatePostalCode(this.value);
        $(this).toggleClass('is-valid', isValid);
        $(this).toggleClass('is-invalid', this.value.length > 0 && !isValid);
    });
    
    // Limpieza de mensajes al empezar a escribir
    $postalInput.on('focus', () => {
        $message.slideUp();
    });
    
    // Feedback visual al escribir
    $postalInput.on('keyup', function(e) {
        // Si presiona Enter y el código es válido, enviar formulario
        if (e.keyCode === 13 && validatePostalCode(this.value)) {
            $form.submit();
        }
    });
    
    // Prevenir múltiples envíos
    let isSubmitting = false;
    $(window).on('beforeunload', () => {
        if (isSubmitting) {
            return zszbFront.i18n.submitting;
        }
    });
    
    // Restaurar estado normal si hay error de red
    window.addEventListener('online', () => {
        $form.removeClass('loading');
        isSubmitting = false;
    });

})(jQuery);