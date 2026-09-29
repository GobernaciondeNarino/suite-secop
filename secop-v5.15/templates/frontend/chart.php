<?php
/**
 * Template: Renderizado de gráfica en el frontend
 * 
 * @package SecopSuite
 * 
 * Variables disponibles:
 * - $unique_id: ID único del contenedor
 * - $chart_id: ID del post de la gráfica
 * - $config: Configuración de la gráfica
 * - $extra_class: Clases CSS adicionales
 */

if (!defined('ABSPATH')) {
    exit;
}

// Allow callers (e.g. sc_chart with a dependencia att) to set an initial filter.
// Normal [secop_chart] renders leave this unset → no attribute → no filter override.
$dependencia_filter = $dependencia_filter ?? '';

// v5.1.9: click-to-drill (popup con contratos de la categoría). Off por defecto.
$drill_enabled = $drill_enabled ?? false;
$drill_column  = $drill_column ?? '';

$chart_title = get_the_title($chart_id);
$show_toolbar = $config['show_toolbar'] ?? true;
$toolbar_options = $config['toolbar_options'] ?? ['detail', 'share', 'data', 'image', 'download'];
$chart_height = $config['chart_height'] ?? 400;
?>

<div id="<?php echo esc_attr($unique_id); ?>"
     class="ss-chart-container<?php echo esc_attr($extra_class); ?>"
     data-chart-id="<?php echo esc_attr($chart_id); ?>"
     data-chart-type="<?php echo esc_attr($config['chart_type']); ?>"
     <?php if ($dependencia_filter !== '') : ?>data-dependencia="<?php echo esc_attr($dependencia_filter); ?>"<?php endif; ?>>
    
    <?php if ($show_toolbar): ?>
    <!-- Barra de herramientas -->
    <div class="ss-toolbar" role="toolbar" aria-label="<?php esc_attr_e('Herramientas de la gráfica', 'secop-suite'); ?>">
        <?php if (in_array('detail', $toolbar_options)): ?>
        <button type="button" class="ss-toolbar-btn" data-action="detail" aria-label="<?php esc_attr_e('Ver detalle de la gráfica', 'secop-suite'); ?>" title="<?php esc_attr_e('Detalle', 'secop-suite'); ?>">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span><?php esc_html_e('Detalle', 'secop-suite'); ?></span>
        </button>
        <?php endif; ?>

        <?php if (in_array('share', $toolbar_options)): ?>
        <button type="button" class="ss-toolbar-btn" data-action="share" aria-label="<?php esc_attr_e('Compartir gráfica', 'secop-suite'); ?>" title="<?php esc_attr_e('Compartir', 'secop-suite'); ?>">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="18" cy="5" r="3"></circle>
                <circle cx="6" cy="12" r="3"></circle>
                <circle cx="18" cy="19" r="3"></circle>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
            </svg>
            <span><?php esc_html_e('Compartir', 'secop-suite'); ?></span>
        </button>
        <?php endif; ?>

        <?php if (in_array('data', $toolbar_options)): ?>
        <button type="button" class="ss-toolbar-btn" data-action="data" aria-label="<?php esc_attr_e('Ver datos de la gráfica', 'secop-suite'); ?>" title="<?php esc_attr_e('Datos', 'secop-suite'); ?>">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="3" y1="9" x2="21" y2="9"></line>
                <line x1="3" y1="15" x2="21" y2="15"></line>
                <line x1="9" y1="3" x2="9" y2="21"></line>
                <line x1="15" y1="3" x2="15" y2="21"></line>
            </svg>
            <span><?php esc_html_e('Datos', 'secop-suite'); ?></span>
        </button>
        <?php endif; ?>

        <?php if (in_array('image', $toolbar_options)): ?>
        <button type="button" class="ss-toolbar-btn" data-action="image" aria-label="<?php esc_attr_e('Descargar como imagen', 'secop-suite'); ?>" title="<?php esc_attr_e('Imagen', 'secop-suite'); ?>">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <polyline points="21 15 16 10 5 21"></polyline>
            </svg>
            <span><?php esc_html_e('Imagen', 'secop-suite'); ?></span>
        </button>
        <?php endif; ?>

        <?php if (in_array('download', $toolbar_options)): ?>
        <button type="button" class="ss-toolbar-btn" data-action="download" aria-label="<?php esc_attr_e('Descargar datos CSV', 'secop-suite'); ?>" title="<?php esc_attr_e('Descarga', 'secop-suite'); ?>">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            <span><?php esc_html_e('Descarga', 'secop-suite'); ?></span>
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Contenedor de la gráfica -->
    <div class="ss-chart-wrapper" style="height: <?php echo intval($chart_height); ?>px;">
        <!-- Loading -->
        <div class="ss-loading">
            <div class="ss-spinner"></div>
            <p><?php esc_html_e('Cargando datos...', 'secop-suite'); ?></p>
        </div>

        <!-- Chart render area -->
        <div class="ss-chart-render" id="<?php echo esc_attr($unique_id); ?>-render"></div>

        <!-- Error message -->
        <div class="ss-error-message" style="display: none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <p><?php esc_html_e('Error al cargar los datos', 'secop-suite'); ?></p>
        </div>
    </div>
</div>

<!-- Modal para ver datos -->
<div class="ss-modal" id="<?php echo esc_attr($unique_id); ?>-data-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Datos de la gráfica', 'secop-suite'); ?>" style="display: none;">
    <div class="ss-modal-overlay"></div>
    <div class="ss-modal-content">
        <div class="ss-modal-header">
            <h3><?php echo esc_html($chart_title); ?> - <?php esc_html_e('Datos', 'secop-suite'); ?></h3>
            <button type="button" class="ss-modal-close">&times;</button>
        </div>
        <div class="ss-modal-body">
            <div class="ss-data-table-wrapper">
                <table class="ss-data-table">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="ss-modal-footer">
            <button type="button" class="ss-btn ss-btn-secondary ss-modal-close-btn"><?php esc_html_e('Cerrar', 'secop-suite'); ?></button>
            <button type="button" class="ss-btn ss-btn-primary" data-action="download-from-modal"><?php esc_html_e('Descargar CSV', 'secop-suite'); ?></button>
        </div>
    </div>
</div>

<!-- Modal para compartir -->
<div class="ss-modal ss-share-modal" id="<?php echo esc_attr($unique_id); ?>-share-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Compartir gráfica', 'secop-suite'); ?>" style="display: none;">
    <div class="ss-modal-overlay"></div>
    <div class="ss-modal-content ss-modal-small">
        <div class="ss-modal-header">
            <h3><?php esc_html_e('Compartir', 'secop-suite'); ?></h3>
            <button type="button" class="ss-modal-close">&times;</button>
        </div>
        <div class="ss-modal-body">
            <div class="ss-share-buttons">
                <a href="#" class="ss-share-btn ss-share-facebook" data-network="facebook" title="Facebook">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/>
                    </svg>
                </a>
                <a href="#" class="ss-share-btn ss-share-twitter" data-network="twitter" title="Twitter/X">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                </a>
                <a href="#" class="ss-share-btn ss-share-linkedin" data-network="linkedin" title="LinkedIn">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                    </svg>
                </a>
                <a href="#" class="ss-share-btn ss-share-whatsapp" data-network="whatsapp" title="WhatsApp">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </a>
                <button type="button" class="ss-share-btn ss-share-copy" data-action="copy-link" title="<?php esc_attr_e('Copiar enlace', 'secop-suite'); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                </button>
            </div>
            <div class="ss-share-link">
                <input type="text" readonly value="<?php echo esc_url(get_permalink() . '#' . $unique_id); ?>" />
            </div>
        </div>
    </div>
</div>

<!-- Modal de detalle -->
<div class="ss-modal ss-detail-modal" id="<?php echo esc_attr($unique_id); ?>-detail-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Detalle de la gráfica', 'secop-suite'); ?>" style="display: none;">
    <div class="ss-modal-overlay"></div>
    <div class="ss-modal-content ss-modal-small">
        <div class="ss-modal-header">
            <h3><?php esc_html_e('Información de la Gráfica', 'secop-suite'); ?></h3>
            <button type="button" class="ss-modal-close">&times;</button>
        </div>
        <div class="ss-modal-body">
            <table class="ss-detail-table">
                <tr>
                    <th><?php esc_html_e('Título', 'secop-suite'); ?></th>
                    <td><?php echo esc_html($chart_title); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Tipo', 'secop-suite'); ?></th>
                    <td><?php echo esc_html(ucfirst($config['chart_type'])); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Fuente', 'secop-suite'); ?></th>
                    <td>SECOP - Sistema Electrónico de Contratación Pública</td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Entidad', 'secop-suite'); ?></th>
                    <td>Gobernación de Nariño</td>
                </tr>
                <?php if (!empty($config['date_from']) || !empty($config['date_to'])): ?>
                <tr>
                    <th><?php esc_html_e('Período', 'secop-suite'); ?></th>
                    <td>
                        <?php 
                        $from = $config['date_from'] ?? 'Inicio';
                        $to = $config['date_to'] ?? 'Actualidad';
                        echo esc_html($from . ' - ' . $to);
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e('Última actualización', 'secop-suite'); ?></th>
                    <td><?php echo esc_html(get_the_modified_date('', $chart_id) . ' ' . get_the_modified_time('', $chart_id)); ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- Configuración JSON para JavaScript -->
<script type="application/json" id="<?php echo esc_attr($unique_id); ?>-config">
<?php echo wp_json_encode([
    'chartId' => $chart_id,
    'uniqueId' => $unique_id,
    'type' => $config['chart_type'],
    'colors' => $config['colors'] ?? ['#844e80', '#ff7300', '#ffc53b', '#3eba6a', '#0080c3'],
    'height' => $chart_height,
    'showLegend' => !empty($config['show_legend']),
    'legendMode' => $config['legend_mode'] ?? 'text',
    'legendPosition' => $config['legend_position'] ?? 'bottom',
    'showTimeline' => !empty($config['show_timeline']),
    'yAxisTitle' => $config['y_axis_title'] ?? '',
    'xAxisTitle' => $config['x_axis_title'] ?? '',
    'numberFormat' => $config['number_format'] ?? 'colombiano',
    // v5.3.2: campos del tooltip. Ausente en [secop_chart] → frontend usa el
    // valor por defecto ['categoria','valor'] (comportamiento previo intacto).
    'tooltipFields' => (!empty($config['tooltip_fields']) && is_array($config['tooltip_fields'])) ? $config['tooltip_fields'] : ['categoria', 'valor'],
    'multiY' => !empty($config['y_fields']),
    'title' => $chart_title,
    'chartNonce' => wp_create_nonce('secop_suite_chart_' . $chart_id),
    'drill' => (bool) $drill_enabled,
    'drillColumn' => (string) $drill_column,
], JSON_HEX_TAG | JSON_HEX_APOS); ?>
</script>
