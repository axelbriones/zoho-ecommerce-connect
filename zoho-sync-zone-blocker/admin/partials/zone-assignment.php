<?php
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="zszb-zone-grid">
        <div class="zszb-add-zone">
            <h2><?php _e('Agregar Nueva Zona', 'zoho-sync-zone-blocker'); ?></h2>
            <form id="zszb-add-zone-form" method="post">
                <?php wp_nonce_field('zszb_add_zone', 'zszb_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="distributor_id"><?php _e('Distribuidor', 'zoho-sync-zone-blocker'); ?></label>
                        </th>
                        <td>
                            <select name="distributor_id" id="distributor_id" required>
                                <?php
                                $distributors = ZSZB_Distributor_Zones::get_distributors();
                                foreach ($distributors as $distributor) {
                                    echo sprintf(
                                        '<option value="%d">%s</option>',
                                        $distributor->ID,
                                        esc_html($distributor->display_name)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="postal_codes"><?php _e('Códigos Postales', 'zoho-sync-zone-blocker'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="postal_codes" 
                                   id="postal_codes" 
                                   class="regular-text"
                                   placeholder="<?php _e('Ej: 28001-28029,28031', 'zoho-sync-zone-blocker'); ?>"
                                   required>
                            <p class="description">
                                <?php _e('Ingrese códigos individuales separados por comas o rangos usando guión', 'zoho-sync-zone-blocker'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Agregar Zona', 'zoho-sync-zone-blocker')); ?>
            </form>
        </div>

        <div class="zszb-zones-list">
            <h2><?php _e('Zonas Asignadas', 'zoho-sync-zone-blocker'); ?></h2>
            <?php
            $zones = ZSZB_Zone_Manager::get_zones();
            if ($zones): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Distribuidor', 'zoho-sync-zone-blocker'); ?></th>
                            <th><?php _e('Códigos Postales', 'zoho-sync-zone-blocker'); ?></th>
                            <th><?php _e('Estado', 'zoho-sync-zone-blocker'); ?></th>
                            <th><?php _e('Acciones', 'zoho-sync-zone-blocker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zones as $zone): ?>
                            <tr>
                                <td><?php echo esc_html($zone->distributor_name); ?></td>
                                <td><?php echo esc_html($zone->postal_codes); ?></td>
                                <td><?php echo $zone->active ? __('Activo', 'zoho-sync-zone-blocker') : __('Inactivo', 'zoho-sync-zone-blocker'); ?></td>
                                <td>
                                    <button class="button zszb-edit-zone" 
                                            data-zone-id="<?php echo esc_attr($zone->id); ?>">
                                        <?php _e('Editar', 'zoho-sync-zone-blocker'); ?>
                                    </button>
                                    <button class="button button-link-delete zszb-delete-zone" 
                                            data-zone-id="<?php echo esc_attr($zone->id); ?>">
                                        <?php _e('Eliminar', 'zoho-sync-zone-blocker'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No hay zonas asignadas.', 'zoho-sync-zone-blocker'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>