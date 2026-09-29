<?php
/**
 * Template: Configuración de filtro de búsqueda
 *
 * @package SecopSuite
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ss-config-wrapper">
    <!-- Fuente de Datos -->
    <div class="ss-config-section">
        <h3>
            <span class="dashicons dashicons-database"></span>
            <?php _e('Fuente de Datos', 'secop-suite'); ?>
        </h3>

        <table class="form-table ss-form-table">
            <tr>
                <th>
                    <label for="ss_filter_table_name"><?php _e('Tabla de Datos', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_filter_table_name" id="ss_filter_table_name" class="regular-text">
                        <option value=""><?php _e('-- Seleccionar tabla --', 'secop-suite'); ?></option>
                        <?php foreach ($tables as $table_name => $table_label): ?>
                            <option value="<?php echo esc_attr($table_name); ?>"
                                    <?php selected($config['table_name'] ?? '', $table_name); ?>>
                                <?php echo esc_html($table_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
    </div>

    <!-- Campos de Filtro -->
    <div class="ss-config-section" id="ss-filter-fields-section" style="<?php echo empty($config['table_name']) ? 'display:none;' : ''; ?>">
        <h3>
            <span class="dashicons dashicons-filter"></span>
            <?php _e('Campos de Filtro', 'secop-suite'); ?>
        </h3>

        <div style="padding: 20px;">
            <p class="description"><?php _e('Configure los campos que el usuario podrá usar para filtrar los datos.', 'secop-suite'); ?></p>

            <div id="ss-filter-fields-container">
                <?php
                $fields = $config['fields'] ?? [];
                if (empty($fields)) {
                    $fields = [['column' => '', 'label' => '', 'type' => 'input', 'placeholder' => '', 'operator' => 'LIKE']];
                }
                foreach ($fields as $index => $field):
                ?>
                    <div class="ss-filter-field-row" style="background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 15px; margin-bottom: 10px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div>
                                <label><strong><?php _e('Columna', 'secop-suite'); ?></strong></label>
                                <select name="ss_filter_fields[<?php echo $index; ?>][column]" class="widefat ss-filter-column-select" data-saved-value="<?php echo esc_attr($field['column'] ?? ''); ?>">
                                    <option value=""><?php _e('-- Seleccionar --', 'secop-suite'); ?></option>
                                </select>
                            </div>
                            <div>
                                <label><strong><?php _e('Etiqueta', 'secop-suite'); ?></strong></label>
                                <input type="text" name="ss_filter_fields[<?php echo $index; ?>][label]" class="widefat" value="<?php echo esc_attr($field['label'] ?? ''); ?>" placeholder="<?php esc_attr_e('Nombre visible', 'secop-suite'); ?>" />
                            </div>
                            <div>
                                <label><strong><?php _e('Tipo de Campo', 'secop-suite'); ?></strong></label>
                                <select name="ss_filter_fields[<?php echo $index; ?>][type]" class="widefat">
                                    <option value="input" <?php selected($field['type'] ?? 'input', 'input'); ?>><?php _e('Texto (input)', 'secop-suite'); ?></option>
                                    <option value="select" <?php selected($field['type'] ?? '', 'select'); ?>><?php _e('Lista desplegable (select)', 'secop-suite'); ?></option>
                                    <option value="range" <?php selected($field['type'] ?? '', 'range'); ?>><?php _e('Rango (desde-hasta)', 'secop-suite'); ?></option>
                                    <option value="checkbox" <?php selected($field['type'] ?? '', 'checkbox'); ?>><?php _e('Opciones múltiples (checkbox)', 'secop-suite'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="ss-field-values-preview" style="margin-top:8px;font-size:12px;color:#555;display:none;"></div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end;">
                            <div>
                                <label><strong><?php _e('Placeholder', 'secop-suite'); ?></strong></label>
                                <input type="text" name="ss_filter_fields[<?php echo $index; ?>][placeholder]" class="widefat" value="<?php echo esc_attr($field['placeholder'] ?? ''); ?>" placeholder="<?php esc_attr_e('Texto de ayuda', 'secop-suite'); ?>" />
                            </div>
                            <div>
                                <label><strong><?php _e('Operador', 'secop-suite'); ?></strong></label>
                                <select name="ss_filter_fields[<?php echo $index; ?>][operator]" class="widefat">
                                    <option value="=" <?php selected($field['operator'] ?? '', '='); ?>><?php _e('Igual (=)', 'secop-suite'); ?></option>
                                    <option value="LIKE" <?php selected($field['operator'] ?? 'LIKE', 'LIKE'); ?>><?php _e('Contiene (LIKE)', 'secop-suite'); ?></option>
                                    <option value="!=" <?php selected($field['operator'] ?? '', '!='); ?>><?php _e('Diferente (!=)', 'secop-suite'); ?></option>
                                    <option value=">" <?php selected($field['operator'] ?? '', '>'); ?>><?php _e('Mayor que (>)', 'secop-suite'); ?></option>
                                    <option value="<" <?php selected($field['operator'] ?? '', '<'); ?>><?php _e('Menor que (<)', 'secop-suite'); ?></option>
                                    <option value=">=" <?php selected($field['operator'] ?? '', '>='); ?>><?php _e('Mayor o igual (>=)', 'secop-suite'); ?></option>
                                    <option value="<=" <?php selected($field['operator'] ?? '', '<='); ?>><?php _e('Menor o igual (<=)', 'secop-suite'); ?></option>
                                </select>
                            </div>
                            <div>
                                <button type="button" class="button ss-remove-filter-field" style="color: #d63638;">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button" id="ss-add-filter-field">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php _e('Añadir Campo de Filtro', 'secop-suite'); ?>
            </button>
        </div>
    </div>

    <!-- Columnas de Resultado -->
    <div class="ss-config-section" id="ss-result-columns-section" style="<?php echo empty($config['table_name']) ? 'display:none;' : ''; ?>">
        <h3>
            <span class="dashicons dashicons-editor-table"></span>
            <?php _e('Columnas de Resultado', 'secop-suite'); ?>
        </h3>

        <div style="padding: 20px;">
            <p class="description"><?php _e('Seleccione las columnas que se mostrarán en la lista de resultados.', 'secop-suite'); ?></p>

            <div id="ss-result-columns-container">
                <!-- Populated via JS -->
            </div>
        </div>
    </div>

    <!-- Opciones de Resultado -->
    <div class="ss-config-section">
        <h3>
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('Opciones de Resultado', 'secop-suite'); ?>
        </h3>

        <table class="form-table ss-form-table">
            <tr>
                <th>
                    <label for="ss_results_per_page"><?php _e('Resultados por Página', 'secop-suite'); ?></label>
                </th>
                <td>
                    <input type="number" name="ss_results_per_page" id="ss_results_per_page"
                           value="<?php echo esc_attr($config['results_per_page'] ?? 20); ?>"
                           min="5" max="100" class="small-text" />
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_filter_order_by"><?php _e('Ordenar Por', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_filter_order_by" id="ss_filter_order_by" class="ss-filter-column-select" data-saved-value="<?php echo esc_attr($config['order_by'] ?? 'fecha_de_firma_del_contrato'); ?>">
                        <option value=""><?php _e('-- Seleccionar --', 'secop-suite'); ?></option>
                    </select>
                    <select name="ss_filter_order_dir" id="ss_filter_order_dir">
                        <option value="DESC" <?php selected($config['order_dir'] ?? 'DESC', 'DESC'); ?>><?php _e('Descendente', 'secop-suite'); ?></option>
                        <option value="ASC" <?php selected($config['order_dir'] ?? 'DESC', 'ASC'); ?>><?php _e('Ascendente', 'secop-suite'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><?php _e('Enlace al Proceso', 'secop-suite'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="ss_show_url_link" value="1"
                               <?php checked(!empty($config['show_url_link'])); ?> />
                        <?php _e('Mostrar icono de enlace al proceso (url_contrato) al final de cada fila', 'secop-suite'); ?>
                    </label>
                    <br><br>
                    <label>
                        <?php _e('Campo URL:', 'secop-suite'); ?>
                        <select name="ss_url_field" class="ss-filter-column-select" data-saved-value="<?php echo esc_attr($config['url_field'] ?? 'url_contrato'); ?>">
                            <option value=""><?php _e('-- Seleccionar --', 'secop-suite'); ?></option>
                        </select>
                    </label>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- Template para campo de filtro -->
<script type="text/template" id="ss-filter-field-template">
    <div class="ss-filter-field-row" style="background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 15px; margin-bottom: 10px;">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
            <div>
                <label><strong><?php _e('Columna', 'secop-suite'); ?></strong></label>
                <select name="ss_filter_fields[{{index}}][column]" class="widefat ss-filter-column-select">
                    <option value=""><?php _e('-- Seleccionar --', 'secop-suite'); ?></option>
                </select>
            </div>
            <div>
                <label><strong><?php _e('Etiqueta', 'secop-suite'); ?></strong></label>
                <input type="text" name="ss_filter_fields[{{index}}][label]" class="widefat" placeholder="<?php esc_attr_e('Nombre visible', 'secop-suite'); ?>" />
            </div>
            <div>
                <label><strong><?php _e('Tipo de Campo', 'secop-suite'); ?></strong></label>
                <select name="ss_filter_fields[{{index}}][type]" class="widefat">
                    <option value="input"><?php _e('Texto (input)', 'secop-suite'); ?></option>
                    <option value="select"><?php _e('Lista desplegable (select)', 'secop-suite'); ?></option>
                    <option value="range"><?php _e('Rango (desde-hasta)', 'secop-suite'); ?></option>
                    <option value="checkbox"><?php _e('Opciones múltiples (checkbox)', 'secop-suite'); ?></option>
                </select>
            </div>
        </div>
        <div class="ss-field-values-preview" style="margin-top:8px;font-size:12px;color:#555;display:none;"></div>
        <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end;">
            <div>
                <label><strong><?php _e('Placeholder', 'secop-suite'); ?></strong></label>
                <input type="text" name="ss_filter_fields[{{index}}][placeholder]" class="widefat" placeholder="<?php esc_attr_e('Texto de ayuda', 'secop-suite'); ?>" />
            </div>
            <div>
                <label><strong><?php _e('Operador', 'secop-suite'); ?></strong></label>
                <select name="ss_filter_fields[{{index}}][operator]" class="widefat">
                    <option value="="><?php _e('Igual (=)', 'secop-suite'); ?></option>
                    <option value="LIKE" selected><?php _e('Contiene (LIKE)', 'secop-suite'); ?></option>
                    <option value="!="><?php _e('Diferente (!=)', 'secop-suite'); ?></option>
                    <option value=">"><?php _e('Mayor que (>)', 'secop-suite'); ?></option>
                    <option value="<"><?php _e('Menor que (<)', 'secop-suite'); ?></option>
                    <option value=">="><?php _e('Mayor o igual (>=)', 'secop-suite'); ?></option>
                    <option value="<="><?php _e('Menor o igual (<=)', 'secop-suite'); ?></option>
                </select>
            </div>
            <div>
                <button type="button" class="button ss-remove-filter-field" style="color: #d63638;">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
    </div>
</script>

<!-- Datos guardados para JS -->
<script type="text/javascript">
    var ssFilterSavedConfig = <?php echo wp_json_encode($config); ?>;
</script>
