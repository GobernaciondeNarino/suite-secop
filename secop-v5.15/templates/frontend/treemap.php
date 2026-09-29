<?php
/**
 * Template: Treemap composable de contratación [secop_dep_treemap] (v5.10.0).
 *
 * Treemap independiente que comparte el estado de filtro a nivel de página con
 * las listas [secop_dep_lista] y otros treemaps presentes (filtrado cruzado vía
 * window.SecopCoord). Al hacer clic en una celda se conmuta el filtro de la
 * dimensión correspondiente (dependencia / modalidad / tipo_contrato); la
 * dimensión «contratistas» no participa como filtro (stateField vacío). El
 * render lo gestiona assets/js/dep-treemap.js, que lee la config JSON inline.
 *
 * Variables disponibles:
 * - $uid      string  ID único del contenedor.
 * - $cfg      array   Config JSON inline (uid, dimension, dimColumn, stateField,
 *                     metric, colors[], legend, legendmode, toolbar, csvUrl, limit).
 * - $toolbar  bool    Mostrar la barra de herramientas.
 * - $legend   bool    Mostrar la leyenda.
 * - $height   int     Altura mínima (px) del área del treemap.
 * - $csv_url  string  URL REST de descarga de TODA la vista (vigencia actual).
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="<?php echo esc_attr($uid); ?>" class="ss-ctree-wrapper ss-coord-el"
     data-uid="<?php echo esc_attr($uid); ?>">
    <script type="application/json" id="<?php echo esc_attr($uid); ?>-cfg"><?php
        echo wp_json_encode($cfg, JSON_HEX_TAG | JSON_HEX_APOS);
    ?></script>

    <?php if ($toolbar) : ?>
    <div class="ss-toolbar" role="toolbar" aria-label="<?php esc_attr_e('Herramientas del treemap', 'secop-suite'); ?>">
        <button type="button" class="ss-toolbar-btn" data-action="clear"
                aria-label="<?php esc_attr_e('Limpiar filtros', 'secop-suite'); ?>"
                title="<?php esc_attr_e('Limpiar', 'secop-suite'); ?>">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18"></path>
                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                <path d="M10 11v6M14 11v6"></path>
                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
            </svg>
            <span><?php esc_html_e('Limpiar', 'secop-suite'); ?></span>
        </button>

        <button type="button" class="ss-toolbar-btn" data-action="image"
                aria-label="<?php esc_attr_e('Descargar como imagen', 'secop-suite'); ?>"
                title="<?php esc_attr_e('Imagen', 'secop-suite'); ?>">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <polyline points="21 15 16 10 5 21"></polyline>
            </svg>
            <span><?php esc_html_e('Imagen', 'secop-suite'); ?></span>
        </button>

        <button type="button" class="ss-toolbar-btn" data-action="download"
                aria-label="<?php esc_attr_e('Descargar datos CSV (vista completa)', 'secop-suite'); ?>"
                title="<?php esc_attr_e('Datos', 'secop-suite'); ?>">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span><?php esc_html_e('Datos', 'secop-suite'); ?></span>
        </button>
    </div>
    <?php endif; ?>

    <div class="ss-ctree-chart" style="min-height:<?php echo (int) $height; ?>px">
        <div class="ss-ctree-loading"><?php esc_html_e('Cargando…', 'secop-suite'); ?></div>
    </div>

    <?php if ($legend) : ?>
    <div class="ss-ctree-legend" aria-hidden="false"></div>
    <?php endif; ?>
</div>
