<?php
/**
 * Template: [secop_diccionario] — Diccionario de datos y guía de uso de la API.
 *
 * Variables inyectadas por Open_Data::render_shortcode():
 * - $titulo   : string
 * - $datasets : array  Open_Data::datasets() (filtrado por el atributo api)
 * - $usage    : array  Open_Data::usage()
 * - $types    : array  Open_Data::type_labels()
 * - $ejemplos : bool
 * - $base     : string URL base de la API REST
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}

$ss_example = static function (string $path, array $query = []) use ($base): array {
    $url = $base . $path;
    if ($query) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    return ['href' => $url, 'label' => rawurldecode($url)];
};

$ss_examples = [
    'contratos' => [
        [$ss_example('contracts', ['per_page' => 20, 'page' => 1]), __('Primeros 20 contratos (los más recientes primero).', 'secop-suite')],
        [$ss_example('contracts', ['modalidad_de_contratacion_like' => 'directa']), __('Contratos cuya modalidad contiene "directa".', 'secop-suite')],
        [$ss_example('contracts', ['valor_contrato_min' => 100000000, 'order_by' => 'valor_contrato', 'order' => 'desc']), __('Contratos desde 100 millones, de mayor a menor valor.', 'secop-suite')],
        [$ss_example('contracts', ['fecha_de_firma_del_contrato_min' => date('Y') . '-01-01']), __('Contratos firmados desde el 1 de enero del año en curso.', 'secop-suite')],
        [$ss_example('export/csv', ['tipo_de_contrato' => 'Obra']), __('Descargar en CSV solo los contratos de obra.', 'secop-suite')],
    ],
    'consulta' => [
        [$ss_example('consulta'), __('Una fila por contrato de la vigencia actual, con su ejecución presupuestal.', 'secop-suite')],
        [$ss_example('consulta', ['agrupar' => 'detalle']), __('Una fila por asiento presupuestal (sin repetidos).', 'secop-suite')],
        [$ss_example('consulta', ['nombredependencia' => 'SECRETARIA DE EDUCACION']), __('Contratos ejecutados por una dependencia.', 'secop-suite')],
        [$ss_example('consulta', ['order_by' => 'valor_efectivo', 'order' => 'desc', 'per_page' => 10]), __('Los 10 contratos con mayor valor efectivo.', 'secop-suite')],
        [$ss_example('consulta/csv', ['modalidad_de_contratacion_like' => 'directa']), __('Descargar en CSV los contratos de contratación directa.', 'secop-suite')],
    ],
];

$ss_responses = [
    'contratos' => [
        'data' => [['id' => 123, 'numero_del_contrato' => 'CD-001-' . date('Y'), 'valor_contrato' => '15000000.00', '…' => '…']],
        'meta' => ['total' => 1840, 'per_page' => 10, 'current_page' => 1, 'total_pages' => 184],
    ],
    'consulta' => [
        'vigencia' => (int) date('Y'), 'agrupacion' => 'contrato', 'page' => 1, 'per_page' => 100, 'total' => 950, 'total_pages' => 10,
        'data' => [['numero_del_contrato' => 'CD-001-' . date('Y'), 'nombredependencia' => 'SECRETARIA DE EDUCACION', 'valor_contrato' => '15000000.00', 'valordebito' => '9000000.00', 'valor_efectivo' => '9000000.00', '…' => '…']],
    ],
];
?>
<section class="ss-dicc" aria-labelledby="ss-dicc-title">
    <header class="ss-dicc-header">
        <h2 id="ss-dicc-title" class="ss-dicc-title"><?php echo esc_html($titulo); ?></h2>
        <p class="ss-dicc-lead">
            <?php esc_html_e('Datos abiertos de la contratación pública de la entidad, disponibles de forma libre y gratuita para consulta, reutilización y análisis. Todas las direcciones parten de la URL base:', 'secop-suite'); ?>
        </p>
        <p><code class="ss-dicc-code"><?php echo esc_html($base); ?></code></p>
    </header>

    <ol class="ss-dicc-steps" aria-label="<?php esc_attr_e('Cómo funciona la API', 'secop-suite'); ?>">
        <li class="ss-dicc-step">
            <strong><?php esc_html_e('Elija el conjunto de datos', 'secop-suite'); ?></strong>
            <span><?php esc_html_e('Contratos SECOP (todas las vigencias) o la ejecución presupuestal de la vigencia actual.', 'secop-suite'); ?></span>
        </li>
        <li class="ss-dicc-step">
            <strong><?php esc_html_e('Filtre y ordene desde la URL', 'secop-suite'); ?></strong>
            <span><?php esc_html_e('Use el nombre de cualquier campo del diccionario como parámetro, con los sufijos _like, _min o _max.', 'secop-suite'); ?></span>
        </li>
        <li class="ss-dicc-step">
            <strong><?php esc_html_e('Consuma o descargue', 'secop-suite'); ?></strong>
            <span><?php esc_html_e('JSON paginado para aplicaciones, o CSV/TXT completos para hojas de cálculo.', 'secop-suite'); ?></span>
        </li>
    </ol>

    <?php foreach ($datasets as $ds_key => $ds) : ?>
    <article class="ss-dicc-dataset" aria-labelledby="ss-dicc-<?php echo esc_attr($ds_key); ?>">
        <h3 id="ss-dicc-<?php echo esc_attr($ds_key); ?>"><?php echo esc_html($ds['titulo']); ?></h3>
        <p><?php echo esc_html($ds['descripcion']); ?></p>
        <p class="ss-dicc-unique">
            <strong><?php esc_html_e('Sin duplicados:', 'secop-suite'); ?></strong>
            <?php echo esc_html($ds['unicidad']); ?>
        </p>
        <?php if (isset($ds['disponible']) && !$ds['disponible']) : ?>
            <p class="ss-dicc-warning"><?php esc_html_e('Este conjunto de datos no está disponible en este momento.', 'secop-suite'); ?></p>
        <?php endif; ?>

        <h4><?php esc_html_e('Puntos de acceso', 'secop-suite'); ?></h4>
        <div class="ss-dicc-scroll">
            <table class="ss-dicc-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Método', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Dirección', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Formato', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ds['endpoints'] as $ep) : ?>
                    <tr>
                        <td><span class="ss-dicc-badge"><?php echo esc_html($ep['metodo']); ?></span></td>
                        <td><code class="ss-dicc-code"><?php echo esc_html($ep['url']); ?></code></td>
                        <td><?php echo esc_html($ep['formato']); ?></td>
                        <td><?php echo esc_html($ep['descripcion']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php $ss_first = true; foreach ($ds['agrupaciones'] as $grp_key => $grp) : ?>
        <details class="ss-dicc-fields"<?php echo $ss_first ? ' open' : ''; ?>>
            <summary>
                <?php
                printf(
                    /* translators: 1: grouping name, 2: number of fields */
                    esc_html__('Diccionario de campos — %1$s (%2$d campos)', 'secop-suite'),
                    esc_html($grp_key),
                    count($grp['campos'])
                );
                ?>
            </summary>
            <p><?php echo esc_html($grp['descripcion']); ?></p>
            <div class="ss-dicc-scroll">
                <table class="ss-dicc-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Campo', 'secop-suite'); ?></th>
                            <th scope="col"><?php esc_html_e('Tipo', 'secop-suite'); ?></th>
                            <th scope="col"><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grp['campos'] as $field) : ?>
                        <tr>
                            <td><code class="ss-dicc-code"><?php echo esc_html($field['nombre']); ?></code></td>
                            <td><?php echo esc_html($types[$field['tipo']] ?? $field['tipo']); ?></td>
                            <td><?php echo esc_html($field['descripcion'] !== '' ? $field['descripcion'] : '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
        <?php $ss_first = false; endforeach; ?>

        <?php if ($ejemplos && !empty($ss_examples[$ds_key])) : ?>
        <h4><?php esc_html_e('Ejemplos', 'secop-suite'); ?></h4>
        <ul class="ss-dicc-examples">
            <?php foreach ($ss_examples[$ds_key] as [$ex, $what]) : ?>
            <li>
                <a href="<?php echo esc_url($ex['href']); ?>" target="_blank" rel="noopener noreferrer"><code class="ss-dicc-code"><?php echo esc_html($ex['label']); ?></code></a>
                <span><?php echo esc_html($what); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <h4><?php esc_html_e('Estructura de la respuesta JSON', 'secop-suite'); ?></h4>
        <pre class="ss-dicc-pre"><code><?php echo esc_html((string) wp_json_encode($ss_responses[$ds_key] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></code></pre>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>

    <article class="ss-dicc-dataset" aria-labelledby="ss-dicc-params">
        <h3 id="ss-dicc-params"><?php esc_html_e('Parámetros de consulta', 'secop-suite'); ?></h3>
        <div class="ss-dicc-scroll">
            <table class="ss-dicc-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Parámetro', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Aplica a', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Descripción', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usage['parametros'] as $prm) : ?>
                    <tr>
                        <td><code class="ss-dicc-code"><?php echo esc_html($prm['nombre']); ?></code></td>
                        <td><?php echo esc_html($prm['aplica']); ?></td>
                        <td><?php echo esc_html($prm['descripcion']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h4><?php esc_html_e('Condiciones de uso', 'secop-suite'); ?></h4>
        <ul class="ss-dicc-notes">
            <?php foreach ($usage['notas'] as $note) : ?>
            <li><?php echo esc_html($note); ?></li>
            <?php endforeach; ?>
        </ul>

        <p class="ss-dicc-machine">
            <?php esc_html_e('Versión legible por máquinas de este diccionario (JSON):', 'secop-suite'); ?>
            <a href="<?php echo esc_url($base . 'diccionario'); ?>" target="_blank" rel="noopener noreferrer"><code class="ss-dicc-code"><?php echo esc_html($base . 'diccionario'); ?></code></a>
        </p>
    </article>
</section>
