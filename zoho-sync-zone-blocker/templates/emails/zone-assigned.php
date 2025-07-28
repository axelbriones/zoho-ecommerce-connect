<?php
/**
 * Email Template - Nueva Zona Asignada
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html($email_heading); ?></title>
</head>
<body>
    <div class="email-container">
        <h1><?php echo esc_html($email_heading); ?></h1>
        <p><?php printf(
            __('Hola %s,', 'zoho-sync-zone-blocker'),
            esc_html($distributor_name)
        ); ?></p>
        <p><?php _e('Se te ha asignado una nueva zona de distribución:', 'zoho-sync-zone-blocker'); ?></p>
        
        <div class="zone-details">
            <p><strong><?php _e('Zona:', 'zoho-sync-zone-blocker'); ?></strong> <?php echo esc_html($zone_name); ?></p>
            <p><strong><?php _e('Códigos Postales:', 'zoho-sync-zone-blocker'); ?></strong> <?php echo esc_html($postal_codes); ?></p>
        </div>
    </div>
</body>
</html>