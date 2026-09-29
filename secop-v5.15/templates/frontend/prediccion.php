<?php
/**
 * Template: Predicción de contratación (serie mensual + proyección).
 *
 * Evolución mensual del valor contratado según el MES DEL CONTRATO (columna
 * `fecha`, DD/MM/YYYY) con una línea de proyección PUNTEADA a fin de vigencia.
 * Los datos se cargan por AJAX (secop_dep_prediccion) y se renderizan con
 * assets/js/dep-prediccion.js (d3plus.LinePlot).
 *
 * Variables disponibles:
 * - $atts array  Atributos del shortcode (dependencia, height, selector).
 * - $deps array  Lista de dependencias para el selector (vacío si selector="off").
 * - $uid  string ID único del contenedor.
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="ss-pred-module">
    <?php if (!empty($deps)) : ?>
        <div class="ss-pred-controls">
            <label><?php esc_html_e('Dependencia:', 'secop-suite'); ?>
                <select class="ss-pred-selector">
                    <option value=""><?php esc_html_e('— Todas —', 'secop-suite'); ?></option>
                    <?php foreach ($deps as $d) : ?>
                        <option value="<?php echo esc_attr($d); ?>" <?php selected($atts['dependencia'], $d); ?>><?php echo esc_html($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    <?php endif; ?>

    <div id="<?php echo esc_attr($uid); ?>" class="ss-pred-wrapper"
         data-dependencia="<?php echo esc_attr($atts['dependencia']); ?>"
         style="min-height:<?php echo (int) $atts['height']; ?>px">
        <div class="ss-pred-chart" style="min-height:<?php echo (int) $atts['height']; ?>px"></div>
        <div class="ss-pred-meta"></div>
    </div>
</div>
