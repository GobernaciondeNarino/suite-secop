<?php
/**
 * Template: Red ego de contratación (Rings d3plus).
 *
 * v5.12.0: al inicio (sin dependencia seleccionada) muestra la RED COMPLETA de
 * todos los nodos; al elegir una dependencia (vía el selector separado
 * [secop_dep_selector] o el inline opcional) se centra en ella en anillos
 * concéntricos. El centro proviene del estado compartido SecopCoord. Reutiliza el
 * endpoint AJAX secop_dep_network (limit=0); render en assets/js/dep-rings.js.
 *
 * Variables disponibles:
 * - $atts array  Atributos del shortcode (dependencia, height, selector).
 * - $deps array  Lista de dependencias para el selector inline (vacío si selector="off").
 * - $uid  string ID único del contenedor.
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="ss-rings-module">
    <?php if (!empty($deps)) : ?>
        <div class="ss-rings-controls">
            <label><?php esc_html_e('Dependencia central:', 'secop-suite'); ?>
                <select class="ss-rings-selector">
                    <option value=""><?php esc_html_e('— Toda la red —', 'secop-suite'); ?></option>
                    <?php foreach ($deps as $d) : ?>
                        <option value="<?php echo esc_attr($d); ?>" <?php selected($atts['dependencia'], $d); ?>><?php echo esc_html($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    <?php endif; ?>

    <div id="<?php echo esc_attr($uid); ?>" class="ss-rings-wrapper"
         data-dependencia="<?php echo esc_attr($atts['dependencia']); ?>"
         style="min-height:<?php echo (int) $atts['height']; ?>px">
        <div class="ss-rings-chart"></div>
    </div>
</div>
