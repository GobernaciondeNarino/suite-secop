<?php
/**
 * Template: Red de contratación (grafo de fuerza d3).
 *
 * Dependencias como nodos centrales conectadas a sus contratistas, tipos de
 * contrato y modalidades de contratación. Los datos se cargan por AJAX
 * (secop_dep_network) y se renderizan con assets/js/dep-network.js.
 *
 * Variables disponibles:
 * - $atts array  Atributos del shortcode (dependencia, limit, height, selector).
 * - $deps array  Lista de dependencias para el selector (vacío si selector="off").
 * - $uid  string ID único del contenedor.
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="ss-red-module">
    <?php if (!empty($deps)) : ?>
        <div class="ss-red-controls">
            <label><?php esc_html_e('Dependencia:', 'secop-suite'); ?>
                <select class="ss-red-selector">
                    <option value=""><?php esc_html_e('— Todas —', 'secop-suite'); ?></option>
                    <?php foreach ($deps as $d) : ?>
                        <option value="<?php echo esc_attr($d); ?>" <?php selected($atts['dependencia'], $d); ?>><?php echo esc_html($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    <?php endif; ?>

    <div id="<?php echo esc_attr($uid); ?>" class="ss-red-wrapper"
         data-dependencia="<?php echo esc_attr($atts['dependencia']); ?>"
         data-limit="<?php echo (int) $atts['limit']; ?>"
         style="min-height:<?php echo (int) $atts['height']; ?>px">
        <div class="ss-red-svg"></div>
        <div class="ss-red-legend"></div>
    </div>
</div>
