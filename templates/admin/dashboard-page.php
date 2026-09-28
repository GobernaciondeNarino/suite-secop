<?php
/**
 * Template: Panel de Control (Dashboard)
 *
 * @package SecopSuite
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ss-admin-wrap">
    <h1><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('Panel de Control', 'secop-suite'); ?> <span class="ss-version">v<?php echo esc_html(SECOP_SUITE_VERSION); ?></span></h1>

    <div class="ss-dashboard">
        <!-- KPI Cards -->
        <div class="ss-cards">
            <div class="ss-card ss-card-primary">
                <div class="ss-card-icon"><span class="dashicons dashicons-media-text"></span></div>
                <div class="ss-card-content">
                    <h3><?php echo esc_html(number_format($total_records)); ?></h3>
                    <p><?php esc_html_e('Contratos Registrados', 'secop-suite'); ?></p>
                </div>
            </div>
            <div class="ss-card ss-card-success">
                <div class="ss-card-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="ss-card-content">
                    <h3>$<?php echo esc_html(number_format($total_value, 0, ',', '.')); ?></h3>
                    <p><?php esc_html_e('Valor Total Contratos', 'secop-suite'); ?></p>
                </div>
            </div>
            <div class="ss-card">
                <div class="ss-card-icon"><span class="dashicons dashicons-chart-bar"></span></div>
                <div class="ss-card-content">
                    <h3><?php echo esc_html($total_charts); ?></h3>
                    <p><?php esc_html_e('Gráficas Creadas', 'secop-suite'); ?></p>
                </div>
            </div>
            <div class="ss-card">
                <div class="ss-card-icon"><span class="dashicons dashicons-filter"></span></div>
                <div class="ss-card-content">
                    <h3><?php echo esc_html($total_filters); ?></h3>
                    <p><?php esc_html_e('Filtros Creados', 'secop-suite'); ?></p>
                </div>
            </div>
        </div>

        <!-- Status Row -->
        <div class="ss-dashboard-row">
            <!-- System Status -->
            <div class="ss-panel ss-panel-half">
                <h2><span class="dashicons dashicons-heart"></span> <?php esc_html_e('Estado del Sistema', 'secop-suite'); ?></h2>
                <table class="ss-status-table">
                    <tr>
                        <td><?php esc_html_e('Estado', 'secop-suite'); ?></td>
                        <td>
                            <?php if ($is_importing): ?>
                                <span class="ss-status-badge ss-status-warning"><span class="dashicons dashicons-update ss-spin"></span> <?php esc_html_e('Importando...', 'secop-suite'); ?></span>
                            <?php else: ?>
                                <span class="ss-status-badge ss-status-ok"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Operativo', 'secop-suite'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Última Importación', 'secop-suite'); ?></td>
                        <td><strong><?php echo $last_import ? esc_html(date_i18n('d M Y, H:i', strtotime($last_import))) : esc_html__('Nunca', 'secop-suite'); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('PHP', 'secop-suite'); ?></td>
                        <td><code><?php echo esc_html(PHP_VERSION); ?></code></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('WordPress', 'secop-suite'); ?></td>
                        <td><code><?php echo esc_html(get_bloginfo('version')); ?></code></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Plugin', 'secop-suite'); ?></td>
                        <td><code>v<?php echo esc_html(SECOP_SUITE_VERSION); ?></code></td>
                    </tr>
                </table>
            </div>

            <!-- Quick Actions -->
            <div class="ss-panel ss-panel-half">
                <h2><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('Acciones Rápidas', 'secop-suite'); ?></h2>
                <div class="ss-quick-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=secop-suite')); ?>" class="ss-quick-action">
                        <span class="ss-qa-icon" style="background: #e8f4fd;"><span class="dashicons dashicons-download" style="color: #2271b1;"></span></span>
                        <span class="ss-qa-text">
                            <strong><?php esc_html_e('Importar Datos', 'secop-suite'); ?></strong>
                            <small><?php esc_html_e('Ejecutar importación SECOP', 'secop-suite'); ?></small>
                        </span>
                    </a>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=secop_chart')); ?>" class="ss-quick-action">
                        <span class="ss-qa-icon" style="background: #e8fde8;"><span class="dashicons dashicons-chart-bar" style="color: #00a32a;"></span></span>
                        <span class="ss-qa-text">
                            <strong><?php esc_html_e('Nueva Gráfica', 'secop-suite'); ?></strong>
                            <small><?php esc_html_e('Crear visualización', 'secop-suite'); ?></small>
                        </span>
                    </a>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=secop_filter')); ?>" class="ss-quick-action">
                        <span class="ss-qa-icon" style="background: #fde8f4;"><span class="dashicons dashicons-filter" style="color: #9b59b6;"></span></span>
                        <span class="ss-qa-text">
                            <strong><?php esc_html_e('Nuevo Filtro', 'secop-suite'); ?></strong>
                            <small><?php esc_html_e('Crear filtro de búsqueda', 'secop-suite'); ?></small>
                        </span>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=secop-suite-records')); ?>" class="ss-quick-action">
                        <span class="ss-qa-icon" style="background: #fdf4e8;"><span class="dashicons dashicons-editor-table" style="color: #dba617;"></span></span>
                        <span class="ss-qa-text">
                            <strong><?php esc_html_e('Ver Registros', 'secop-suite'); ?></strong>
                            <small><?php esc_html_e('Explorar contratos', 'secop-suite'); ?></small>
                        </span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Contracts by Year -->
        <?php if (!empty($by_year)): ?>
        <div class="ss-panel">
            <h2><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Contratos por Año', 'secop-suite'); ?></h2>
            <div class="ss-year-bars">
                <?php
                $max_count = max(array_column($by_year, 'count'));
                foreach ($by_year as $row):
                    $pct = $max_count > 0 ? ($row->count / $max_count) * 100 : 0;
                ?>
                <div class="ss-year-bar-row">
                    <span class="ss-year-label"><?php echo esc_html($row->anno); ?></span>
                    <div class="ss-year-bar-track">
                        <div class="ss-year-bar-fill" style="width: <?php echo esc_attr($pct); ?>%;"></div>
                    </div>
                    <span class="ss-year-count"><?php echo esc_html(number_format((int)$row->count)); ?></span>
                    <span class="ss-year-value">$<?php echo esc_html(number_format((float)$row->total_value, 0, ',', '.')); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contracts by Type -->
        <?php if (!empty($by_type)): ?>
        <div class="ss-panel">
            <h2><span class="dashicons dashicons-category"></span> <?php esc_html_e('Top 10 Tipos de Contrato', 'secop-suite'); ?></h2>
            <div class="ss-type-grid">
                <?php
                $type_colors = ['#2271b1', '#00a32a', '#ff7300', '#e74c3c', '#9b59b6', '#1abc9c', '#dba617', '#844e80', '#3eba6a', '#0080c3'];
                foreach ($by_type as $i => $row):
                    $color = $type_colors[$i % count($type_colors)];
                ?>
                <div class="ss-type-card" style="border-left: 4px solid <?php echo esc_attr($color); ?>;">
                    <div class="ss-type-name"><?php echo esc_html($row->tipo_de_contrato ?: __('Sin tipo', 'secop-suite')); ?></div>
                    <div class="ss-type-stats">
                        <span class="ss-type-count"><?php echo esc_html(number_format((int)$row->count)); ?> <?php esc_html_e('contratos', 'secop-suite'); ?></span>
                        <span class="ss-type-value">$<?php echo esc_html(number_format((float)$row->total_value, 0, ',', '.')); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Modules Overview -->
        <div class="ss-panel">
            <h2><span class="dashicons dashicons-screenoptions"></span> <?php esc_html_e('Módulos del Plugin', 'secop-suite'); ?></h2>
            <div class="ss-modules-grid">
                <div class="ss-module-card">
                    <div class="ss-module-header" style="background: linear-gradient(135deg, #2271b1, #4fa3d9);">
                        <span class="dashicons dashicons-download"></span>
                        <h3><?php esc_html_e('Importación', 'secop-suite'); ?></h3>
                    </div>
                    <div class="ss-module-body">
                        <p><?php esc_html_e('Importación automatizada desde la API SECOP con procesamiento por lotes y sistema UPSERT.', 'secop-suite'); ?></p>
                        <div class="ss-module-shortcode"><code>WP-CLI: wp secop import</code></div>
                    </div>
                </div>
                <div class="ss-module-card">
                    <div class="ss-module-header" style="background: linear-gradient(135deg, #00a32a, #4dc94d);">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <h3><?php esc_html_e('Visualización', 'secop-suite'); ?></h3>
                    </div>
                    <div class="ss-module-body">
                        <p><?php esc_html_e('11 tipos de gráficas D3plus: Barras, Líneas, Área, Pie, Donut, Treemap, Árbol, Burbujas, Red, Apiladas, Agrupadas.', 'secop-suite'); ?></p>
                        <div class="ss-module-shortcode"><code>[secop_chart id="X"]</code></div>
                    </div>
                </div>
                <div class="ss-module-card">
                    <div class="ss-module-header" style="background: linear-gradient(135deg, #9b59b6, #c39bd3);">
                        <span class="dashicons dashicons-filter"></span>
                        <h3><?php esc_html_e('Filtros', 'secop-suite'); ?></h3>
                    </div>
                    <div class="ss-module-body">
                        <p><?php esc_html_e('Filtros de búsqueda configurables: input, select, rango, checkbox. Resultados con enlace al proceso SECOP.', 'secop-suite'); ?></p>
                        <div class="ss-module-shortcode"><code>[secop_filter id="X"]</code></div>
                    </div>
                </div>
                <div class="ss-module-card">
                    <div class="ss-module-header" style="background: linear-gradient(135deg, #e74c3c, #f19c99);">
                        <span class="dashicons dashicons-download"></span>
                        <h3><?php esc_html_e('Exportar Datos', 'secop-suite'); ?></h3>
                    </div>
                    <div class="ss-module-body">
                        <p><?php esc_html_e('Descarga datos en CSV, TXT o consume la API JSON en línea. Insertar en cualquier página con shortcode.', 'secop-suite'); ?></p>
                        <div class="ss-module-shortcode"><code>[secop_export]</code></div>
                    </div>
                </div>
                <div class="ss-module-card">
                    <div class="ss-module-header" style="background: linear-gradient(135deg, #dba617, #f0c040);">
                        <span class="dashicons dashicons-rest-api"></span>
                        <h3><?php esc_html_e('API REST', 'secop-suite'); ?></h3>
                    </div>
                    <div class="ss-module-body">
                        <p><?php esc_html_e('7 endpoints REST para contratos, estadísticas, gráficas y exportación con paginación y filtros.', 'secop-suite'); ?></p>
                        <div class="ss-module-shortcode"><code>/wp-json/secop-suite/v1/</code></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Shortcode Card -->
        <div class="ss-panel">
            <h2><span class="dashicons dashicons-download"></span> <?php esc_html_e('Exportar Datos — Shortcode', 'secop-suite'); ?></h2>
            <p class="description"><?php esc_html_e('Inserta este shortcode en cualquier página o entrada para mostrar botones de descarga (CSV, TXT) y enlace a la API JSON.', 'secop-suite'); ?></p>
            <div class="ss-shortcode-box" style="margin-top:12px;">
                <code id="ss-export-shortcode-display" style="display:block; padding:12px; background:#f6f7f7; border-radius:4px; font-size:14px;">[secop_export]</code>
                <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="button ss-copy-shortcode" onclick="navigator.clipboard.writeText('[secop_export]').then(function(){var b=event.target.closest('.button');b.textContent='✓ Copiado';setTimeout(function(){b.textContent='Copiar Shortcode';},1500);})">
                        <?php esc_html_e('Copiar Shortcode', 'secop-suite'); ?>
                    </button>
                    <span class="description" style="display:flex; align-items:center;"><?php esc_html_e('También puedes personalizar el título:', 'secop-suite'); ?> <code style="margin-left:6px;">[secop_export title="Datos Contratos"]</code></span>
                </div>
            </div>
            <div style="margin-top:16px;">
                <h4 style="margin:0 0 8px;"><?php esc_html_e('Endpoints de descarga directa:', 'secop-suite'); ?></h4>
                <div class="ss-api-docs">
                    <div class="ss-api-endpoint"><code>GET /wp-json/secop-suite/v1/export/csv</code> <span class="description"><?php esc_html_e('Descargar todos los contratos en CSV', 'secop-suite'); ?></span></div>
                    <div class="ss-api-endpoint"><code>GET /wp-json/secop-suite/v1/export/txt</code> <span class="description"><?php esc_html_e('Descargar todos los contratos en TXT', 'secop-suite'); ?></span></div>
                    <div class="ss-api-endpoint"><code>GET /wp-json/secop-suite/v1/contracts</code> <span class="description"><?php esc_html_e('API JSON con paginación y filtros', 'secop-suite'); ?></span></div>
                </div>
            </div>
        </div>
    </div>
</div>
