<?php
/**
 * Template: Depuración de base de datos (eliminación de registros duplicados).
 *
 * Variables inyectadas por Deduplicator::render_page():
 * - $tables  : array  tablas depurables [tabla => label, pk, columns]
 * - $diag    : array  Deduplicator::diagnostics()
 * - $batches : array  lotes eliminados con respaldo
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap ss-admin-wrap ss-dedup">
    <h1>
        <span class="dashicons dashicons-database-remove" aria-hidden="true"></span>
        <?php esc_html_e('Depuración de base de datos', 'secop-suite'); ?>
    </h1>

    <p class="ss-dedup-lead">
        <?php esc_html_e('Detecte y elimine registros duplicados en las tablas de contratos y de Sysman. Primero analice: verá cuántos grupos hay y una muestra de cada uno. Al eliminar se conserva siempre un registro por grupo y cada fila borrada se guarda en un respaldo que puede restaurarse.', 'secop-suite'); ?>
    </p>

    <?php if (empty($tables)) : ?>
        <div class="notice notice-warning"><p><?php esc_html_e('No se encontraron tablas depurables (se requiere una clave primaria de una sola columna).', 'secop-suite'); ?></p></div>
    <?php else : ?>

    <div class="ss-panel">
        <h2><?php esc_html_e('1. Diagnóstico', 'secop-suite'); ?></h2>
        <table class="widefat striped ss-dedup-diag">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Tabla', 'secop-suite'); ?></th>
                    <th scope="col"><?php esc_html_e('Registros', 'secop-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($diag['tables'] as $t) : ?>
                <tr>
                    <td><strong><?php echo esc_html($t['label']); ?></strong><br><code><?php echo esc_html($t['table']); ?></code></td>
                    <td><?php echo esc_html(number_format_i18n($t['rows'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <ul class="ss-dedup-checks">
            <?php foreach ($diag['checks'] as $check) : ?>
            <li class="<?php echo $check['ok'] ? 'is-ok' : 'is-warn'; ?>">
                <span class="dashicons <?php echo $check['ok'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
                <span class="screen-reader-text"><?php echo $check['ok'] ? esc_html__('Correcto:', 'secop-suite') : esc_html__('Atención:', 'secop-suite'); ?></span>
                <strong><?php echo esc_html($check['label']); ?>.</strong>
                <?php echo esc_html($check['text']); ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if (isset($diag['unique_index']) && !$diag['unique_index']) : ?>
        <p>
            <button type="button" class="button" id="ss-dedup-unique" <?php disabled(($diag['dup_numbers'] ?? 0) > 0); ?>>
                <?php esc_html_e('Restaurar índice único por número de contrato', 'secop-suite'); ?>
            </button>
        </p>
        <?php endif; ?>
    </div>

    <div class="ss-panel">
        <h2><?php esc_html_e('2. Analizar duplicados', 'secop-suite'); ?></h2>
        <form id="ss-dedup-form" onsubmit="return false;">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ss-dedup-table"><?php esc_html_e('Tabla', 'secop-suite'); ?></label></th>
                    <td>
                        <select id="ss-dedup-table">
                            <?php foreach ($tables as $name => $meta) : ?>
                            <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($meta['label'] . ' (' . $name . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Criterio de duplicado', 'secop-suite'); ?></th>
                    <td>
                        <fieldset id="ss-dedup-presets">
                            <legend class="screen-reader-text"><?php esc_html_e('Criterio de duplicado', 'secop-suite'); ?></legend>
                        </fieldset>
                        <details id="ss-dedup-custom" class="ss-dedup-custom">
                            <summary><?php esc_html_e('Columnas del criterio (personalizar)', 'secop-suite'); ?></summary>
                            <p class="description"><?php esc_html_e('Dos filas son duplicadas si coinciden exactamente en TODAS las columnas marcadas (se distinguen mayúsculas, tildes y espacios).', 'secop-suite'); ?></p>
                            <div id="ss-dedup-columns" class="ss-dedup-columns"></div>
                        </details>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ss-dedup-keep"><?php esc_html_e('Registro a conservar', 'secop-suite'); ?></label></th>
                    <td>
                        <select id="ss-dedup-keep">
                            <option value="max"><?php esc_html_e('El más reciente (mayor ID)', 'secop-suite'); ?></option>
                            <option value="min"><?php esc_html_e('El más antiguo (menor ID)', 'secop-suite'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Filas incompletas', 'secop-suite'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="ss-dedup-skip-empty" value="1">
                            <?php esc_html_e('Ignorar filas con algún campo del criterio vacío (recomendado en criterios lógicos: evita tomar por iguales dos filas solo porque les faltan los mismos datos)', 'secop-suite'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" class="button button-primary" id="ss-dedup-analyze">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <?php esc_html_e('Analizar duplicados', 'secop-suite'); ?>
                </button>
            </p>
        </form>

        <div id="ss-dedup-status" class="ss-dedup-status" role="status" aria-live="polite"></div>

        <div id="ss-dedup-result" hidden>
            <div class="ss-dedup-summary" id="ss-dedup-summary"></div>
            <div class="ss-dedup-scroll">
                <table class="widefat striped" id="ss-dedup-sample"></table>
            </div>
            <div class="ss-dedup-danger" id="ss-dedup-danger">
                <h3><?php esc_html_e('3. Eliminar duplicados', 'secop-suite'); ?></h3>
                <p><label>
                    <input type="checkbox" id="ss-dedup-confirm">
                    <?php esc_html_e('Revisé la muestra y confirmo que estos registros son duplicados.', 'secop-suite'); ?>
                </label></p>
                <button type="button" class="button button-primary ss-button-danger" id="ss-dedup-run" disabled>
                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                    <?php esc_html_e('Eliminar duplicados (con respaldo)', 'secop-suite'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="ss-panel">
        <h2><?php esc_html_e('Respaldos de depuraciones', 'secop-suite'); ?></h2>
        <?php if (empty($batches)) : ?>
            <p><?php esc_html_e('Aún no hay depuraciones registradas.', 'secop-suite'); ?></p>
        <?php else : ?>
        <div class="ss-dedup-scroll">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Fecha', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Tabla', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Filas', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Criterio', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Usuario', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Acciones', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $b) :
                        $crit = json_decode((string) $b['criteria'], true) ?: [];
                        $user = get_userdata((int) $b['usuario']);
                    ?>
                    <tr>
                        <td><?php echo esc_html(mysql2date(get_option('date_format') . ' H:i', $b['fecha'])); ?></td>
                        <td><code><?php echo esc_html($b['table_name']); ?></code></td>
                        <td><?php echo esc_html(number_format_i18n((int) $b['filas'])); ?></td>
                        <td><code><?php echo esc_html(implode(', ', (array) ($crit['columns'] ?? []))); ?></code></td>
                        <td><?php echo esc_html($user ? $user->display_name : '#' . (int) $b['usuario']); ?></td>
                        <td>
                            <button type="button" class="button ss-dedup-restore" data-batch="<?php echo esc_attr($b['batch_id']); ?>"><?php esc_html_e('Restaurar', 'secop-suite'); ?></button>
                            <button type="button" class="button-link-delete ss-dedup-purge" data-batch="<?php echo esc_attr($b['batch_id']); ?>"><?php esc_html_e('Borrar respaldo', 'secop-suite'); ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>
