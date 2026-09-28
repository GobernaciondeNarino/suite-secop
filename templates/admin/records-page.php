<?php
/**
 * Template: Página de registros
 * 
 * @package SecopSuite
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ss-admin-wrap">
    <h1>
        <span class="dashicons dashicons-list-view"></span>
        <?php esc_html_e('Registros de Contratos', 'secop-suite'); ?>
    </h1>

    <!-- Pestañas de navegación -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=secop-suite-records&tab=actual')); ?>"
           class="nav-tab<?php echo ($tab === 'actual') ? ' nav-tab-active' : ''; ?>">
            <?php esc_html_e('Actual', 'secop-suite'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=secop-suite-records&tab=consulta')); ?>"
           class="nav-tab<?php echo ($tab === 'consulta') ? ' nav-tab-active' : ''; ?>">
            <?php esc_html_e('Consulta', 'secop-suite'); ?>
        </a>
    </nav>

<?php if ($tab === 'actual'): ?>

    <!-- Filtros -->
    <div class="ss-filters-panel">
        <form method="get" class="ss-filters-form">
            <input type="hidden" name="page" value="secop-suite-records" />
            
            <div class="ss-filter-group">
                <label for="search"><?php esc_html_e('Buscar', 'secop-suite'); ?></label>
                <input type="text"
                       id="search"
                       name="search"
                       value="<?php echo esc_attr($_GET['search'] ?? ''); ?>"
                       placeholder="<?php esc_attr_e('Contratista, objeto, número...', 'secop-suite'); ?>" />
            </div>

            <div class="ss-filter-group">
                <label for="anno"><?php esc_html_e('Año', 'secop-suite'); ?></label>
                <select id="anno" name="anno">
                    <option value=""><?php esc_html_e('Todos los años', 'secop-suite'); ?></option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo esc_attr($year); ?>" <?php selected($_GET['anno'] ?? '', $year); ?>>
                            <?php echo esc_html($year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ss-filter-group">
                <label for="estado"><?php esc_html_e('Estado', 'secop-suite'); ?></label>
                <select id="estado" name="estado">
                    <option value=""><?php esc_html_e('Todos los estados', 'secop-suite'); ?></option>
                    <?php foreach ($estados as $estado): ?>
                        <option value="<?php echo esc_attr($estado); ?>" <?php selected($_GET['estado'] ?? '', $estado); ?>>
                            <?php echo esc_html($estado); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ss-filter-actions">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e('Filtrar', 'secop-suite'); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=secop-suite-records')); ?>" class="button">
                    <?php esc_html_e('Limpiar', 'secop-suite'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Resumen -->
    <div class="ss-records-summary">
        <p>
            <?php
            printf(
                esc_html__('Mostrando %1$d de %2$d registros', 'secop-suite'),
                count($records),
                $total_records
            );
            ?>
        </p>
    </div>

    <!-- Tabla de registros -->
    <?php if (!empty($records)): ?>
        <div class="ss-table-responsive">
            <table class="wp-list-table widefat fixed striped ss-records-table">
                <thead>
                    <tr>
                        <th scope="col" class="column-referencia"><?php esc_html_e('Referencia', 'secop-suite'); ?></th>
                        <th scope="col" class="column-proveedor"><?php esc_html_e('Proveedor', 'secop-suite'); ?></th>
                        <th scope="col" class="column-tipo"><?php esc_html_e('Tipo', 'secop-suite'); ?></th>
                        <th scope="col" class="column-valor"><?php esc_html_e('Valor', 'secop-suite'); ?></th>
                        <th scope="col" class="column-fecha"><?php esc_html_e('Fecha Firma', 'secop-suite'); ?></th>
                        <th scope="col" class="column-estado"><?php esc_html_e('Estado', 'secop-suite'); ?></th>
                        <th scope="col" class="column-acciones"><?php esc_html_e('Acciones', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td class="column-referencia">
                                <strong><?php echo esc_html($record->numero_del_contrato); ?></strong>
                                <div class="row-actions">
                                    <span class="id"><?php echo esc_html($record->numero_de_proceso); ?></span>
                                </div>
                            </td>
                            <td class="column-proveedor">
                                <span class="ss-proveedor-name"><?php echo esc_html($record->nom_raz_social_contratista); ?></span>
                                <div class="row-actions">
                                    <span class="doc"><?php echo esc_html($record->tipo_documento_proveedor); ?>: <?php echo esc_html($record->documento_proveedor); ?></span>
                                </div>
                            </td>
                            <td class="column-tipo">
                                <?php echo esc_html($record->tipo_de_contrato); ?>
                                <div class="row-actions">
                                    <span class="modalidad"><?php echo esc_html($record->modalidad_de_contratacion); ?></span>
                                </div>
                            </td>
                            <td class="column-valor">
                                <strong>$<?php echo esc_html(number_format((float)$record->valor_contrato, 0, ',', '.')); ?></strong>
                            </td>
                            <td class="column-fecha">
                                <?php echo $record->fecha_de_firma_del_contrato ? esc_html(date_i18n('d/m/Y', strtotime($record->fecha_de_firma_del_contrato))) : '-'; ?>
                            </td>
                            <td class="column-estado">
                                <?php
                                $estado_class = 'ss-estado-default';
                                $estado_value = $record->estado_del_proceso ?? '';
                                if (stripos($estado_value, 'aprobado') !== false || stripos($estado_value, 'activo') !== false) {
                                    $estado_class = 'ss-estado-aprobado';
                                } elseif (stripos($estado_value, 'liquidado') !== false) {
                                    $estado_class = 'ss-estado-liquidado';
                                } elseif (stripos($estado_value, 'terminado') !== false || stripos($estado_value, 'modificado') !== false) {
                                    $estado_class = 'ss-estado-terminado';
                                }
                                ?>
                                <span class="ss-estado <?php echo esc_attr($estado_class); ?>">
                                    <?php echo esc_html($estado_value); ?>
                                </span>
                            </td>
                            <td class="column-acciones">
                                <button type="button"
                                        class="button button-small ss-view-details"
                                        data-id="<?php echo esc_attr($record->id); ?>"
                                        title="<?php esc_attr_e('Ver detalles', 'secop-suite'); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                                <?php if (!empty($record->url_contrato)): ?>
                                    <a href="<?php echo esc_url($record->url_contrato); ?>"
                                       target="_blank"
                                       class="button button-small"
                                       title="<?php esc_attr_e('Ver en SECOP', 'secop-suite'); ?>">
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
            <div class="ss-pagination">
                <?php
                $base_url = admin_url('admin.php?page=secop-suite-records');
                if (!empty($_GET['search'])) {
                    $base_url = add_query_arg('search', sanitize_text_field($_GET['search']), $base_url);
                }
                if (!empty($_GET['anno'])) {
                    $base_url = add_query_arg('anno', sanitize_text_field($_GET['anno']), $base_url);
                }
                if (!empty($_GET['estado'])) {
                    $base_url = add_query_arg('estado', sanitize_text_field($_GET['estado']), $base_url);
                }

                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%', $base_url),
                    'format' => '',
                    'prev_text' => '&laquo; ' . __('Anterior', 'secop-suite'),
                    'next_text' => __('Siguiente', 'secop-suite') . ' &raquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                ]);
                ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="ss-no-records">
            <span class="dashicons dashicons-info"></span>
            <p><?php esc_html_e('No se encontraron registros con los filtros seleccionados.', 'secop-suite'); ?></p>
        </div>
    <?php endif; ?>

<?php elseif ($tab === 'consulta'): ?>

    <!-- Tab Consulta: datos del VIEW vista_secop_sysman, vigencia actual -->
    <div class="ss-records-summary" style="margin-top:12px;">
        <p>
            <?php
            printf(
                esc_html__('Ejecución de contratos — vigencia %d (máx. 200 filas, ordenado por valor ejecutado desc.)', 'secop-suite'),
                (int) current_time('Y')
            );
            ?>
        </p>
    </div>

    <?php if (!empty($consulta_rows)): ?>
        <div class="ss-table-responsive">
            <table class="wp-list-table widefat fixed striped ss-records-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Dependencia', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('N° Proceso', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('N° Contrato', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Tercero', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Débito', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Crédito', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Saldo x Ejecutar', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Valor Contrato', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Año', 'secop-suite'); ?></th>
                        <th><?php esc_html_e('Mes', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consulta_rows as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row['nombredependencia'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['numero_de_proceso'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['numero_del_contrato'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['nombretercero'] ?? ''); ?></td>
                            <td>$<?php echo esc_html(number_format((float)($row['valordebito'] ?? 0), 0, ',', '.')); ?></td>
                            <td>$<?php echo esc_html(number_format((float)($row['valorcredito'] ?? 0), 0, ',', '.')); ?></td>
                            <td>$<?php echo esc_html(number_format((float)($row['saldoporejecutaresp'] ?? 0), 0, ',', '.')); ?></td>
                            <td>$<?php echo esc_html(number_format((float)($row['valor_contrato'] ?? 0), 0, ',', '.')); ?></td>
                            <td><?php echo esc_html($row['anio'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['mes'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="ss-no-records">
            <span class="dashicons dashicons-info"></span>
            <p>
                <?php esc_html_e('No hay datos de consulta para la vigencia actual.', 'secop-suite'); ?>
                <?php esc_html_e('Verifique el diagnóstico en el módulo Contratación o reactive el plugin para crear el VIEW.', 'secop-suite'); ?>
            </p>
        </div>
    <?php endif; ?>

<?php endif; // fin de pestañas ?>
</div>

<!-- Modal de detalles -->
<div id="ss-detail-modal" class="ss-modal" style="display: none;">
    <div class="ss-modal-content">
        <div class="ss-modal-header">
            <h2><?php esc_html_e('Detalles del Contrato', 'secop-suite'); ?></h2>
            <button type="button" class="ss-modal-close">&times;</button>
        </div>
        <div class="ss-modal-body" id="ss-detail-content">
            <!-- Contenido cargado por AJAX -->
        </div>
    </div>
</div>
