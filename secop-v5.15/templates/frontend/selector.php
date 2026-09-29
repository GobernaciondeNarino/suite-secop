<?php
/**
 * Template: Selector de filtro composable [secop_dep_selector] (v5.12.0).
 *
 * Un <select> independiente que fija un campo del estado compartido a nivel de
 * página (SecopCoord): dependencia / modalidad / tipo_contrato. Gobierna la red
 * ego [secop_dep_rings] y cualquier otro elemento suscrito (treemap, listas…).
 * El cambio lo procesa assets/js/dep-selector.js.
 *
 * Variables disponibles:
 * - $campo    string  Campo de estado: dependencia|modalidad|tipo_contrato.
 * - $titulo   string  Etiqueta visible (vacío = "Dependencia:").
 * - $todas    string  Etiqueta de la opción "sin filtro" (value="").
 * - $options  array   Lista de valores (strings) seleccionables.
 * - $uid      string  ID único del control.
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}
$label = ($titulo !== '') ? $titulo : __('Dependencia:', 'secop-suite');
?>
<div class="ss-dep-selector">
    <label for="<?php echo esc_attr($uid); ?>"><?php echo esc_html($label); ?>
        <select id="<?php echo esc_attr($uid); ?>" class="ss-dep-selector-coord"
                data-campo="<?php echo esc_attr($campo); ?>">
            <option value=""><?php echo esc_html($todas); ?></option>
            <?php foreach ($options as $opt) : ?>
                <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</div>
