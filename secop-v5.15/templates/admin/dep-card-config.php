<?php if (!defined('ABSPATH')) exit; ?>
<table class="form-table">
  <tr>
    <th><label for="dep_dimension"><?php esc_html_e('Dimensión', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_dimension" id="dep_dimension">
        <?php foreach ($dim_labels as $dim => $label) : if (!isset($dimensions[$dim])) continue; ?>
          <option value="<?php echo esc_attr($dim); ?>" <?php selected($config['dimension'] ?? '', $dim); ?>>
            <?php echo esc_html($label); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="description"><?php esc_html_e('Cada dimensión admite ciertos tipos de gráfica.', 'secop-suite'); ?></p>
    </td>
  </tr>
  <tr>
    <th><label for="dep_metric"><?php esc_html_e('Métrica', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_metric" id="dep_metric">
        <?php foreach ($metrics as $key => $m) : ?>
          <option value="<?php echo esc_attr($key); ?>" <?php selected($config['metric'] ?? 'valor_contrato', $key); ?>>
            <?php echo esc_html($m['label']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="description"><?php esc_html_e('Valor a medir en el eje de la gráfica.', 'secop-suite'); ?></p>
    </td>
  </tr>
  <tr>
    <th><label for="dep_order"><?php esc_html_e('Ordenar por', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_order" id="dep_order">
        <option value="valor" <?php selected($config['order'] ?? 'valor', 'valor'); ?>><?php esc_html_e('Valor', 'secop-suite'); ?></option>
        <option value="etiqueta" <?php selected($config['order'] ?? 'valor', 'etiqueta'); ?>><?php esc_html_e('Etiqueta', 'secop-suite'); ?></option>
      </select>
      <select name="dep_order_dir" id="dep_order_dir">
        <option value="DESC" <?php selected($config['order_dir'] ?? 'DESC', 'DESC'); ?>><?php esc_html_e('Descendente', 'secop-suite'); ?></option>
        <option value="ASC" <?php selected($config['order_dir'] ?? 'DESC', 'ASC'); ?>><?php esc_html_e('Ascendente', 'secop-suite'); ?></option>
      </select>
      <p class="description"><?php esc_html_e('En la dimensión «Mensual» el orden es siempre cronológico.', 'secop-suite'); ?></p>
    </td>
  </tr>
  <tr>
    <th><label for="dep_limit"><?php esc_html_e('Límite de filas', 'secop-suite'); ?></label></th>
    <td>
      <input type="number" name="dep_limit" id="dep_limit" min="0" step="1"
        value="<?php echo esc_attr((string) (int) ($config['limit'] ?? 0)); ?>" class="small-text">
      <p class="description"><?php esc_html_e('Número máximo de categorías a mostrar. 0 = sin límite (por defecto se aplican 50, excepto en «Mensual»).', 'secop-suite'); ?></p>
    </td>
  </tr>
  <tr>
    <th><label for="dep_chart_type"><?php esc_html_e('Tipo de gráfica', 'secop-suite'); ?></label></th>
    <td>
      <?php
      // Solo los tipos compatibles con la dimensión ACTUAL, sin duplicados. Al
      // cambiar la dimensión, dep-card-preview.js reconstruye este desplegable.
      $cur_dim = $config['dimension'] ?? 'dependencia';
      if (!isset($dimensions[$cur_dim])) {
          $cur_dim = (string) array_key_first($dimensions);
      }
      $cur_types = $dimensions[$cur_dim];
      $cur_type  = $config['chart_type'] ?? '';
      if (!in_array($cur_type, $cur_types, true)) {
          $cur_type = $cur_types[0] ?? 'bar';
      }
      ?>
      <select name="dep_chart_type" id="dep_chart_type">
        <?php foreach ($cur_types as $tp) : ?>
          <option value="<?php echo esc_attr($tp); ?>" <?php selected($cur_type, $tp); ?>>
            <?php echo esc_html($chart_type_labels[$tp] ?? $tp); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="dep_dependencia"><?php esc_html_e('Dependencia (opcional)', 'secop-suite'); ?></label></th>
    <td>
      <?php if (!empty($dependencias)) : ?>
        <select name="dep_dependencia" id="dep_dependencia">
          <option value="" <?php selected($config['dependencia'] ?? '', ''); ?>><?php esc_html_e('— Todas las dependencias —', 'secop-suite'); ?></option>
          <?php foreach ($dependencias as $dep) : ?>
            <option value="<?php echo esc_attr($dep); ?>" <?php selected($config['dependencia'] ?? '', $dep); ?>>
              <?php echo esc_html($dep); ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php else : ?>
        <input type="text" name="dep_dependencia" id="dep_dependencia"
          value="<?php echo esc_attr($config['dependencia'] ?? ''); ?>" class="regular-text"
          placeholder="<?php esc_attr_e('Vacío = todas las dependencias', 'secop-suite'); ?>">
      <?php endif; ?>
    </td>
  </tr>
  <tr>
    <th><label for="dep_colors"><?php esc_html_e('Colores', 'secop-suite'); ?></label></th>
    <td>
      <input type="text" name="dep_colors" id="dep_colors"
        value="<?php echo esc_attr(!empty($config['colors']) && is_array($config['colors']) ? implode(', ', $config['colors']) : ''); ?>"
        class="large-text" placeholder="#844e80, #ff7300, #ffc53b, #3eba6a, #0080c3, #e74c3c, #9b59b6, #1abc9c">
      <p class="description">
        <?php esc_html_e('Lista de colores en formato hexadecimal (#rrggbb) separados por comas. Vacío = paleta por defecto:', 'secop-suite'); ?>
        <code>#844e80, #ff7300, #ffc53b, #3eba6a, #0080c3, #e74c3c, #9b59b6, #1abc9c</code>
      </p>
    </td>
  </tr>
</table>

<h3 style="margin:1em 0 .5em;"><?php esc_html_e('Personalización del gráfico', 'secop-suite'); ?></h3>
<?php
// v5.3.0: opciones de personalización del gráfico (mirror del Visualizer).
$dep_number_formats = [
    'colombiano'    => __('Colombiano (1.000.000)', 'secop-suite'),
    'millones'      => __('Millones (1M)', 'secop-suite'),
    'internacional' => __('Internacional (1,000,000)', 'secop-suite'),
    'sin_formato'   => __('Sin formato', 'secop-suite'),
];
$dep_toolbar_options = [
    'detail'   => __('Detalle', 'secop-suite'),
    'share'    => __('Compartir', 'secop-suite'),
    'data'     => __('Datos', 'secop-suite'),
    'image'    => __('Imagen', 'secop-suite'),
    'download' => __('Descargar', 'secop-suite'),
];
$dep_sel_toolbar = (!empty($config['toolbar_options']) && is_array($config['toolbar_options']))
    ? $config['toolbar_options']
    : ['share', 'data', 'image', 'download'];
// v5.3.2: campos del tooltip (categoría / valor / nº de contratos).
$dep_tooltip_options = [
    'categoria' => __('Categoría', 'secop-suite'),
    'valor'     => __('Valor', 'secop-suite'),
    'conteo'    => __('Nº de contratos', 'secop-suite'),
];
$dep_sel_tooltip = (!empty($config['tooltip_fields']) && is_array($config['tooltip_fields']))
    ? $config['tooltip_fields']
    : ['categoria', 'valor'];
?>
<table class="form-table">
  <tr>
    <th><label for="dep_number_format"><?php esc_html_e('Formato de números', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_number_format" id="dep_number_format">
        <?php foreach ($dep_number_formats as $fmt => $label) : ?>
          <option value="<?php echo esc_attr($fmt); ?>" <?php selected($config['number_format'] ?? 'colombiano', $fmt); ?>>
            <?php echo esc_html($label); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="dep_chart_height"><?php esc_html_e('Altura (px)', 'secop-suite'); ?></label></th>
    <td>
      <input type="number" name="dep_chart_height" id="dep_chart_height" min="150" step="10"
        value="<?php echo esc_attr($config['chart_height'] ?? 400); ?>" class="small-text"> px
    </td>
  </tr>
  <tr>
    <th><label for="dep_x_title"><?php esc_html_e('Título Eje X', 'secop-suite'); ?></label></th>
    <td>
      <input type="text" name="dep_x_title" id="dep_x_title"
        value="<?php echo esc_attr($config['x_axis_title'] ?? ''); ?>" class="regular-text">
    </td>
  </tr>
  <tr>
    <th><label for="dep_y_title"><?php esc_html_e('Título Eje Y', 'secop-suite'); ?></label></th>
    <td>
      <input type="text" name="dep_y_title" id="dep_y_title"
        value="<?php echo esc_attr($config['y_axis_title'] ?? ''); ?>" class="regular-text">
    </td>
  </tr>
  <tr>
    <th><?php esc_html_e('Leyenda', 'secop-suite'); ?></th>
    <td>
      <label>
        <input type="checkbox" name="dep_show_legend" id="dep_show_legend" value="1" <?php checked($config['show_legend'] ?? true); ?>>
        <?php esc_html_e('Mostrar leyenda', 'secop-suite'); ?>
      </label>
    </td>
  </tr>
  <tr>
    <th><label for="dep_legend_mode"><?php esc_html_e('Modo de leyenda', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_legend_mode" id="dep_legend_mode">
        <option value="text" <?php selected($config['legend_mode'] ?? 'text', 'text'); ?>><?php esc_html_e('Texto + Icono', 'secop-suite'); ?></option>
        <option value="icon" <?php selected($config['legend_mode'] ?? 'text', 'icon'); ?>><?php esc_html_e('Solo Icono', 'secop-suite'); ?></option>
      </select>
    </td>
  </tr>
  <tr>
    <th><label for="dep_legend_position"><?php esc_html_e('Posición de leyenda', 'secop-suite'); ?></label></th>
    <td>
      <select name="dep_legend_position" id="dep_legend_position">
        <option value="bottom" <?php selected($config['legend_position'] ?? 'bottom', 'bottom'); ?>><?php esc_html_e('Abajo', 'secop-suite'); ?></option>
        <option value="top" <?php selected($config['legend_position'] ?? 'bottom', 'top'); ?>><?php esc_html_e('Arriba', 'secop-suite'); ?></option>
        <option value="left" <?php selected($config['legend_position'] ?? 'bottom', 'left'); ?>><?php esc_html_e('Izquierda', 'secop-suite'); ?></option>
        <option value="right" <?php selected($config['legend_position'] ?? 'bottom', 'right'); ?>><?php esc_html_e('Derecha', 'secop-suite'); ?></option>
      </select>
    </td>
  </tr>
  <tr>
    <th><?php esc_html_e('Barra de herramientas', 'secop-suite'); ?></th>
    <td>
      <label>
        <input type="checkbox" name="dep_show_toolbar" id="dep_show_toolbar" value="1" <?php checked($config['show_toolbar'] ?? true); ?>>
        <?php esc_html_e('Mostrar barra de herramientas', 'secop-suite'); ?>
      </label>
      <p style="margin:8px 0 0;">
        <?php foreach ($dep_toolbar_options as $opt => $label) : ?>
          <label style="margin-right:14px; display:inline-block;">
            <input type="checkbox" name="dep_toolbar_options[]" value="<?php echo esc_attr($opt); ?>"
              <?php checked(in_array($opt, $dep_sel_toolbar, true)); ?>>
            <?php echo esc_html($label); ?>
          </label>
        <?php endforeach; ?>
      </p>
    </td>
  </tr>
  <tr>
    <th><?php esc_html_e('Campos del tooltip', 'secop-suite'); ?></th>
    <td>
      <p style="margin:0 0 4px;">
        <?php foreach ($dep_tooltip_options as $opt => $label) : ?>
          <label style="margin-right:14px; display:inline-block;">
            <input type="checkbox" name="dep_tooltip_fields[]" value="<?php echo esc_attr($opt); ?>"
              <?php checked(in_array($opt, $dep_sel_tooltip, true)); ?>>
            <?php echo esc_html($label); ?>
          </label>
        <?php endforeach; ?>
      </p>
      <p class="description"><?php esc_html_e('Datos que aparecen al pasar el cursor sobre cada barra/sección. «Nº de contratos» muestra los contratos distintos de la categoría.', 'secop-suite'); ?></p>
    </td>
  </tr>
</table>

<?php
// v5.3.1: Filtros configurables (columna/operador/valor).
$dep_filter_ops = [
    '='    => __('Igual =', 'secop-suite'),
    '!='   => __('Distinto !=', 'secop-suite'),
    '>'    => __('Mayor >', 'secop-suite'),
    '<'    => __('Menor <', 'secop-suite'),
    '>='   => __('Mayor o igual >=', 'secop-suite'),
    '<='   => __('Menor o igual <=', 'secop-suite'),
    'LIKE' => __('Contiene LIKE', 'secop-suite'),
];
$dep_saved_filters = (!empty($config['filters']) && is_array($config['filters'])) ? array_values($config['filters']) : [];
?>
<h3 style="margin:1em 0 .5em;"><?php esc_html_e('Filtros', 'secop-suite'); ?></h3>
<p class="description" style="margin-bottom:.5em;">
  <?php esc_html_e('Restrinja los datos por columna, operador y valor. Se aplican además del año vigente y la dependencia.', 'secop-suite'); ?>
</p>
<div id="dep-filters-rows">
  <?php
  // Renderiza las filas guardadas más una fila vacía al final.
  $dep_filter_render = $dep_saved_filters;
  $dep_filter_render[] = ['field' => '', 'operator' => '=', 'value' => ''];
  foreach ($dep_filter_render as $i => $f) :
      $f_field = $f['field'] ?? '';
      $f_op    = $f['operator'] ?? '=';
      $f_val   = $f['value'] ?? '';
  ?>
    <div class="dep-filter-row" style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
      <select name="dep_filters[<?php echo (int) $i; ?>][field]" class="dep-filter-field">
        <option value=""><?php esc_html_e('— Columna —', 'secop-suite'); ?></option>
        <?php foreach ($filter_columns as $col => $label) : ?>
          <option value="<?php echo esc_attr($col); ?>" <?php selected($f_field, $col); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
      </select>
      <select name="dep_filters[<?php echo (int) $i; ?>][operator]" class="dep-filter-operator">
        <?php foreach ($dep_filter_ops as $op => $op_label) : ?>
          <option value="<?php echo esc_attr($op); ?>" <?php selected($f_op, $op); ?>><?php echo esc_html($op_label); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="dep_filters[<?php echo (int) $i; ?>][value]" class="dep-filter-value"
        value="<?php echo esc_attr($f_val); ?>" placeholder="<?php esc_attr_e('Valor', 'secop-suite'); ?>">
      <button type="button" class="button dep-filter-remove" title="<?php esc_attr_e('Quitar', 'secop-suite'); ?>"><?php esc_html_e('Quitar', 'secop-suite'); ?></button>
    </div>
  <?php endforeach; ?>
</div>
<p>
  <button type="button" class="button" id="dep-filter-add">
    <span class="dashicons dashicons-plus-alt2" style="vertical-align:text-bottom"></span>
    <?php esc_html_e('Añadir filtro', 'secop-suite'); ?>
  </button>
</p>

<script type="text/template" id="dep-filter-row-tpl">
  <div class="dep-filter-row" style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
    <select name="dep_filters[{{i}}][field]" class="dep-filter-field">
      <option value=""><?php esc_html_e('— Columna —', 'secop-suite'); ?></option>
      <?php foreach ($filter_columns as $col => $label) : ?>
        <option value="<?php echo esc_attr($col); ?>"><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
    <select name="dep_filters[{{i}}][operator]" class="dep-filter-operator">
      <?php foreach ($dep_filter_ops as $op => $op_label) : ?>
        <option value="<?php echo esc_attr($op); ?>"><?php echo esc_html($op_label); ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="dep_filters[{{i}}][value]" class="dep-filter-value" placeholder="<?php esc_attr_e('Valor', 'secop-suite'); ?>">
    <button type="button" class="button dep-filter-remove" title="<?php esc_attr_e('Quitar', 'secop-suite'); ?>"><?php esc_html_e('Quitar', 'secop-suite'); ?></button>
  </div>
</script>
