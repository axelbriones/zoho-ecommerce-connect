<?php
/**
 * Settings Display
 *
 * @package ZohoSyncCustomers
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Configuración - Zoho Sync Customers', 'zoho-sync-customers'); ?></h1>
    
    <!-- Settings Navigation Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active" data-tab="general"><?php _e('General', 'zoho-sync-customers'); ?></a>
        <a href="#sync" class="nav-tab" data-tab="sync"><?php _e('Sincronización', 'zoho-sync-customers'); ?></a>
        <a href="#distributors" class="nav-tab" data-tab="distributors"><?php _e('Distribuidores', 'zoho-sync-customers'); ?></a>
        <a href="#b2b" class="nav-tab" data-tab="b2b"><?php _e('B2B', 'zoho-sync-customers'); ?></a>
        <a href="#pricing" class="nav-tab" data-tab="pricing"><?php _e('Precios', 'zoho-sync-customers'); ?></a>
        <a href="#mapping" class="nav-tab" data-tab="mapping"><?php _e('Mapeo de Campos', 'zoho-sync-customers'); ?></a>
        <a href="#advanced" class="nav-tab" data-tab="advanced"><?php _e('Avanzado', 'zoho-sync-customers'); ?></a>
    </nav>
    
    <!-- General Settings Tab -->
    <div id="general-tab" class="tab-content active">
        <h2><?php _e('Configuración General', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('zoho_customers_general'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Habilitar Plugin', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_enabled" value="yes" 
                                       <?php checked(get_option('zoho_customers_enabled', 'yes'), 'yes'); ?>>
                                <?php _e('Activar funcionalidades del plugin', 'zoho-sync-customers'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Desactivar temporalmente todas las funcionalidades sin desinstalar el plugin.', 'zoho-sync-customers'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Modo de Depuración', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_debug_mode" value="yes" 
                                       <?php checked(get_option('zoho_customers_debug_mode', 'no'), 'yes'); ?>>
                                <?php _e('Habilitar logging detallado', 'zoho-sync-customers'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Registra información detallada para depuración. Solo usar en desarrollo.', 'zoho-sync-customers'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Crear Usuarios Automáticamente', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_auto_create_users" value="yes" 
                                       <?php checked(get_option('zoho_customers_auto_create_users', 'yes'), 'yes'); ?>>
                                <?php _e('Crear usuarios de WordPress automáticamente desde Zoho', 'zoho-sync-customers'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Los contactos de Zoho CRM se convertirán automáticamente en usuarios de WordPress.', 'zoho-sync-customers'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Rol por Defecto', 'zoho-sync-customers'); ?></th>
                    <td>
                        <select name="zoho_customers_default_role">
                            <?php
                            $current_role = get_option('zoho_customers_default_role', 'customer');
                            $roles = wp_roles()->get_names();
                            
                            foreach ($roles as $role_key => $role_name) {
                                echo '<option value="' . esc_attr($role_key) . '" ' . selected($current_role, $role_key, false) . '>' . esc_html($role_name) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php _e('Rol asignado por defecto a nuevos usuarios creados desde Zoho.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Prefijo de Usuario', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="text" name="zoho_customers_user_prefix" 
                               value="<?php echo esc_attr(get_option('zoho_customers_user_prefix', 'zoho_')); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php _e('Prefijo para nombres de usuario creados automáticamente.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Guardar Configuración General', 'zoho-sync-customers')); ?>
        </form>
    </div>
    
    <!-- Sync Settings Tab -->
    <div id="sync-tab" class="tab-content">
        <h2><?php _e('Configuración de Sincronización', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('zoho_customers_sync'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Sincronización Habilitada', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_sync_enabled" value="yes" 
                                       <?php checked(get_option('zoho_customers_sync_enabled', 'yes'), 'yes'); ?>>
                                <?php _e('Habilitar sincronización automática', 'zoho-sync-customers'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Intervalo de Sincronización', 'zoho-sync-customers'); ?></th>
                    <td>
                        <select name="zoho_customers_sync_interval">
                            <?php
                            $current_interval = get_option('zoho_customers_sync_interval', 'hourly');
                            $intervals = array(
                                'every_15_minutes' => __('Cada 15 minutos', 'zoho-sync-customers'),
                                'every_30_minutes' => __('Cada 30 minutos', 'zoho-sync-customers'),
                                'hourly' => __('Cada hora', 'zoho-sync-customers'),
                                'twicedaily' => __('Dos veces al día', 'zoho-sync-customers'),
                                'daily' => __('Diariamente', 'zoho-sync-customers'),
                                'weekly' => __('Semanalmente', 'zoho-sync-customers')
                            );
                            
                            foreach ($intervals as $value => $label) {
                                echo '<option value="' . esc_attr($value) . '" ' . selected($current_interval, $value, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Dirección de Sincronización', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <?php
                            $sync_direction = get_option('zoho_customers_sync_direction', 'both');
                            ?>
                            <label>
                                <input type="radio" name="zoho_customers_sync_direction" value="both" 
                                       <?php checked($sync_direction, 'both'); ?>>
                                <?php _e('Bidireccional (Zoho ↔ WooCommerce)', 'zoho-sync-customers'); ?>
                            </label><br>
                            
                            <label>
                                <input type="radio" name="zoho_customers_sync_direction" value="from_zoho" 
                                       <?php checked($sync_direction, 'from_zoho'); ?>>
                                <?php _e('Solo desde Zoho (Zoho → WooCommerce)', 'zoho-sync-customers'); ?>
                            </label><br>
                            
                            <label>
                                <input type="radio" name="zoho_customers_sync_direction" value="to_zoho" 
                                       <?php checked($sync_direction, 'to_zoho'); ?>>
                                <?php _e('Solo hacia Zoho (WooCommerce → Zoho)', 'zoho-sync-customers'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Límite de Registros por Lote', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="number" name="zoho_customers_batch_size" 
                               value="<?php echo esc_attr(get_option('zoho_customers_batch_size', 100)); ?>" 
                               min="10" max="500" class="small-text">
                        <p class="description">
                            <?php _e('Número máximo de registros a procesar en cada lote.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Timeout de Sincronización', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="number" name="zoho_customers_sync_timeout" 
                               value="<?php echo esc_attr(get_option('zoho_customers_sync_timeout', 300)); ?>" 
                               min="60" max="3600" class="small-text">
                        <span><?php _e('segundos', 'zoho-sync-customers'); ?></span>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Guardar Configuración de Sincronización', 'zoho-sync-customers')); ?>
        </form>
    </div>
    
    <!-- Distributors Settings Tab -->
    <div id="distributors-tab" class="tab-content">
        <h2><?php _e('Configuración de Distribuidores', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('zoho_customers_distributors'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Sistema de Distribuidores Habilitado', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_distributors_enabled" value="yes" 
                                       <?php checked(get_option('zoho_customers_distributors_enabled', 'yes'), 'yes'); ?>>
                                <?php _e('Habilitar sistema de distribuidores', 'zoho-sync-customers'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Aprobación Automática', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_auto_approve_distributors" value="yes" 
                                       <?php checked(get_option('zoho_customers_auto_approve_distributors', 'no'), 'yes'); ?>>
                                <?php _e('Aprobar automáticamente nuevos distribuidores', 'zoho-sync-customers'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Los nuevos distribuidores serán aprobados automáticamente sin revisión manual.', 'zoho-sync-customers'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Nivel por Defecto', 'zoho-sync-customers'); ?></th>
                    <td>
                        <select name="zoho_customers_default_distributor_level">
                            <?php
                            $current_level = get_option('zoho_customers_default_distributor_level', 'level_1');
                            $distributor_manager = ZohoSyncCustomers_DistributorManager::get_instance();
                            $levels = $distributor_manager->get_distributor_levels();
                            
                            foreach ($levels as $level_key => $level_data) {
                                echo '<option value="' . esc_attr($level_key) . '" ' . selected($current_level, $level_key, false) . '>' . esc_html($level_data['name']) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php _e('Nivel asignado por defecto a nuevos distribuidores.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Campos Requeridos', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <?php
                            $required_fields = get_option('zoho_customers_distributor_required_fields', array('company_name', 'tax_id'));
                            $available_fields = array(
                                'company_name' => __('Nombre de la Empresa', 'zoho-sync-customers'),
                                'tax_id' => __('NIT/RUT', 'zoho-sync-customers'),
                                'business_license' => __('Licencia Comercial', 'zoho-sync-customers'),
                                'years_in_business' => __('Años en el Negocio', 'zoho-sync-customers'),
                                'annual_revenue' => __('Ingresos Anuales', 'zoho-sync-customers'),
                                'references' => __('Referencias Comerciales', 'zoho-sync-customers')
                            );
                            
                            foreach ($available_fields as $field_key => $field_label):
                            ?>
                            <label>
                                <input type="checkbox" name="zoho_customers_distributor_required_fields[]" 
                                       value="<?php echo esc_attr($field_key); ?>"
                                       <?php checked(in_array($field_key, $required_fields)); ?>>
                                <?php echo esc_html($field_label); ?>
                            </label><br>
                            <?php endforeach; ?>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Guardar Configuración de Distribuidores', 'zoho-sync-customers')); ?>
        </form>
    </div>
    
    <!-- B2B Settings Tab -->
    <div id="b2b-tab" class="tab-content">
        <h2><?php _e('Configuración B2B', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('zoho_customers_b2b'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Sistema B2B Habilitado', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_b2b_enabled" value="yes" 
                                       <?php checked(get_option('zoho_customers_b2b_enabled', 'yes'), 'yes'); ?>>
                                <?php _e('Habilitar funcionalidades B2B', 'zoho-sync-customers'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Aprobación Requerida', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_b2b_approval_required" value="yes" 
                                       <?php checked(get_option('zoho_customers_b2b_approval_required', 'yes'), 'yes'); ?>>
                                <?php _e('Requerir aprobación manual para clientes B2B', 'zoho-sync-customers'); ?>
                            </label>
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
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Guardar Configuración B2B', 'zoho-sync-customers')); ?>
        </form>
    </div>
    
    <!-- Pricing Settings Tab -->
    <div id="pricing-tab" class="tab-content">
        <h2><?php _e('Configuración de Precios', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('zoho_customers_pricing'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Sistema de Precios Habilitado', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_pricing_enabled" value="yes" 
                                       <?php checked(get_option('zoho_customers_pricing_enabled', 'yes'), 'yes'); ?>>
                                <?php _e('Habilitar precios por nivel', 'zoho-sync-customers'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Mostrar Precio Original', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_show_original_price" value="yes" 
                                       <?php checked(get_option('zoho_customers_show_original_price', 'yes'), 'yes'); ?>>
                                <?php _e('Mostrar precio original tachado', 'zoho-sync-customers'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Redondeo de Precios', 'zoho-sync-customers'); ?></th>
                    <td>
                        <select name="zoho_customers_price_rounding">
                            <?php
                            $current_rounding = get_option('zoho_customers_price_rounding', 'none');
                            $rounding_options = array(
                                'none' => __('Sin redondeo', 'zoho-sync-customers'),
                                'up' => __('Redondear hacia arriba', 'zoho-sync-customers'),
                                'down' => __('Redondear hacia abajo', 'zoho-sync-customers'),
                                'nearest' => __('Redondear al más cercano', 'zoho-sync-customers')
                            );
                            
                            foreach ($rounding_options as $value => $label) {
                                echo '<option value="' . esc_attr($value) . '" ' . selected($current_rounding, $value, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Guardar Configuración de Precios', 'zoho-sync-customers')); ?>
        </form>
    </div>
    
    <!-- Field Mapping Tab -->
    <div id="mapping-tab" class="tab-content">
        <h2><?php _e('Mapeo de Campos', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('zoho_customers_mapping'); ?>
            
            <p><?php _e('Configure cómo se mapean los campos entre Zoho CRM y WooCommerce:', 'zoho-sync-customers'); ?></p>
            
            <table class="form-table">
                <thead>
                    <tr>
                        <th><?php _e('Campo de WooCommerce', 'zoho-sync-customers'); ?></th>
                        <th><?php _e('Campo de Zoho CRM', 'zoho-sync-customers'); ?></th>
                        <th><?php _e('Dirección de Sincronización', 'zoho-sync-customers'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $field_mappings = get_option('zoho_customers_field_mapping', array());
                    $default_mappings = array(
                        'first_name' => array('zoho_field' => 'First_Name', 'direction' => 'both'),
                        'last_name' => array('zoho_field' => 'Last_Name', 'direction' => 'both'),
                        'email' => array('zoho_field' => 'Email', 'direction' => 'both'),
                        'phone' => array('zoho_field' => 'Phone', 'direction' => 'both'),
                        'company' => array('zoho_field' => 'Account_Name', 'direction' => 'both'),
                        'billing_address_1' => array('zoho_field' => 'Mailing_Street', 'direction' => 'both'),
                        'billing_city' => array('zoho_field' => 'Mailing_City', 'direction' => 'both'),
                        'billing_state' => array('zoho_field' => 'Mailing_State', 'direction' => 'both'),
                        'billing_postcode' => array('zoho_field' => 'Mailing_Zip', 'direction' => 'both'),
                        'billing_country' => array('zoho_field' => 'Mailing_Country', 'direction' => 'both')
                    );
                    
                    $field_mappings = wp_parse_args($field_mappings, $default_mappings);
                    
                    foreach ($field_mappings as $wc_field => $mapping):
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $wc_field))); ?></strong>
                            <input type="hidden" name="zoho_customers_field_mapping[<?php echo esc_attr($wc_field); ?>][wc_field]" value="<?php echo esc_attr($wc_field); ?>">
                        </td>
                        <td>
                            <input type="text" name="zoho_customers_field_mapping[<?php echo esc_attr($wc_field); ?>][zoho_field]" 
                                   value="<?php echo esc_attr($mapping['zoho_field']); ?>" class="regular-text">
                        </td>
                        <td>
                            <select name="zoho_customers_field_mapping[<?php echo esc_attr($wc_field); ?>][direction]">
                                <option value="both" <?php selected($mapping['direction'], 'both'); ?>><?php _e('Bidireccional', 'zoho-sync-customers'); ?></option>
                                <option value="from_zoho" <?php selected($mapping['direction'], 'from_zoho'); ?>><?php _e('Solo desde Zoho', 'zoho-sync-customers'); ?></option>
                                <option value="to_zoho" <?php selected($mapping['direction'], 'to_zoho'); ?>><?php _e('Solo hacia Zoho', 'zoho-sync-customers'); ?></option>
                                <option value="none" <?php selected($mapping['direction'], 'none'); ?>><?php _e('No sincronizar', 'zoho-sync-customers'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h3><?php _e('Campos Personalizados', 'zoho-sync-customers'); ?></h3>
            <div id="custom-fields-container">
                <?php
                $custom_fields = get_option('zoho_customers_custom_fields', array());
                foreach ($custom_fields as $index => $field):
                ?>
                <div class="custom-field-row">
                    <input type="text" name="zoho_customers_custom_fields[<?php echo $index; ?>][wc_field]" 
                           value="<?php echo esc_attr($field['wc_field']); ?>" placeholder="<?php _e('Campo WooCommerce', 'zoho-sync-customers'); ?>">
                    <input type="text" name="zoho_customers_custom_fields[<?php echo $index; ?>][zoho_field]" 
                           value="<?php echo esc_attr($field['zoho_field']); ?>" placeholder="<?php _e('Campo Zoho', 'zoho-sync-customers'); ?>">
                    <select name="zoho_customers_custom_fields[<?php echo $index; ?>][direction]">
                        <option value="both" <?php selected($field['direction'], 'both'); ?>><?php _e('Bidireccional', 'zoho-sync-customers'); ?></option>
                        <option value="from_zoho" <?php selected($field['direction'], 'from_zoho'); ?>><?php _e('Solo desde Zoho', 'zoho-sync-customers'); ?></option>
                        <option value="to_zoho" <?php selected($field['direction'], 'to_zoho'); ?>><?php _e('Solo hacia Zoho', 'zoho-sync-customers'); ?></option>
                    </select>
                    <button type="button" class="button remove-custom-field"><?php _e('Eliminar', 'zoho-sync-customers'); ?></button>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" id="add-custom-field" class="button"><?php _e('Agregar Campo Personalizado', 'zoho-sync-customers'); ?></button>
            
            <?php submit_button(__('Guardar Mapeo de Campos', 'zoho-sync-customers')); ?>
        </form>
    </div>
    
    <!-- Advanced Settings Tab -->
    <div id="advanced-tab" class="tab-content">
        <h2><?php _e('Configuración Avanzada', 'zoho-sync-customers'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('zoho_customers_advanced'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Límite de Rate Limiting', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="number" name="zoho_customers_rate_limit" 
                               value="<?php echo esc_attr(get_option('zoho_customers_rate_limit', 100)); ?>" 
                               min="10" max="1000" class="small-text">
                        <span><?php _e('requests/hour', 'zoho-sync-customers'); ?></span>
                        <p class="description">
                            <?php _e('Límite de peticiones por hora a la API de Zoho.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Reintentos en Caso de Error', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="number" name="zoho_customers_retry_attempts"
                               value="<?php echo esc_attr(get_option('zoho_customers_retry_attempts', 3)); ?>"
                               min="1" max="10" class="small-text">
                        <p class="description">
                            <?php _e('Número de reintentos en caso de error en las peticiones a Zoho.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Tiempo de Espera entre Reintentos', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="number" name="zoho_customers_retry_delay"
                               value="<?php echo esc_attr(get_option('zoho_customers_retry_delay', 5)); ?>"
                               min="1" max="60" class="small-text">
                        <span><?php _e('segundos', 'zoho-sync-customers'); ?></span>
                        <p class="description">
                            <?php _e('Tiempo de espera entre reintentos fallidos.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Limpiar Logs Automáticamente', 'zoho-sync-customers'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="zoho_customers_auto_cleanup_logs" value="yes"
                                       <?php checked(get_option('zoho_customers_auto_cleanup_logs', 'yes'), 'yes'); ?>>
                                <?php _e('Eliminar logs antiguos automáticamente', 'zoho-sync-customers'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Los logs más antiguos de 30 días se eliminarán automáticamente.', 'zoho-sync-customers'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Webhook Secret', 'zoho-sync-customers'); ?></th>
                    <td>
                        <input type="text" name="zoho_customers_webhook_secret"
                               value="<?php echo esc_attr(get_option('zoho_customers_webhook_secret', wp_generate_password(32, false))); ?>"
                               class="regular-text">
                        <button type="button" id="generate-webhook-secret" class="button"><?php _e('Generar Nuevo', 'zoho-sync-customers'); ?></button>
                        <p class="description">
                            <?php _e('Clave secreta para validar webhooks de Zoho. Manténgala segura.', 'zoho-sync-customers'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Guardar Configuración Avanzada', 'zoho-sync-customers')); ?>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).data('tab');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show target content
        $('.tab-content').removeClass('active');
        $('#' + targetTab + '-tab').addClass('active');
    });
    
    // Add custom field
    $('#add-custom-field').on('click', function() {
        var container = $('#custom-fields-container');
        var index = container.find('.custom-field-row').length;
        
        var newField = $('<div class="custom-field-row">' +
            '<input type="text" name="zoho_customers_custom_fields[' + index + '][wc_field]" placeholder="Campo WooCommerce">' +
            '<input type="text" name="zoho_customers_custom_fields[' + index + '][zoho_field]" placeholder="Campo Zoho">' +
            '<select name="zoho_customers_custom_fields[' + index + '][direction]">' +
                '<option value="both">Bidireccional</option>' +
                '<option value="from_zoho">Solo desde Zoho</option>' +
                '<option value="to_zoho">Solo hacia Zoho</option>' +
            '</select>' +
            '<button type="button" class="button remove-custom-field">Eliminar</button>' +
            '</div>');
        
        container.append(newField);
    });
    
    // Remove custom field
    $(document).on('click', '.remove-custom-field', function() {
        $(this).closest('.custom-field-row').remove();
    });
    
    // Generate webhook secret
    $('#generate-webhook-secret').on('click', function() {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        var secret = '';
        for (var i = 0; i < 32; i++) {
            secret += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        $('input[name="zoho_customers_webhook_secret"]').val(secret);
    });
    
    // Form validation
    $('form').on('submit', function(e) {
        var isValid = true;
        var errorMessage = '';
        
        // Validate batch size
        var batchSize = parseInt($('input[name="zoho_customers_batch_size"]').val());
        if (batchSize < 10 || batchSize > 500) {
            isValid = false;
            errorMessage += 'El tamaño del lote debe estar entre 10 y 500.\n';
        }
        
        // Validate timeout
        var timeout = parseInt($('input[name="zoho_customers_sync_timeout"]').val());
        if (timeout < 60 || timeout > 3600) {
            isValid = false;
            errorMessage += 'El timeout debe estar entre 60 y 3600 segundos.\n';
        }
        
        // Validate rate limit
        var rateLimit = parseInt($('input[name="zoho_customers_rate_limit"]').val());
        if (rateLimit < 10 || rateLimit > 1000) {
            isValid = false;
            errorMessage += 'El límite de rate limiting debe estar entre 10 y 1000.\n';
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Errores de validación:\n' + errorMessage);
        }
    });
    
    // Auto-save draft settings
    var autoSaveTimer;
    $('input, select, textarea').on('change', function() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function() {
            // Could implement auto-save functionality here
            console.log('Settings changed - consider auto-save');
        }, 2000);
    });
});
</script>

<style>
.nav-tab-wrapper {
    margin-bottom: 20px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.custom-field-row {
    margin-bottom: 10px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.custom-field-row input,
.custom-field-row select {
    margin-right: 10px;
    width: 200px;
}

.custom-field-row .button {
    vertical-align: top;
}

.form-table th {
    width: 200px;
}

.form-table td {
    padding: 15px 10px;
}

.form-table .description {
    margin-top: 5px;
    font-style: italic;
    color: #666;
}

.settings-section {
    margin-bottom: 30px;
}

.settings-section h3 {
    margin-top: 30px;
    margin-bottom: 10px;
}

fieldset label {
    display: block;
    margin-bottom: 5px;
}

fieldset label input[type="checkbox"],
fieldset label input[type="radio"] {
    margin-right: 5px;
}
</style>
                    <td>
                        <input type="number" name="zoho_