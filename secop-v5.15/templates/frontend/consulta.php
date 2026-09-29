<?php
/**
 * Template: Datos Abiertos — Consulta de ejecución por dependencia (vigencia actual).
 *
 * Variables inyectadas por sc_consulta():
 * - $formato : string  (tabla|csv|txt|json)
 * - $rest    : string  URL base del endpoint REST /consulta
 * - $rows    : array   group_by_dimension('dependencia') — label, valor, conteo
 * - $vig     : int     vigencia (año actual)
 *
 * FIX 7: Si $formato es csv/txt/json, renderiza sólo el enlace de descarga correspondiente.
 *        Si $formato es tabla (por defecto), renderiza todos los botones y la tabla completa.
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}

// FIX 7: render parcial para formatos de descarga/API directa
if ($formato === 'json') : ?>
<div class="ss-consulta-wrap ss-consulta-wrap--single">
    <a href="<?php echo esc_url($rest); ?>"
       class="ss-consulta-btn ss-consulta-json"
       target="_blank" rel="noopener noreferrer">
        <strong>JSON API</strong>
        <small><?php esc_html_e('Interfaz REST paginada', 'secop-suite'); ?></small>
    </a>
</div>
<?php elseif ($formato === 'csv') : ?>
<div class="ss-consulta-wrap ss-consulta-wrap--single">
    <a href="<?php echo esc_url($rest . '/csv'); ?>"
       class="ss-consulta-btn ss-consulta-csv" download>
        <strong>CSV</strong>
        <small><?php esc_html_e('Hoja de cálculo (vigencia completa)', 'secop-suite'); ?></small>
    </a>
</div>
<?php elseif ($formato === 'txt') : ?>
<div class="ss-consulta-wrap ss-consulta-wrap--single">
    <a href="<?php echo esc_url($rest . '/txt'); ?>"
       class="ss-consulta-btn ss-consulta-txt" download>
        <strong>TXT</strong>
        <small><?php esc_html_e('Texto ancho fijo (vigencia completa)', 'secop-suite'); ?></small>
    </a>
</div>
<?php else : /* $formato === 'tabla' — vista completa por defecto */ ?>
<div class="ss-consulta-wrap">

    <p class="ss-consulta-vigencia">
        <?php
        printf(
            /* translators: %s: año de la vigencia presupuestal */
            esc_html__('Datos Abiertos — Ejecución presupuestal vigencia %s', 'secop-suite'),
            '<strong>' . esc_html((string) $vig) . '</strong>'
        );
        ?>
    </p>

    <div class="ss-consulta-buttons">
        <a href="<?php echo esc_url($rest); ?>"
           class="ss-consulta-btn ss-consulta-json"
           target="_blank" rel="noopener noreferrer">
            <strong>JSON API</strong>
            <small><?php esc_html_e('Interfaz REST paginada', 'secop-suite'); ?></small>
        </a>
        <a href="<?php echo esc_url($rest . '/csv'); ?>"
           class="ss-consulta-btn ss-consulta-csv" download>
            <strong>CSV</strong>
            <small><?php esc_html_e('Hoja de cálculo (vigencia completa)', 'secop-suite'); ?></small>
        </a>
        <a href="<?php echo esc_url($rest . '/txt'); ?>"
           class="ss-consulta-btn ss-consulta-txt" download>
            <strong>TXT</strong>
            <small><?php esc_html_e('Texto ancho fijo (vigencia completa)', 'secop-suite'); ?></small>
        </a>
    </div>

    <?php if (!empty($rows)) : ?>
    <table class="ss-consulta-table widefat">
        <thead>
            <tr>
                <th><?php esc_html_e('Dependencia', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Valor Ejecutado', 'secop-suite'); ?></th>
                <th><?php esc_html_e('Contratos', 'secop-suite'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) : ?>
            <tr>
                <td><?php echo esc_html((string) ($row['label'] ?? '')); ?></td>
                <td><?php echo esc_html(\SecopSuite\Stats::money((float) ($row['valor'] ?? 0))); ?></td>
                <td><?php echo esc_html((string) (int) ($row['conteo'] ?? 0)); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p class="ss-consulta-nodata">
        <?php esc_html_e('No hay datos disponibles para la vigencia actual.', 'secop-suite'); ?>
    </p>
    <?php endif; ?>

</div>
<?php endif; ?>
