<?php
/**
 * Template: Datos Abiertos — Submenú admin.
 *
 * Documenta los shortcodes y endpoints REST de datos abiertos.
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Datos Abiertos — SECOP Suite', 'secop-suite'); ?></h1>

    <p><?php esc_html_e(
        'Estos shortcodes publican datos contractuales como datos abiertos, exponiendo endpoints REST accesibles públicamente en formatos JSON, CSV y TXT.',
        'secop-suite'
    ); ?></p>

    <h2><?php esc_html_e('Shortcodes disponibles', 'secop-suite'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Shortcode', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Parámetros', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Formatos', 'secop-suite'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[secop_export]</code></td>
                <td><?php esc_html_e(
                    'Todos los contratos importados (tabla completa SECOP). Muestra botones de descarga CSV, TXT y enlace JSON API REST hacia los endpoints /export y /contracts.',
                    'secop-suite'
                ); ?></td>
                <td><code>title</code>, <code>class</code></td>
                <td>CSV, TXT, JSON</td>
            </tr>
            <tr>
                <td><code>[secop_consulta formato="tabla"]</code></td>
                <td><?php esc_html_e(
                    'Seguimiento de ejecución presupuestal por dependencia para la vigencia actual (desde el VIEW de Sysman). Muestra tabla resumen y botones de descarga/API hacia los endpoints /consulta.',
                    'secop-suite'
                ); ?></td>
                <td><code>formato</code> — tabla | csv | txt | json</td>
                <td>tabla, CSV, TXT, JSON</td>
            </tr>
        </tbody>
    </table>

    <h2><?php esc_html_e('Endpoints REST', 'secop-suite'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Endpoint', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Formato', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/consulta')); ?></code></td>
                <td>JSON</td>
                <td><?php esc_html_e(
                    'Ejecución presupuestal de la vigencia actual, paginada. Parámetros: page (default 1), per_page (default 100, máx 1000). Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/consulta/csv')); ?></code></td>
                <td>CSV</td>
                <td><?php esc_html_e(
                    'Descarga CSV completa de la vigencia actual, ordenada por valor de débito descendente. Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/consulta/txt')); ?></code></td>
                <td>TXT</td>
                <td><?php esc_html_e(
                    'Descarga TXT ancho fijo de la vigencia actual, ordenada por valor de débito descendente. Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/export/csv')); ?></code></td>
                <td>CSV</td>
                <td><?php esc_html_e(
                    'Exportación CSV completa de todos los contratos SECOP (tabla principal). Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/export/txt')); ?></code></td>
                <td>TXT</td>
                <td><?php esc_html_e(
                    'Exportación TXT ancho fijo de todos los contratos SECOP (tabla principal). Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(rest_url('secop-suite/v1/contracts')); ?></code></td>
                <td>JSON</td>
                <td><?php esc_html_e(
                    'API REST de contratos SECOP con filtros: per_page, page, anno, estado, search, fecha_desde, fecha_hasta. Acceso público.',
                    'secop-suite'
                ); ?></td>
            </tr>
        </tbody>
    </table>

    <h2><?php esc_html_e('Ejemplos de uso de la API', 'secop-suite'); ?></h2>
    <p><?php esc_html_e(
        'Desde la versión 5.11.0 las APIs de Datos Abiertos aceptan filtros por cualquier campo directamente en la URL. Cada nombre de columna puede usarse como parámetro con estos sufijos:',
        'secop-suite'
    ); ?></p>
    <ul>
        <li><code>?columna=valor</code> — <?php esc_html_e('igualdad exacta.', 'secop-suite'); ?></li>
        <li><code>?columna_like=valor</code> — <?php esc_html_e('contiene (LIKE %valor%).', 'secop-suite'); ?></li>
        <li><code>?columna_min=valor</code> — <?php esc_html_e('mayor o igual (≥), para números/fechas.', 'secop-suite'); ?></li>
        <li><code>?columna_max=valor</code> — <?php esc_html_e('menor o igual (≤), para números/fechas.', 'secop-suite'); ?></li>
        <li><code>order_by=columna&order=asc|desc</code> — <?php esc_html_e('orden por columna y dirección.', 'secop-suite'); ?></li>
        <li><code>page</code>, <code>per_page</code> — <?php esc_html_e('paginación (JSON).', 'secop-suite'); ?></li>
    </ul>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Ejemplo', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Qué hace', 'secop-suite'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code><?php echo esc_html(esc_url(rest_url('secop-suite/v1/consulta'))); ?></code></td>
                <td><?php esc_html_e('Todos los registros de la vigencia actual, JSON paginado.', 'secop-suite'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(esc_url(rest_url('secop-suite/v1/consulta')) . '?nombredependencia=SECRETARIA DE EDUCACION'); ?></code></td>
                <td><?php esc_html_e('Filtrar por dependencia (igualdad exacta).', 'secop-suite'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(esc_url(rest_url('secop-suite/v1/consulta')) . '?modalidad_de_contratacion_like=directa'); ?></code></td>
                <td><?php esc_html_e('Modalidad que contiene "directa".', 'secop-suite'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(esc_url(rest_url('secop-suite/v1/consulta')) . '?valor_contrato_min=100000000&order_by=valor_contrato&order=desc'); ?></code></td>
                <td><?php esc_html_e('Contratos ≥ 100M, de mayor a menor.', 'secop-suite'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(esc_url(rest_url('secop-suite/v1/consulta/csv')) . '?tipo_de_contrato=Prestación de servicios'); ?></code></td>
                <td><?php esc_html_e('Descargar CSV filtrado por tipo de contrato.', 'secop-suite'); ?></td>
            </tr>
            <tr>
                <td><code><?php echo esc_html(esc_url(rest_url('secop-suite/v1/contracts')) . '?estado=Aprobado&per_page=50&page=2'); ?></code></td>
                <td><?php esc_html_e('Contratos aprobados, 50 por página, página 2.', 'secop-suite'); ?></td>
            </tr>
        </tbody>
    </table>
    <p><em><?php esc_html_e(
        'Nota de privacidad: las columnas de datos personales del proveedor (documento_proveedor / tipo_documento_proveedor) nunca se exponen ni se pueden filtrar/ordenar (Ley 1581). Las columnas y direcciones de orden inválidas se ignoran de forma segura.',
        'secop-suite'
    ); ?></em></p>
</div>
