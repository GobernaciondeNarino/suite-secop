<?php
/**
 * Template: pestaña «Respaldos» de SECOP Suite > Configuración.
 * El .wrap y el h1 los pone config-page.php.
 *
 * Variables inyectadas por Config_Backup::render_tab():
 * - $notice  : array{tipo:string,texto:string}|false  resultado de la última acción
 * - $backups : array  respaldos guardados (sin el campo data)
 * - $pages   : array  páginas que usan shortcodes del plugin (Config_Backup::shortcode_pages())
 * - $nonce   : string prefijo de los nonces de las acciones
 *
 * @package SecopSuite
 */
if (!defined('ABSPATH')) {
    exit;
}

$ss_post_url = admin_url('admin-post.php');
$ss_rotas    = array_sum(array_map(static fn($p) => count($p['rotos']), $pages));
?>
<div class="ss-respaldos">
    <h2 class="ss-config-section-title">
        <span class="dashicons dashicons-backup" aria-hidden="true"></span>
        <?php esc_html_e('Respaldos de la configuración', 'secop-suite'); ?>
    </h2>

    <?php if (is_array($notice) && !empty($notice['texto'])) : ?>
        <div class="notice notice-<?php echo ($notice['tipo'] ?? '') === 'success' ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($notice['texto']); ?></p>
        </div>
    <?php endif; ?>

    <p class="ss-dedup-lead">
        <?php esc_html_e('Cada respaldo guarda las gráficas, los filtros y las cards (con su configuración completa y su número de ID, que es el que usan los shortcodes), las opciones del plugin y la definición de la vista de Contratación. Los contratos no se incluyen: se vuelven a importar desde datos.gov.co.', 'secop-suite'); ?>
    </p>
    <p class="ss-dedup-lead">
        <?php esc_html_e('Se crea un respaldo automático la primera vez que se carga cada versión nueva (ese se conserva siempre), al desactivar el plugin y antes de cualquier cambio en la vista o en la tabla de contratos. Restaurar no borra nada: actualiza o recrea los elementos del respaldo y antes guarda el estado actual.', 'secop-suite'); ?>
    </p>

    <div class="ss-panel">
        <h2><?php esc_html_e('Crear o importar', 'secop-suite'); ?></h2>
        <div class="ss-respaldos-acciones">
            <form method="post" action="<?php echo esc_url($ss_post_url); ?>">
                <input type="hidden" name="action" value="secop_respaldo_crear">
                <?php wp_nonce_field($nonce . '_crear'); ?>
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e('Crear respaldo ahora', 'secop-suite'); ?>
                </button>
            </form>

            <form method="post" action="<?php echo esc_url($ss_post_url); ?>" enctype="multipart/form-data"
                  class="ss-respaldos-importar"
                  onsubmit="return confirm('<?php echo esc_js(__('¿Restaurar la configuración desde este archivo? Antes se guardará el estado actual como respaldo.', 'secop-suite')); ?>');">
                <input type="hidden" name="action" value="secop_respaldo_importar">
                <?php wp_nonce_field($nonce . '_importar'); ?>
                <label for="ss-respaldo-archivo"><?php esc_html_e('Restaurar desde un archivo .json descargado:', 'secop-suite'); ?></label>
                <input type="file" id="ss-respaldo-archivo" name="respaldo" accept=".json,application/json" required>
                <span class="description"><?php esc_html_e('Desde un archivo no se restaura la vista, y las consultas personalizadas de las gráficas se validan igual que en el editor.', 'secop-suite'); ?></span>
                <button type="submit" class="button"><?php esc_html_e('Restaurar desde archivo', 'secop-suite'); ?></button>
            </form>
        </div>
    </div>

    <div class="ss-panel">
        <h2><?php esc_html_e('Respaldos guardados', 'secop-suite'); ?></h2>
        <?php if (!$backups) : ?>
            <p><?php esc_html_e('Aún no hay respaldos. Use «Crear respaldo ahora».', 'secop-suite'); ?></p>
        <?php else : ?>
        <div class="ss-dedup-scroll">
        <table class="widefat striped ss-respaldos-tabla">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col"><?php esc_html_e('Fecha', 'secop-suite'); ?></th>
                    <th scope="col"><?php esc_html_e('Motivo', 'secop-suite'); ?></th>
                    <th scope="col"><?php esc_html_e('Contenido', 'secop-suite'); ?></th>
                    <th scope="col"><?php esc_html_e('Acciones', 'secop-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($backups as $b) :
                $sum = json_decode((string) $b['summary'], true) ?: [];
                $id  = (int) $b['id'];
                $dl  = wp_nonce_url(add_query_arg(['action' => 'secop_respaldo_descargar', 'id' => $id], $ss_post_url), $nonce . '_descargar');
                ?>
                <tr>
                    <td><?php echo esc_html((string) $id); ?></td>
                    <td>
                        <?php echo esc_html(mysql2date(get_option('date_format') . ' H:i', (string) $b['created_at'])); ?><br>
                        <span class="description">v<?php echo esc_html((string) $b['plugin_version']); ?></span>
                    </td>
                    <td>
                        <?php echo esc_html((string) $b['reason']); ?>
                        <?php if ((int) $b['is_auto'] === 0) : ?>
                            <br><span class="description"><?php esc_html_e('Se conserva: no se elimina automáticamente', 'secop-suite'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: 1: gráficas 2: filtros 3: cards 4: opciones */
                            __('%1$d gráficas, %2$d filtros, %3$d cards, %4$d opciones', 'secop-suite'),
                            (int) ($sum['graficas'] ?? 0),
                            (int) ($sum['filtros'] ?? 0),
                            (int) ($sum['cards'] ?? 0),
                            (int) ($sum['opciones'] ?? 0)
                        ));
                        ?><br>
                        <span class="description">
                            <?php echo !empty($sum['vista']) ? esc_html__('Incluye la vista', 'secop-suite') : esc_html__('Sin vista', 'secop-suite'); ?>
                            <?php if (isset($sum['contratos']) && $sum['contratos'] !== null) : ?>
                                · <?php echo esc_html(sprintf(__('%s contratos en la BD', 'secop-suite'), number_format_i18n((int) $sum['contratos']))); ?>
                            <?php endif; ?>
                            · <?php echo esc_html(size_format((int) $b['bytes'])); ?>
                        </span>
                    </td>
                    <td class="ss-respaldos-botones">
                        <a class="button button-small" href="<?php echo esc_url($dl); ?>"><?php esc_html_e('Descargar', 'secop-suite'); ?></a>
                        <form method="post" action="<?php echo esc_url($ss_post_url); ?>"
                              onsubmit="return confirm('<?php echo esc_js(sprintf(__('¿Restaurar el respaldo #%d? Antes se guardará el estado actual como respaldo.', 'secop-suite'), $id)); ?>');">
                            <input type="hidden" name="action" value="secop_respaldo_restaurar">
                            <input type="hidden" name="id" value="<?php echo esc_attr((string) $id); ?>">
                            <?php wp_nonce_field($nonce . '_restaurar'); ?>
                            <label class="ss-respaldos-vista">
                                <input type="checkbox" name="vista" value="1" <?php disabled(empty($sum['vista'])); ?>>
                                <?php esc_html_e('con la vista', 'secop-suite'); ?>
                            </label>
                            <button type="submit" class="button button-small button-primary"><?php esc_html_e('Restaurar', 'secop-suite'); ?></button>
                        </form>
                        <form method="post" action="<?php echo esc_url($ss_post_url); ?>"
                              onsubmit="return confirm('<?php echo esc_js(sprintf(__('¿Eliminar el respaldo #%d? Esta acción no se puede deshacer.', 'secop-suite'), $id)); ?>');">
                            <input type="hidden" name="action" value="secop_respaldo_eliminar">
                            <input type="hidden" name="id" value="<?php echo esc_attr((string) $id); ?>">
                            <?php wp_nonce_field($nonce . '_eliminar'); ?>
                            <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('Eliminar', 'secop-suite'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="ss-panel">
        <h2><?php esc_html_e('Páginas que usan shortcodes del plugin', 'secop-suite'); ?></h2>
        <?php if (!$pages) : ?>
            <p><?php esc_html_e('No se encontraron páginas ni entradas con shortcodes de SECOP Suite.', 'secop-suite'); ?></p>
        <?php else : ?>
            <p class="description">
                <?php
                echo $ss_rotas > 0
                    ? esc_html(sprintf(__('Atención: %d shortcodes apuntan a gráficas, filtros o cards que no existen. Restaure un respaldo para recuperarlos.', 'secop-suite'), $ss_rotas))
                    : esc_html__('Todos los shortcodes apuntan a elementos existentes.', 'secop-suite');
                ?>
            </p>
            <div class="ss-dedup-scroll">
            <table class="widefat striped ss-respaldos-paginas">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Página', 'secop-suite'); ?></th>
                        <th scope="col"><?php esc_html_e('Shortcodes', 'secop-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $p) : ?>
                    <tr>
                        <td>
                            <?php $ss_edit = get_edit_post_link($p['ID']); ?>
                            <?php if ($ss_edit) : ?>
                                <a href="<?php echo esc_url($ss_edit); ?>"><?php echo esc_html($p['titulo'] !== '' ? $p['titulo'] : '#' . $p['ID']); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($p['titulo'] !== '' ? $p['titulo'] : '#' . $p['ID']); ?>
                            <?php endif; ?>
                            <br><span class="description"><?php echo esc_html($p['tipo'] . ' · ' . $p['estado']); ?></span>
                        </td>
                        <td>
                            <?php foreach ($p['shortcodes'] as $code) : ?>
                                <?php $ss_roto = in_array($code, $p['rotos'], true); ?>
                                <code class="<?php echo $ss_roto ? 'ss-respaldos-roto' : ''; ?>"><?php echo esc_html($code); ?></code>
                                <?php if ($ss_roto) : ?>
                                    <strong class="ss-respaldos-roto-txt"><?php esc_html_e('no existe', 'secop-suite'); ?></strong>
                                <?php endif; ?>
                                <br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>
