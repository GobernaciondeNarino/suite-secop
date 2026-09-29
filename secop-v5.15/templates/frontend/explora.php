<?php
/**
 * Template: Explorador interactivo de contratación [secop_dep_explora] (v5.6.0).
 *
 * Treemap de dependencias (vigencia actual). Al hacer clic en una celda se
 * despliega un panel inferior dividido en dos: a la izquierda la lista de
 * modalidades (clicables) y a la derecha un acordeón de contratistas cuyos
 * elementos se expanden para mostrar los contratos del contratista. Todo se
 * actualiza por AJAX (secop_dep_explora_*). El renderizado lo gestiona
 * assets/js/dep-explora.js, que lee la config JSON inline.
 *
 * Variables disponibles:
 * - $atts          array  Atributos del shortcode (campos, height).
 * - $campos        array  Campos de fila 1 validados (orden de columnas de la tabla).
 * - $field_labels  array  [columna => etiqueta] de explora_fields().
 * - $csv_url       string URL REST de descarga de TODA la vista (vigencia actual).
 * - $uid           string ID único del contenedor.
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}

$cfg = [
    'uid'         => $uid,
    'campos'      => array_values($campos),
    'fieldLabels' => $field_labels,
];
?>
<div id="<?php echo esc_attr($uid); ?>" class="ss-explora-wrapper" data-uid="<?php echo esc_attr($uid); ?>">
    <script type="application/json" id="<?php echo esc_attr($uid); ?>-cfg"><?php
        echo wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_APOS);
    ?></script>

    <div class="ss-explora-header">
        <a class="ss-explora-download button" href="<?php echo esc_url($csv_url); ?>" target="_blank" rel="noopener">
            <?php esc_html_e('Descargar datos (vista completa)', 'secop-suite'); ?>
        </a>
    </div>

    <div class="ss-explora-tree" style="min-height:<?php echo (int) $atts['height']; ?>px">
        <div class="ss-explora-loading"><?php esc_html_e('Cargando dependencias…', 'secop-suite'); ?></div>
    </div>

    <div class="ss-explora-panel" style="display:none">
        <div class="ss-explora-dep-title"></div>
        <div class="ss-explora-cols">
            <div class="ss-explora-modalidades">
                <h4><?php esc_html_e('Modalidades', 'secop-suite'); ?></h4>
                <ul class="ss-explora-mod-list"></ul>
            </div>
            <div class="ss-explora-contratistas">
                <h4><?php esc_html_e('Contratistas', 'secop-suite'); ?></h4>
                <div class="ss-explora-acc"></div>
            </div>
        </div>
    </div>
</div>
