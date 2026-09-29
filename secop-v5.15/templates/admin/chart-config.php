<?php
/**
 * Template: Configuración de gráfica
 * 
 * @package SecopSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

$chart_types = [
    'bar' => __('Barras', 'secop-suite'),
    'line' => __('Líneas', 'secop-suite'),
    'area' => __('Área', 'secop-suite'),
    'pie' => __('Pie / Torta', 'secop-suite'),
    'donut' => __('Donut', 'secop-suite'),
    'treemap' => __('Treemap', 'secop-suite'),
    'tree' => __('Árbol (Tree)', 'secop-suite'),
    'pack' => __('Burbujas (Pack)', 'secop-suite'),
    'network' => __('Red (Network)', 'secop-suite'),
    'stacked_bar' => __('Barras Apiladas', 'secop-suite'),
    'grouped_bar' => __('Barras Agrupadas', 'secop-suite'),
];

$aggregates = [
    'SUM' => __('Suma (SUM)', 'secop-suite'),
    'COUNT' => __('Contar (COUNT)', 'secop-suite'),
    'AVG' => __('Promedio (AVG)', 'secop-suite'),
    'MAX' => __('Máximo (MAX)', 'secop-suite'),
    'MIN' => __('Mínimo (MIN)', 'secop-suite'),
];

$number_formats = [
    'colombiano' => __('Colombiano (1.000.000)', 'secop-suite'),
    'millones' => __('Millones (1M, 100MMll)', 'secop-suite'),
    'internacional' => __('Internacional (1,000,000)', 'secop-suite'),
    'sin_formato' => __('Sin formato', 'secop-suite'),
];

$default_colors = '#844e80,#ff7300,#ffc53b,#3eba6a,#0080c3,#e74c3c,#9b59b6,#1abc9c';
?>

<div class="ss-config-wrapper">
    <!-- Tipo de Gráfica -->
    <div class="ss-config-section">
        <h3>
            <span class="dashicons dashicons-chart-bar"></span>
            <?php _e('Tipo de Gráfica', 'secop-suite'); ?>
        </h3>
        
        <div class="ss-chart-types">
            <?php foreach ($chart_types as $type => $label): ?>
                <label class="ss-chart-type-option <?php echo ($config['chart_type'] ?? 'bar') === $type ? 'selected' : ''; ?>">
                    <input type="radio" 
                           name="ss_chart_type" 
                           value="<?php echo esc_attr($type); ?>"
                           <?php checked($config['chart_type'] ?? 'bar', $type); ?> />
                    <span class="ss-chart-type-icon" data-type="<?php echo esc_attr($type); ?>"></span>
                    <span class="ss-chart-type-label"><?php echo esc_html($label); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <!-- Heat Map Guide (Accordion) -->
        <div class="ss-chart-guide" id="ss-chart-guide">
            <p class="ss-guide-toggle" id="ss-guide-toggle" style="cursor:pointer; user-select:none;">
                <span class="dashicons dashicons-lightbulb"></span>
                <?php _e('Guía de Variables Recomendadas', 'secop-suite'); ?>
                <span id="ss-guide-chart-name" style="color: var(--ss-primary);"></span>
                <span class="dashicons dashicons-arrow-down-alt2 ss-guide-arrow" style="float:right;"></span>
            </p>
            <div class="ss-guide-content" id="ss-guide-content" style="display:none;">
                <div class="ss-guide-matrix" id="ss-guide-matrix"></div>
                <div class="ss-guide-legend">
                    <span class="ss-guide-legend-item"><span class="ss-guide-legend-swatch" style="background: #22c55e;"></span> <?php _e('Óptimo', 'secop-suite'); ?></span>
                    <span class="ss-guide-legend-item"><span class="ss-guide-legend-swatch" style="background: #facc15;"></span> <?php _e('Compatible', 'secop-suite'); ?></span>
                    <span class="ss-guide-legend-item"><span class="ss-guide-legend-swatch" style="background: #e5e7eb;"></span> <?php _e('Posible', 'secop-suite'); ?></span>
                    <span class="ss-guide-legend-item"><span class="ss-guide-legend-swatch" style="background: #fca5a5;"></span> <?php _e('No recomendado', 'secop-suite'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Fuente de Datos -->
    <div class="ss-config-section">
        <h3>
            <span class="dashicons dashicons-database"></span>
            <?php _e('Fuente de Datos', 'secop-suite'); ?>
        </h3>

        <table class="form-table ss-form-table">
            <tr>
                <th>
                    <label for="ss_table_name"><?php _e('Tabla de Datos', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_table_name" id="ss_table_name" class="regular-text">
                        <option value=""><?php _e('-- Seleccionar tabla --', 'secop-suite'); ?></option>
                        <?php foreach ($tables as $table_name => $table_label): ?>
                            <option value="<?php echo esc_attr($table_name); ?>" 
                                    <?php selected($config['table_name'] ?? '', $table_name); ?>>
                                <?php echo esc_html($table_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Selecciona la tabla de donde se obtendrán los datos', 'secop-suite'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Configuración de Campos -->
    <div class="ss-config-section" id="ss-fields-section" style="<?php echo empty($config['table_name']) ? 'display:none;' : ''; ?>">
        <h3>
            <span class="dashicons dashicons-editor-table"></span>
            <?php _e('Configuración de Campos', 'secop-suite'); ?>
        </h3>

        <table class="form-table ss-form-table">
            <tr>
                <th>
                    <label for="ss_x_field"><?php _e('Eje X / Categoría', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_x_field" id="ss_x_field" class="regular-text ss-column-select">
                        <option value=""><?php _e('-- Seleccionar campo --', 'secop-suite'); ?></option>
                    </select>
                    <p class="description"><?php _e('Campo para el eje X o categorías (ej: año, modalidad)', 'secop-suite'); ?></p>
                </td>
            </tr>

            <tr class="ss-date-grouping-row" style="<?php echo empty($config['x_date_grouping']) ? 'display:none;' : ''; ?>">
                <th>
                    <label for="ss_x_date_grouping"><?php _e('Agrupar Fecha Por', 'secop-suite'); ?></label>
                </th>
                <td>
                    <?php
                    $date_groupings = [
                        'full' => __('Fecha Completa (día/mes/año)', 'secop-suite'),
                        'year' => __('Solo Año (2024, 2025...)', 'secop-suite'),
                        'month' => __('Año y Mes (2024-01, 2024-02...)', 'secop-suite'),
                        'month_name' => __('Nombre del Mes (Enero, Febrero...)', 'secop-suite'),
                        'quarter' => __('Trimestre (2024-Q1, 2024-Q2...)', 'secop-suite'),
                        'week' => __('Semana del Año (2024-W01...)', 'secop-suite'),
                    ];
                    ?>
                    <select name="ss_x_date_grouping" id="ss_x_date_grouping" class="regular-text">
                        <?php foreach ($date_groupings as $grouping => $label): ?>
                            <option value="<?php echo esc_attr($grouping); ?>" 
                                    <?php selected($config['x_date_grouping'] ?? 'year', $grouping); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Selecciona cómo agrupar los datos cuando el Eje X es una fecha', 'secop-suite'); ?></p>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_y_field"><?php _e('Eje Y / Valor', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_y_field" id="ss_y_field" class="regular-text ss-column-select">
                        <option value=""><?php _e('-- Seleccionar campo --', 'secop-suite'); ?></option>
                    </select>
                    <p class="description"><?php _e('Campo numérico para el eje Y (ej: valor_contrato)', 'secop-suite'); ?></p>
                </td>
            </tr>

            <!-- Multi Y Fields (optional, for bar types) -->
            <tr class="ss-multi-y-row" id="ss-multi-y-row" style="display:none;">
                <th>
                    <label><?php _e('Valores Y Múltiples', 'secop-suite'); ?></label>
                </th>
                <td>
                    <label style="display:flex; align-items:center; gap:6px; margin-bottom:10px;">
                        <input type="checkbox" id="ss-enable-multi-y" <?php checked(!empty($config['y_fields'])); ?> />
                        <strong><?php _e('Habilitar múltiples campos Y', 'secop-suite'); ?></strong>
                    </label>
                    <div id="ss-multi-y-content" style="<?php echo empty($config['y_fields']) ? 'display:none;' : ''; ?>">
                    <p class="description" style="margin-bottom:10px;">
                        <?php _e('Cada campo Y será una serie independiente en la gráfica (ej: Apropiación vigente vs Apropiación inicial).', 'secop-suite'); ?>
                    </p>
                    <div id="ss-y-fields-container">
                        <?php
                        $y_fields = $config['y_fields'] ?? [];
                        foreach ($y_fields as $index => $yf):
                        ?>
                            <div class="ss-y-field-row">
                                <select name="ss_y_fields[<?php echo $index; ?>][column]" class="ss-column-select ss-y-field-select" data-saved-value="<?php echo esc_attr($yf['column'] ?? ''); ?>">
                                    <option value=""><?php _e('-- Campo Y --', 'secop-suite'); ?></option>
                                </select>
                                <input type="text"
                                       name="ss_y_fields[<?php echo $index; ?>][label]"
                                       class="ss-y-field-label"
                                       value="<?php echo esc_attr($yf['label'] ?? ''); ?>"
                                       placeholder="<?php esc_attr_e('Etiqueta (ej: Apropiación vigente)', 'secop-suite'); ?>" />
                                <button type="button" class="button ss-remove-y-field">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button" id="ss-add-y-field">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e('Añadir Campo Y', 'secop-suite'); ?>
                    </button>
                    <p class="description" style="margin-top:10px; color:#2271b1;">
                        <span class="dashicons dashicons-info" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php _e('Cuando se activa, el campo "Eje Y / Valor" simple se ignora y cada campo Y genera su propia serie.', 'secop-suite'); ?>
                    </p>
                    </div><!-- ss-multi-y-content -->
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_aggregate"><?php _e('Función de Agregación', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_aggregate" id="ss_aggregate" class="regular-text">
                        <?php foreach ($aggregates as $agg => $label): ?>
                            <option value="<?php echo esc_attr($agg); ?>" 
                                    <?php selected($config['aggregate'] ?? 'SUM', $agg); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_group_by"><?php _e('Agrupar Por (Series)', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_group_by" id="ss_group_by" class="regular-text ss-column-select">
                        <option value=""><?php _e('-- Sin agrupación adicional --', 'secop-suite'); ?></option>
                    </select>
                    <p class="description"><?php _e('Campo adicional para crear múltiples series (opcional)', 'secop-suite'); ?></p>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_color_field"><?php _e('Color Por', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_color_field" id="ss_color_field" class="regular-text ss-column-select">
                        <option value=""><?php _e('-- Usar eje X --', 'secop-suite'); ?></option>
                    </select>
                    <p class="description"><?php _e('Campo para asignar colores diferentes', 'secop-suite'); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Filtros de Datos -->
    <div class="ss-config-section" id="ss-filters-section" style="<?php echo empty($config['table_name']) ? 'display:none;' : ''; ?>">
        <h3>
            <span class="dashicons dashicons-filter"></span>
            <?php _e('Filtros de Datos', 'secop-suite'); ?>
        </h3>

        <table class="form-table ss-form-table">
            <tr>
                <th>
                    <label for="ss_date_field"><?php _e('Campo de Fecha', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_date_field" id="ss_date_field" class="regular-text ss-column-select">
                        <option value=""><?php _e('-- Seleccionar campo --', 'secop-suite'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th>
                    <label><?php _e('Rango de Fechas', 'secop-suite'); ?></label>
                </th>
                <td>
                    <div class="ss-date-range">
                        <input type="date" 
                               name="ss_date_from" 
                               id="ss_date_from"
                               value="<?php echo esc_attr($config['date_from'] ?? ''); ?>" />
                        <span><?php _e('hasta', 'secop-suite'); ?></span>
                        <input type="date" 
                               name="ss_date_to" 
                               id="ss_date_to"
                               value="<?php echo esc_attr($config['date_to'] ?? ''); ?>" />
                    </div>
                </td>
            </tr>

            <tr>
                <th>
                    <label><?php _e('Filtros Adicionales', 'secop-suite'); ?></label>
                </th>
                <td>
                    <div id="ss-filters-container">
                        <?php 
                        $filters = $config['filters'] ?? [];
                        if (empty($filters)) {
                            $filters = [['field' => '', 'operator' => '=', 'value' => '']];
                        }
                        foreach ($filters as $index => $filter): 
                        ?>
                            <div class="ss-filter-row">
                                <select name="ss_filters[<?php echo $index; ?>][field]" class="ss-column-select ss-filter-field">
                                    <option value=""><?php _e('Campo', 'secop-suite'); ?></option>
                                </select>
                                <select name="ss_filters[<?php echo $index; ?>][operator]" class="ss-filter-operator">
                                    <option value="=" <?php selected($filter['operator'] ?? '', '='); ?>>=</option>
                                    <option value="!=" <?php selected($filter['operator'] ?? '', '!='); ?>>!=</option>
                                    <option value=">" <?php selected($filter['operator'] ?? '', '>'); ?>>&gt;</option>
                                    <option value="<" <?php selected($filter['operator'] ?? '', '<'); ?>>&lt;</option>
                                    <option value=">=" <?php selected($filter['operator'] ?? '', '>='); ?>>&gt;=</option>
                                    <option value="<=" <?php selected($filter['operator'] ?? '', '<='); ?>>&lt;=</option>
                                    <option value="LIKE" <?php selected($filter['operator'] ?? '', 'LIKE'); ?>>LIKE</option>
                                </select>
                                <input type="text" 
                                       name="ss_filters[<?php echo $index; ?>][value]" 
                                       class="ss-filter-value"
                                       value="<?php echo esc_attr($filter['value'] ?? ''); ?>"
                                       placeholder="<?php esc_attr_e('Valor', 'secop-suite'); ?>" />
                                <button type="button" class="button ss-remove-filter">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button" id="ss-add-filter">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e('Añadir Filtro', 'secop-suite'); ?>
                    </button>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_limit"><?php _e('Límite de Registros', 'secop-suite'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           name="ss_limit" 
                           id="ss_limit"
                           value="<?php echo esc_attr($config['limit'] ?? ''); ?>"
                           min="0"
                           placeholder="<?php esc_attr_e('Sin límite', 'secop-suite'); ?>" 
                           class="small-text" />
                    <p class="description"><?php _e('Dejar vacío para mostrar todos los datos', 'secop-suite'); ?></p>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_order_by"><?php _e('Ordenar Por', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_order_by" id="ss_order_by" class="ss-column-select">
                        <option value=""><?php _e('-- Sin ordenar --', 'secop-suite'); ?></option>
                    </select>
                    <select name="ss_order_dir" id="ss_order_dir">
                        <option value="DESC" <?php selected($config['order_dir'] ?? 'DESC', 'DESC'); ?>>
                            <?php _e('Descendente', 'secop-suite'); ?>
                        </option>
                        <option value="ASC" <?php selected($config['order_dir'] ?? 'DESC', 'ASC'); ?>>
                            <?php _e('Ascendente', 'secop-suite'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>
    </div>

    <!-- Apariencia -->
    <div class="ss-config-section">
        <h3>
            <span class="dashicons dashicons-admin-appearance"></span>
            <?php _e('Apariencia', 'secop-suite'); ?>
        </h3>

        <table class="form-table ss-form-table">
            <tr>
                <th>
                    <label for="ss_chart_height"><?php _e('Altura de la Gráfica', 'secop-suite'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           name="ss_chart_height" 
                           id="ss_chart_height"
                           value="<?php echo esc_attr($config['chart_height'] ?? 400); ?>"
                           min="200"
                           max="1000"
                           class="small-text" /> px
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_y_axis_title"><?php _e('Título Eje Y', 'secop-suite'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           name="ss_y_axis_title" 
                           id="ss_y_axis_title"
                           value="<?php echo esc_attr($config['y_axis_title'] ?? 'Valor en Pesos Colombianos'); ?>"
                           class="regular-text" />
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_x_axis_title"><?php _e('Título Eje X', 'secop-suite'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           name="ss_x_axis_title" 
                           id="ss_x_axis_title"
                           value="<?php echo esc_attr($config['x_axis_title'] ?? ''); ?>"
                           class="regular-text" />
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_number_format"><?php _e('Formato de Números', 'secop-suite'); ?></label>
                </th>
                <td>
                    <select name="ss_number_format" id="ss_number_format">
                        <?php foreach ($number_formats as $format => $label): ?>
                            <option value="<?php echo esc_attr($format); ?>" 
                                    <?php selected($config['number_format'] ?? 'colombiano', $format); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th>
                    <label for="ss_colors"><?php _e('Paleta de Colores', 'secop-suite'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           name="ss_colors" 
                           id="ss_colors"
                           value="<?php echo esc_attr(implode(',', $config['colors'] ?? explode(',', $default_colors))); ?>"
                           class="large-text"
                           placeholder="<?php echo esc_attr($default_colors); ?>" />
                    <p class="description"><?php _e('Colores hexadecimales separados por coma', 'secop-suite'); ?></p>
                    <div class="ss-color-preview" id="ss-color-preview"></div>
                </td>
            </tr>

            <tr>
                <th><?php _e('Leyenda', 'secop-suite'); ?></th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="ss_show_legend"
                               id="ss_show_legend_check"
                               value="1"
                               <?php checked(!empty($config['show_legend'])); ?> />
                        <?php _e('Mostrar leyenda', 'secop-suite'); ?>
                    </label>

                    <div class="ss-legend-options" style="<?php echo empty($config['show_legend']) ? 'display:none;' : ''; ?> margin-top:10px; padding:10px; background:#f6f7f7; border-radius:4px;">
                        <div style="display:flex; gap:20px; flex-wrap:wrap;">
                            <div>
                                <label style="font-weight:600; font-size:12px; display:block; margin-bottom:4px;"><?php _e('Modo', 'secop-suite'); ?></label>
                                <select name="ss_legend_mode" id="ss_legend_mode" style="min-width:150px;">
                                    <option value="text" <?php selected($config['legend_mode'] ?? 'text', 'text'); ?>><?php _e('Texto + Icono', 'secop-suite'); ?></option>
                                    <option value="icon" <?php selected($config['legend_mode'] ?? 'text', 'icon'); ?>><?php _e('Solo Icono', 'secop-suite'); ?></option>
                                </select>
                            </div>
                            <div>
                                <label style="font-weight:600; font-size:12px; display:block; margin-bottom:4px;"><?php _e('Posición', 'secop-suite'); ?></label>
                                <select name="ss_legend_position" id="ss_legend_position" style="min-width:150px;">
                                    <option value="bottom" <?php selected($config['legend_position'] ?? 'bottom', 'bottom'); ?>><?php _e('Abajo', 'secop-suite'); ?></option>
                                    <option value="left" <?php selected($config['legend_position'] ?? 'bottom', 'left'); ?>><?php _e('Izquierda', 'secop-suite'); ?></option>
                                    <option value="right" <?php selected($config['legend_position'] ?? 'bottom', 'right'); ?>><?php _e('Derecha', 'secop-suite'); ?></option>
                                    <option value="top" <?php selected($config['legend_position'] ?? 'bottom', 'top'); ?>><?php _e('Arriba', 'secop-suite'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <th><?php _e('Línea de Tiempo', 'secop-suite'); ?></th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="ss_show_timeline"
                               value="1"
                               <?php checked(!empty($config['show_timeline'])); ?> />
                        <?php _e('Mostrar línea de tiempo interactiva', 'secop-suite'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <!-- Barra de Herramientas -->
    <div class="ss-config-section">
        <h3>
            <span class="dashicons dashicons-admin-tools"></span>
            <?php _e('Barra de Herramientas', 'secop-suite'); ?>
        </h3>

        <table class="form-table ss-form-table">
            <tr>
                <th><?php _e('Mostrar Barra', 'secop-suite'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="ss_show_toolbar" 
                               id="ss_show_toolbar"
                               value="1"
                               <?php checked($config['show_toolbar'] ?? true); ?> />
                        <?php _e('Mostrar barra de herramientas superior', 'secop-suite'); ?>
                    </label>
                </td>
            </tr>

            <tr class="ss-toolbar-options" style="<?php echo empty($config['show_toolbar']) && isset($config['show_toolbar']) ? 'display:none;' : ''; ?>">
                <th><?php _e('Opciones a Mostrar', 'secop-suite'); ?></th>
                <td>
                    <?php
                    $toolbar_options = $config['toolbar_options'] ?? ['detail', 'share', 'data', 'image', 'download'];
                    $available_options = [
                        'detail' => __('Detalle (info)', 'secop-suite'),
                        'share' => __('Compartir', 'secop-suite'),
                        'data' => __('Ver Datos', 'secop-suite'),
                        'image' => __('Guardar Imagen', 'secop-suite'),
                        'download' => __('Descargar CSV', 'secop-suite'),
                    ];
                    foreach ($available_options as $option => $label):
                    ?>
                        <label class="ss-toolbar-option">
                            <input type="checkbox" 
                                   name="ss_toolbar_options[]" 
                                   value="<?php echo esc_attr($option); ?>"
                                   <?php checked(in_array($option, $toolbar_options)); ?> />
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Query Personalizada (Avanzado) -->
    <div class="ss-config-section ss-advanced-section">
        <h3>
            <span class="dashicons dashicons-editor-code"></span>
            <?php _e('Query Personalizada (Avanzado)', 'secop-suite'); ?>
            <button type="button" class="button button-small ss-toggle-advanced">
                <?php _e('Expandir', 'secop-suite'); ?>
            </button>
        </h3>

        <div class="ss-advanced-content" style="display: none;">
            <table class="form-table ss-form-table">
                <tr>
                    <th>
                        <label>
                            <input type="checkbox" 
                                   name="ss_use_custom_query" 
                                   id="ss_use_custom_query"
                                   value="1"
                                   <?php checked(!empty($config['custom_query'])); ?> />
                            <?php _e('Usar Query SQL Personalizada', 'secop-suite'); ?>
                        </label>
                    </th>
                    <td>
                        <p class="description ss-warning">
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('⚠️ Solo usar si sabes lo que haces. La query debe retornar columnas x_value, y_value y opcionalmente group_value.', 'secop-suite'); ?>
                        </p>
                    </td>
                </tr>

                <tr class="ss-custom-query-row" style="<?php echo empty($config['custom_query']) ? 'display:none;' : ''; ?>">
                    <td colspan="2">
                        <textarea name="ss_custom_query" 
                                  id="ss_custom_query"
                                  class="large-text code"
                                  rows="8"
                                  placeholder="SELECT YEAR(fecha_de_firma_del_contrato) AS x_value, modalidad_de_contratacion AS group_value, SUM(valor_contrato) AS y_value FROM wp_secop_contracts GROUP BY x_value, group_value"><?php echo esc_textarea($config['custom_query'] ?? ''); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- Template para filtros adicionales -->
<script type="text/template" id="ss-filter-template">
    <div class="ss-filter-row">
        <select name="ss_filters[{{index}}][field]" class="ss-column-select ss-filter-field">
            <option value=""><?php _e('Campo', 'secop-suite'); ?></option>
        </select>
        <select name="ss_filters[{{index}}][operator]" class="ss-filter-operator">
            <option value="=">=</option>
            <option value="!=">!=</option>
            <option value=">">&gt;</option>
            <option value="<">&lt;</option>
            <option value=">=">&gt;=</option>
            <option value="<=">&lt;=</option>
            <option value="LIKE">LIKE</option>
        </select>
        <input type="text" 
               name="ss_filters[{{index}}][value]" 
               class="ss-filter-value"
               placeholder="<?php esc_attr_e('Valor', 'secop-suite'); ?>" />
        <button type="button" class="button ss-remove-filter">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>
</script>

<!-- Template para campos Y adicionales -->
<script type="text/template" id="ss-y-field-template">
    <div class="ss-y-field-row">
        <select name="ss_y_fields[{{index}}][column]" class="ss-column-select ss-y-field-select">
            <option value=""><?php _e('-- Campo Y --', 'secop-suite'); ?></option>
        </select>
        <input type="text"
               name="ss_y_fields[{{index}}][label]"
               class="ss-y-field-label"
               placeholder="<?php esc_attr_e('Etiqueta (ej: Apropiación vigente)', 'secop-suite'); ?>" />
        <button type="button" class="button ss-remove-y-field">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>
</script>

<!-- Datos guardados para JS -->
<script type="text/javascript">
    var ssSavedConfig = <?php echo wp_json_encode($config); ?>;
</script>
